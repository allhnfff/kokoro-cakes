<?php

declare(strict_types=1);

namespace App\Controllers;

use App\App;
use App\Domain\MenuCatalog;
use App\Domain\OrderRules;
use App\Domain\TableAccess;
use App\Domain\ValidationError;
use App\Domain\Validator;
use App\Http\Csrf;
use App\Http\HttpException;
use App\Http\Session;
use App\Http\View;

/** Halaman pelanggan: beranda, daftar menu, detail menu, masuk meja lewat QR. */
final class ShopController extends Controller
{
    public function home(): void
    {
        $menu = $this->data()->listMenu();
        $categories = $this->data()->listCategories();
        // Indeks kategori dari data nyata: jumlah menu, harga termurah yang tersedia, tiga contoh nama.
        $index = [];
        foreach ($categories as $c) {
            $items = array_values(array_filter($menu, static fn ($m) => $m['id_kategori'] === $c['id']));
            if (!$items) {
                continue;
            }
            $available = array_values(array_filter($items, static fn ($m) => $m['tersedia']));
            $index[] = $c + [
                'count'  => count($items),
                'min'    => $available ? min(array_column($available, 'harga')) : null,
                // Tiga menu contoh: namanya ditulis dan fotonya ditampilkan kecil (foto = menu yang disebut).
                'sample' => array_slice($available, 0, 3),
            ];
        }
        // Pilihan dapur hari ini (dipilih kasir), hanya yang sedang Tersedia.
        $byId = MenuCatalog::byId($menu);
        $picks = [];
        foreach (array_keys($this->todayPicks()) as $id) {
            if (isset($byId[$id]) && $byId[$id]['tersedia']) {
                $picks[] = $byId[$id];
            }
        }
        // Pesanan perangkat ini yang belum selesai: ditampilkan langsung di beranda (bukan sekadar kalimat "bisa dipantau").
        $activeOrders = array_slice(array_values(array_filter(
            $this->data()->listOrders(['type' => 'customer', 'owner_hash' => Session::ownerHash()], ['limit' => 10]),
            static fn ($o) => OrderRules::isActive((string) $o['status_pesanan'])
        )), 0, 3);
        $heroPhoto = owner_image('hero');
        $this->render('shop/home', [
            'title'      => 'Beranda',
            'index'      => $index,
            'activeOrders' => $activeOrders,
            'picks'      => $picks,
            'heroPhoto'  => $heroPhoto,
            'slides'     => $heroPhoto !== null ? $this->heroSlides($heroPhoto, $picks, $index) : [],
        ]);
    }

    /**
     * Slide foto beranda: foto suasana kafe dulu, lalu menu Pilihan Dapur, lalu satu menu per kategori.
     * Hanya menu yang Tersedia dan punya foto; maksimal 5 menu agar titik navigasinya tetap sedikit.
     * @param list<array> $picks @param list<array> $index
     * @return list<array{src:string,menu:?array,pick:bool}>
     */
    private function heroSlides(string $heroPhoto, array $picks, array $index): array
    {
        $slides = [['src' => $heroPhoto, 'menu' => null, 'pick' => false]];
        $seen = [];
        $add = static function (array $m, bool $pick) use (&$slides, &$seen): void {
            $photo = menu_photo($m);
            if ($photo === null || isset($seen[$m['id']]) || count($slides) > 5) {
                return;
            }
            $seen[$m['id']] = true;
            $slides[] = ['src' => $photo, 'menu' => $m, 'pick' => $pick];
        };
        foreach ($picks as $m) {
            $add($m, true);
        }
        foreach ($index as $c) {
            foreach ($c['sample'] as $m) {
                if (menu_photo($m) !== null && !isset($seen[$m['id']])) {
                    $add($m, false);
                    break;
                }
            }
        }
        return $slides;
    }

