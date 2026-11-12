<?php

declare(strict_types=1);

namespace App\Http;

use App\App;

/**
 * Sesi PHP. Nama cookie berbeda per website (SESSION_NAME) supaya beberapa
 * proyek di localhost (port berbeda) tidak saling menimpa sesi.
 */
final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $lifetime = (int) App::config('session.lifetime');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.gc_maxlifetime', (string) $lifetime);
        session_name((string) App::config('session.name'));
        if (App::config('session.driver') === 'redis') {
            ini_set('session.lazy_write', '1');
            session_set_save_handler(new RedisSessionHandler(
                static fn () => \App\Data\RedisLocalAdapter::createClient((array) App::config('redis')),
                $lifetime
            ), true);
        }
        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path'     => '/',
            'secure'   => self::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    /** HTTPS langsung, atau lewat proxy tepercaya (TRUST_PROXY=true, misalnya Vercel). */
    public static function isHttps(): bool
    {
        if (($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }
        return App::config('app.trust_proxy') === true
            && strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }

    /**
     * ID perangkat pelanggan (32 hex). Dibuat terpisah dari session_id PHP agar tidak berubah
     * saat login kasir. Yang disimpan di pesanan hanya hash-nya (ownerHash).
     */
    public static function deviceId(): string
    {
        if (empty($_SESSION['device_id']) || !preg_match('/^[a-f0-9]{32}$/', (string) $_SESSION['device_id'])) {
            $_SESSION['device_id'] = bin2hex(random_bytes(16));
        }
        return $_SESSION['device_id'];
    }

    /** Hash pemilik pesanan. Yang disimpan di pesanan hanya hash, bukan ID sesi asli. */
    public static function ownerHash(): string
    {
        return hash('sha256', 'owner|' . self::deviceId());
    }

    /**
     * Kode meja yang sedang dipakai pelanggan (hasil scan QR), atau null.
     * Sesi meja kedaluwarsa setelah session.table_lifetime detik.
     */
    public static function table(): ?string
    {
        $t = $_SESSION['table'] ?? null;
        if (!is_array($t) || !isset($t['code'], $t['at'])) {
            return null;
        }
        if (time() - (int) $t['at'] > (int) App::config('session.table_lifetime')) {
            unset($_SESSION['table']);
            return null;
        }
        return (string) $t['code'];
    }

    public static function setTable(string $code, int $qrVersion = 1, int $tableSession = 1): void
    {
        $_SESSION['table'] = ['code' => $code, 'at' => time(), 'v' => $qrVersion, 's' => $tableSession];
    }

    /** Sesi meja saat tamu memindai QR (dibandingkan dengan sesi di meja:{kode}; naik saat meja dikosongkan). */
    public static function tableSession(): int
    {
        return (int) ($_SESSION['table']['s'] ?? 1);
    }

    /** Versi QR yang dipindai saat masuk meja (dibandingkan dengan qr_versi di Redis). */
    public static function tableVersion(): int
    {
        return (int) ($_SESSION['table']['v'] ?? 1);
    }

    public static function forgetTable(): void
    {
        unset($_SESSION['table']);
    }

    public static function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    /** @return list<array{type:string,message:string}> */
    public static function takeFlash(): array
    {
        $f = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $f;
    }

    public static function setOld(array $input): void
    {
        $_SESSION['_old'] = $input;
    }

    public static function takeOld(): array
    {
        $o = $_SESSION['_old'] ?? [];
        unset($_SESSION['_old']);
        return is_array($o) ? $o : [];
    }
}
