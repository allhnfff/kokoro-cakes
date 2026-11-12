<?php

declare(strict_types=1);

namespace App\Data;

use App\Domain\OrderRules;
use Predis\Client;
use Predis\ClientInterface;
use Predis\ClientException;
use Predis\PredisException;
use Predis\Response\ServerException;

/**
 * Implementasi DataService di atas Redis (Predis).
 *
 * Key wajib dari prompt dipakai apa adanya (tanpa prefix):
 *   users:{id}  meja:{kode}  menu:{id}  kategori:{id}:menu  cart:{kode_meja}
 *   pesanan:{id}  pesanan:{id}:detail
 * Key tambahan (indeks, riwayat, idempotensi) dijelaskan di docs/KONTRAK-DATA.md.
 *
 * Semua perubahan yang menyentuh lebih dari satu key dijalankan lewat skrip Lua (LuaScripts).
 */
final class RedisLocalAdapter implements DataService
{
    public const SEED_MARKER = 'kokoro:seed:v1';
    /** Set id menu yang sudah dihapus admin (tidak dipakai ulang, tidak dibuat lagi oleh sinkron config). */
    public const DELETED_MENU_KEY = 'idx:menu:dihapus';
    /** Pesanan bayar di kasir yang belum dibayar; skor = batas waktu bayar (UNIX). */
    public const UNPAID_KEY = 'idx:pesanan:belum-bayar';
    /** Field menu yang boleh diisi admin lewat createMenu/updateMenu. */
    private const MENU_FIELDS = ['id_kategori', 'nama_menu', 'nama_jp', 'harga', 'deskripsi', 'status', 'pasangan', 'pasangan_alasan'];
    /** Set id menu pilihan dapur; diberi EXPIREAT tengah malam sehingga kosong lagi esok hari. */
    public const PICKS_KEY = 'pilihan:hari-ini';

    /** @param array<string,mixed> $order konfigurasi 'order' */
    public function __construct(
        private readonly ClientInterface $redis,
        private readonly array $order,
        private readonly string $label = 'Redis lokal'
    ) {
    }

    /** @param array<string,mixed> $config seluruh konfigurasi aplikasi */
    public static function fromConfig(array $config, ?int $database = null): self
    {
        $r = $config['redis'];
        $db = $database ?? (int) $r['database'];
        return new self(
            self::createClient($r, $db),
            (array) $config['order'],
            ($r['scheme'] ?? 'tcp') === 'tls'
                ? sprintf('Redis online (TLS) %s', $r['host'])
                : sprintf('Redis lokal %s:%d (db %d)', $r['host'], $r['port'], $db)
        );
    }

    /** Membuat klien Predis. Tidak langsung terhubung; koneksi dibuka saat perintah pertama. */
    public static function createClient(array $cfg, ?int $database = null): Client
    {
        $params = [
            'scheme'             => $cfg['scheme'] ?? 'tcp',
            'host'               => $cfg['host'],
            'port'               => (int) $cfg['port'],
            'database'           => $database ?? (int) $cfg['database'],
            'timeout'            => (float) $cfg['timeout'],
            'read_write_timeout' => 5,
        ];
        if (($cfg['username'] ?? '') !== '') {
            $params['username'] = $cfg['username'];
        }
        if (($cfg['password'] ?? '') !== '') {
            $params['password'] = $cfg['password'];
        }
        if ($params['scheme'] === 'tls') {
            $params['ssl'] = ['verify_peer' => true, 'verify_peer_name' => true];
        }
        return new Client($params, ['exceptions' => true]);
    }

    public function client(): ClientInterface
    {
        return $this->redis;
    }

    // =====================================================================
    // Info
    // =====================================================================

    public function describe(): array
    {
        return ['driver' => 'redis', 'label' => $this->label];
    }

    public function health(): array
    {
        try {
            $pong = (string) $this->redis->ping();
            $seeded = $this->redis->get(self::SEED_MARKER) === '1';
            return ['ok' => true, 'detail' => 'PING -> ' . $pong, 'seeded' => $seeded];
        } catch (\Throwable $e) {
            return ['ok' => false, 'detail' => 'Redis tidak dapat dihubungi.'];
        }
    }

    // =====================================================================
    // Menu & kategori
    // =====================================================================

