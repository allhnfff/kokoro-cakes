<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Aturan status pesanan Kokoro Cakes.
 *   Penyajian : Masuk > Diproses > Siap Diantar > Selesai
 *   Bayar dulu: tidak ada langkah maju (termasuk Mulai proses) sebelum pembayaran Lunas.
 *               "Bayar di kasir" yang tidak dibayar sampai batas_bayar dibatalkan otomatis.
 *   Batal     : hanya dari Masuk (alasan wajib); jika sudah lunas, pembayaran dikembalikan (simulasi)
 *   Meja      : Terisi sejak pesanan pertama; TETAP Terisi setelah pesanan selesai (tamu boleh duduk lama).
 *               Kembali Tersedia hanya saat staf menekan Kosongkan setelah tamu pergi.
 * Status pembayaran dipisahkan dari status penyajian: Belum Bayar / Lunas / Dikembalikan.
 */
final class OrderRules
{
    public const STATUSES = ['Masuk', 'Diproses', 'Siap Diantar', 'Selesai', 'Dibatalkan'];

    /** Status yang masih perlu ditangani dapur/kasir. */
    public const ACTIVE = ['Masuk', 'Diproses', 'Siap Diantar'];

    /** Transisi maju yang sah (pembatalan ditangani terpisah). */
    public const TRANSITIONS = [
        'Masuk'        => 'Diproses',
        'Diproses'     => 'Siap Diantar',
        'Siap Diantar' => 'Selesai',
    ];

    public const CANCELLABLE_STATUS = 'Masuk';

    public const PAYMENT_STATUSES = ['Belum Bayar', 'Lunas', 'Dikembalikan'];

    public const MENU_STATUSES = ['Tersedia', 'Habis'];

    public static function nextStatus(string $current): ?string
    {
        return self::TRANSITIONS[$current] ?? null;
    }

    public static function canCancel(string $status): bool
    {
        return $status === self::CANCELLABLE_STATUS;
    }

    /** Pesanan "bayar di kasir" yang belum dibayar: dapur belum boleh menyiapkannya. */
    public static function awaitingPayment(array $order): bool
    {
        return ($order['status_pesanan'] ?? '') === 'Masuk' && ($order['status_bayar'] ?? '') === 'Belum Bayar';
    }

    public static function isActive(string $status): bool
    {
        return in_array($status, self::ACTIVE, true);
    }

    /** Urutan langkah untuk timeline pelanggan. */
    public static function steps(): array
    {
        return ['Masuk', 'Diproses', 'Siap Diantar', 'Selesai'];
    }

    /** Teks tombol tindakan kasir untuk status berikutnya. */
    public static function actionLabel(string $next): string
    {
        return match ($next) {
            'Diproses'     => 'Mulai proses',
            'Siap Diantar' => 'Siap diantar',
            'Selesai'      => 'Selesai',
            default        => 'Perbarui',
        };
    }

    /** Penjelasan singkat untuk pelanggan. */
    public static function customerText(string $status): string
    {
        return match ($status) {
            'Masuk'        => 'Pesanan sudah diterima dan menunggu disiapkan.',
            'Diproses'     => 'Pesananmu sedang disiapkan.',
            'Siap Diantar' => 'Pesananmu siap dan sedang diantar ke meja.',
            'Selesai'      => 'Pesanan selesai. Terima kasih sudah mampir!',
            'Dibatalkan'   => 'Pesanan ini dibatalkan.',
            default        => '',
        };
    }

    public static function paymentLabel(string $method): string
    {
        return match ($method) {
            'qris'  => 'QRIS (simulasi)',
            'tunai' => 'Bayar di kasir',
            default => $method,
        };
    }
}
