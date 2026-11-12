# Kontrak data Kokoro Cakes

Dokumen ini menjelaskan key Redis, bentuk data yang dipakai antarmuka `App\Data\DataService`, kode error, aturan status, dan keputusan desain dari prompt, proposal, dan wireframe.

## 1. Keputusan dari acuan

| Bagian | Keputusan |
|---|---|
| Backend | PHP murni modular, Predis, Redis sebagai database utama. |
| Frontend | HTML, CSS, JavaScript biasa, Fetch API untuk polling. |
| Desain | Hijau teh gelap, krem, pink lembut; pelanggan mobile-first, kasir desktop/tablet. |
| Meja | 1A–2D Indoor, 3A–3D Outdoor (wireframe pelanggan). Kapasitas adalah asumsi contoh. |
| Kategori | Cake & Dessert, Pastry, Drink, Seasonal (proposal) + Wagashi (tambahan). Setiap kategori dan menu punya nama Jepang (`nama_jp`). |
| Harga | Lima contoh wireframe dipertahankan: Strawberry Cake 28.000; Matcha Cake 32.000; Tiramisu 28.000; Iced Matcha Latte 24.000; Chocolate Latte 26.000. Harga lain adalah contoh. |
| Stok | Dua status sesuai prompt: Tersedia/Habis. Tidak ada stok angka. |
| Foto | Field `foto` kosong. Website mencari `public/assets/img/menu/foto/{id}.webp/.jpg/.png`; jika tidak ada, kartu menu tampil tanpa gambar. Prompt foto: PROMPT-GAMBAR-MENU.md. |
| Kasir | Hanya mengubah ketersediaan menu. Tombol "Tambah Menu" pada wireframe tidak diikuti karena prompt melarangnya; CRUD menu kelak untuk admin. |
| QRIS | Simulasi. Tidak ada payment gateway. |
| Status | Masuk, Diproses, Siap Diantar, Selesai (+ Dibatalkan). Label Baru/Pesanan Diterima di gambar dipetakan ke Masuk. |
| Pajak | `TAX_PERCENT`, bawaan 10 (contoh wireframe pelanggan). Bukan tarif usaha yang sudah dikonfirmasi. Pelanggan dan kasir melihat total server yang sama. |

## 2. Key wajib dari prompt (nama dipakai apa adanya, tanpa prefix)

| Key | Tipe | Field / isi |
|---|---|---|
| `users:{id_user}` | Hash | nama, email, password_hash, role (kasir/admin), status |
| `meja:{kode_meja}` | Hash | kapasitas, status (Tersedia/Terisi) + area, aktif, qr_versi (tidak ada = 1; naik saat admin mengganti QR), tagihan_terbuka (id pesanan bayar di kasir yang belum dibayar; maksimal satu per meja), terisi_sejak (waktu pesanan pertama sejak meja kosong), sesi (naik setiap meja dikosongkan; sesi tamu menyimpan nilai ini) |
| `menu:{id_menu}` | Hash | id_kategori, nama_menu, harga, deskripsi, foto, status (Tersedia/Habis) + nama_jp, pasangan (id menu yang disarankan), pasangan_alasan |
| `kategori:{id_kategori}:menu` | Set | ID menu |
| `cart:{kode_meja}` | Hash | id_menu → qty. Kedaluwarsa 6 jam tanpa perubahan. |
| `pesanan:{id_pesanan}` | Hash | kode_pesanan, kode_meja, waktu, total, status_pesanan, metode_bayar + field tambahan di bawah |
| `pesanan:{id_pesanan}:detail` | List | JSON per baris: id_menu, nama_menu, jumlah, harga_satuan, subtotal (snapshot) |

Field tambahan `pesanan:{id}`: `id`, `nama_pemesan`, `catatan`, `subtotal`, `pajak_persen`, `pajak`, `jumlah_item`, `status_bayar` (Belum Bayar/Lunas/Dikembalikan), `dibayar_at`, `selesai_at`, `dibatalkan_at`, `dibatalkan_oleh`, `alasan_batal`, `owner_hash` (sha256 perangkat pemesan), `request_id`, `updated_at`.

## 3. Key tambahan

