<?php

declare(strict_types=1);

/**
 * MENGHAPUS seluruh data proyek Kokoro Cakes di Redis LOKAL (meja, menu, akun kasir, keranjang, pesanan).
 * Gunakan hanya jika ingin mengulang demo dari awal.
 *
 *     php scripts/reset-demo.php --confirm=RESET-DEMO
 *
 * Pengaman:
 *   - Hanya APP_ENV=local.
 *   - Ditolak jika REDIS_HOST bukan 127.0.0.1 / localhost.
 *   - Ditolak jika REDIS_DB sama dengan REDIS_TEST_DB.
 *   - Hanya menghapus key berpola milik proyek ini (lihat RedisDemoSeeder::KEY_PATTERNS),
 *     tidak memakai FLUSHDB/FLUSHALL.
 * Setelah reset, jalankan lagi: php scripts/seed-demo.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../app/bootstrap.php';

use App\App;
use App\Data\RedisDemoSeeder;
use App\Data\RedisLocalAdapter;

if (!in_array('--confirm=RESET-DEMO', $argv, true)) {
    fwrite(STDERR, "Perintah ini menghapus semua data demo termasuk pesanan. Jalankan dengan: php scripts/reset-demo.php --confirm=RESET-DEMO\n");
    exit(1);
}
if (App::config('app.env') !== 'local') {
    fwrite(STDERR, "DITOLAK: reset hanya untuk APP_ENV=local.\n");
    exit(1);
}
$cfg = App::config('redis');
if (!in_array($cfg['host'], ['127.0.0.1', 'localhost', '::1'], true)) {
    fwrite(STDERR, "DITOLAK: REDIS_HOST '{$cfg['host']}' bukan Redis lokal.\n");
    exit(1);
}
if ((int) $cfg['database'] === (int) $cfg['test_database']) {
    fwrite(STDERR, "DITOLAK: REDIS_DB sama dengan REDIS_TEST_DB.\n");
    exit(1);
}

try {
    $n = (new RedisDemoSeeder(RedisLocalAdapter::createClient($cfg)))->deleteProjectKeys();
} catch (\Predis\PredisException $e) {
    fwrite(STDERR, "GAGAL terhubung ke Redis.\n");
    exit(1);
}
echo "Terhapus {$n} key milik proyek di database {$cfg['database']}. Jalankan: php scripts/seed-demo.php\n";
