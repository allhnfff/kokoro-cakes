<?php

declare(strict_types=1);

namespace App\Data;

/**
 * Skrip Lua yang dijalankan Redis secara ATOMIK (EVAL).
 *
 * Kenapa Lua?
 *   Selama satu skrip berjalan, Redis tidak menjalankan perintah klien lain.
 *   Jadi "cek meja + cek menu + hitung total + simpan pesanan + kosongkan keranjang + catat request_id"
 *   terjadi sebagai satu langkah yang tidak bisa disela checkout lain (mencegah race condition).
 *
 * PENTING: Redis TIDAK punya rollback otomatis. Jika satu perintah gagal di tengah skrip,
 * perintah tulis sebelumnya TETAP tersimpan. Karena itu setiap skrip dibagi dua tahap:
 *   TAHAP 1  : hanya membaca + memvalidasi semuanya. Bila ada masalah -> keluar tanpa menulis.
 *   TAHAP 2  : menulis, dengan data yang sudah pasti valid.
 *
 * Semua key yang disentuh dikirim lewat KEYS (tidak dirakit di dalam skrip).
 * Ditujukan untuk satu instance Redis standalone, bukan Redis Cluster.
 * Banyak field ditulis dengan HMSET (bukan HSET banyak field) agar tetap jalan di Redis 3.x for Windows.
 */
final class LuaScripts
{
    /**
     * Seeder data awal. KEYS[1] marker seed, KEYS[2..] key tujuan.
     * ARGV[1] JSON daftar record {type: hash|set, values: [...]} sesuai urutan KEYS[2..].
     * Memeriksa SEMUA konflik sebelum write pertama; tidak pernah menimpa data yang sudah ada.
     */
    public const SEED = <<<'LUA'
local marker_type = redis.call('TYPE', KEYS[1]).ok
if marker_type == 'string' and redis.call('GET', KEYS[1]) == '1' then
  return {'already_seeded'}
end
if marker_type ~= 'none' then
  return redis.error_reply('SEED_MARKER_CONFLICT')
end

local records = cjson.decode(ARGV[1])
if #records ~= #KEYS - 1 then
  return redis.error_reply('SEED_INVALID_PAYLOAD')
end
for i, record in ipairs(records) do
  if record.type ~= 'hash' and record.type ~= 'set' then
    return redis.error_reply('SEED_INVALID_TYPE')
  end
  if type(record.values) ~= 'table' or #record.values == 0 then
    return redis.error_reply('SEED_EMPTY_VALUES')
  end
  if record.type == 'hash' and #record.values % 2 ~= 0 then
    return redis.error_reply('SEED_INVALID_HASH')
  end
  for _, value in ipairs(record.values) do
    if type(value) ~= 'string' then
      return redis.error_reply('SEED_INVALID_VALUE')
    end
  end
  if redis.call('EXISTS', KEYS[i + 1]) == 1 then
    return redis.error_reply('SEED_KEY_CONFLICT')
  end
end

for i, record in ipairs(records) do
  if record.type == 'hash' then
    redis.call('HMSET', KEYS[i + 1], unpack(record.values))
  else
    redis.call('SADD', KEYS[i + 1], unpack(record.values))
  end
end
redis.call('SET', KEYS[1], '1')
return {'seeded'}
LUA;