    public function listCategories(): array
    {
        return $this->guard(function (ClientInterface $r): array {
            $ids = $r->smembers('idx:kategori');
            $rows = $ids ? $r->pipeline(function ($p) use ($ids) {
                foreach ($ids as $id) {
                    $p->hgetall('kategori:' . $id);
                }
            }) : [];
            $out = [];
            foreach ($ids as $i => $id) {
                $h = $rows[$i] ?? [];
                if (!$h) {
                    continue;
                }
                $out[] = [
                    'id'            => (string) $id,
                    'nama_kategori' => (string) $h['nama_kategori'],
                    'nama_jp'       => (string) ($h['nama_jp'] ?? ''),
                    'urutan'        => (int) ($h['urutan'] ?? 99),
                ];
            }
            usort($out, static fn ($a, $b) => [$a['urutan'], $a['id']] <=> [$b['urutan'], $b['id']]);
            return $out;
        });
    }

    public function listMenu(): array
    {
        $order = [];
        foreach ($this->listCategories() as $c) {
            $order[$c['id']] = $c['urutan'];
        }
        return $this->guard(function (ClientInterface $r) use ($order): array {
            $ids = $r->smembers('idx:menu');
            $rows = $ids ? $r->pipeline(function ($p) use ($ids) {
                foreach ($ids as $id) {
                    $p->hgetall('menu:' . $id);
                }
            }) : [];
            $out = [];
            foreach ($ids as $i => $id) {
                if (!empty($rows[$i])) {
                    $out[] = self::hydrateMenu((string) $id, $rows[$i]);
                }
            }
            usort($out, static fn ($a, $b) => [$order[$a['id_kategori']] ?? 99, (int) substr($a['id'], 1)]
                <=> [$order[$b['id_kategori']] ?? 99, (int) substr($b['id'], 1)]);
            return $out;
        });
    }

    public function getMenu(string $menuId): ?array
    {
        $h = $this->guard(fn (ClientInterface $r) => $r->hgetall('menu:' . $menuId));
        return $h ? self::hydrateMenu($menuId, $h) : null;
    }

    public function setMenuStatus(string $menuId, string $status): string
    {
        if (!in_array($status, OrderRules::MENU_STATUSES, true)) {
            throw new DomainRejected('BAD_REQUEST', 'Status menu tidak dikenal.');
        }
        $res = $this->lua(LuaScripts::SET_MENU_STATUS, ['menu:' . $menuId], [$status], true);
        return (string) $res['status'];
    }

    public function createMenu(array $fields): string
    {
        $flat = self::menuFields($fields);
        for ($try = 0; $try < 3; $try++) {
            $id = $this->guard(function (ClientInterface $r): string {
                $max = 0;
                foreach (array_merge($r->smembers('idx:menu'), $r->smembers(self::DELETED_MENU_KEY)) as $existing) {
                    $max = max($max, (int) substr((string) $existing, 1));
                }
                return 'm' . ($max + 1);
            });
            try {
                $this->lua(LuaScripts::CREATE_MENU, ['menu:' . $id, 'idx:menu', self::DELETED_MENU_KEY,
                    'kategori:' . $fields['id_kategori'], 'kategori:' . $fields['id_kategori'] . ':menu'],
                    [$id, json_encode(array_merge($flat, ['foto', '']), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)], true);
                return $id;
            } catch (DomainRejected $e) {
                if ($e->errorCode !== 'ID_TAKEN') {
                    throw $e;
                }
                // Admin lain menambah menu pada saat yang sama: hitung ulang id lalu coba lagi.
            }
        }
        throw new DomainRejected('ID_TAKEN', 'Gagal membuat kode menu baru. Coba lagi.');
    }

    public function updateMenu(string $menuId, array $fields): void
    {
        $old = $this->getMenu($menuId);
        if ($old === null) {
            throw new DomainRejected('MENU_NOT_FOUND');
        }
        $new = (string) $fields['id_kategori'];
        $this->lua(LuaScripts::UPDATE_MENU, ['menu:' . $menuId, 'kategori:' . $old['id_kategori'] . ':menu',
            'kategori:' . $new, 'kategori:' . $new . ':menu'],
            [$menuId, $old['id_kategori'], json_encode(self::menuFields($fields), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)], true);
    }