| Key | Tipe | Tujuan |
|---|---|---|
| `kategori:{id}` | Hash | nama_kategori, nama_jp, urutan |
| `idx:meja`, `idx:menu`, `idx:kategori`, `idx:users` | Set | Daftar ID |
| `idx:users:email` | Hash | email huruf kecil → ID staf |
| `kokoro:seed:v1` | String | Marker agar seed tidak berulang |
| `seq:pesanan` | String | Penghitung nomor pesanan (INCR) |
| `idx:pesanan` | Sorted set | ID pesanan, skor = waktu dibuat |
| `idx:pesanan:selesai` | Sorted set | ID pesanan selesai, skor = waktu selesai (untuk rekap) |
| `idx:pesanan:belum-bayar` | Sorted set | ID pesanan bayar di kasir yang belum dibayar, skor = `batas_bayar`. Disapu `expireUnpaidOrders()` di setiap permintaan (ZRANGEBYSCORE), keluar saat dibayar/dibatalkan |
| `pesanan:{id}:riwayat` | List | JSON: jenis (status/bayar), status, at, by, note |
| `meja:{kode}:aktif` | Set | ID pesanan meja itu yang belum Selesai/Dibatalkan |
| `pelanggan:{owner_hash}:pesanan` | Sorted set | Pesanan milik satu perangkat |
| `checkout:request:{request_id}` | String (TTL 24 jam) | JSON order_id + payload_hash untuk idempotensi |
| `idx:menu:dihapus` | Set | ID menu yang dihapus admin: tidak dipakai ulang dan tidak dibuat lagi oleh sinkron `config/menu.php` |
| `pilihan:hari-ini` | Set (EXPIREAT tengah malam WIB) | ID menu "pilihan dapur hari ini" yang dipilih kasir, maksimal 3. Kosong lagi otomatis esok hari. |

Data awal: 12 hash meja + 39 hash menu + 5 hash kategori + 1 hash user + 1 hash indeks email + 5 set relasi + 4 set indeks + 1 marker = **68 key**, semuanya tanpa TTL.

**Sinkron katalog** (skrip Lua `SYNC_CATALOG`, dijalankan `seed-demo.php` bila marker sudah ada): kategori/menu yang belum ada ditambahkan beserta indeks dan relasinya; untuk yang sudah ada hanya field yang belum ada diisi dengan `HSETNX`. Semua tipe key diperiksa dulu sebelum menulis. Database yang dulu di-seed dengan 15 menu (42 key) menjadi 39 menu.

## 4. Antarmuka `DataService`

Controller dan Domain hanya memanggil antarmuka ini (`app/Data/DataService.php`); implementasinya `RedisLocalAdapter`.

| Operasi | Keterangan |
|---|---|
| `listCategories()`, `listMenu()`, `getMenu(id)` | Baca menu. `harga` berupa int, `tersedia` bool. |
| `setMenuStatus(id, status)` | Lua `SET_MENU_STATUS`; menu yang tidak ada tidak dibuat. |
| `createMenu(fields)`, `updateMenu(id, fields)`, `deleteMenu(id)` | Lua `CREATE_MENU` / `UPDATE_MENU` / `DELETE_MENU` (admin). Id baru = angka terbesar di `idx:menu` ∪ `idx:menu:dihapus` + 1. Ubah menandai `diubah_admin=1` agar sinkron config tidak menimpa teksnya. Hapus juga mengeluarkan menu dari `kategori:{k}:menu` dan `pilihan:hari-ini`; pesanan lama tidak berubah. Kode tolak: `ID_TAKEN`, `CATEGORY_NOT_FOUND`, `MENU_CHANGED`, `MENU_NOT_FOUND`. |
| `rotateTableQr(kode)` | Lua `ROTATE_QR`: `qr_versi` + 1. Token QR = HMAC(APP_KEY, `meja|kode` untuk versi 1, `meja|kode|vN` untuk versi N). Sesi tamu menyimpan versi QR; versi berbeda = sesi meja dihapus. |
| `createStaff(nama, email, hash, role)` | Lua `CREATE_STAFF`: email unik lewat `idx:users:email` (`EMAIL_TAKEN`). Dipakai `scripts/create-admin.php`. |
| `listTodayPicks()`, `setTodayPick(id, on, limit, expireAt)` | Lua `TOGGLE_PICK`: hanya menu Tersedia, maksimal `limit`, lalu `EXPIREAT` tengah malam. Kode tolak: `PICK_LIMIT`, `PICK_UNAVAILABLE`, `MENU_NOT_FOUND`. |
| `listTables()`, `getTable(kode)` | Termasuk `pesanan_aktif`. |
| `releaseTable(kode)` | Lua `RELEASE_TABLE`; ditolak jika masih ada pesanan aktif. |
| `getCart`, `setCartItem`, `removeCartItem` | Keranjang per meja. |
| `checkout(request)` | Lua `CHECKOUT`. Request tidak berisi harga. |
| `getCheckoutResult(request_id, access)` | Mencari hasil checkout lama. |
| `getOrder(id, access)`, `listOrders(access, filter)` | `access` = `['type'=>'kasir']` atau `['type'=>'customer','owner_hash'=>…]`. |
| `updateOrderStatus(id, from, to, actor)` | Lua `UPDATE_STATUS`. Semua langkah maju ditolak `PAYMENT_REQUIRED` sebelum status bayar Lunas (bayar dulu, baru disiapkan). |
| `confirmPayment(id, actor)` | Lua `CONFIRM_PAYMENT`: Lunas, keluar dari `idx:pesanan:belum-bayar`, lepas `tagihan_terbuka` meja. |
| `cancelOrder(id, reason, actor)` | Lua `CANCEL`. |
| `expireUnpaidOrders(now, reason)` | Batalkan (pelaku `sistem`) pesanan di `idx:pesanan:belum-bayar` yang lewat batas dan masih Belum Bayar; pesanan yang sudah lunas tidak tersentuh. Checkout bayar di kasir ditolak `UNPAID_EXISTS` jika meja masih punya `tagihan_terbuka`. |
| `getSalesSummary(from, to)` | Rekap pesanan Selesai. |
| `findStaffByEmail`, `setStaffPasswordHash` | Akun kasir. |

