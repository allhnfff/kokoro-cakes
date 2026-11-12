<?php

declare(strict_types=1);

namespace App\Controllers;

use App\App;
use App\Data\DomainRejected;
use App\Domain\KasirAuth;
use App\Domain\MenuCatalog;
use App\Domain\OrderRules;
use App\Domain\TableAccess;
use App\Domain\ValidationError;
use App\Domain\Validator;
use App\Http\Csrf;
use App\Http\HttpException;
use App\Http\Session;
use App\Http\View;
use App\Support\QrCode;

/**
 * Halaman kasir (desktop/tablet).
 * - Mengelola status pesanan dan konfirmasi pembayaran.
 * - Mengubah KETERSEDIAAN menu (Tersedia/Habis) saja; tidak menambah atau menghapus menu.
 * - Melihat status meja, tautan QR, dan rekap penjualan.
 */
final class KasirController extends Controller
{
    public const ORDERS_PER_PAGE = 20;

    // ---------------- Login ----------------

    public function loginForm(): void
    {
        if (KasirAuth::user() !== null) {
            View::redirect('/kasir');
        }
        $this->renderKasir('kasir/login', ['title' => 'Masuk Kasir', 'old' => Session::takeOld()]);
    }

    public function login(): void
    {
        Csrf::verify();
        $login = trim($this->post('login') !== '' ? $this->post('login') : $this->post('email'));
        $error = KasirAuth::attempt($this->data(), $login, $this->post('password'), $this->post('ingat') === '1');
        if ($error !== null) {
            Session::setOld(['login' => $login]);
            Session::flash('error', $error);
            View::redirect('/kasir/login');
        }
        Session::flash('success', 'Selamat bekerja, ' . KasirAuth::user()['nama'] . '.');
        View::redirect('/kasir');
    }

    public function logout(): void
    {
        Csrf::verify();
        KasirAuth::logout();
        Session::flash('success', 'Kamu sudah keluar.');
        View::redirect('/kasir/login');
    }

    // ---------------- Dashboard ----------------

    public function dashboard(): void
    {
        KasirAuth::require();
        $orders = $this->data()->listOrders(KasirAuth::access(), ['limit' => 500]);
        $columns = array_fill_keys(OrderRules::ACTIVE, []);
        $todayStart = (new \DateTimeImmutable('today'))->getTimestamp();
        $today = ['orders' => 0, 'omzet' => 0, 'selesai' => 0];
        $unpaid = 0;
        foreach ($orders as $o) {
            if (isset($columns[$o['status_pesanan']])) {
                // Pesanan aktif: yang paling lama menunggu tampil paling atas.
                array_unshift($columns[$o['status_pesanan']], $o);
                if ($o['status_bayar'] === 'Belum Bayar') {
                    $unpaid++;
                }
            }
            if ($o['waktu'] >= $todayStart && $o['status_pesanan'] !== 'Dibatalkan') {
                $today['orders']++;
                $today['omzet'] += $o['status_pesanan'] === 'Selesai' ? $o['total'] : 0;
                $today['selesai'] += $o['status_pesanan'] === 'Selesai' ? 1 : 0;
            }
        }
        $this->renderKasir('kasir/dashboard', [
            'title'   => 'Dashboard',
            'columns' => $columns,
            'today'   => $today,
            'unpaid'  => $unpaid,
            'tables'  => $this->data()->listTables(),
            'pollMs'  => (int) App::config('poll.kasir'),
        ]);
    }

    // ---------------- Pesanan ----------------

    public function orders(): void
    {
        KasirAuth::require();
        $filter = ['status' => '', 'meja' => '', 'q' => mb_substr($this->query('q'), 0, 60)];
        $status = $this->query('status');
        if (in_array($status, OrderRules::STATUSES, true)) {
            $filter['status'] = $status;
        }
        try {
            $filter['meja'] = $this->query('meja') !== '' ? Validator::tableCode($this->query('meja')) : '';
        } catch (ValidationError) {
            $filter['meja'] = '';
        }
        $all = $this->data()->listOrders(KasirAuth::access(), $filter + ['limit' => 1000]);
        $total = count($all);
        $pages = max(1, (int) ceil($total / self::ORDERS_PER_PAGE));
        $page = min($pages, max(1, (int) $this->query('hal')));
        $this->renderKasir('kasir/orders', [
            'title'  => 'Pesanan',
            'orders' => array_slice($all, ($page - 1) * self::ORDERS_PER_PAGE, self::ORDERS_PER_PAGE),
            'filter' => $filter,
            'total'  => $total,
            'page'   => $page,
            'pages'  => $pages,
            'tables' => array_column($this->data()->listTables(), 'kode'),
        ]);
    }

    private function loadOrder(string $id): array
    {
        try {
            $order = $this->data()->getOrder(Validator::orderId($id), KasirAuth::access());
        } catch (ValidationError) {
            $order = null;
        }
        if ($order === null) {
            throw new HttpException(404, 'Pesanan tidak ditemukan.');
        }
        return $order;
    }

