<?php

declare(strict_types=1);

namespace App\Domain;

use App\App;
use App\Data\DataService;
use App\Http\View;

/**
 * Login kasir.
 * - Akun disimpan di Redis (users:{id}); login dengan username (idx:users:username), email juga diterima.
 *   Yang disimpan hanya hash password.
 * - password_verify, regenerasi ID sesi saat login, batas percobaan gagal, keluar otomatis saat idle.
 * - Hanya role kasir (atau admin) berstatus Aktif yang boleh masuk.
 */
final class KasirAuth
{
    private const MAX_FAILS = 5;
    private const LOCK_SECONDS = 60;
    private const ROLES = ['kasir', 'admin'];

    /** @return string|null pesan error, atau null jika berhasil */
    public static function attempt(DataService $data, string $login, string $password, bool $remember = false): ?string
    {
        $lockedUntil = (int) ($_SESSION['_kasir_lock_until'] ?? 0);
        if ($lockedUntil > time()) {
            return 'Terlalu banyak percobaan gagal. Tunggu ' . ($lockedUntil - time()) . ' detik.';
        }
        $login = trim($login);
        $staff = ($login !== '' && strlen($login) <= 120) ? $data->findStaffByLogin($login) : null;
        // Tetap jalankan password_verify walau email tidak ada, supaya waktu respons mirip.
        $hash = $staff['password_hash'] ?? password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
        $okPass = password_verify($password, $hash);
        if ($staff === null || !$okPass || !in_array($staff['role'], self::ROLES, true) || $staff['status'] !== 'Aktif') {
            usleep(300000); // perlambat tebakan password
            $fails = (int) ($_SESSION['_kasir_fails'] ?? 0) + 1;
            $_SESSION['_kasir_fails'] = $fails;
            if ($fails >= self::MAX_FAILS) {
                $_SESSION['_kasir_lock_until'] = time() + self::LOCK_SECONDS;
                $_SESSION['_kasir_fails'] = 0;
            }
            return 'Username atau password salah.';
        }
        session_regenerate_id(true);
        unset($_SESSION['_kasir_fails'], $_SESSION['_kasir_lock_until']);
        $_SESSION['kasir'] = [
            'id'        => $staff['id'],
            'nama'      => $staff['nama'],
            'username'  => $staff['username'] ?? '',
            'email'     => $staff['email'],
            'role'      => $staff['role'],
            'login_at'  => time(),
            'remember'  => $remember,
            'last_seen' => time(),
        ];
        return null;
    }

    public static function user(): ?array
    {
        $k = $_SESSION['kasir'] ?? null;
        if (!is_array($k)) {
            return null;
        }
        // "Ingat saya": tetap masuk sampai umur sesi (12 jam); tanpa itu keluar otomatis setelah idle 2 jam.
        $idle = !empty($k['remember']) ? (int) App::config('session.lifetime') : (int) App::config('session.kasir_idle_limit');
        if (time() - (int) ($k['last_seen'] ?? 0) > $idle) {
            unset($_SESSION['kasir']);
            return null;
        }
        // Diperbarui paling sering sekali per menit, supaya polling dashboard tidak menulis sesi terus-menerus.
        if (time() - (int) ($k['last_seen'] ?? 0) >= 60) {
            $_SESSION['kasir']['last_seen'] = time();
        }
        return $k;
    }

    /** Wajib login; jika belum, arahkan ke halaman login. */
    public static function require(): array
    {
        $user = self::user();
        if ($user === null) {
            View::redirect('/kasir/login');
        }
        return $user;
    }

    /** Wajib login sebagai admin (kelola menu, ganti QR). Kasir biasa mendapat 403. */
    public static function requireAdmin(): array
    {
        $user = self::require();
        if (($user['role'] ?? '') !== 'admin') {
            throw new \App\Http\HttpException(403, 'Halaman ini hanya untuk admin. Minta admin toko untuk mengubahnya.');
        }
        return $user;
    }

    public static function isAdmin(?array $user): bool
    {
        return ($user['role'] ?? '') === 'admin';
    }

    public static function logout(): void
    {
        unset($_SESSION['kasir']);
        session_regenerate_id(true);
    }

    /** Konteks akses untuk layanan data. */
    public static function access(): array
    {
        return ['type' => 'kasir'];
    }

    /** Nama pelaku untuk riwayat pesanan. */
    public static function actor(array $user): string
    {
        return ($user['role'] ?? 'kasir') . ':' . (($user['username'] ?? '') !== '' ? $user['username'] : $user['email']);
    }
}
