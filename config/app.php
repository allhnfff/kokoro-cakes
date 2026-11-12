<?php
/**
 * Konfigurasi aplikasi. Semua nilai dibaca dari file .env (lihat .env.example).
 * Jangan menulis password di file ini; file ini ikut masuk Git.
 *
 * @var callable $env  fungsi env(KEY, default) yang disiapkan bootstrap.php
 */

$int = static function (string $key, int $default, int $min, int $max) use ($env): int {
    $value = filter_var($env($key, (string) $default), FILTER_VALIDATE_INT, [
        'options' => ['min_range' => $min, 'max_range' => $max],
    ]);
    if ($value === false) {
        throw new RuntimeException("Nilai {$key} di .env tidak valid (harus bilangan bulat {$min}–{$max}).");
    }
    return $value;
};

$scheme = strtolower((string) $env('REDIS_SCHEME', 'tcp'));
if (!in_array($scheme, ['tcp', 'tls'], true)) {
    throw new RuntimeException('REDIS_SCHEME hanya menerima tcp atau tls.');
}
$timezone = (string) $env('APP_TIMEZONE', 'Asia/Jakarta');
if (!in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
    throw new RuntimeException('APP_TIMEZONE tidak valid.');
}

return [
    'app' => [
        'env'      => (string) $env('APP_ENV', 'local'),
        'debug'    => filter_var($env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN),
        'url'      => rtrim((string) $env('APP_URL', 'http://localhost:8000'), '/'),
        'timezone' => $timezone,
        'key'      => (string) $env('APP_KEY', ''),
        // true jika web berjalan di belakang proxy HTTPS tepercaya (misalnya Vercel):
        // header X-Forwarded-Proto dipakai untuk mengenali HTTPS (cookie Secure).
        'trust_proxy' => filter_var($env('TRUST_PROXY', 'false'), FILTER_VALIDATE_BOOLEAN),
    ],

    'store' => [
        'name'      => (string) $env('STORE_NAME', 'Kokoro Cakes'),
        'tagline'   => (string) $env('STORE_TAGLINE', 'Japanese cake & tea house'),
        // Kosong = tidak ditampilkan di footer.
        'address'   => trim((string) $env('STORE_ADDRESS', '')),
        'hours'     => trim((string) $env('STORE_HOURS', '10.00–21.00 WIB')),
        'developer' => (string) $env('DEVELOPER_NAME', ''),
        // Wi-Fi tamu: ditampilkan di beranda untuk tamu yang sudah di meja, hanya jika diisi.
        'wifi_name'     => trim((string) $env('STORE_WIFI_NAME', '')),
        'wifi_password' => (string) $env('STORE_WIFI_PASSWORD', ''),
    ],

    'session' => [
        // Nama cookie WAJIB berbeda per website: cookie localhost tidak dipisahkan per port.
        'name'            => (string) $env('SESSION_NAME', 'KOKORO_SESS'),
        // file (bawaan, cocok di laptop) atau redis (wajib di Vercel/serverless).
        'driver'          => in_array($d = strtolower((string) $env('SESSION_DRIVER', 'file')), ['file', 'redis'], true) ? $d
            : throw new RuntimeException('SESSION_DRIVER hanya menerima file atau redis.'),
        'lifetime'        => 60 * 60 * 12, // 12 jam (riwayat pesanan pelanggan di perangkat ini)
        'table_lifetime'  => 60 * 60 * 4,  // sesi meja dari scan QR berlaku 4 jam
        'kasir_idle_limit' => 60 * 60 * 2, // kasir otomatis keluar setelah 2 jam tidak aktif
    ],

    'order' => [
        'tax_percent'     => $int('TAX_PERCENT', 10, 0, 100),
        'max_qty_per_line' => 20,
        'payment_methods' => [
            'qris'  => 'QRIS (simulasi)',
            'tunai' => 'Bayar di kasir',
        ],
        // Catatan checkout (request_id) disimpan 24 jam untuk mencegah pesanan ganda.
        'request_ttl'     => 60 * 60 * 24,
        // Keranjang meja kedaluwarsa jika 6 jam tidak disentuh.
        'cart_ttl'        => 60 * 60 * 6,
        // Bayar dulu, baru disiapkan: pesanan "bayar di kasir" dibatalkan otomatis jika belum dibayar
        // dalam sekian menit (mencegah pesanan iseng saat ramai).
        'unpaid_minutes'  => $int('ORDER_UNPAID_MINUTES', 15, 5, 120),
    ],

    'redis' => [
        'scheme'        => $scheme,
        'host'          => trim((string) $env('REDIS_HOST', '127.0.0.1')),
        'port'          => $int('REDIS_PORT', 6379, 1, 65535),
        'username'      => (string) $env('REDIS_USERNAME', ''),
        'password'      => (string) $env('REDIS_PASSWORD', ''),
        'database'      => $int('REDIS_DB', 0, 0, 255),
        'timeout'       => $int('REDIS_TIMEOUT', 3, 1, 30),
        'test_database' => $int('REDIS_TEST_DB', 15, 0, 255),
    ],

    'seed' => [
        'nama'     => trim((string) $env('SEED_KASIR_NAMA', 'Kasir Kokoro')),
        'username' => strtolower(trim((string) $env('SEED_KASIR_USERNAME', 'kasir'))),
        'email'    => strtolower(trim((string) $env('SEED_KASIR_EMAIL', 'kasir@kokoro.test'))),
        'password' => (string) $env('SEED_KASIR_PASSWORD', ''),
        // Akun admin (kelola menu, ganti QR), dibuat dengan: php scripts/create-admin.php
        'admin_nama'     => trim((string) $env('SEED_ADMIN_NAMA', 'Admin Kokoro')),
        'admin_username' => strtolower(trim((string) $env('SEED_ADMIN_USERNAME', 'admin'))),
        'admin_email'    => strtolower(trim((string) $env('SEED_ADMIN_EMAIL', 'admin@kokoro.test'))),
        'admin_password' => (string) $env('SEED_ADMIN_PASSWORD', ''),
    ],

    // Mode preview lokal (misalnya Mobile Preview di dalam iframe). Bawaan MATI.
    // Hanya berlaku jika APP_ENV=local; di environment lain selalu diabaikan.
    'preview' => [
        'frame_enabled'   => filter_var($env('LOCAL_PREVIEW_FRAME', 'false'), FILTER_VALIDATE_BOOLEAN),
        'frame_ancestors' => (string) $env('LOCAL_PREVIEW_FRAME_ANCESTORS', ''),
    ],

    // Interval polling JavaScript (milidetik).
    // Interval pembaruan otomatis (milidetik). Versi online memakai nilai lebih besar agar hemat
    // kuota perintah Redis (Upstash gratis). 0 = mati. Polling juga berhenti saat tab tidak dilihat.
    'poll' => [
        'menu'  => $int('MENU_POLL_MS', 5000, 0, 600000),
        'order' => $int('ORDER_POLL_MS', 5000, 0, 600000),
        'kasir' => $int('KASIR_POLL_MS', 5000, 0, 600000),
    ],
];
