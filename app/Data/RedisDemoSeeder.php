<?php

declare(strict_types=1);

namespace App\Data;

use Predis\ClientInterface;

/**
 * Mengisi DATA AWAL (meja, kategori, menu, akun kasir) ke Redis. Hanya dijalankan manual
 * lewat scripts/seed-demo.php, tidak pernah otomatis oleh website.
 *
 * Aturan aman:
 *   - Hanya pada APP_ENV=local atau testing.
 *   - Semua key tujuan diperiksa SEBELUM menulis (skrip Lua SEED). Jika ada benturan tanpa
 *     marker seed yang sah, proses gagal dan tidak menimpa apa pun.
 *   - Jika marker kokoro:seed:v1 sudah bernilai 1, seed ulang dilewati: password, harga,
 *     status menu, dan status meja yang sudah berubah tetap dipertahankan.
 *   - Tidak pernah menjalankan FLUSHALL/FLUSHDB.
 */
final class RedisDemoSeeder
{
    public const MARKER = RedisLocalAdapter::SEED_MARKER;

    /** Field menu/kategori yang selalu disamakan dengan config saat sinkron (teks, bukan harga/status). */
    public const TEXT_FIELDS = ['nama_jp', 'deskripsi', 'pasangan', 'pasangan_alasan'];

    /** Pola key milik proyek ini (dipakai reset-demo & tes). Tidak menyentuh key lain di Redis. */
    public const KEY_PATTERNS = [
        'meja:*', 'menu:*', 'kategori:*', 'users:*', 'idx:*', 'cart:*', 'pesanan:*', 'pelanggan:*',
        'checkout:request:*', 'seq:pesanan', 'kokoro:*', 'pilihan:*', 'sess:*', 'foto:*',
    ];

    public function __construct(private readonly ClientInterface $redis)
    {
    }

