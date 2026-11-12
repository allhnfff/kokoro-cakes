<?php

declare(strict_types=1);

/**
 * Mengisi DATA AWAL ke Redis: 12 meja, kategori + menu dari config/menu.php, 1 akun kasir.
 *
 * Jalankan manual dari folder proyek:
 *     php scripts/seed-demo.php
 *
 * Pertama kali: syarat APP_ENV=local atau testing dan SEED_KASIR_PASSWORD terisi (8–72 byte).
 * Berikutnya (sudah pernah di-seed): hanya menambahkan kategori/menu BARU dari config/menu.php.
 * Harga, status menu, status meja, pesanan, dan password yang sudah ada tidak pernah diubah.
 * Tidak pernah dijalankan otomatis oleh website.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../app/bootstrap.php';

use App\App;
use App\Data\RedisDemoSeeder;
use App\Data\RedisLocalAdapter;

$cfg = App::config('redis');
if ((int) $cfg['database'] === (int) $cfg['test_database']) {
    fwrite(STDERR, "DITOLAK: REDIS_DB sama dengan REDIS_TEST_DB ({$cfg['test_database']}). Pisahkan database data dan tes.\n");
    exit(1);
}
if (!in_array(App::config('app.env'), ['local', 'testing'], true)) {
    fwrite(STDERR, "DITOLAK: seeder data demo hanya untuk APP_ENV=local atau testing.\n");
    exit(1);
}

echo 'File konfigurasi : ' . App::config('env_file') . "\n";
echo "Redis tujuan     : {$cfg['host']}:{$cfg['port']} database {$cfg['database']}\n";

$menuData = require App::root() . '/config/menu.php';
try {
    $client = RedisLocalAdapter::createClient($cfg);
    $client->ping();
    $seeder = new RedisDemoSeeder($client);

    if ($client->get(RedisDemoSeeder::MARKER) === '1') {
        echo "Seed v1 sudah pernah dijalankan. Meja, harga, status, pesanan, dan password tidak diubah.\n";
        $sync = $seeder->syncCatalog($menuData);
        echo "Sinkron menu     : {$sync['kategori_baru']} kategori baru, {$sync['menu_baru']} menu baru, "
            . "{$sync['field_baru']} field baru, {$sync['field_diperbarui']} teks menu diperbarui "
            . "(deskripsi/pasangan). Harga & status tidak diubah.\n";
        echo 'Total menu       : ' . $client->scard('idx:menu') . "\n";
        exit(0);
    }

    $result = $seeder->run(
        ['env' => (string) App::config('app.env'), 'seed' => (array) App::config('seed')],
        $menuData,
        require App::root() . '/config/meja.php'
    );
} catch (\Predis\Response\ServerException $e) {
    fwrite(STDERR, 'Seeder ditolak Redis (' . strtok($e->getMessage(), ' ') . "). "
        . "Ada key yang sudah dipakai data lain atau marker tidak sah. Data tidak dihapus otomatis.\n");
    exit(1);
} catch (\Predis\PredisException $e) {
    fwrite(STDERR, "GAGAL terhubung ke Redis. Pastikan Redis server berjalan, lalu coba lagi.\n");
    exit(1);
} catch (\RuntimeException $e) {
    fwrite(STDERR, 'DITOLAK: ' . $e->getMessage() . "\n");
    exit(1);
}

$c = $result['counts'];
echo "Seed berhasil: {$c['meja']} meja, {$c['kategori']} kategori, {$c['menu']} menu, {$c['kasir']} kasir ({$result['keys']} key).\n";
echo 'Email kasir      : ' . App::config('seed.email') . "\n";
echo "Password tersimpan sebagai hash; tidak ditampilkan. Boleh kosongkan lagi SEED_KASIR_PASSWORD di .env.\n";
