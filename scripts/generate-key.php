<?php

declare(strict_types=1);

/**
 * Membuat APP_KEY acak (64 hex) dan menuliskannya ke .env.
 * APP_KEY dipakai untuk menandatangani tautan QR meja (/meja/{kode}/{token}).
 *
 *     php scripts/generate-key.php            (hanya jika APP_KEY masih kosong)
 *     php scripts/generate-key.php --force    (ganti kunci; semua tautan QR lama tidak berlaku)
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../app/bootstrap.php';

use App\App;
use App\Support\Env;

$envFile = Env::resolveFile(App::root());
if (!is_file($envFile)) {
    fwrite(STDERR, 'File ' . basename($envFile) . " belum ada. Salin dulu .env.example menjadi .env.\n");
    exit(1);
}
$current = Env::parseFile($envFile)['APP_KEY'] ?? '';
if (strlen($current) >= 32 && !in_array('--force', $argv, true)) {
    echo "APP_KEY sudah ada. Tidak diubah (pakai --force untuk mengganti; tautan QR lama akan tidak berlaku).\n";
    exit(0);
}

$line = 'APP_KEY=' . bin2hex(random_bytes(32));
$content = (string) file_get_contents($envFile);
if (preg_match('/^APP_KEY=.*$/m', $content)) {
    $content = (string) preg_replace_callback('/^APP_KEY=.*$/m', static fn () => $line, $content);
} else {
    $content = rtrim($content) . PHP_EOL . $line . PHP_EOL;
}
file_put_contents($envFile, $content);
echo 'APP_KEY baru tersimpan di ' . basename($envFile) . ".\n";