    /** Hanya kembali ke halaman kasir yang dikenal (bukan URL bebas dari browser). */
    private function back(array $order): string
    {
        return $this->post('kembali') === 'dashboard' ? '/kasir' : '/kasir/pesanan/' . $order['id'];
    }

    public function orderShow(string $id): void
    {
        KasirAuth::require();
        $order = $this->loadOrder($id);
        $this->renderKasir('kasir/order', [
            'title' => 'Pesanan ' . $order['kode_pesanan'],
            'order' => $order,
            'next'  => OrderRules::nextStatus($order['status_pesanan']),
        ]);
    }

    public function updateStatus(string $id): void
    {
        $user = KasirAuth::require();
        Csrf::verify();
        $order = $this->loadOrder($id);
        try {
            $from = Validator::orderStatus($this->post('from'));
            $to = Validator::orderStatus($this->post('to'));
            $note = Validator::optionalText($this->post('catatan'), 'Catatan', 120);
            $res = $this->data()->updateOrderStatus($order['id'], $from, $to, KasirAuth::actor($user), $note);
            $msg = $res['status'] === 'unchanged'
                ? "Status {$order['kode_pesanan']} sudah {$to}."
                : "{$order['kode_pesanan']} sekarang {$to}.";
            if ($res['table_idle']) {
                $msg .= " Meja {$order['kode_meja']} tetap Terisi; tekan Kosongkan di halaman Meja setelah tamu pergi.";
            }
            Session::flash($res['status'] === 'unchanged' ? 'info' : 'success', $msg);
        } catch (ValidationError | DomainRejected $e) {
            Session::flash('error', $e->getMessage());
        }
        View::redirect($this->back($order));
    }

    public function confirmPayment(string $id): void
    {
        $user = KasirAuth::require();
        Csrf::verify();
        $order = $this->loadOrder($id);
        try {
            $res = $this->data()->confirmPayment($order['id'], KasirAuth::actor($user));
            Session::flash(
                $res === 'paid' ? 'success' : 'info',
                $res === 'paid'
                    ? "Pembayaran {$order['kode_pesanan']} sebesar " . rupiah($order['total']) . ' dicatat lunas.'
                    : 'Pesanan ini sudah lunas sebelumnya.'
            );
        } catch (DomainRejected $e) {
            Session::flash('error', $e->getMessage());
        }
        View::redirect($this->back($order));
    }

    public function cancel(string $id): void
    {
        $user = KasirAuth::require();
        Csrf::verify();
        $order = $this->loadOrder($id);
        try {
            $reason = Validator::text($this->post('reason'), 'Alasan pembatalan', 5, 200);
            $res = $this->data()->cancelOrder($order['id'], $reason, KasirAuth::actor($user));
            Session::flash(
                $res['status'] === 'cancelled' ? 'success' : 'info',
                $res['status'] === 'cancelled' ? 'Pesanan dibatalkan.' : 'Pesanan sudah dibatalkan sebelumnya.'
            );
        } catch (ValidationError | DomainRejected $e) {
            Session::flash('error', $e->getMessage());
        }
        View::redirect('/kasir/pesanan/' . $order['id']);
    }

    // ---------------- Ketersediaan menu ----------------

    public function menu(): void
    {
        KasirAuth::require();
        $categories = $this->data()->listCategories();
        $menu = $this->data()->listMenu();
        $this->renderKasir('kasir/menu', [
            'title'  => 'Stok Menu',
            'groups' => MenuCatalog::group($menu, $categories),
            'picks'  => $this->todayPicks(),
            'pickLimit' => MenuCatalog::PICK_LIMIT,
            'habis'  => count(array_filter($menu, static fn ($m) => !$m['tersedia'])),
            'total'  => count($menu),
        ]);
    }

    public function menuStatus(string $id): void
    {
        KasirAuth::require();
        Csrf::verify();
        try {
            $menuId = Validator::menuId($id);
            $status = Validator::menuStatus($this->post('status'));
            $menu = $this->data()->getMenu($menuId);
            if ($menu === null) {
                throw new HttpException(404, 'Menu tidak ditemukan.');
            }
            $res = $this->data()->setMenuStatus($menuId, $status);
            Session::flash($res === 'updated' ? 'success' : 'info', "{$menu['nama_menu']} sekarang {$status}.");
        } catch (ValidationError | DomainRejected $e) {
            Session::flash('error', $e->getMessage());
        }
        View::redirect('/kasir/menu' . (isset($menuId) ? '#' . $menuId : ''));
    }

