<?php

declare(strict_types=1);

namespace App\Data;

/**
 * Permintaan ditolak karena aturan bisnis (bukan karena gangguan koneksi).
 * Kode error mengikuti docs/KONTRAK-DATA.md.
 */
final class DomainRejected extends \RuntimeException
{
    /** @param array<string,mixed> $details */
    public function __construct(
        public readonly string $errorCode,
        string $message = '',
        public readonly array $details = []
    ) {
        parent::__construct($message !== '' ? $message : self::defaultMessage($errorCode));
    }

    /** Pesan berbahasa Indonesia yang aman untuk pengguna. */
    public static function defaultMessage(string $code): string
    {
        return match ($code) {
            'MENU_UNAVAILABLE'     => 'Ada menu yang sedang habis atau tidak tersedia. Periksa keranjang lalu coba lagi.',
            'MENU_NOT_FOUND'       => 'Menu tidak ditemukan.',
            'CATEGORY_NOT_FOUND'   => 'Kategori tidak ditemukan.',
            'MENU_CHANGED'         => 'Menu ini baru saja diubah di tempat lain. Muat ulang lalu coba lagi.',
            'EMAIL_TAKEN'          => 'Email ini sudah dipakai akun lain.',
            'USERNAME_TAKEN'       => 'Username ini sudah dipakai akun lain.',
            'STAFF_NOT_FOUND'      => 'Akun staf tidak ditemukan.',
            'PICK_LIMIT'           => 'Pilihan dapur maksimal 3 menu per hari. Hapus salah satu dulu.',
            'PICK_UNAVAILABLE'     => 'Menu yang sedang habis tidak bisa dijadikan pilihan dapur.',
            'KEY_TYPE_CONFLICT'    => 'Key Redis untuk data ini dipakai dengan tipe lain. Periksa database.',
            'TABLE_UNAVAILABLE'    => 'Meja ini sedang tidak aktif. Hubungi kasir.',
            'INVALID_QTY'          => 'Jumlah harus bilangan bulat positif.',
            'EMPTY_ORDER'          => 'Keranjang kosong.',
            'CART_CHANGED'         => 'Keranjang meja berubah sebelum pesanan dikirim. Silakan tinjau ulang.',
            'IDEMPOTENCY_CONFLICT' => 'Permintaan checkout ini sudah dipakai untuk isi pesanan yang berbeda. Silakan tinjau ulang.',
            'ORDER_ID_COLLISION'   => 'Nomor pesanan bentrok. Silakan kirim ulang.',
            'ORDER_NOT_FOUND'      => 'Pesanan tidak ditemukan.',
            'INVALID_TRANSITION'   => 'Perubahan status tersebut tidak diizinkan.',
            'STATUS_CONFLICT'      => 'Status pesanan sudah berubah. Muat ulang halaman lalu coba lagi.',
            'PAYMENT_REQUIRED'     => 'Pesanan belum dibayar. Terima pembayaran dulu; pesanan baru boleh disiapkan setelah lunas.',
            'UNPAID_EXISTS'        => 'Meja ini masih punya pesanan bayar di kasir yang belum dibayar. Bayar dulu di kasir, atau pilih QRIS untuk pesanan ini.',
            'NOT_CANCELLABLE'      => 'Pesanan hanya dapat dibatalkan saat berstatus Masuk.',
            'ALREADY_CANCELLED'    => 'Pesanan sudah dibatalkan.',
            'REASON_REQUIRED'      => 'Alasan pembatalan wajib diisi.',
            'TABLE_HAS_ORDERS'     => 'Meja masih punya pesanan yang belum selesai.',
            'TABLE_NOT_FOUND'      => 'Meja tidak ditemukan.',
            default                => 'Permintaan ditolak (' . $code . ').',
        };
    }
}