    public function deleteMenu(string $menuId): void
    {
        $old = $this->getMenu($menuId);
        if ($old === null) {
            throw new DomainRejected('MENU_NOT_FOUND');
        }
        $this->lua(LuaScripts::DELETE_MENU, ['menu:' . $menuId, 'idx:menu', self::DELETED_MENU_KEY,
            'kategori:' . $old['id_kategori'] . ':menu', self::PICKS_KEY, 'foto:menu:' . $menuId], [$menuId, $old['id_kategori']], true);
    }

    public function setMenuPhoto(string $menuId, string $mime, string $bytes): string
    {
        $version = substr(hash('sha256', $bytes), 0, 12);
        $url = '/foto/menu/' . $menuId . '/' . $version;
        $this->lua(LuaScripts::SET_MENU_PHOTO, ['menu:' . $menuId, 'foto:menu:' . $menuId], [$mime, $bytes, $version, $url], true);
        return $url;
    }

    public function removeMenuPhoto(string $menuId): void
    {
        $this->lua(LuaScripts::SET_MENU_PHOTO, ['menu:' . $menuId, 'foto:menu:' . $menuId], ['', '', '', ''], true);
    }

    public function getMenuPhoto(string $menuId, string $version): ?array
    {
        [$tipe, $data, $ver] = $this->guard(fn (ClientInterface $r) => $r->hmget('foto:menu:' . $menuId, ['tipe', 'data', 'versi']));
        if ($data === null || $tipe === null || !hash_equals((string) $ver, $version)) {
            return null;
        }
        return ['tipe' => (string) $tipe, 'data' => (string) $data];
    }

