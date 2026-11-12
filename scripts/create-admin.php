<?php

declare(strict_types=1);

/**
 * Membuat akun ADMIN (kelola menu, ganti QR meja) dari isian .env:
 *     SEED_ADMIN_NAMA, SEED_ADMIN_EMAIL, SEED_ADMIN_PASSWORD (8–72 byte)
 *
 *     php scripts/create-admin.php
 *
 * Yang disimpan di Redis hanya HASH password. Password tidak pernah ditampilkan.
 * Jika email sudah terdaftar, tidak ada yang diubah. Ganti password admin:
 *     php scripts/set-kasir-password.php admin@kokoro.test
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../app/bootstrap.php';

use App\App;
use App\Data\DataServiceUnavailable;
use App\Data\DomainRejected;
use App\Data\RedisLocalAdapter;

$seed = (array) App::config('seed');
$nama = (string) $seed['admin_nama'];
$email = (string) $seed['admin_email'];
$username = (string) $seed['admin_username'];
$password = (string) $seed['admin_password'];

if ($nama === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false || preg_match('/^[a-z0-9][a-z0-9._-]{2,29}$/', $username) !== 1) {
    fwrite(STDERR, "SEED_ADMIN_NAMA, SEED_ADMIN_USERNAME, dan SEED_ADMIN_EMAIL di .env harus diisi dengan benar.\n");
    exit(1);
}
if (strlen($password) < 8 || strlen($password) > 72) {
    fwrite(STDERR, "Isi SEED_ADMIN_PASSWORD di .env (8–72 byte) lalu jalankan lagi.\n");
    exit(1);
}
if (hash_equals((string) $seed['password'], $password)) {
    fwrite(STDERR, "Password admin harus berbeda dari password kasir.\n");
    exit(1);
}

$data = RedisLocalAdapter::fromConfig(App::allConfig());
try {
    $existing = $data->findStaffByEmail($email);
    if ($existing !== null) {
        echo "Akun {$email} sudah ada (role {$existing['role']}). Tidak ada yang diubah.\n";
        exit(0);
    }
    $id = $data->createStaff($nama, $email, password_hash($password, PASSWORD_DEFAULT), 'admin', $username);
} catch (DataServiceUnavailable) {
    fwrite(STDERR, "GAGAL terhubung ke Redis. Pastikan Redis berjalan.\n");
    exit(1);
} catch (DomainRejected $e) {
    fwrite(STDERR, 'DITOLAK: ' . $e->getMessage() . "\n");
    exit(1);
}

echo "Akun admin dibuat: {$nama}, username {$username} (users:{$id}).\n";
echo "Masuk lewat /kasir/login. Menu \"Kelola Menu\" dan tombol \"Ganti QR\" hanya muncul untuk admin.\n";
echo "Password tersimpan sebagai hash; boleh kosongkan lagi SEED_ADMIN_PASSWORD di .env.\n";
