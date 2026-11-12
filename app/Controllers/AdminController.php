<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Data\DomainRejected;
use App\Domain\KasirAuth;
use App\Domain\MenuCatalog;
use App\Domain\MenuForm;
use App\Domain\MenuPhoto;
use App\Domain\ValidationError;
use App\Domain\Validator;
use App\Http\Csrf;
use App\Http\HttpException;
use App\Http\Session;
use App\Http\View;

/**
 * Halaman khusus ADMIN (role admin): kelola menu dan ganti QR meja.
 * Kasir biasa tidak bisa membuka halaman ini (403).
 */
final class AdminController extends Controller
{
    // ---------------- Kelola menu ----------------

    public function menuIndex(): void
    {
        KasirAuth::requireAdmin();
        $categories = $this->data()->listCategories();
        $this->renderKasir('kasir/admin-menu', [
            'title'  => 'Kelola Menu',
            'groups' => MenuCatalog::group($this->data()->listMenu(), $categories),
        ]);
    }

    public function menuCreateForm(): void
    {
        KasirAuth::requireAdmin();
        $this->showForm(null, [
            'nama_menu' => '', 'nama_jp' => '', 'id_kategori' => '', 'harga' => '', 'deskripsi' => '',
            'status' => 'Tersedia', 'pasangan' => '', 'pasangan_alasan' => '',
        ], []);
    }

    public function menuStore(): void
    {
        KasirAuth::requireAdmin();
        Csrf::verify();
        [$fields, $errors] = $this->validate(null);
        if ($errors) {
            $this->showForm(null, $fields, $errors, 422);
            return;
        }
        try {
            $id = $this->data()->createMenu($fields);
        } catch (DomainRejected $e) {
            $this->showForm(null, $fields, ['_form' => $e->getMessage()], 422);
            return;
        }
        if (MenuPhoto::present($_FILES['foto'] ?? null)) {
            $photo = MenuPhoto::read($_FILES['foto']);
            $this->data()->setMenuPhoto($id, $photo['mime'], $photo['bytes']);
        }
        Session::flash('success', "{$fields['nama_menu']} ditambahkan dengan kode {$id}.");
        View::redirect('/kasir/kelola-menu#' . $id);
    }

    public function menuEditForm(string $id): void
    {
        KasirAuth::requireAdmin();
        $menu = $this->findMenu($id);
        $this->showForm($menu, $menu, []);
    }

    public function menuUpdate(string $id): void
    {
        KasirAuth::requireAdmin();
        Csrf::verify();
        $menu = $this->findMenu($id);
        [$fields, $errors] = $this->validate($menu['id']);
        if ($errors) {
            $this->showForm($menu, $fields, $errors, 422);
            return;
        }
        try {
            $this->data()->updateMenu($menu['id'], $fields);
        } catch (DomainRejected $e) {
            $this->showForm($menu, $fields, ['_form' => $e->getMessage()], 422);
            return;
        }
        if (MenuPhoto::present($_FILES['foto'] ?? null)) {
            $photo = MenuPhoto::read($_FILES['foto']);
            $this->data()->setMenuPhoto($menu['id'], $photo['mime'], $photo['bytes']);
        } elseif (($_POST['hapus_foto'] ?? '') === '1') {
            $this->data()->removeMenuPhoto($menu['id']);
        }
        Session::flash('success', "{$fields['nama_menu']} disimpan.");
        View::redirect('/kasir/kelola-menu#' . $menu['id']);
    }

    public function menuDelete(string $id): void
    {
        KasirAuth::requireAdmin();
        Csrf::verify();
        $menu = $this->findMenu($id);
        try {
            $this->data()->deleteMenu($menu['id']); // foto unggahan ikut terhapus di skrip yang sama
            Session::flash('success', "{$menu['nama_menu']} dihapus dari menu. Riwayat pesanan lama tidak berubah.");
        } catch (DomainRejected $e) {
            Session::flash('error', $e->getMessage());
        }
        View::redirect('/kasir/kelola-menu');
    }

    // ---------------- QR meja ----------------

    /** Ganti QR meja: QR lama (yang mungkin sudah difoto orang) langsung tidak berlaku. */
    public function rotateQr(string $code): void
    {
        KasirAuth::requireAdmin();
        Csrf::verify();
        try {
            $code = Validator::tableCode($code);
            $version = $this->data()->rotateTableQr($code);
            Session::flash('success', "QR meja {$code} sudah diganti (versi {$version}). Cetak QR yang baru dan tempel di meja; QR lama tidak berlaku lagi.");
        } catch (ValidationError | DomainRejected $e) {
            Session::flash('error', $e->getMessage());
        }
        View::redirect('/kasir/meja');
    }

    // ---------------- Bantuan ----------------

    /** @return array{0: array<string,string|int>, 1: array<string,string>} */
    private function validate(?string $selfId): array
    {
        $categories = array_column($this->data()->listCategories(), 'id');
        $menuIds = array_column($this->data()->listMenu(), 'id');
        $res = MenuForm::validate($_POST, $categories, $menuIds, $selfId);
        $errors = $res['errors'];
        if (MenuPhoto::present($_FILES['foto'] ?? null) && ($err = MenuPhoto::check($_FILES['foto'])) !== null) {
            $errors['foto'] = $err;
        }
        return [$res['fields'], $errors];
    }

    /** @return array<string,mixed> */
    private function findMenu(string $id): array
    {
        try {
            $id = Validator::menuId($id);
        } catch (ValidationError) {
            throw new HttpException(404, 'Menu tidak ditemukan.');
        }
        $menu = $this->data()->getMenu($id);
        if ($menu === null) {
            throw new HttpException(404, 'Menu tidak ditemukan.');
        }
        return $menu;
    }

    /** @param array<string,mixed>|null $menu  @param array<string,mixed> $values  @param array<string,string> $errors */
    private function showForm(?array $menu, array $values, array $errors, int $status = 200): void
    {
        $all = $this->data()->listMenu();
        $this->renderKasir('kasir/admin-menu-form', [
            'title'      => $menu === null ? 'Tambah Menu' : 'Ubah ' . $menu['nama_menu'],
            'menu'       => $menu,
            'values'     => $values,
            'errors'     => $errors,
            'categories' => $this->data()->listCategories(),
            'pairOptions' => array_values(array_filter($all, static fn ($m) => $menu === null || $m['id'] !== $menu['id'])),
        ], $status);
    }
}