    /**
     * Sinkronisasi katalog setelah seed: tambah kategori/menu yang belum ada, isi field yang belum ada.
     * Field teks di ARGV[2] (nama Jepang, deskripsi, pasangan) disamakan dengan config;
     * field lain tidak pernah ditimpa (HSETNX), jadi harga & status tetap.
     * KEYS[1] marker seed  KEYS[2] idx:kategori  KEYS[3] idx:menu  KEYS[4..] hash & set relasi
     * ARGV[1] JSON [{type: kategori|menu, id, fields: [...], hkey: i, skey: j}]
     * ARGV[2] JSON daftar nama field teks
     * ARGV[3] (opsional) indeks KEYS untuk idx:menu:dihapus: menu yang dihapus admin tidak dibuat lagi.
     * Menu berpenanda diubah_admin=1 tidak ditimpa teksnya (perubahan admin menang atas config).
     */
    public const SYNC_CATALOG = <<<'LUA'
if redis.call('GET', KEYS[1]) ~= '1' then
  return cjson.encode({ok = false, code = 'NOT_SEEDED'})
end
local records = cjson.decode(ARGV[1])
local text = {}
for _, f in ipairs(cjson.decode(ARGV[2] or '[]')) do
  text[f] = true
end
local dead = nil
if ARGV[3] then
  dead = KEYS[tonumber(ARGV[3])]
  local td = redis.call('TYPE', dead).ok
  if td ~= 'none' and td ~= 'set' then
    return cjson.encode({ok = false, code = 'KEY_TYPE_CONFLICT', key = dead})
  end
end
-- TAHAP 1: periksa tipe semua key (hash/set atau belum ada) sebelum menulis apa pun
for i = 2, 3 do
  local t = redis.call('TYPE', KEYS[i]).ok
  if t ~= 'none' and t ~= 'set' then
    return cjson.encode({ok = false, code = 'KEY_TYPE_CONFLICT', key = KEYS[i]})
  end
end
for _, r in ipairs(records) do
  local h, s = KEYS[r.hkey], KEYS[r.skey]
  if not h or not s or (r.type ~= 'kategori' and r.type ~= 'menu') or #r.fields % 2 ~= 0 then
    return cjson.encode({ok = false, code = 'BAD_REQUEST'})
  end
  local th = redis.call('TYPE', h).ok
  local ts = redis.call('TYPE', s).ok
  if (th ~= 'none' and th ~= 'hash') then
    return cjson.encode({ok = false, code = 'KEY_TYPE_CONFLICT', key = h})
  end
  if (ts ~= 'none' and ts ~= 'set') then
    return cjson.encode({ok = false, code = 'KEY_TYPE_CONFLICT', key = s})
  end
end
-- TAHAP 2: tulis
local added = {kategori = 0, menu = 0}
local filled = 0
local updated = 0
for _, r in ipairs(records) do
  local h = KEYS[r.hkey]
  local skip = r.type == 'menu' and dead ~= nil and redis.call('SISMEMBER', dead, r.id) == 1
  local admin = redis.call('HGET', h, 'diubah_admin') == '1'
  if skip then
    -- menu ini sudah dihapus admin: biarkan terhapus
  elseif redis.call('EXISTS', h) == 0 then
    redis.call('HMSET', h, unpack(r.fields))
    added[r.type] = added[r.type] + 1
    if r.type == 'kategori' then
      redis.call('SADD', KEYS[2], r.id)
    else
      redis.call('SADD', KEYS[3], r.id)
      redis.call('SADD', KEYS[r.skey], r.id)
    end
  else
    for j = 1, #r.fields, 2 do
      local f, v = r.fields[j], r.fields[j + 1]
      if text[f] and not admin then
        local cur = redis.call('HGET', h, f)
        if cur == false then
          redis.call('HSET', h, f, v)
          filled = filled + 1
        elseif cur ~= v then
          redis.call('HSET', h, f, v)
          updated = updated + 1
        end
      else
        filled = filled + redis.call('HSETNX', h, f, v)
      end
    end
  end
end
return cjson.encode({ok = true, kategori = added.kategori, menu = added.menu, fields = filled, updated = updated})
LUA;

