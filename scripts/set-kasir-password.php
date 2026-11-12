<?php

declare(strict_types=1);

/**
 * Mengganti password akun kasir yang sudah ada di Redis TANPA menuliskannya di file mana pun.
 * Yang disimpan hanya HASH (password_hash).
 *
 *     php scripts/set-kasir-password.php
 *     php scripts/set-kasir-password.php admin      (username atau email akun)
 *
 * Jangan kirim password aslimu ke siapa pun, termasuk ke chat.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../app/bootstrap.php';

use App\App;
use App\Data\DataServiceUnavailable;
use App\Data\RedisLocalAdapter;

function ask_hidden(string $prompt): string
{
    echo $prompt;
    $isWindows = DIRECTORY_SEPARATOR === '\\';
    if (!$isWindows && function_exists('shell_exec') && trim((string) shell_exec('command -v stty')) !== '') {
        shell_exec('stty -echo');
        $value = rtrim((string) fgets(STDIN), "\r\n");
        shell_exec('stty echo');
        echo PHP_EOL;
        return $value;
    }
    // Windows: input tidak bisa disembunyikan dengan mudah tanpa ekstensi tambahan.
    echo '(peringatan: ketikan terlihat di layar) ';
    return rtrim((string) fgets(STDIN), "\r\n");
}

$email = strtolower(trim((string) ($argv[1] ?? App::config('seed.username'))));
$data = RedisLocalAdapter::fromConfig(App::allConfig());
try {
    $staff = $data->findStaffByLogin($email);
} catch (DataServiceUnavailable) {
    fwrite(STDERR, "GAGAL terhubung ke Redis.\n");
    exit(1);
}
if ($staff === null) {
    fwrite(STDERR, "Akun {$email} tidak ditemukan. Jalankan seed dulu: php scripts/seed-demo.php\n");
    exit(1);
}

echo "Akun: {$staff['nama']}, username " . ($staff['username'] ?: '-') . " (role {$staff['role']})\n";
$p1 = ask_hidden('Password baru (8–72 karakter): ');
if (strlen($p1) < 8 || strlen($p1) > 72) {
    fwrite(STDERR, "Password harus 8–72 byte.\n");
    exit(1);
}
$p2 = ask_hidden('Ulangi password: ');
if (!hash_equals($p1, $p2)) {
    fwrite(STDERR, "Password tidak sama.\n");
    exit(1);
}
$data->setStaffPasswordHash($staff['id'], password_hash($p1, PASSWORD_DEFAULT));
echo "Berhasil. Hash password baru tersimpan di Redis (password asli tidak disimpan).\n";