    /**
     * @param array{env:string, seed:array{nama:string,email:string,password:string}} $config
     * @param array{kategori:array<string,array<string,string>>, menu:list<array>} $menuData  isi config/menu.php
     * @param array<string,array<string,string>> $tables  isi config/meja.php
     * @return array{status:string, counts:array<string,int>, keys:int}
     */
    public function run(array $config, array $menuData, array $tables): array
    {
        if (!in_array($config['env'], ['local', 'testing'], true)) {
            throw new \RuntimeException('Seeder data demo hanya boleh dijalankan pada APP_ENV=local atau testing.');
        }
        $seed = $config['seed'];
        if ($seed['nama'] === '' || strlen($seed['nama']) > 100) {
            throw new \RuntimeException('SEED_KASIR_NAMA wajib diisi, maksimal 100 byte.');
        }
        if (filter_var($seed['email'], FILTER_VALIDATE_EMAIL) === false) {
            throw new \RuntimeException('SEED_KASIR_EMAIL harus berupa email yang valid.');
        }
        if (strlen($seed['password']) < 8 || strlen($seed['password']) > 72 || str_contains($seed['password'], "\0")) {
            throw new \RuntimeException('SEED_KASIR_PASSWORD wajib berisi 8–72 byte dan tidak mengandung byte NUL.');
        }

        $hashes = [];
        $sets = [];
        foreach ($tables as $code => $fields) {
            $hashes['meja:' . $code] = $fields;
        }
        $catalog = self::catalog($menuData);
        foreach ($catalog['kategori'] as $id => $fields) {
            $hashes['kategori:' . $id] = $fields;
            $sets['kategori:' . $id . ':menu'] = [];
        }
        $menuIds = [];
        foreach ($catalog['menu'] as $id => $fields) {
            $hashes['menu:' . $id] = $fields;
            $sets['kategori:' . $fields['id_kategori'] . ':menu'][] = $id;
            $menuIds[] = $id;
        }
        $username = (string) ($seed['username'] ?? 'kasir');
        if (preg_match('/^[a-z0-9][a-z0-9._-]{2,29}$/', $username) !== 1) {
            throw new \RuntimeException('SEED_KASIR_USERNAME tidak valid (3–30 huruf kecil/angka/titik/strip).');
        }
        $hashes['users:1'] = [
            'nama'          => $seed['nama'],
            'username'      => $username,
            'email'         => $seed['email'],
            'password_hash' => password_hash($seed['password'], PASSWORD_DEFAULT),
            'role'          => 'kasir',
            'status'        => 'Aktif',
        ];
        $hashes['idx:users:email'] = [$seed['email'] => '1'];
        // Login staf memakai username (idx:users:username); email tetap disimpan sebagai data kontak.
        $hashes['idx:users:username'] = [$username => '1'];
        $sets['idx:users'] = ['1'];
        $sets['idx:meja'] = array_keys($tables);
        $sets['idx:kategori'] = array_keys($menuData['kategori']);
        $sets['idx:menu'] = $menuIds;

        // KEYS memuat seluruh key yang diakses Lua, dengan marker pada posisi pertama.
        $keys = [self::MARKER];
        $records = [];
        foreach ($hashes as $key => $fields) {
            $keys[] = $key;
            $flat = [];
            foreach ($fields as $field => $value) {
                $flat[] = (string) $field;
                $flat[] = (string) $value;
            }
            $records[] = ['type' => 'hash', 'values' => $flat];
        }
        foreach ($sets as $key => $members) {
            $keys[] = $key;
            $records[] = ['type' => 'set', 'values' => array_map('strval', $members)];
        }
        $payload = json_encode($records, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $reply = $this->redis->eval(LuaScripts::SEED, count($keys), ...array_merge($keys, [$payload]));
        return [
            'status' => (string) $reply[0],
            'counts' => [
                'meja'     => count($tables),
                'kategori' => count($menuData['kategori']),
                'menu'     => count($menuIds),
                'kasir'    => 1,
            ],
            'keys' => count($keys),
        ];
    }

    /**
     * Menyamakan katalog di Redis dengan config/menu.php TANPA menimpa data yang sudah ada:
     *   - kategori/menu yang belum ada -> ditambahkan (hash + indeks + relasi kategori)
     *   - kategori/menu yang sudah ada -> field teks (TEXT_FIELDS) disamakan dengan config,
     *     field lain hanya diisi bila belum ada (HSETNX), jadi harga, status Tersedia/Habis,
     *     dan perubahan kasir tetap utuh.
     * Hanya boleh setelah seed v1 selesai. Seluruh perubahan dijalankan atomik dalam satu skrip Lua.
     * @return array{kategori_baru:int, menu_baru:int, field_baru:int, field_diperbarui:int}
     */
    public function syncCatalog(array $menuData): array
    {
        $catalog = self::catalog($menuData);
        $keys = [self::MARKER, 'idx:kategori', 'idx:menu'];
        $records = [];
        $add = static function (string $key) use (&$keys): int {
            $keys[] = $key;
            return count($keys); // indeks KEYS di Lua (mulai 1)
        };
        foreach ($catalog['kategori'] as $id => $fields) {
            $records[] = ['type' => 'kategori', 'id' => $id, 'fields' => self::flat($fields),
                'hkey' => $add('kategori:' . $id), 'skey' => $add('kategori:' . $id . ':menu')];
        }
        foreach ($catalog['menu'] as $id => $fields) {
            $records[] = ['type' => 'menu', 'id' => $id, 'fields' => self::flat($fields),
                'hkey' => $add('menu:' . $id), 'skey' => $add('kategori:' . $fields['id_kategori'] . ':menu')];
        }
        $payload = json_encode($records, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $textFields = json_encode(self::TEXT_FIELDS, JSON_THROW_ON_ERROR);
        $deadIndex = (string) $add(RedisLocalAdapter::DELETED_MENU_KEY);
        $reply = json_decode((string) $this->redis->eval(LuaScripts::SYNC_CATALOG, count($keys), ...array_merge($keys, [$payload, $textFields, $deadIndex])), true);
        if (!is_array($reply) || empty($reply['ok'])) {
            throw new \RuntimeException('Sinkronisasi menu ditolak: ' . ($reply['code'] ?? 'balasan tidak dikenal')
                . (isset($reply['key']) ? ' (' . $reply['key'] . ')' : '') . '. Tidak ada data yang diubah.');
        }
        return ['kategori_baru' => (int) $reply['kategori'], 'menu_baru' => (int) $reply['menu'],
            'field_baru' => (int) $reply['fields'], 'field_diperbarui' => (int) $reply['updated']];
    }

    /**
     * Validasi isi config/menu.php dan ubah menjadi field hash Redis.
     * @return array{kategori: array<string,array<string,string>>, menu: array<string,array<string,string>>}
     */
    private static function catalog(array $menuData): array
    {
        $kategori = [];
        foreach ($menuData['kategori'] as $id => $fields) {
            if (!preg_match('/^k[1-9][0-9]?$/', (string) $id) || ($fields['nama_kategori'] ?? '') === '') {
                throw new \RuntimeException("Data kategori {$id} tidak valid.");
            }
            $kategori[$id] = array_map('strval', $fields);
        }
        $menu = [];
        foreach ($menuData['menu'] as $row) {
            [$id, $category, $name, $nameJp, $price, $description, $status] = $row;
            if (!preg_match('/^m[1-9][0-9]{0,3}$/', (string) $id) || isset($menu[$id])) {
                throw new \RuntimeException("Kode menu {$id} tidak valid atau dobel.");
            }
            if (!isset($kategori[$category])) {
                throw new \RuntimeException("Menu {$id} memakai kategori {$category} yang tidak ada.");
            }
            if (!in_array($status, ['Tersedia', 'Habis'], true) || !is_int($price) || $price < 0 || $name === '') {
                throw new \RuntimeException("Data menu {$id} tidak valid.");
            }
            $menu[$id] = [
                'id_kategori'     => $category,
                'nama_menu'       => $name,
                'nama_jp'         => $nameJp,
                'harga'           => (string) $price,
                'deskripsi'       => $description,
                // Kosong: foto dibaca dari public/assets/img/menu/foto/{id}.webp bila ada.
                'foto'            => '',
                'status'          => $status,
                'pasangan'        => '',
                'pasangan_alasan' => '',
            ];
        }
        foreach ($menuData['pasangan'] ?? [] as $id => $pair) {
            [$pairId, $reason] = $pair + ['', ''];
            if (!isset($menu[$id], $menu[$pairId]) || $pairId === $id || trim((string) $reason) === '') {
                throw new \RuntimeException("Pasangan menu {$id} -> {$pairId} tidak valid.");
            }
            $menu[$id]['pasangan'] = (string) $pairId;
            $menu[$id]['pasangan_alasan'] = (string) $reason;
        }
        return ['kategori' => $kategori, 'menu' => $menu];
    }

    /** @param array<string,string> $fields @return list<string> */
    private static function flat(array $fields): array
    {
        $flat = [];
        foreach ($fields as $k => $v) {
            $flat[] = (string) $k;
            $flat[] = (string) $v;
        }
        return $flat;
    }

    /** Hapus semua key berpola milik proyek ini (untuk reset demo). @return int jumlah key terhapus */
    public function deleteProjectKeys(): int
    {
        $deleted = 0;
        foreach (self::KEY_PATTERNS as $pattern) {
            $cursor = '0';
            do {
                [$cursor, $keys] = $this->redis->scan($cursor, ['MATCH' => $pattern, 'COUNT' => 500]);
                if ($keys) {
                    $deleted += (int) $this->redis->del($keys);
                }
            } while ((string) $cursor !== '0');
        }
        return $deleted;
    }
}
