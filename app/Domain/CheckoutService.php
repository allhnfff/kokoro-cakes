<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Aturan checkout di sisi website:
 *  - Validasi form (nama pemesan, catatan, metode bayar).
 *  - Menghitung ringkasan biaya dari harga server (pratinjau). Rumus pajak SAMA dengan skrip Lua.
 *  - Menyusun request checkout untuk layanan data (tanpa harga; total final dihitung di Redis).
 */
final class CheckoutService
{
    /** @param array<string,string> $methods  @param int $taxPercent */
    public function __construct(
        private readonly array $methods,
        private readonly int $taxPercent
    ) {
    }

    /**
     * @param array<string,mixed> $post isi $_POST (tidak dipercaya)
     * @return array{nama_pemesan:string, catatan:string, metode_bayar:string}
     */
    public function validateForm(array $post): array
    {
        return [
            'nama_pemesan' => Validator::text($post['nama_pemesan'] ?? '', 'Nama pemesan', 2, 40),
            'catatan'      => Validator::optionalText($post['catatan'] ?? '', 'Catatan', 200),
            'metode_bayar' => Validator::paymentMethod($post['metode_bayar'] ?? '', $this->methods),
        ];
    }

    /**
     * Semua kesalahan form sekaligus, per kolom. Aturannya sama persis dengan validateForm().
     * @return array<string,string> nama kolom => pesan
     */
    public function fieldErrors(array $post): array
    {
        $errors = [];
        $check = static function (string $field, callable $fn) use (&$errors): void {
            try {
                $fn();
            } catch (ValidationError $e) {
                $errors[$field] = $e->getMessage();
            }
        };
        $check('nama_pemesan', static fn () => Validator::text($post['nama_pemesan'] ?? '', 'Nama pemesan', 2, 40));
        $check('catatan', static fn () => Validator::optionalText($post['catatan'] ?? '', 'Catatan', 200));
        $check('metode_bayar', fn () => Validator::paymentMethod($post['metode_bayar'] ?? '', $this->methods));
        return $errors;
    }

    /** Pajak dibulatkan ke rupiah terdekat. Rumus sama dengan LuaScripts::CHECKOUT. */
    public static function tax(int $subtotal, int $percent): int
    {
        return intdiv($subtotal * $percent + 50, 100);
    }

    /** Ringkasan biaya dari keranjang detail (harga sudah dari server). */
    public function quote(array $cart): array
    {
        $tax = self::tax($cart['subtotal'], $this->taxPercent);
        return [
            'subtotal'     => $cart['subtotal'],
            'pajak_persen' => $this->taxPercent,
            'pajak'        => $tax,
            'total'        => $cart['subtotal'] + $tax,
            'units'        => $cart['units'],
        ];
    }

    /**
     * @param array{nama_pemesan:string,catatan:string,metode_bayar:string} $form
     * @param list<array{id_menu:string,qty:int}> $items
     */
    public function buildRequest(string $requestId, string $table, string $ownerHash, array $form, array $items): array
    {
        return [
            'request_id'   => $requestId,
            'kode_meja'    => $table,
            'owner_hash'   => $ownerHash,
            'nama_pemesan' => $form['nama_pemesan'],
            'catatan'      => $form['catatan'],
            'metode_bayar' => $form['metode_bayar'],
            'items'        => array_map(static fn (array $l): array => [
                'id_menu' => $l['id_menu'],
                'qty'     => (int) $l['qty'],
            ], $items),
        ];
    }

    /** Sidik isi keranjang untuk mendeteksi keranjang berubah antara tinjau dan konfirmasi. */
    public static function cartFingerprint(array $rawCart): string
    {
        $rows = array_map(static fn ($l) => $l['id_menu'] . '=' . $l['qty'], $rawCart);
        sort($rows);
        return hash('sha256', implode('|', $rows));
    }
}
