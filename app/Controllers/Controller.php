<?php

declare(strict_types=1);

namespace App\Controllers;

use App\App;
use App\Data\DataService;
use App\Data\DataServiceUnavailable;
use App\Domain\CartService;
use App\Domain\KasirAuth;
use App\Http\Session;
use App\Http\View;

/** Fungsi bantu bersama untuk semua controller. */
abstract class Controller
{
    protected function data(): DataService
    {
        return App::data();
    }

    protected function cart(): CartService
    {
        return new CartService($this->data(), (int) App::config('order.max_qty_per_line'));
    }

    /**
     * Kode meja dari scan QR, atau null.
     * Sesi tamu tidak berlaku lagi jika admin mengganti QR meja itu, atau staf sudah mengosongkan
     * meja (tamu sudah pergi), sehingga HP tamu lama tidak bisa memesan ke meja yang sudah dipakai orang lain.
     */
    protected function table(): ?string
    {
        static $checked = [];
        $code = Session::table();
        if ($code === null) {
            return null;
        }
        $key = $code . '|' . Session::tableVersion() . '|' . Session::tableSession();
        if (!array_key_exists($key, $checked)) {
            $why = null;
            try {
                $t = $this->data()->getTable($code);
                if ($t === null || $t['qr_versi'] !== Session::tableVersion()) {
                    $why = "QR meja {$code} sudah diganti. Scan QR yang baru di meja untuk memesan.";
                } elseif ($t['sesi'] !== Session::tableSession()) {
                    $why = "Sesi di meja {$code} sudah berakhir. Scan QR di meja untuk memesan lagi.";
                }
            } catch (DataServiceUnavailable) {
                // Redis gangguan: halaman yang membutuhkan data akan menampilkan errornya
            }
            $checked[$key] = $why === null;
            if ($why !== null) {
                Session::forgetTable();
                Session::flash('info', $why);
            }
        }
        return $checked[$key] ? $code : null;
    }

    /** Wajib sudah scan QR meja; jika belum, arahkan ke beranda. */
    protected function requireTable(): string
    {
        $table = $this->table();
        if ($table === null) {
            Session::flash('info', 'Scan QR di meja terlebih dahulu untuk mulai memesan.');
            View::redirect('/');
        }
        return $table;
    }

    /** Jumlah item keranjang meja untuk header; null jika belum ada meja atau Redis gangguan. */
    protected function cartCount(): ?int
    {
        $table = $this->table();
        if ($table === null) {
            return null;
        }
        try {
            return $this->cart()->count($table);
        } catch (DataServiceUnavailable) {
            return null;
        }
    }

    /** Id menu pilihan dapur hari ini. @return array<string,true> */
    protected function todayPicks(): array
    {
        return array_fill_keys($this->data()->listTodayPicks(), true);
    }

    /** Render halaman pelanggan. */
    protected function render(string $view, array $vars = [], int $status = 200): void
    {
        $common = [
            'flash'     => Session::takeFlash(),
            'source'    => $this->data()->describe(),
            'table'     => $this->table(),
            'cartCount' => $this->cartCount(),
        ];
        View::render($view, $vars + $common, 'layout', $status);
    }

    /** Render halaman kasir. */
    protected function renderKasir(string $view, array $vars = [], int $status = 200): void
    {
        $common = [
            'flash'  => Session::takeFlash(),
            'source' => $this->data()->describe(),
            'kasir'  => KasirAuth::user(),
        ];
        View::render($view, $vars + $common, 'kasir/layout', $status);
    }

    protected function post(string $key): string
    {
        $v = $_POST[$key] ?? '';
        return is_string($v) ? $v : '';
    }

    protected function query(string $key): string
    {
        $v = $_GET[$key] ?? '';
        return is_string($v) ? trim($v) : '';
    }
}