    /**
     * Checkout atomik + idempoten.
     * KEYS[1] checkout:request:{request_id}
     * KEYS[2] pesanan:{id}            KEYS[3] pesanan:{id}:detail     KEYS[4] pesanan:{id}:riwayat
     * KEYS[5] idx:pesanan             KEYS[6] pelanggan:{owner_hash}:pesanan
     * KEYS[7] meja:{kode}             KEYS[8] meja:{kode}:aktif       KEYS[9] cart:{kode}
     * KEYS[10..] menu:{id_menu} untuk setiap item (urutan sama dengan ARGV[3])
     * KEYS[terakhir] idx:pesanan:belum-bayar (sorted set, skor = batas waktu bayar)
     * ARGV[1] payload_hash  ARGV[2] header JSON  ARGV[3] items JSON [{id_menu, qty}]
     * ARGV[4] pajak persen  ARGV[5] waktu UNIX   ARGV[6] TTL catatan request (detik)
     * ARGV[7] batas bayar di kasir (detik)
     *
     * Bayar dulu, baru disiapkan: pesanan "bayar di kasir" masuk dengan status bayar Belum Bayar,
     * dicatat sebagai tagihan_terbuka di meja:{kode} (satu tagihan terbuka per meja), dan diberi
     * batas_bayar. Dapur baru boleh memproses setelah Lunas (lihat UPDATE_STATUS).
     */
    public const CHECKOUT = <<<'LUA'
local existing = redis.call('GET', KEYS[1])
if existing then
  local rec = cjson.decode(existing)
  if rec['payload_hash'] == ARGV[1] then
    return cjson.encode({ok = true, status = 'duplicate', order_id = rec['order_id']})
  end
  return cjson.encode({ok = false, code = 'IDEMPOTENCY_CONFLICT'})
end
if redis.call('EXISTS', KEYS[2]) == 1 then
  return cjson.encode({ok = false, code = 'ORDER_ID_COLLISION'})
end

local header = cjson.decode(ARGV[2])
local items = cjson.decode(ARGV[3])
local tax_percent = tonumber(ARGV[4])
local now = tonumber(ARGV[5])
if #items == 0 then
  return cjson.encode({ok = false, code = 'EMPTY_ORDER'})
end
local unpaid_seconds = tonumber(ARGV[7] or '')
if #KEYS ~= 10 + #items or tax_percent == nil or now == nil or unpaid_seconds == nil then
  return cjson.encode({ok = false, code = 'BAD_REQUEST'})
end

-- ===== TAHAP 1: VALIDASI (belum ada penulisan) =====
local meja = redis.call('HMGET', KEYS[7], 'aktif', 'status', 'tagihan_terbuka')
if meja[1] ~= '1' then
  return cjson.encode({ok = false, code = 'TABLE_UNAVAILABLE'})
end
if header['metode_bayar'] == 'tunai' and meja[3] and meja[3] ~= '' then
  return cjson.encode({ok = false, code = 'UNPAID_EXISTS', order_id = meja[3]})
end
if redis.call('TYPE', KEYS[9]).ok ~= 'hash' or redis.call('HLEN', KEYS[9]) ~= #items then
  return cjson.encode({ok = false, code = 'CART_CHANGED'})
end

local problems = {}
local lines = {}
local seen = {}
local subtotal = 0
local units = 0
for i, it in ipairs(items) do
  local mkey = KEYS[9 + i]
  local id = it['id_menu']
  local qty = tonumber(it['qty'])
  if type(id) ~= 'string' or mkey ~= 'menu:' .. id or seen[id] then
    return cjson.encode({ok = false, code = 'BAD_REQUEST'})
  end
  seen[id] = true
  if qty == nil or qty < 1 or qty > 99 or qty ~= math.floor(qty) then
    table.insert(problems, {code = 'INVALID_QTY', id_menu = id})
  elseif tonumber(redis.call('HGET', KEYS[9], id)) ~= qty then
    table.insert(problems, {code = 'CART_CHANGED', id_menu = id})
  else
    local m = redis.call('HMGET', mkey, 'nama_menu', 'harga', 'status')
    local price = tonumber(m[2])
    if not m[1] or price == nil then
      table.insert(problems, {code = 'MENU_NOT_FOUND', id_menu = id})
    elseif m[3] ~= 'Tersedia' then
      table.insert(problems, {code = 'MENU_UNAVAILABLE', id_menu = id, nama_menu = m[1]})
    else
      lines[i] = {id_menu = id, nama_menu = m[1], jumlah = qty, harga_satuan = price, subtotal = price * qty}
      subtotal = subtotal + price * qty
      units = units + qty
    end
  end
end
if #problems > 0 then
  return cjson.encode({ok = false, code = problems[1]['code'], problems = problems})
end

-- ===== TAHAP 2: PENULISAN (semua data sudah valid) =====
local tax = math.floor((subtotal * tax_percent + 50) / 100)
local oid = header['id']
for _, l in ipairs(lines) do
  redis.call('RPUSH', KEYS[3], cjson.encode(l))
end

header['waktu'] = now
header['subtotal'] = subtotal
header['pajak_persen'] = tax_percent
header['pajak'] = tax
header['total'] = subtotal + tax
header['jumlah_item'] = units
header['status_pesanan'] = 'Masuk'
header['updated_at'] = now
local pay_note = 'Bayar di kasir sebelum pesanan disiapkan.'
local unpaid = false
if header['metode_bayar'] == 'qris' then
  header['status_bayar'] = 'Lunas'
  header['dibayar_at'] = now
  pay_note = 'Pembayaran QRIS simulasi berhasil.'
else
  header['status_bayar'] = 'Belum Bayar'
  header['batas_bayar'] = now + unpaid_seconds
  unpaid = true
end
local flat = {}
for k, v in pairs(header) do
  table.insert(flat, k)
  table.insert(flat, tostring(v))
end
redis.call('HMSET', KEYS[2], unpack(flat))
redis.call('RPUSH', KEYS[4], cjson.encode({jenis = 'status', status = 'Masuk', at = now, by = 'pelanggan',
  note = 'Pesanan diterima dari meja ' .. header['kode_meja'] .. '. ' .. pay_note}))
redis.call('ZADD', KEYS[5], now, oid)
redis.call('ZADD', KEYS[6], now, oid)
redis.call('SADD', KEYS[8], oid)
if meja[2] ~= 'Terisi' then
  redis.call('HSET', KEYS[7], 'terisi_sejak', tostring(now))
end
redis.call('HSET', KEYS[7], 'status', 'Terisi')
if unpaid then
  redis.call('ZADD', KEYS[#KEYS], now + unpaid_seconds, oid)
  redis.call('HSET', KEYS[7], 'tagihan_terbuka', oid)
end
redis.call('DEL', KEYS[9])
redis.call('SET', KEYS[1], cjson.encode({order_id = oid, payload_hash = ARGV[1], created_at = now}),
  'EX', tonumber(ARGV[6]))
return cjson.encode({ok = true, status = 'created', order_id = oid})
LUA;

    /**
     * Ubah status penyajian (Masuk > Diproses > Siap Diantar > Selesai).
     * KEYS[1] pesanan:{id}  KEYS[2] pesanan:{id}:riwayat  KEYS[3] idx:pesanan:selesai
     * KEYS[4] meja:{kode}   KEYS[5] meja:{kode}:aktif
     * ARGV[1] status_asal  ARGV[2] status_tujuan  ARGV[3] pelaku  ARGV[4] waktu
     * ARGV[5] JSON transisi sah  ARGV[6] kode_meja  ARGV[7] id pesanan  ARGV[8] catatan kasir (opsional)
     */
    public const UPDATE_STATUS = <<<'LUA'
if redis.call('EXISTS', KEYS[1]) == 0 then
  return cjson.encode({ok = false, code = 'ORDER_NOT_FOUND'})
end
local h = redis.call('HMGET', KEYS[1], 'kode_meja', 'status_pesanan', 'status_bayar')
if h[1] ~= ARGV[6] then
  return cjson.encode({ok = false, code = 'BAD_REQUEST'})
end
local current, to = h[2], ARGV[2]
if current == to then
  return cjson.encode({ok = true, status = 'unchanged'})
end
if current ~= ARGV[1] then
  return cjson.encode({ok = false, code = 'STATUS_CONFLICT', current = current})
end
local tr = cjson.decode(ARGV[5])
if tr[current] ~= to then
  return cjson.encode({ok = false, code = 'INVALID_TRANSITION', current = current})
end
-- Bayar dulu, baru disiapkan: tidak ada langkah maju (termasuk Mulai proses) sebelum Lunas.
if h[3] ~= 'Lunas' then
  return cjson.encode({ok = false, code = 'PAYMENT_REQUIRED'})
end

local now = ARGV[4]
local idle = false
redis.call('HMSET', KEYS[1], 'status_pesanan', to, 'updated_at', now)
local note = 'Status menjadi ' .. to .. '.'
if to == 'Diproses' then
  note = 'Pesanan sedang disiapkan.'
elseif to == 'Siap Diantar' then
  note = 'Pesanan siap diantar ke meja.'
elseif to == 'Selesai' then
  note = 'Pesanan sudah diantar dan lunas.'
  redis.call('HSET', KEYS[1], 'selesai_at', now)
  redis.call('ZADD', KEYS[3], now, ARGV[7])
  redis.call('SREM', KEYS[5], ARGV[7])
  -- Meja TIDAK dilepas otomatis: tamu boleh tetap duduk setelah pesanannya selesai.
  -- Meja baru Tersedia saat staf menekan Kosongkan (RELEASE_TABLE) setelah tamu pergi.
  idle = redis.call('SCARD', KEYS[5]) == 0
end
if ARGV[8] and ARGV[8] ~= '' then
  note = note .. ' Catatan: ' .. ARGV[8]
end
redis.call('RPUSH', KEYS[2], cjson.encode({jenis = 'status', status = to, at = tonumber(now), by = ARGV[3], note = note}))
return cjson.encode({ok = true, status = 'updated', table_idle = idle})
LUA;

    /**
     * Pembatalan (hanya saat Masuk). Jika sudah lunas, status bayar menjadi Dikembalikan (simulasi).
     * KEYS[1] pesanan:{id}  KEYS[2] pesanan:{id}:riwayat  KEYS[3] meja:{kode}  KEYS[4] meja:{kode}:aktif
     * KEYS[5] idx:pesanan:belum-bayar
     * ARGV[1] alasan  ARGV[2] pelaku  ARGV[3] waktu  ARGV[4] kode_meja  ARGV[5] id pesanan
     * ARGV[6] '1' = hanya jika belum dibayar (pembatalan otomatis karena lewat batas bayar)
     */
    public const CANCEL = <<<'LUA'
if redis.call('EXISTS', KEYS[1]) == 0 then
  return cjson.encode({ok = false, code = 'ORDER_NOT_FOUND'})
end
local h = redis.call('HMGET', KEYS[1], 'kode_meja', 'status_pesanan', 'status_bayar')
if h[1] ~= ARGV[4] then
  return cjson.encode({ok = false, code = 'BAD_REQUEST'})
end
if h[2] == 'Dibatalkan' then
  return cjson.encode({ok = true, status = 'already_cancelled'})
end
if h[2] ~= 'Masuk' then
  return cjson.encode({ok = false, code = 'NOT_CANCELLABLE', current = h[2]})
end
if ARGV[1] == '' then
  return cjson.encode({ok = false, code = 'REASON_REQUIRED'})
end
if ARGV[6] == '1' and h[3] ~= 'Belum Bayar' then
  return cjson.encode({ok = false, code = 'NOT_CANCELLABLE', current = h[2]})
end

local now = ARGV[3]
local pay = h[3]
local note = 'Dibatalkan: ' .. ARGV[1] .. '.'
if pay == 'Lunas' then
  pay = 'Dikembalikan'
  note = note .. ' Pembayaran simulasi dikembalikan.'
end
redis.call('HMSET', KEYS[1], 'status_pesanan', 'Dibatalkan', 'status_bayar', pay, 'alasan_batal', ARGV[1],
  'dibatalkan_oleh', ARGV[2], 'dibatalkan_at', now, 'updated_at', now)
redis.call('SREM', KEYS[4], ARGV[5])
redis.call('ZREM', KEYS[5], ARGV[5])
if redis.call('HGET', KEYS[3], 'tagihan_terbuka') == ARGV[5] then
  redis.call('HDEL', KEYS[3], 'tagihan_terbuka')
end
-- Meja tidak dilepas otomatis (tamu mungkin masih duduk dan memesan lagi); staf yang mengosongkan.
local idle = redis.call('SCARD', KEYS[4]) == 0
redis.call('RPUSH', KEYS[2], cjson.encode({jenis = 'status', status = 'Dibatalkan', at = tonumber(now), by = ARGV[2], note = note}))
return cjson.encode({ok = true, status = 'cancelled', table_idle = idle})
LUA;

    /**
     * Konfirmasi pembayaran di kasir (metode tunai).
     * KEYS[1] pesanan:{id}  KEYS[2] pesanan:{id}:riwayat  KEYS[3] meja:{kode}  KEYS[4] idx:pesanan:belum-bayar
     * ARGV[1] pelaku  ARGV[2] waktu  ARGV[3] id pesanan
     * Setelah lunas: keluar dari antrean batas bayar dan tagihan_terbuka meja dilepas.
     */
    public const CONFIRM_PAYMENT = <<<'LUA'
if redis.call('EXISTS', KEYS[1]) == 0 then
  return cjson.encode({ok = false, code = 'ORDER_NOT_FOUND'})
end
local h = redis.call('HMGET', KEYS[1], 'status_pesanan', 'status_bayar')
if h[1] == 'Dibatalkan' then
  return cjson.encode({ok = false, code = 'ALREADY_CANCELLED'})
end
if h[2] == 'Lunas' then
  return cjson.encode({ok = true, status = 'already_paid'})
end
redis.call('HMSET', KEYS[1], 'status_bayar', 'Lunas', 'dibayar_at', ARGV[2], 'updated_at', ARGV[2])
redis.call('ZREM', KEYS[4], ARGV[3])
if redis.call('HGET', KEYS[3], 'tagihan_terbuka') == ARGV[3] then
  redis.call('HDEL', KEYS[3], 'tagihan_terbuka')
end
redis.call('RPUSH', KEYS[2], cjson.encode({jenis = 'bayar', status = h[1], at = tonumber(ARGV[2]), by = ARGV[1],
  note = 'Pembayaran di kasir dikonfirmasi. Pesanan boleh disiapkan.'}))
return cjson.encode({ok = true, status = 'paid'})
LUA;

    /**
     * Kosongkan meja setelah tamu pergi (satu-satunya cara meja kembali Tersedia).
     * Ditolak jika masih ada pesanan aktif. Keranjang meja dihapus dan sesi meja dinaikkan,
     * sehingga sesi tamu sebelumnya berakhir.
     * KEYS[1] meja:{kode}  KEYS[2] meja:{kode}:aktif  KEYS[3] cart:{kode}
     */
    public const RELEASE_TABLE = <<<'LUA'
if redis.call('EXISTS', KEYS[1]) == 0 then
  return cjson.encode({ok = false, code = 'TABLE_NOT_FOUND'})
end
if redis.call('SCARD', KEYS[2]) > 0 then
  return cjson.encode({ok = false, code = 'TABLE_HAS_ORDERS'})
end
local was = redis.call('HGET', KEYS[1], 'status')
local sesi = tonumber(redis.call('HGET', KEYS[1], 'sesi') or '1') or 1
-- sesi + 1: HP tamu yang sudah pergi tidak bisa lagi memesan ke meja ini; tamu baru scan QR yang sama.
redis.call('HMSET', KEYS[1], 'status', 'Tersedia', 'sesi', tostring(sesi + 1))
redis.call('HDEL', KEYS[1], 'terisi_sejak', 'tagihan_terbuka')
redis.call('DEL', KEYS[3])
if was == 'Tersedia' then
  return cjson.encode({ok = true, status = 'unchanged'})
end
return cjson.encode({ok = true, status = 'released'})
LUA;

    /**
     * Ganti QR meja: naikkan qr_versi (tidak ada = versi 1). QR dan sesi versi lama tidak berlaku lagi.
     * KEYS[1] meja:{kode}
     */
    public const ROTATE_QR = <<<'LUA'
if redis.call('EXISTS', KEYS[1]) == 0 then
  return cjson.encode({ok = false, code = 'TABLE_NOT_FOUND'})
end
local v = tonumber(redis.call('HGET', KEYS[1], 'qr_versi') or '1') or 1
redis.call('HSET', KEYS[1], 'qr_versi', tostring(v + 1))
return cjson.encode({ok = true, version = v + 1})
LUA;

    /**
     * Admin menambah menu. Id dipilih PHP (angka terbesar + 1); skrip menolak jika id sudah pernah dipakai,
     * termasuk menu yang sudah dihapus, agar riwayat penjualan per id_menu tidak tercampur.
     * KEYS[1] menu:{id}  KEYS[2] idx:menu  KEYS[3] idx:menu:dihapus  KEYS[4] kategori:{k}  KEYS[5] kategori:{k}:menu
     * ARGV[1] id  ARGV[2] JSON field datar [nama, nilai, ...]
     */
    public const CREATE_MENU = <<<'LUA'
if redis.call('EXISTS', KEYS[1]) == 1 or redis.call('SISMEMBER', KEYS[2], ARGV[1]) == 1
  or redis.call('SISMEMBER', KEYS[3], ARGV[1]) == 1 then
  return cjson.encode({ok = false, code = 'ID_TAKEN'})
end
if redis.call('EXISTS', KEYS[4]) == 0 then
  return cjson.encode({ok = false, code = 'CATEGORY_NOT_FOUND'})
end
local fields = cjson.decode(ARGV[2])
if #fields == 0 or #fields % 2 ~= 0 then
  return cjson.encode({ok = false, code = 'BAD_REQUEST'})
end
redis.call('HMSET', KEYS[1], unpack(fields))
redis.call('SADD', KEYS[2], ARGV[1])
redis.call('SADD', KEYS[5], ARGV[1])
return cjson.encode({ok = true, status = 'created', id = ARGV[1]})
LUA;

    /**
     * Admin mengubah menu (nama, harga, deskripsi, kategori, status, pasangan). Ditandai diubah_admin=1
     * sehingga sinkron dari config/menu.php tidak menimpa teksnya lagi.
     * KEYS[1] menu:{id}  KEYS[2] kategori:{lama}:menu  KEYS[3] kategori:{baru}  KEYS[4] kategori:{baru}:menu
     * ARGV[1] id  ARGV[2] kategori lama yang diharapkan  ARGV[3] JSON field datar
     */
    public const UPDATE_MENU = <<<'LUA'
if redis.call('HEXISTS', KEYS[1], 'nama_menu') == 0 then
  return cjson.encode({ok = false, code = 'MENU_NOT_FOUND'})
end
if redis.call('HGET', KEYS[1], 'id_kategori') ~= ARGV[2] then
  return cjson.encode({ok = false, code = 'MENU_CHANGED'})
end
if redis.call('EXISTS', KEYS[3]) == 0 then
  return cjson.encode({ok = false, code = 'CATEGORY_NOT_FOUND'})
end
local fields = cjson.decode(ARGV[3])
if #fields == 0 or #fields % 2 ~= 0 then
  return cjson.encode({ok = false, code = 'BAD_REQUEST'})
end
table.insert(fields, 'diubah_admin')
table.insert(fields, '1')
redis.call('HMSET', KEYS[1], unpack(fields))
if KEYS[2] ~= KEYS[4] then
  redis.call('SREM', KEYS[2], ARGV[1])
  redis.call('SADD', KEYS[4], ARGV[1])
end
return cjson.encode({ok = true, status = 'updated'})
LUA;

    /**
     * Admin menghapus menu. Pesanan lama tidak berubah (harga & nama sudah tersalin di pesanan:{id}:detail).
     * Id dicatat di idx:menu:dihapus agar tidak dipakai ulang dan tidak dibuat lagi oleh sinkron config.
     * KEYS[1] menu:{id}  KEYS[2] idx:menu  KEYS[3] idx:menu:dihapus  KEYS[4] kategori:{k}:menu  KEYS[5] pilihan:hari-ini
     * KEYS[6] foto:menu:{id} (foto unggahan admin, ikut dihapus)
     * ARGV[1] id  ARGV[2] kategori yang diharapkan
     */
    public const DELETE_MENU = <<<'LUA'
if redis.call('HEXISTS', KEYS[1], 'nama_menu') == 0 then
  return cjson.encode({ok = false, code = 'MENU_NOT_FOUND'})
end
if redis.call('HGET', KEYS[1], 'id_kategori') ~= ARGV[2] then
  return cjson.encode({ok = false, code = 'MENU_CHANGED'})
end
local t = redis.call('TYPE', KEYS[5]).ok
if t ~= 'none' and t ~= 'set' then
  return cjson.encode({ok = false, code = 'KEY_TYPE_CONFLICT'})
end
redis.call('DEL', KEYS[1], KEYS[6])
redis.call('SREM', KEYS[2], ARGV[1])
redis.call('SREM', KEYS[4], ARGV[1])
redis.call('SADD', KEYS[3], ARGV[1])
redis.call('SREM', KEYS[5], ARGV[1])
return cjson.encode({ok = true, status = 'deleted'})
LUA;

    /**
     * Foto menu unggahan admin disimpan di Redis (bukan folder), agar tetap ada di hosting serverless
     * seperti Vercel yang foldernya tidak permanen. Field foto di menu:{id} menunjuk ke alamat foto.
     * KEYS[1] menu:{id}  KEYS[2] foto:menu:{id}
     * ARGV[1] tipe (image/jpeg|png|webp) atau '' untuk menghapus  ARGV[2] isi file  ARGV[3] versi  ARGV[4] alamat foto
     * Hapus: field foto = '-' (sembunyikan juga foto bawaan berbentuk file).
     */
    public const SET_MENU_PHOTO = <<<'LUA'
if redis.call('HEXISTS', KEYS[1], 'nama_menu') == 0 then
  return cjson.encode({ok = false, code = 'MENU_NOT_FOUND'})
end
if ARGV[1] == '' then
  redis.call('DEL', KEYS[2])
  redis.call('HSET', KEYS[1], 'foto', '-')
  return cjson.encode({ok = true, status = 'removed'})
end
redis.call('DEL', KEYS[2])
redis.call('HMSET', KEYS[2], 'tipe', ARGV[1], 'data', ARGV[2], 'versi', ARGV[3])
redis.call('HSET', KEYS[1], 'foto', ARGV[4])
return cjson.encode({ok = true, status = 'saved'})
LUA;

    /**
     * Tambah akun staf (admin/kasir). Email unik (idx:users:email) dan username unik (idx:users:username).
     * KEYS[1] users:{id}  KEYS[2] idx:users  KEYS[3] idx:users:email  KEYS[4] idx:users:username
     * ARGV[1] id  ARGV[2] email (huruf kecil)  ARGV[3] JSON field datar  ARGV[4] username
     */
    public const CREATE_STAFF = <<<'LUA'
if redis.call('HEXISTS', KEYS[3], ARGV[2]) == 1 then
  return cjson.encode({ok = false, code = 'EMAIL_TAKEN'})
end
if redis.call('HEXISTS', KEYS[4], ARGV[4]) == 1 then
  return cjson.encode({ok = false, code = 'USERNAME_TAKEN'})
end
if redis.call('EXISTS', KEYS[1]) == 1 or redis.call('SISMEMBER', KEYS[2], ARGV[1]) == 1 then
  return cjson.encode({ok = false, code = 'ID_TAKEN'})
end
local fields = cjson.decode(ARGV[3])
redis.call('HMSET', KEYS[1], unpack(fields))
redis.call('SADD', KEYS[2], ARGV[1])
redis.call('HSET', KEYS[3], ARGV[2], ARGV[1])
redis.call('HSET', KEYS[4], ARGV[4], ARGV[1])
return cjson.encode({ok = true, status = 'created', id = ARGV[1]})
LUA;

    /**
     * Ganti username login staf (untuk akun lama yang dibuat sebelum login memakai username).
     * KEYS[1] users:{id}  KEYS[2] idx:users:username
     * ARGV[1] id  ARGV[2] username baru
     */
    public const SET_USERNAME = <<<'LUA'
if redis.call('EXISTS', KEYS[1]) == 0 then
  return cjson.encode({ok = false, code = 'STAFF_NOT_FOUND'})
end
local owner = redis.call('HGET', KEYS[2], ARGV[2])
if owner and owner ~= ARGV[1] then
  return cjson.encode({ok = false, code = 'USERNAME_TAKEN'})
end
local old = redis.call('HGET', KEYS[1], 'username')
if old and old ~= ARGV[2] and redis.call('HGET', KEYS[2], old) == ARGV[1] then
  redis.call('HDEL', KEYS[2], old)
end
redis.call('HSET', KEYS[1], 'username', ARGV[2])
redis.call('HSET', KEYS[2], ARGV[2], ARGV[1])
if old == ARGV[2] then
  return cjson.encode({ok = true, status = 'unchanged'})
end
return cjson.encode({ok = true, status = 'updated'})
LUA;

    /**
     * Ubah ketersediaan menu (Tersedia / Habis). Menu yang tidak ada tidak dibuat.
     * KEYS[1] menu:{id}  ARGV[1] status baru
     */
    /**
     * Pilihan dapur hari ini (dipilih kasir). Set dengan EXPIREAT tengah malam, jadi kosong lagi esok hari.
     * KEYS[1] menu:{id}  KEYS[2] pilihan:hari-ini
     * ARGV[1] id menu  ARGV[2] tambah|hapus  ARGV[3] batas jumlah  ARGV[4] waktu kedaluwarsa (UNIX)
     */
    public const TOGGLE_PICK = <<<'LUA'
if redis.call('HEXISTS', KEYS[1], 'nama_menu') == 0 then
  return cjson.encode({ok = false, code = 'MENU_NOT_FOUND'})
end
local t = redis.call('TYPE', KEYS[2]).ok
if t ~= 'none' and t ~= 'set' then
  return cjson.encode({ok = false, code = 'KEY_TYPE_CONFLICT'})
end
if ARGV[2] == 'hapus' then
  if redis.call('SREM', KEYS[2], ARGV[1]) == 1 then
    return cjson.encode({ok = true, status = 'removed'})
  end
  return cjson.encode({ok = true, status = 'unchanged'})
end
if redis.call('SISMEMBER', KEYS[2], ARGV[1]) == 1 then
  return cjson.encode({ok = true, status = 'unchanged'})
end
if redis.call('HGET', KEYS[1], 'status') ~= 'Tersedia' then
  return cjson.encode({ok = false, code = 'PICK_UNAVAILABLE'})
end
if redis.call('SCARD', KEYS[2]) >= tonumber(ARGV[3]) then
  return cjson.encode({ok = false, code = 'PICK_LIMIT'})
end
redis.call('SADD', KEYS[2], ARGV[1])
redis.call('EXPIREAT', KEYS[2], tonumber(ARGV[4]))
return cjson.encode({ok = true, status = 'added'})
LUA;

    public const SET_MENU_STATUS = <<<'LUA'
if redis.call('HEXISTS', KEYS[1], 'nama_menu') == 0 then
  return cjson.encode({ok = false, code = 'MENU_NOT_FOUND'})
end
if redis.call('HGET', KEYS[1], 'status') == ARGV[1] then
  return cjson.encode({ok = true, status = 'unchanged'})
end
redis.call('HSET', KEYS[1], 'status', ARGV[1])
return cjson.encode({ok = true, status = 'updated'})
LUA;
}
