<?php

declare(strict_types=1);

namespace App\Domain;

use App\App;

/**
 * Tautan QR meja.
 * Keranjang memakai key cart:{kode_meja}. Agar orang di luar meja tidak bisa mengubah keranjang
 * hanya dengan menebak kode meja, tautan QR membawa token HMAC dari APP_KEY:
 *     /meja/1A/<token 16 hex>
 * Token tidak disimpan di Redis; server cukup menghitung ulang dan membandingkan.
 *
 * QR bisa diganti admin kapan saja: setiap meja punya qr_versi di meja:{kode} (bawaan 1).
 * Versi 1 memakai rumus lama (QR yang sudah tercetak tetap sah); versi berikutnya ikut
 * dimasukkan ke HMAC, sehingga QR versi lama langsung tidak berlaku.
 */
final class TableAccess
{
    public static function isConfigured(): bool
    {
        return strlen((string) App::config('app.key')) >= 32;
    }

    public static function token(string $tableCode, int $version = 1): string
    {
        if (!self::isConfigured()) {
            throw new \RuntimeException('APP_KEY belum diatur. Jalankan: php scripts/generate-key.php');
        }
        $msg = $version <= 1 ? 'meja|' . $tableCode : 'meja|' . $tableCode . '|v' . $version;
        return substr(hash_hmac('sha256', $msg, (string) App::config('app.key')), 0, 16);
    }

    public static function verify(string $tableCode, string $token, int $version = 1): bool
    {
        return self::isConfigured()
            && preg_match('/^[a-f0-9]{16}$/', $token) === 1
            && hash_equals(self::token($tableCode, $version), $token);
    }

    /** Path tautan QR, misalnya /meja/1A/3f9c... */
    public static function path(string $tableCode, int $version = 1): string
    {
        return '/meja/' . rawurlencode($tableCode) . '/' . self::token($tableCode, $version);
    }
}
