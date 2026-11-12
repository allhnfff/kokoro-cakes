<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Validasi input dari browser. Semua dicek di SERVER; browser tidak dipercaya.
 * Setiap fungsi mengembalikan nilai yang sudah bersih atau melempar ValidationError.
 */
final class Validator
{
    public static function menuId(mixed $value): string
    {
        $v = is_string($value) ? strtolower(trim($value)) : '';
        if (!preg_match('/^m[1-9][0-9]{0,3}$/', $v)) {
            throw new ValidationError('Kode menu tidak valid.');
        }
        return $v;
    }

    public static function tableCode(mixed $value): string
    {
        $v = is_string($value) ? strtoupper(trim($value)) : '';
        if (!preg_match('/^[1-9][0-9]?[A-Z]$/', $v)) {
            throw new ValidationError('Kode meja tidak valid.');
        }
        return $v;
    }

    /** Jumlah wajib bilangan bulat positif. "0", "-1", "1.5", "1e2", "abc" ditolak. */
    public static function quantity(mixed $value, int $max = 20): int
    {
        $v = is_string($value) || is_int($value) ? trim((string) $value) : '';
        if (!preg_match('/^[0-9]{1,3}$/', $v)) {
            throw new ValidationError('Jumlah harus bilangan bulat positif.');
        }
        $n = (int) $v;
        if ($n < 1) {
            throw new ValidationError('Jumlah minimal 1.');
        }
        if ($n > $max) {
            throw new ValidationError("Jumlah maksimal {$max} per menu.");
        }
        return $n;
    }

    public static function text(mixed $value, string $field, int $min, int $max): string
    {
        $v = is_string($value) ? trim(preg_replace('/\s+/u', ' ', $value) ?? '') : '';
        $len = function_exists('mb_strlen') ? mb_strlen($v, 'UTF-8') : strlen($v);
        if ($len < $min) {
            throw new ValidationError($min <= 1 ? "{$field} wajib diisi." : "{$field} minimal {$min} karakter.");
        }
        if ($len > $max) {
            throw new ValidationError("{$field} maksimal {$max} karakter.");
        }
        return $v;
    }

    public static function optionalText(mixed $value, string $field, int $max): string
    {
        $v = is_string($value) ? trim($value) : '';
        return $v === '' ? '' : self::text($v, $field, 0, $max);
    }

    /** @param array<string,string> $methods daftar metode dari konfigurasi */
    public static function paymentMethod(mixed $value, array $methods): string
    {
        $v = is_string($value) ? trim($value) : '';
        if (!array_key_exists($v, $methods)) {
            throw new ValidationError('Pilih metode pembayaran.');
        }
        return $v;
    }

    public static function orderStatus(mixed $value): string
    {
        $v = is_string($value) ? trim($value) : '';
        if (!in_array($v, OrderRules::STATUSES, true)) {
            throw new ValidationError('Status tidak dikenal.');
        }
        return $v;
    }

    public static function menuStatus(mixed $value): string
    {
        $v = is_string($value) ? trim($value) : '';
        if (!in_array($v, OrderRules::MENU_STATUSES, true)) {
            throw new ValidationError('Status menu harus Tersedia atau Habis.');
        }
        return $v;
    }

    public static function orderId(mixed $value): int
    {
        $v = is_string($value) || is_int($value) ? trim((string) $value) : '';
        if (!preg_match('/^[1-9][0-9]{0,8}$/', $v)) {
            throw new ValidationError('Nomor pesanan tidak valid.');
        }
        return (int) $v;
    }

    public static function requestId(mixed $value): string
    {
        $v = is_string($value) ? trim($value) : '';
        if (!preg_match('/^[a-f0-9]{32}$/', $v)) {
            throw new ValidationError('request_id tidak valid.');
        }
        return $v;
    }

    /** Username staf untuk login: 3–30 karakter huruf kecil, angka, titik, garis bawah, atau strip. */
    public static function username(mixed $value): string
    {
        $v = is_string($value) ? strtolower(trim($value)) : '';
        if (preg_match('/^[a-z0-9][a-z0-9._-]{2,29}$/', $v) !== 1) {
            throw new ValidationError('Username 3–30 karakter: huruf kecil, angka, titik, garis bawah, atau strip.');
        }
        return $v;
    }

    public static function email(mixed $value): string
    {
        $v = is_string($value) ? strtolower(trim($value)) : '';
        if (strlen($v) > 120 || filter_var($v, FILTER_VALIDATE_EMAIL) === false) {
            throw new ValidationError('Format email tidak valid.');
        }
        return $v;
    }

    public static function date(mixed $value): ?string
    {
        $v = is_string($value) ? trim($value) : '';
        if ($v === '') {
            return null;
        }
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $v);
        if (!$d || $d->format('Y-m-d') !== $v) {
            throw new ValidationError('Format tanggal harus YYYY-MM-DD.');
        }
        return $v;
    }
}
