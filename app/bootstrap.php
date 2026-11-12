<?php

declare(strict_types=1);

/**
 * Bootstrap: dipanggil oleh public/index.php, script di scripts/, dan tes.
 * 1. Memuat autoload Composer
 * 2. Membaca .env
 * 3. Menyusun konfigurasi + validasi dasar
 */

use App\App;
use App\Support\Env;

$root = dirname(__DIR__);

if (!is_file($root . '/vendor/autoload.php')) {
    $msg = 'Folder vendor/ belum ada. Buka terminal di folder proyek lalu jalankan: composer install';
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $msg . PHP_EOL);
        exit(1);
    }
    http_response_code(500);
    echo '<h1>Dependency belum terpasang</h1><p>' . htmlspecialchars($msg) . '</p>';
    exit;
}

require $root . '/vendor/autoload.php';
require_once __DIR__ . '/Support/helpers.php';

$envFile = Env::resolveFile($root);
$envValues = Env::parseFile($envFile);

$env = static function (string $key, ?string $default = null) use ($envValues): ?string {
    // Variabel lingkungan sistem (jika ada) mengalahkan isi file .env
    $sys = getenv($key);
    if ($sys !== false && $sys !== '') {
        return $sys;
    }
    return $envValues[$key] ?? $default;
};

$config = require $root . '/config/app.php';
$config['env_file'] = basename($envFile);
$config['env_file_found'] = is_file($envFile);

date_default_timezone_set($config['app']['timezone']);

if (!in_array($config['app']['env'], ['local', 'testing', 'production'], true)) {
    throw new RuntimeException("APP_ENV harus 'local', 'testing', atau 'production'.");
}
if ($config['redis']['host'] === '') {
    throw new RuntimeException('REDIS_HOST wajib diisi.');
}

App::init($root, $config);
