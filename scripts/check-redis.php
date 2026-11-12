<?php

declare(strict_types=1);

/**
 * Memeriksa koneksi website -> Redis memakai pengaturan di .env, plus ringkasan data.
 *     php scripts/check-redis.php
 * Hasil yang diharapkan: "PING -> PONG", info persistensi, dan jumlah meja/menu.
 * Tidak menampilkan password atau hash.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../app/bootstrap.php';

use App\App;
use App\Data\RedisLocalAdapter;

$cfg = App::config('redis');
echo 'File konfigurasi : ' . App::config('env_file') . (App::config('env_file_found') ? '' : '  (TIDAK DITEMUKAN — salin .env.example menjadi .env)') . "\n";
echo "Redis tujuan     : {$cfg['scheme']}://{$cfg['host']}:{$cfg['port']} database {$cfg['database']}"
    . ($cfg['password'] !== '' ? ' (memakai password)' : ' (tanpa password)') . "\n";
echo 'APP_KEY          : ' . (strlen((string) App::config('app.key')) >= 32 ? 'sudah diatur' : 'BELUM — jalankan php scripts/generate-key.php') . "\n";

try {
    $r = RedisLocalAdapter::createClient($cfg);
    echo 'PING -> ' . $r->ping() . "\n";
    $info = $r->info('server');
    $server = $info['Server'] ?? $info;
    echo 'Versi server     : ' . ($server['redis_version'] ?? '?') . "\n";
    try {
        $aof = $r->config('GET', 'appendonly');
        $save = $r->config('GET', 'save');
        echo 'appendonly (AOF) : ' . ($aof['appendonly'] ?? '?') . "\n";
        echo 'save (RDB)       : ' . (($save['save'] ?? '') === '' ? '(mati)' : $save['save']) . "\n";
    } catch (\Predis\Response\ServerException) {
        echo "Persistensi      : (perintah CONFIG dinonaktifkan di server ini)\n";
    }
    if ($r->get(RedisLocalAdapter::SEED_MARKER) !== '1') {
        echo "Seed             : BELUM — jalankan php scripts/seed-demo.php\n";
        exit(0);
    }
    echo "Seed             : v1 selesai\n";
    echo 'Meja             : ' . $r->scard('idx:meja') . "\n";
    echo 'Kategori         : ' . $r->scard('idx:kategori') . "\n";
    echo 'Menu             : ' . $r->scard('idx:menu') . "\n";
    echo 'Staf             : ' . $r->scard('idx:users') . "\n";
    echo 'Pesanan          : ' . $r->zcard('idx:pesanan') . "\n";
    $m = $r->hgetall('meja:1A');
    echo 'Contoh meja 1A   : ' . json_encode($m, JSON_UNESCAPED_UNICODE) . "\n";
    exit(0);
} catch (\Predis\Response\ServerException $e) {
    $m = $e->getMessage();
    echo (str_contains($m, 'NOAUTH') || str_contains($m, 'WRONGPASS'))
        ? "GAGAL: autentikasi ditolak. Periksa REDIS_PASSWORD/REDIS_USERNAME di .env.\n"
        : 'GAGAL: Redis menolak perintah: ' . strtok($m, ' ') . "\n";
    exit(1);
} catch (\Predis\PredisException $e) {
    echo "GAGAL terhubung ke Redis.\n";
    echo "Periksa: (1) Redis/Memurai sudah berjalan? (2) REDIS_HOST/REDIS_PORT di .env benar? (3) port tidak dipakai program lain?\n";
    exit(1);
}