    public function createStaff(string $nama, string $email, string $passwordHash, string $role, string $username = ''): string
    {
        $email = strtolower(trim($email));
        $username = strtolower(trim($username)) ?: strstr($email, '@', true);
        for ($try = 0; $try < 3; $try++) {
            $id = (string) ($this->guard(fn (ClientInterface $r) => max(array_map('intval', $r->smembers('idx:users') ?: ['0']))) + 1);
            try {
                $this->lua(LuaScripts::CREATE_STAFF, ['users:' . $id, 'idx:users', 'idx:users:email', 'idx:users:username'], [$id, $email,
                    json_encode(['nama', $nama, 'username', $username, 'email', $email, 'password_hash', $passwordHash, 'role', $role, 'status', 'Aktif'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), $username], true);
                return $id;
            } catch (DomainRejected $e) {
                if ($e->errorCode !== 'ID_TAKEN') {
                    throw $e;
                }
            }
        }
        throw new DomainRejected('ID_TAKEN', 'Gagal membuat akun. Coba lagi.');
    }

    /** @param array<string,mixed> $fields @return list<string> field datar untuk HMSET */
    private static function menuFields(array $fields): array
    {
        $flat = [];
        foreach (self::MENU_FIELDS as $f) {
            if (!array_key_exists($f, $fields)) {
                throw new DomainRejected('BAD_REQUEST', "Field {$f} wajib ada.");
            }
            $flat[] = $f;
            $flat[] = (string) $fields[$f];
        }
        return $flat;
    }

    public function listTodayPicks(): array
    {
        $ids = $this->guard(fn (ClientInterface $r) => $r->smembers(self::PICKS_KEY));
        sort($ids, SORT_NATURAL);
        return array_values(array_map('strval', $ids));
    }

    public function setTodayPick(string $menuId, bool $on, int $limit, int $expireAt): string
    {
        $res = $this->lua(LuaScripts::TOGGLE_PICK, ['menu:' . $menuId, self::PICKS_KEY],
            [$menuId, $on ? 'tambah' : 'hapus', (string) $limit, (string) $expireAt], true);
        return (string) $res['status'];
    }

    /** @param array<string,string> $h */
    private static function hydrateMenu(string $id, array $h): array
    {
        return [
            'id'          => $id,
            'id_kategori' => (string) ($h['id_kategori'] ?? ''),
            'nama_menu'   => (string) ($h['nama_menu'] ?? $id),
            'nama_jp'     => (string) ($h['nama_jp'] ?? ''),
            'harga'       => (int) ($h['harga'] ?? 0),
            'deskripsi'   => (string) ($h['deskripsi'] ?? ''),
            'foto'        => (string) ($h['foto'] ?? ''),
            'status'      => (string) ($h['status'] ?? 'Habis'),
            'tersedia'    => ($h['status'] ?? '') === 'Tersedia',
            // Saran pasangan (id menu lain + alasan); kosong jika tidak ada.
            'pasangan'        => (string) ($h['pasangan'] ?? ''),
            'pasangan_alasan' => (string) ($h['pasangan_alasan'] ?? ''),
        ];
    }

    // =====================================================================
    // Meja
    // =====================================================================

    public function listTables(): array
    {
        return $this->guard(function (ClientInterface $r): array {
            $codes = $r->smembers('idx:meja');
            sort($codes, SORT_NATURAL);
            $rows = $codes ? $r->pipeline(function ($p) use ($codes) {
                foreach ($codes as $c) {
                    $p->hgetall('meja:' . $c);
                    $p->scard('meja:' . $c . ':aktif');
                }
            }) : [];
            $out = [];
            foreach ($codes as $i => $code) {
                $h = $rows[$i * 2] ?? [];
                if ($h) {
                    $out[] = self::hydrateTable((string) $code, $h, (int) ($rows[$i * 2 + 1] ?? 0));
                }
            }
            return $out;
        });
    }

    public function getTable(string $code): ?array
    {
        [$h, $active] = $this->guard(fn (ClientInterface $r) => $r->pipeline(function ($p) use ($code) {
            $p->hgetall('meja:' . $code);
            $p->scard('meja:' . $code . ':aktif');
        }));
        return $h ? self::hydrateTable($code, $h, (int) $active) : null;
    }

    public function releaseTable(string $code): string
    {
        $res = $this->lua(LuaScripts::RELEASE_TABLE, ['meja:' . $code, 'meja:' . $code . ':aktif', 'cart:' . $code], [], true);
        return (string) $res['status'];
    }

    public function rotateTableQr(string $code): int
    {
        $res = $this->lua(LuaScripts::ROTATE_QR, ['meja:' . $code], [], true);
        return (int) $res['version'];
    }

    /** @param array<string,string> $h */
    private static function hydrateTable(string $code, array $h, int $activeOrders): array
    {
        return [
            'kode'          => $code,
            'kapasitas'     => (int) ($h['kapasitas'] ?? 0),
            'status'        => (string) ($h['status'] ?? 'Tersedia'),
            'area'          => (string) ($h['area'] ?? ''),
            'aktif'         => ($h['aktif'] ?? '0') === '1',
            'pesanan_aktif' => $activeOrders,
            'qr_versi'      => max(1, (int) ($h['qr_versi'] ?? 1)),
            // sesi naik setiap meja dikosongkan; sesi tamu dari sebelum itu tidak berlaku lagi.
            'sesi'          => max(1, (int) ($h['sesi'] ?? 1)),
            'terisi_sejak'  => (int) ($h['terisi_sejak'] ?? 0),
        ];
    }

    // =====================================================================
    // Keranjang
    // =====================================================================

    public function getCart(string $tableCode): array
    {
        $h = $this->guard(fn (ClientInterface $r) => $r->hgetall('cart:' . $tableCode));
        $out = [];
        foreach ($h as $menuId => $qty) {
            if ((int) $qty > 0) {
                $out[] = ['id_menu' => (string) $menuId, 'qty' => (int) $qty];
            }
        }
        usort($out, static fn ($a, $b) => (int) substr($a['id_menu'], 1) <=> (int) substr($b['id_menu'], 1));
        return $out;
    }

    public function setCartItem(string $tableCode, string $menuId, int $qty): void
    {
        $key = 'cart:' . $tableCode;
        $ttl = (int) ($this->order['cart_ttl'] ?? 21600);
        $this->guard(function (ClientInterface $r) use ($key, $menuId, $qty, $ttl) {
            $r->pipeline(function ($p) use ($key, $menuId, $qty, $ttl) {
                if ($qty > 0) {
                    $p->hset($key, $menuId, (string) $qty);
                } else {
                    $p->hdel($key, [$menuId]);
                }
                $p->expire($key, $ttl);
            });
        }, true);
    }

    public function removeCartItem(string $tableCode, string $menuId): void
    {
        $this->guard(fn (ClientInterface $r) => $r->hdel('cart:' . $tableCode, [$menuId]), true);
    }

    // =====================================================================
    // Checkout
    // =====================================================================

    public function checkout(array $request): array
    {
        $items = array_map(static fn (array $l): array => ['id_menu' => (string) $l['id_menu'], 'qty' => (int) $l['qty']], $request['items']);
        // Urutan tetap (m1, m2, … m10) supaya sidik payload idempotensi selalu sama dan detail rapi.
        usort($items, static fn ($a, $b) => (int) substr($a['id_menu'], 1) <=> (int) substr($b['id_menu'], 1));
        if (!$items) {
            throw new DomainRejected('EMPTY_ORDER');
        }
        $table = (string) $request['kode_meja'];
        $owner = (string) $request['owner_hash'];
        $payloadHash = hash('sha256', json_encode([
            $table, $owner, $request['nama_pemesan'], $request['catatan'], $request['metode_bayar'], $items,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

        $id = (int) $this->guard(fn (ClientInterface $r) => $r->incr('seq:pesanan'), true);
        $header = [
            'id'            => (string) $id,
            'kode_pesanan'  => sprintf('KK-%s-%04d', date('ymd'), $id),
            'kode_meja'     => $table,
            'nama_pemesan'  => (string) $request['nama_pemesan'],
            'catatan'       => (string) $request['catatan'],
            'metode_bayar'  => (string) $request['metode_bayar'],
            'owner_hash'    => $owner,
            'request_id'    => (string) $request['request_id'],
        ];
        $keys = [
            'checkout:request:' . $request['request_id'],
            'pesanan:' . $id, 'pesanan:' . $id . ':detail', 'pesanan:' . $id . ':riwayat',
            'idx:pesanan', 'pelanggan:' . $owner . ':pesanan',
            'meja:' . $table, 'meja:' . $table . ':aktif', 'cart:' . $table,
        ];
        foreach ($items as $it) {
            $keys[] = 'menu:' . $it['id_menu'];
        }
        $keys[] = self::UNPAID_KEY;
        $res = $this->lua(LuaScripts::CHECKOUT, $keys, [
            $payloadHash,
            json_encode($header, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            json_encode($items, JSON_THROW_ON_ERROR),
            (string) (int) ($this->order['tax_percent'] ?? 0),
            (string) time(),
            (string) (int) ($this->order['request_ttl'] ?? 86400),
            (string) (60 * (int) ($this->order['unpaid_minutes'] ?? 15)),
        ], true);

        $order = $this->getOrder((int) $res['order_id'], ['type' => 'kasir']);
        if ($order === null) {
            throw new DataServiceUnavailable('Pesanan tercatat tetapi belum dapat dibaca ulang.', true);
        }
        return ['status' => (string) $res['status'], 'order' => $order];
    }

    public function getCheckoutResult(string $requestId, array $access): ?array
    {
        $raw = $this->guard(fn (ClientInterface $r) => $r->get('checkout:request:' . $requestId));
        if ($raw === null) {
            return null;
        }
        $rec = json_decode((string) $raw, true);
        if (!is_array($rec) || !isset($rec['order_id'])) {
            return null;
        }
        $order = $this->getOrder((int) $rec['order_id'], $access);
        return $order === null ? null : ['status' => 'duplicate', 'order' => $order];
    }

    // =====================================================================
    // Pesanan
    // =====================================================================

    public function getOrder(int $orderId, array $access): ?array
    {
        [$h, $detail, $history] = $this->guard(fn (ClientInterface $r) => $r->pipeline(function ($p) use ($orderId) {
            $p->hgetall('pesanan:' . $orderId);
            $p->lrange('pesanan:' . $orderId . ':detail', 0, -1);
            $p->lrange('pesanan:' . $orderId . ':riwayat', 0, -1);
        }));
        if (!$h || !$this->canSee($h, $access)) {
            return null;
        }
        $order = self::hydrateOrder($h);
        $order['items'] = array_values(array_filter(array_map(static function ($row) {
            $d = json_decode((string) $row, true);
            return is_array($d) ? [
                'id_menu'      => (string) ($d['id_menu'] ?? ''),
                'nama_menu'    => (string) ($d['nama_menu'] ?? ''),
                'jumlah'       => (int) ($d['jumlah'] ?? 0),
                'harga_satuan' => (int) ($d['harga_satuan'] ?? 0),
                'subtotal'     => (int) ($d['subtotal'] ?? 0),
            ] : null;
        }, $detail)));
        $order['history'] = array_values(array_filter(array_map(static function ($row) {
            $d = json_decode((string) $row, true);
            return is_array($d) ? [
                'jenis'  => (string) ($d['jenis'] ?? 'status'),
                'status' => (string) ($d['status'] ?? ''),
                'at'     => (int) ($d['at'] ?? 0),
                'by'     => (string) ($d['by'] ?? ''),
                'note'   => (string) ($d['note'] ?? ''),
            ] : null;
        }, $history)));
        return $order;
    }

    public function listOrders(array $access, array $filter = []): array
    {
        $source = ($access['type'] ?? '') === 'kasir'
            ? 'idx:pesanan'
            : 'pelanggan:' . (string) ($access['owner_hash'] ?? 'x') . ':pesanan';
        $limit = max(1, min(1000, (int) ($filter['limit'] ?? 100)));
        $rows = $this->guard(function (ClientInterface $r) use ($source) {
            $ids = $r->zrevrange($source, 0, 999);
            return $ids ? $r->pipeline(function ($p) use ($ids) {
                foreach ($ids as $id) {
                    $p->hgetall('pesanan:' . $id);
                }
            }) : [];
        });
        $status = (string) ($filter['status'] ?? '');
        $table = (string) ($filter['meja'] ?? '');
        $q = lower(trim((string) ($filter['q'] ?? '')));
        $out = [];
        foreach ($rows as $h) {
            if (!$h || !$this->canSee($h, $access)) {
                continue;
            }
            $o = self::hydrateOrder($h);
            if ($status !== '' && $o['status_pesanan'] !== $status) {
                continue;
            }
            if ($table !== '' && $o['kode_meja'] !== $table) {
                continue;
            }
            if ($q !== '' && !str_contains(lower($o['kode_pesanan'] . ' ' . $o['nama_pemesan'] . ' ' . $o['kode_meja']), $q)) {
                continue;
            }
            $out[] = $o;
            if (count($out) >= $limit) {
                break;
            }
        }
        return $out;
    }

    public function updateOrderStatus(int $orderId, string $from, string $to, string $actor): array
    {
        $table = $this->orderTable($orderId);
        $res = $this->lua(LuaScripts::UPDATE_STATUS, [
            'pesanan:' . $orderId, 'pesanan:' . $orderId . ':riwayat', 'idx:pesanan:selesai',
            'meja:' . $table, 'meja:' . $table . ':aktif',
        ], [
            $from, $to, $actor, (string) time(),
            json_encode(OrderRules::TRANSITIONS, JSON_THROW_ON_ERROR), $table, (string) $orderId,
        ], true);
        return ['status' => (string) $res['status'], 'table_idle' => !empty($res['table_idle'])];
    }

    public function confirmPayment(int $orderId, string $actor): string
    {
        $table = $this->orderTable($orderId);
        $res = $this->lua(LuaScripts::CONFIRM_PAYMENT, ['pesanan:' . $orderId, 'pesanan:' . $orderId . ':riwayat',
            'meja:' . $table, self::UNPAID_KEY], [$actor, (string) time(), (string) $orderId], true);
        return (string) $res['status'];
    }

    public function cancelOrder(int $orderId, string $reason, string $actor): array
    {
        return $this->cancel($orderId, $reason, $actor, false);
    }

    public function expireUnpaidOrders(int $now, string $reason): int
    {
        $ids = $this->guard(fn (ClientInterface $r) => $r->zrangebyscore(self::UNPAID_KEY, '-inf', (string) $now, ['limit' => [0, 50]]));
        $cancelled = 0;
        foreach ($ids as $id) {
            try {
                $res = $this->cancel((int) $id, $reason, 'sistem', true);
                $cancelled += $res['status'] === 'cancelled' ? 1 : 0;
            } catch (DomainRejected) {
                // Sudah dibayar/diproses/dihapus di sela-sela: cukup keluarkan dari antrean batas bayar.
                $this->guard(fn (ClientInterface $r) => $r->zrem(self::UNPAID_KEY, (string) $id), true);
            }
        }
        return $cancelled;
    }

    /** @return array{status:string, table_idle:bool} */
    private function cancel(int $orderId, string $reason, string $actor, bool $onlyUnpaid): array
    {
        $table = $this->orderTable($orderId);
        $res = $this->lua(LuaScripts::CANCEL, [
            'pesanan:' . $orderId, 'pesanan:' . $orderId . ':riwayat', 'meja:' . $table, 'meja:' . $table . ':aktif', self::UNPAID_KEY,
        ], [trim($reason), $actor, (string) time(), $table, (string) $orderId, $onlyUnpaid ? '1' : '0'], true);
        return ['status' => (string) $res['status'], 'table_idle' => !empty($res['table_idle'])];
    }

    public function getSalesSummary(int $fromTs, int $toTs): array
    {
        $data = $this->guard(function (ClientInterface $r) use ($fromTs, $toTs) {
            $ids = $r->zrangebyscore('idx:pesanan:selesai', (string) $fromTs, '(' . $toTs);
            if (!$ids) {
                return [];
            }
            $rows = $r->pipeline(function ($p) use ($ids) {
                foreach ($ids as $id) {
                    $p->hgetall('pesanan:' . $id);
                    $p->lrange('pesanan:' . $id . ':detail', 0, -1);
                }
            });
            return array_chunk($rows, 2);
        });
        $sum = ['from' => $fromTs, 'to' => $toTs, 'orders' => 0, 'items' => 0, 'subtotal' => 0, 'pajak' => 0, 'total' => 0, 'menu' => []];
        $menu = [];
        foreach ($data as [$h, $detail]) {
            if (!$h || ($h['status_pesanan'] ?? '') !== 'Selesai') {
                continue;
            }
            $sum['orders']++;
            $sum['items'] += (int) ($h['jumlah_item'] ?? 0);
            $sum['subtotal'] += (int) ($h['subtotal'] ?? 0);
            $sum['pajak'] += (int) ($h['pajak'] ?? 0);
            $sum['total'] += (int) ($h['total'] ?? 0);
            foreach ($detail as $row) {
                $d = json_decode((string) $row, true);
                if (!is_array($d)) {
                    continue;
                }
                $id = (string) ($d['id_menu'] ?? '');
                $menu[$id] ??= ['id_menu' => $id, 'nama_menu' => (string) ($d['nama_menu'] ?? $id), 'jumlah' => 0, 'pendapatan' => 0];
                $menu[$id]['jumlah'] += (int) ($d['jumlah'] ?? 0);
                $menu[$id]['pendapatan'] += (int) ($d['subtotal'] ?? 0);
            }
        }
        usort($menu, static fn ($a, $b) => [$b['jumlah'], $b['pendapatan']] <=> [$a['jumlah'], $a['pendapatan']]);
        $sum['menu'] = array_values($menu);
        return $sum;
    }

    /** Kode meja pesanan (dibutuhkan untuk menyusun KEYS skrip Lua). */
    private function orderTable(int $orderId): string
    {
        $table = $this->guard(fn (ClientInterface $r) => $r->hget('pesanan:' . $orderId, 'kode_meja'));
        if ($table === null || $table === '') {
            throw new DomainRejected('ORDER_NOT_FOUND');
        }
        return (string) $table;
    }

    /** @param array<string,string> $h */
    private function canSee(array $h, array $access): bool
    {
        return match ($access['type'] ?? '') {
            'kasir'    => true,
            'customer' => isset($h['owner_hash'], $access['owner_hash'])
                && hash_equals((string) $h['owner_hash'], (string) $access['owner_hash']),
            default    => false,
        };
    }

    /** @param array<string,string> $h */
    private static function hydrateOrder(array $h): array
    {
        $int = static fn (string $k): int => (int) ($h[$k] ?? 0);
        return [
            'id'             => $int('id'),
            'kode_pesanan'   => (string) ($h['kode_pesanan'] ?? ''),
            'kode_meja'      => (string) ($h['kode_meja'] ?? ''),
            'nama_pemesan'   => (string) ($h['nama_pemesan'] ?? ''),
            'catatan'        => (string) ($h['catatan'] ?? ''),
            'waktu'          => $int('waktu'),
            'subtotal'       => $int('subtotal'),
            'pajak_persen'   => $int('pajak_persen'),
            'pajak'          => $int('pajak'),
            'total'          => $int('total'),
            'jumlah_item'    => $int('jumlah_item'),
            'status_pesanan' => (string) ($h['status_pesanan'] ?? ''),
            'metode_bayar'   => (string) ($h['metode_bayar'] ?? ''),
            'status_bayar'   => (string) ($h['status_bayar'] ?? ''),
            'dibayar_at'     => $int('dibayar_at'),
            'batas_bayar'    => $int('batas_bayar'),
            'selesai_at'     => $int('selesai_at'),
            'dibatalkan_at'  => $int('dibatalkan_at'),
            'alasan_batal'   => (string) ($h['alasan_batal'] ?? ''),
            'updated_at'     => $int('updated_at'),
        ];
    }

    // =====================================================================
    // Staf
    // =====================================================================

    public function findStaffByEmail(string $email): ?array
    {
        return $this->findStaffVia('idx:users:email', strtolower(trim($email)));
    }

    public function findStaffByUsername(string $username): ?array
    {
        return $this->findStaffVia('idx:users:username', strtolower(trim($username)));
    }

    public function findStaffByLogin(string $login): ?array
    {
        $login = trim($login);
        return str_contains($login, '@') ? $this->findStaffByEmail($login) : $this->findStaffByUsername($login);
    }

    public function setStaffUsername(string $staffId, string $username): string
    {
        $res = $this->lua(LuaScripts::SET_USERNAME, ['users:' . $staffId, 'idx:users:username'], [$staffId, $username], true);
        return (string) $res['status'];
    }

    /** @return array<string,string>|null */
    private function findStaffVia(string $index, string $value): ?array
    {
        return $this->guard(function (ClientInterface $r) use ($index, $value): ?array {
            $id = $r->hget($index, $value);
            if ($id === null) {
                return null;
            }
            $h = $r->hgetall('users:' . $id);
            if (!$h) {
                return null;
            }
            return [
                'id'            => (string) $id,
                'nama'          => (string) ($h['nama'] ?? ''),
                'username'      => (string) ($h['username'] ?? ''),
                'email'         => (string) ($h['email'] ?? ''),
                'password_hash' => (string) ($h['password_hash'] ?? ''),
                'role'          => (string) ($h['role'] ?? ''),
                'status'        => (string) ($h['status'] ?? ''),
            ];
        });
    }

    public function setStaffPasswordHash(string $staffId, string $hash): void
    {
        $this->guard(function (ClientInterface $r) use ($staffId, $hash) {
            if (!$r->hexists('users:' . $staffId, 'email')) {
                throw new DomainRejected('NOT_FOUND', 'Akun staf tidak ditemukan.');
            }
            $r->hset('users:' . $staffId, 'password_hash', $hash);
        }, true);
    }

    // =====================================================================
    // Infrastruktur
    // =====================================================================

    /**
     * Jalankan skrip Lua yang membalas JSON {ok, ...}. Penolakan -> DomainRejected.
     * @return array<string,mixed>
     */
    private function lua(string $script, array $keys, array $args, bool $write): array
    {
        $raw = $this->guard(fn (ClientInterface $r) => $r->eval($script, count($keys), ...array_merge($keys, $args)), $write);
        $res = json_decode((string) $raw, true);
        if (!is_array($res)) {
            throw new \RuntimeException('Balasan skrip Lua tidak dikenal.');
        }
        if (empty($res['ok'])) {
            throw new DomainRejected((string) ($res['code'] ?? 'UNKNOWN'), '', $res);
        }
        return $res;
    }

    /**
     * Menjalankan perintah Redis dan menerjemahkan gangguan koneksi menjadi DataServiceUnavailable.
     * $write = true: jika koneksi putus, hasilnya belum pasti (mungkin sudah tersimpan).
     */
    private function guard(callable $fn, bool $write = false): mixed
    {
        try {
            return $fn($this->redis);
        } catch (ServerException $e) {
            if (preg_match('/^(NOAUTH|WRONGPASS|LOADING|MISCONF|READONLY|BUSY|OOM|MASTERDOWN)/', $e->getMessage())) {
                throw new DataServiceUnavailable('Redis menolak perintah: ' . strtok($e->getMessage(), ' '), $write, $e);
            }
            throw $e; // kesalahan skrip/perintah = bug program, bukan gangguan koneksi
        } catch (ClientException $e) {
            throw $e;
        } catch (PredisException $e) {
            // Gagal membuka koneksi (StreamInitException), koneksi putus, atau timeout.
            throw new DataServiceUnavailable('Redis tidak dapat dihubungi: ' . $e->getMessage(), $write, $e);
        }
    }
}