    public function menu(): void
    {
        $categories = $this->data()->listCategories();
        $active = $this->query('kategori');
        if (!in_array($active, array_column($categories, 'id'), true)) {
            $active = '';
        }
        $q = mb_substr($this->query('q'), 0, 60);
        $menu = MenuCatalog::search($this->data()->listMenu(), $q);
        if ($active !== '') {
            $menu = array_values(array_filter($menu, static fn ($m) => $m['id_kategori'] === $active));
        }
        $this->render('shop/menu', [
            'title'      => 'Menu',
            'categories' => $categories,
            'groups'     => MenuCatalog::group($menu, $categories),
            'popular'    => $this->popular(),
            'picks'      => $this->todayPicks(),
            'active'     => $active,
            'q'          => $q,
            'total'      => count($menu),
        ]);
    }

    public function menuShow(string $id): void
    {
        try {
            $id = Validator::menuId($id);
        } catch (ValidationError) {
            throw new HttpException(404, 'Menu tidak ditemukan.');
        }
        $item = $this->data()->getMenu($id);
        if ($item === null) {
            throw new HttpException(404, 'Menu tidak ditemukan.');
        }
        $categoryName = '';
        foreach ($this->data()->listCategories() as $c) {
            if ($c['id'] === $item['id_kategori']) {
                $categoryName = $c['nama_kategori'];
            }
        }
        $allMenu = $this->data()->listMenu();
        $related = array_slice(array_values(array_filter(
            $allMenu,
            static fn ($m) => $m['id_kategori'] === $item['id_kategori'] && $m['id'] !== $item['id'] && $m['tersedia']
        )), 0, 4);
        $this->render('shop/menu-item', [
            'title'        => $item['nama_menu'],
            'item'         => $item,
            'categoryName' => $categoryName,
            'related'      => $related,
            'pairing'      => MenuCatalog::pairingFor($item, MenuCatalog::byId($allMenu)),
            'maxQty'       => (int) App::config('order.max_qty_per_line'),
        ]);
    }

    /** Menu yang paling sering dipesan dalam 30 hari terakhir (dari pesanan Selesai). @return array<string,true> */
    private function popular(): array
    {
        $now = time();
        $sales = $this->data()->getSalesSummary($now - 30 * 86400, $now + 1);
        return MenuCatalog::popularIds($sales['menu']);
    }

    /** Panduan cara memesan untuk tamu yang baru pertama kali. */
    public function guide(): void
    {
        $this->render('shop/guide', [
            'title'         => 'Cara Pesan',
            'unpaidMinutes' => (int) App::config('order.unpaid_minutes'),
        ]);
    }

    /** Tautan dari QR meja: /meja/{kode}/{token}. */
    public function enterTable(string $code, string $token): void
    {
        try {
            $code = Validator::tableCode($code);
        } catch (ValidationError) {
            throw new HttpException(404, 'Meja tidak ditemukan.');
        }
        $table = $this->data()->getTable($code);
        // Pesan sama untuk meja tidak ada / token salah / QR lama yang sudah diganti.
        if ($table === null || !TableAccess::verify($code, strtolower($token), $table['qr_versi'])) {
            throw new HttpException(404, 'Kode QR meja tidak valid atau sudah diganti. Minta kasir untuk mengecek QR di meja.');
        }
        if (!$table['aktif']) {
            Session::flash('error', "Meja {$code} sedang tidak aktif. Silakan hubungi kasir.");
            View::redirect('/');
        }
        Session::setTable($code, $table['qr_versi'], $table['sesi']);
        Session::flash('success', "Kamu memesan dari meja {$code} ({$table['area']}). Silakan pilih menu.");
        View::redirect('/menu');
    }

    public function leaveTable(): void
    {
        Csrf::verify();
        Session::forgetTable();
        unset($_SESSION['checkout_draft']);
        Session::flash('info', 'Kamu sudah keluar dari meja. Scan QR lagi untuk memesan.');
        View::redirect('/');
    }
}
