<?php

declare(strict_types=1);

/**
 * Memberi atau mengganti USERNAME login akun staf yang sudah ada (login staf memakai username).
 *
 *     php scripts/set-username.php kasir@kokoro.test kasir
 *     php scripts/set-username.php admin@kokoro.test admin
 *
 * Argumen pertama: email atau username lama. Argumen kedua: username baru.
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
use App\Domain\ValidationError;
use App\Domain\Validator;

$who = (string) ($argv[1] ?? '');
try {
    $username = Validator::username($argv[2] ?? '');
} catch (ValidationError $e) {
    fwrite(STDERR, "Pakai: php scripts/set-username.php <email-atau-username-lama> <username-baru>\n" . $e->getMessage() . "\n");
    exit(1);
}
$data = RedisLocalAdapter::fromConfig(App::allConfig());
try {
    $staff = $data->findStaffByLogin($who);
    if ($staff === null) {
        fwrite(STDERR, "Akun {$who} tidak ditemukan.\n");
        exit(1);
    }
    $res = $data->setStaffUsername($staff['id'], $username);
} catch (DataServiceUnavailable) {
    fwrite(STDERR, "GAGAL terhubung ke Redis.\n");
    exit(1);
} catch (DomainRejected $e) {
    fwrite(STDERR, 'DITOLAK: ' . $e->getMessage() . "\n");
    exit(1);
}
echo ($res === 'unchanged' ? 'Tidak berubah' : 'Berhasil') . ": {$staff['nama']} ({$staff['role']}) sekarang login dengan username {$username}.\n";