Contoh request checkout:

```json
{
  "request_id": "9f0c1e…(32 hex)",
  "kode_meja": "1A",
  "owner_hash": "sha256…",
  "nama_pemesan": "Sakura",
  "catatan": "Es sedikit",
  "metode_bayar": "qris",
  "items": [{"id_menu": "m1", "qty": 2}, {"id_menu": "m9", "qty": 1}]
}
```

## 5. Checkout (skrip Lua `CHECKOUT`)

1. Jika `checkout:request:{id}` ada: payload sama → kembalikan pesanan yang sama (`duplicate`); payload beda → `IDEMPOTENCY_CONFLICT`.
2. **Tahap validasi** (tanpa menulis): meja aktif; isi `cart:{kode}` sama persis dengan item (jumlah baris dan qty); setiap menu ada dan `Tersedia`; qty bilangan bulat 1–99.
3. **Tahap tulis**: detail (harga snapshot), header pesanan dengan subtotal, pajak = ⌊(subtotal × persen + 50) / 100⌋, total; riwayat; indeks; `meja:{kode}:aktif`; meja → Terisi; hapus `cart:{kode}`; simpan catatan request.

QRIS (simulasi) langsung `Lunas`; bayar di kasir `Belum Bayar`.

## 6. Kode error (`DomainRejected`)

| Kode | Arti |
|---|---|
| `EMPTY_ORDER` | Keranjang kosong |
| `CART_CHANGED` | Isi keranjang meja berbeda dengan yang ditinjau |
| `TABLE_UNAVAILABLE` | Meja tidak aktif |
| `MENU_UNAVAILABLE` / `MENU_NOT_FOUND` | Menu habis / tidak ada |
| `INVALID_QTY` | Jumlah tidak sah |
| `IDEMPOTENCY_CONFLICT` | request_id dipakai untuk isi berbeda |
| `ORDER_NOT_FOUND` | Pesanan tidak ada |
| `INVALID_TRANSITION` | Loncat status / mundur |
| `STATUS_CONFLICT` | Status sudah diubah orang lain |
| `PAYMENT_REQUIRED` | Menandai Selesai sebelum Lunas |
| `NOT_CANCELLABLE` / `ALREADY_CANCELLED` | Pembatalan di luar status Masuk / sudah batal |
| `REASON_REQUIRED` | Alasan batal kosong |
| `TABLE_HAS_ORDERS` / `TABLE_NOT_FOUND` | Kosongkan meja yang masih punya pesanan / meja tidak ada |

Gangguan koneksi Redis dilempar sebagai `DataServiceUnavailable` (halaman 503), tidak pernah dianggap "kosong" atau "berhasil". Jika putus saat checkout, pelanggan diarahkan ke halaman "hasil belum diketahui" dan bisa memeriksa ulang dengan `request_id` yang sama.

## 7. Status pesanan

```text
Masuk ──> Diproses ──> Siap Diantar ──> Selesai (wajib Lunas)
  │
  └──> Dibatalkan (alasan wajib; Lunas → Dikembalikan)
```

Setelah Selesai/Dibatalkan, ID pesanan dikeluarkan dari `meja:{kode}:aktif`. Jika set itu kosong, meja kembali Tersedia, di dalam skrip yang sama.

## 8. Rekap

`getSalesSummary` membaca `idx:pesanan:selesai` dalam rentang `[from, to)` berdasarkan waktu selesai, menjumlahkan subtotal, pajak, total, dan item terjual per menu dari detail (harga snapshot).
