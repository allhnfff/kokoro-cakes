<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Data\DataServiceUnavailable;
use App\Data\DomainRejected;
use App\Domain\OrderRules;
use App\Domain\ValidationError;
use App\Domain\Validator;
use App\Http\Csrf;
use App\Http\HttpException;
use App\Http\Session;
use App\Http\View;

/** Pesanan pelanggan: hanya perangkat (sesi) pemesan yang boleh melihat. */
final class OrderController extends Controller
{
    /** Alasan yang bisa dipilih pelanggan. Hanya kunci dari daftar ini yang diterima server. */
    public const CANCEL_REASONS = [
        'salah-pesan' => 'Salah pilih menu',
        'terlalu-lama' => 'Menunggu terlalu lama',
        'tidak-jadi'  => 'Tidak jadi memesan',
        'lainnya'     => 'Alasan lain',
    ];

    private function access(): array
    {
        return ['type' => 'customer', 'owner_hash' => Session::ownerHash()];
    }

    public function index(): void
    {
        $orders = $this->data()->listOrders($this->access(), ['limit' => 50]);
        $this->render('shop/orders', ['title' => 'Pesanan Saya', 'orders' => $orders]);
    }

    private function load(string $id): array
    {
        try {
            $id = Validator::orderId($id);
        } catch (ValidationError) {
            throw new HttpException(404, 'Pesanan tidak ditemukan.');
        }
        $order = $this->data()->getOrder($id, $this->access());
        if ($order === null) {
            // Sama untuk "tidak ada" dan "milik perangkat lain" agar nomor pesanan orang lain tidak bisa ditebak.
            throw new HttpException(404, 'Pesanan tidak ditemukan atau bukan dari perangkat ini.');
        }
        return $order;
    }

    public function show(string $id): void
    {
        $order = $this->load($id);
        if ($this->query('baru') === '1') {
            // Tepat setelah checkout: layar konfirmasi "Pesanan Berhasil!" (wireframe pelanggan no. 7).
            $this->render('shop/order-success', ['title' => 'Pesanan Berhasil', 'order' => $order]);
            return;
        }
        $this->render('shop/order', [
            'title'         => 'Pesanan ' . $order['kode_pesanan'],
            'order'         => $order,
            'isNew'         => $this->query('baru') === '1',
            'cancelReasons' => self::CANCEL_REASONS,
        ]);
    }

    /**
     * Pelanggan membatalkan pesanannya sendiri. Hanya saat status Masuk; dicek lagi secara atomik
     * oleh skrip pembatalan yang sama dengan kasir, jadi aman walaupun tombol ditekan dua kali.
     */
    public function cancel(string $id): void
    {
        Csrf::verify();
        $order = $this->load($id);
        $back = '/pesanan/' . $order['id'];
        $reasonKey = $this->post('reason');
        if (!array_key_exists($reasonKey, self::CANCEL_REASONS)) {
            Session::flash('error', 'Pilih alasan pembatalan terlebih dahulu.');
            View::redirect($back . '#batal');
        }
        if ($order['status_pesanan'] === 'Dibatalkan') {
            Session::flash('info', 'Pesanan ini sudah dibatalkan sebelumnya.');
            View::redirect($back);
        }
        if (!OrderRules::canCancel($order['status_pesanan'])) {
            Session::flash('error', 'Pesanan sudah mulai disiapkan, jadi tidak bisa dibatalkan dari sini. Hubungi kasir.');
            View::redirect($back);
        }
        try {
            $res = $this->data()->cancelOrder($order['id'], 'Oleh pelanggan: ' . self::CANCEL_REASONS[$reasonKey], 'pelanggan');
            Session::flash(
                $res['status'] === 'cancelled' ? 'success' : 'info',
                $res['status'] === 'cancelled' ? 'Pesanan dibatalkan.' : 'Pesanan ini sudah dibatalkan sebelumnya.'
            );
        } catch (DomainRejected $e) {
            Session::flash('error', $e->getMessage());
        } catch (DataServiceUnavailable) {
            Session::flash('error', 'Koneksi terputus, jadi hasil pembatalan belum pasti. Muat ulang halaman ini untuk melihat status terbaru.');
        }
        View::redirect($back);
    }

    /**
     * "Pesan lagi menu yang sama": isi ulang keranjang meja yang sedang dipakai dengan menu & jumlah
     * dari pesanan yang dibatalkan. Harga dan ketersediaan dicek ulang (lewat CartService), jadi menu
     * yang sudah habis atau dihapus dilewati dan disebutkan. Belum ada yang dikirim ke dapur.
     */
    public function reorder(string $id): void
    {
        Csrf::verify();
        $order = $this->load($id);
        $back = '/pesanan/' . $order['id'];
        $table = $this->requireTable();
        if ($order['status_pesanan'] !== 'Dibatalkan') {
            Session::flash('info', 'Hanya pesanan yang dibatalkan yang bisa dipesan ulang dari sini.');
            View::redirect($back);
        }
        $added = 0;
        $skipped = [];
        foreach ($order['items'] as $it) {
            try {
                $this->cart()->add($table, Validator::menuId($it['id_menu']), (int) $it['jumlah']);
                $added++;
            } catch (ValidationError $e) {
                $msg = $e->getMessage();
                $why = match (true) {
                    str_contains($msg, 'habis')       => 'sedang habis',
                    str_contains($msg, 'maksimal')    => 'sudah penuh di keranjang',
                    str_contains($msg, 'tidak aktif') => 'meja tidak aktif',
                    default                           => 'sudah tidak dijual',
                };
                $skipped[] = $it['nama_menu'] . ' (' . $why . ')';
            }
        }
        if ($added === 0) {
            Session::flash('error', 'Menu dari pesanan ini tidak bisa ditambahkan: ' . implode(', ', $skipped) . '.');
            View::redirect($back);
        }
        Session::flash('success', "{$added} menu dari {$order['kode_pesanan']} dimasukkan lagi ke keranjang meja {$table}. Periksa lalu kirim pesanan.");
        if ($skipped) {
            Session::flash('info', 'Tidak ikut ditambahkan: ' . implode(', ', $skipped) . '.');
        }
        View::redirect('/keranjang');
    }
}
