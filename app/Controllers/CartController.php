<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\MenuCatalog;
use App\Domain\ValidationError;
use App\Domain\Validator;
use App\Http\Csrf;
use App\Http\Session;
use App\Http\View;

/** Keranjang meja: lihat, tambah, ubah jumlah, hapus. */
final class CartController extends Controller
{
    public function show(): void
    {
        $table = $this->requireTable();
        $cart = $this->cart()->detailed($table);
        $suggestions = $cart['lines']
            ? MenuCatalog::cartSuggestions($cart['lines'], MenuCatalog::byId($this->data()->listMenu()))
            : [];
        $quote = $cart['lines'] ? (new \App\Domain\CheckoutService((array) \App\App::config('order.payment_methods'), (int) \App\App::config('order.tax_percent')))->quote($cart) : null;
        $this->render('shop/cart', ['title' => 'Keranjang', 'cart' => $cart, 'suggestions' => $suggestions,
            'quote' => $quote, 'catatan' => (string) ($_SESSION['catatan_pesanan'][$table] ?? '')]);
    }

    /** Simpan catatan pesanan dari keranjang (wireframe no. 5), lalu lanjut ke checkout. */
    public function proceed(): void
    {
        Csrf::verify();
        $table = $this->requireTable();
        try {
            $_SESSION['catatan_pesanan'][$table] = Validator::optionalText($this->post('catatan'), 'Catatan', 200);
        } catch (ValidationError $e) {
            Session::flash('error', $e->getMessage());
            View::redirect('/keranjang');
        }
        View::redirect('/checkout');
    }

    public function add(): void
    {
        Csrf::verify();
        $table = $this->requireTable();
        $menuId = '';
        // Dipanggil JavaScript (fetch) -> balas JSON agar halaman tidak perlu dimuat ulang.
        // Tanpa JavaScript, form biasa tetap berjalan dengan redirect seperti sebelumnya.
        $wantsJson = str_starts_with(trim((string) ($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json');
        try {
            $menuId = Validator::menuId($this->post('id_menu'));
            $qty = Validator::quantity($this->post('qty'));
            $menu = $this->cart()->add($table, $menuId, $qty);
            $message = $qty > 1 ? "{$menu['nama_menu']} ({$qty}) masuk keranjang." : "{$menu['nama_menu']} masuk keranjang.";
            if ($wantsJson) {
                View::json(['ok' => true, 'message' => $message, 'cart_count' => $this->cart()->count($table)]);
                return;
            }
            Session::flash('success', $message);
        } catch (ValidationError $e) {
            if ($wantsJson) {
                View::json(['ok' => false, 'message' => $e->getMessage(), 'cart_count' => $this->cart()->count($table)], 422);
                return;
            }
            Session::flash('error', $e->getMessage());
        }
        // Kembali ke halaman asal yang aman (hanya pilihan tetap, bukan URL dari browser).
        View::redirect(match ($this->post('kembali')) {
            'detail' => $menuId !== '' ? '/menu/' . $menuId : '/menu',
            'beranda' => '/',
            'keranjang' => '/keranjang',
            default => '/menu' . ($menuId !== '' ? '#' . $menuId : ''),
        });
    }

    public function update(): void
    {
        Csrf::verify();
        $table = $this->requireTable();
        try {
            $menuId = Validator::menuId($this->post('id_menu'));
            $qty = Validator::quantity($this->post('qty'));
            $this->cart()->update($table, $menuId, $qty);
            Session::flash('success', 'Jumlah diperbarui.');
        } catch (ValidationError $e) {
            Session::flash('error', $e->getMessage());
        }
        View::redirect('/keranjang');
    }

    public function remove(): void
    {
        Csrf::verify();
        $table = $this->requireTable();
        try {
            $menuId = Validator::menuId($this->post('id_menu'));
            $this->cart()->remove($table, $menuId);
            Session::flash('success', 'Item dihapus dari keranjang.');
        } catch (ValidationError $e) {
            Session::flash('error', $e->getMessage());
        }
        View::redirect('/keranjang');
    }
}
