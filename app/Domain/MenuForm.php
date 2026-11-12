<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Validasi form kelola menu (admin). Semua nilai dari browser dianggap tidak tepercaya.
 * Hasil: field siap simpan + pesan error per field (kosong = valid).
 */
final class MenuForm
{
    public const MIN_PRICE = 1000;
    public const MAX_PRICE = 5000000;

    /**
     * @param array<string,mixed> $in  biasanya $_POST
     * @param list<string> $categoryIds
     * @param list<string> $menuIds  id menu yang ada (untuk pilihan pasangan)
     * @return array{fields: array<string,string|int>, errors: array<string,string>}
     */
    public static function validate(array $in, array $categoryIds, array $menuIds, ?string $selfId = null): array
    {
        $errors = [];
        $fields = [];
        $take = static function (string $key, callable $fn) use (&$errors, &$fields, $in): void {
            try {
                $fields[$key] = $fn();
            } catch (ValidationError $e) {
                $errors[$key] = $e->getMessage();
                // Nilai yang salah tetap ditampilkan lagi di form agar admin bisa memperbaikinya.
                $fields[$key] = is_string($in[$key] ?? null) ? (string) $in[$key] : '';
            }
        };

        $take('nama_menu', static fn () => Validator::text($in['nama_menu'] ?? '', 'Nama menu', 2, 60));
        $take('nama_jp', static fn () => Validator::optionalText($in['nama_jp'] ?? '', 'Nama Jepang', 40));
        $take('id_kategori', static function () use ($in, $categoryIds): string {
            $v = is_string($in['id_kategori'] ?? null) ? $in['id_kategori'] : '';
            if (!in_array($v, $categoryIds, true)) {
                throw new ValidationError('Pilih kategori.');
            }
            return $v;
        });
        $take('harga', static function () use ($in): int {
            // Terima "28000", "28.000", atau "28 000".
            $raw = is_string($in['harga'] ?? null) ? preg_replace('/[\s.]/', '', $in['harga']) : '';
            if ($raw === '' || !ctype_digit($raw)) {
                throw new ValidationError('Harga harus berupa angka rupiah, misalnya 28000.');
            }
            $n = (int) $raw;
            if ($n < self::MIN_PRICE || $n > self::MAX_PRICE) {
                throw new ValidationError('Harga harus antara ' . rupiah(self::MIN_PRICE) . ' dan ' . rupiah(self::MAX_PRICE) . '.');
            }
            return $n;
        });
        $take('deskripsi', static fn () => Validator::optionalText($in['deskripsi'] ?? '', 'Deskripsi', 240));
        $take('status', static fn () => Validator::menuStatus($in['status'] ?? ''));
        $take('pasangan', static function () use ($in, $menuIds, $selfId): string {
            $v = is_string($in['pasangan'] ?? null) ? trim($in['pasangan']) : '';
            if ($v === '') {
                return '';
            }
            if (!in_array($v, $menuIds, true) || $v === $selfId) {
                throw new ValidationError('Pilih menu pasangan yang ada (bukan menu ini sendiri).');
            }
            return $v;
        });
        $take('pasangan_alasan', static fn () => Validator::optionalText($in['pasangan_alasan'] ?? '', 'Alasan pasangan', 120));
        if (($fields['pasangan'] ?? '') === '') {
            $fields['pasangan_alasan'] = '';
        }
        return ['fields' => $fields, 'errors' => $errors];
    }
}