    /** Tambah/hapus "pilihan dapur hari ini". */
    public function menuPick(string $id): void
    {
        KasirAuth::require();
        Csrf::verify();
        try {
            $menuId = Validator::menuId($id);
            $menu = $this->data()->getMenu($menuId);
            if ($menu === null) {
                throw new HttpException(404, 'Menu tidak ditemukan.');
            }
            $on = $this->post('aksi') === 'tambah';
            $res = $this->data()->setTodayPick($menuId, $on, MenuCatalog::PICK_LIMIT,
                MenuCatalog::endOfDay((string) App::config('app.timezone')));
            Session::flash($res === 'unchanged' ? 'info' : 'success', $on
                ? "{$menu['nama_menu']} menjadi pilihan dapur hari ini."
                : "{$menu['nama_menu']} dihapus dari pilihan dapur.");
        } catch (ValidationError | DomainRejected $e) {
            Session::flash('error', $e->getMessage());
        }
        View::redirect('/kasir/menu' . (isset($menuId) ? '#' . $menuId : ''));
    }

    // ---------------- Meja ----------------

    public function tables(): void
    {
        KasirAuth::require();
        $configured = TableAccess::isConfigured();
        $base = $this->qrBase();
        $this->renderKasir('kasir/tables', [
            'title'      => 'Meja',
            'tables'     => $this->tablesWithQr($base),
            'configured' => $configured,
            'qrBase'     => $base,
            'localOnly'  => QrCode::isLocalOnly($base),
            'lanIps'     => QrCode::lanAddresses(),
            'isAdmin'    => KasirAuth::isAdmin(KasirAuth::user()),
        ]);
    }

    /** Halaman cetak kartu QR meja (semua meja, atau satu meja lewat ?meja=1A). */
    public function tablesPrint(): void
    {
        KasirAuth::require();
        if (!TableAccess::isConfigured()) {
            throw new HttpException(503, 'APP_KEY belum diatur, jadi QR meja belum bisa dibuat.');
        }
        $base = $this->qrBase();
        $only = $this->query('meja');
        $tables = array_values(array_filter($this->tablesWithQr($base),
            static fn ($t) => $t['aktif'] && ($only === '' || $t['kode'] === $only)));
        foreach ($tables as &$t) {
            $t['qr_svg'] = QrCode::svg($t['qr_url']);
        }
        unset($t);
        $this->renderKasir('kasir/tables-print', [
            'title'     => 'Cetak QR Meja',
            'tables'    => $tables,
            'localOnly' => QrCode::isLocalOnly($base),
        ]);
    }

    /** @return list<array<string,mixed>> meja + tautan QR versi terbaru */
    private function tablesWithQr(string $base): array
    {
        $configured = TableAccess::isConfigured();
        $tables = $this->data()->listTables();
        foreach ($tables as &$t) {
            $t['qr_path'] = $configured ? TableAccess::path($t['kode'], $t['qr_versi']) : '';
            $t['qr_url'] = $configured ? $base . $t['qr_path'] : '';
        }
        unset($t);
        return $tables;
    }

    private function qrBase(): string
    {
        return QrCode::baseUrl((string) App::config('app.url'), (string) ($_SERVER['HTTP_HOST'] ?? ''));
    }

    public function releaseTable(string $code): void
    {
        KasirAuth::require();
        Csrf::verify();
        try {
            $code = Validator::tableCode($code);
            $res = $this->data()->releaseTable($code);
            Session::flash('success', $res === 'released'
                ? "Meja {$code} dikosongkan dan siap untuk tamu berikutnya. Sesi tamu sebelumnya sudah berakhir."
                : "Meja {$code} sudah tersedia; keranjang dan sesi lama dibersihkan.");
        } catch (ValidationError | DomainRejected $e) {
            Session::flash('error', $e->getMessage());
        }
        View::redirect('/kasir/meja');
    }

    // ---------------- Rekap penjualan ----------------

    public function sales(): void
    {
        KasirAuth::require();
        try {
            $from = Validator::date($this->query('dari')) ?? (new \DateTimeImmutable('first day of this month'))->format('Y-m-d');
            $to = Validator::date($this->query('sampai')) ?? (new \DateTimeImmutable('today'))->format('Y-m-d');
        } catch (ValidationError $e) {
            Session::flash('error', $e->getMessage());
            View::redirect('/kasir/rekap');
        }
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }
        $fromTs = (new \DateTimeImmutable($from . ' 00:00:00'))->getTimestamp();
        $toTs = (new \DateTimeImmutable($to . ' 00:00:00'))->modify('+1 day')->getTimestamp();
        $this->renderKasir('kasir/sales', [
            'title'   => 'Laporan Penjualan',
            'summary' => $this->data()->getSalesSummary($fromTs, $toTs),
            // Semua transaksi (tidak dibatalkan) yang dibuat dalam rentang, dan riwayat pesanan selesai terbaru.
            'transaksi' => count(array_filter($all = $this->data()->listOrders(KasirAuth::access(), ['limit' => 1000]),
                static fn ($o) => $o['waktu'] >= $fromTs && $o['waktu'] < $toTs && $o['status_pesanan'] !== 'Dibatalkan')),
            'riwayat' => array_slice(array_values(array_filter($all,
                static fn ($o) => $o['status_pesanan'] === 'Selesai' && $o['selesai_at'] >= $fromTs && $o['selesai_at'] < $toTs)), 0, 10),
            'from'    => $from,
            'to'      => $to,
        ]);
    }
}
