<?php

declare(strict_types=1);

namespace App\Domain;

use App\Data\DataService;

/**
 * Aturan bisnis keranjang meja (lapisan antara controller dan layanan data).
 * - Satu keranjang per meja (key cart:{kode_meja}); semua tamu di meja yang sama berbagi keranjang.
 * - Harga SELALU diambil dari data menu di server, bukan dari browser.
 * - Menu berstatus Habis tidak bisa ditambahkan.
 */
final class CartService
{
    public function __construct(
        private readonly DataService $data,
        private readonly int $maxQty = 20
    ) {
    }

    /** Tambah ke keranjang: jumlah baru = jumlah lama + qty. */
    public function add(string $table, string $menuId, int $qty): array
    {
        $this->requireActiveTable($table);
        $menu = $this->data->getMenu($menuId);
        if ($menu === null) {
            throw new ValidationError('Menu tidak ditemukan.');
        }
        if (!$menu['tersedia']) {
            throw new ValidationError($menu['nama_menu'] . ' sedang habis.');
        }
        $current = 0;
        foreach ($this->data->getCart($table) as $line) {
            if ($line['id_menu'] === $menuId) {
                $current = $line['qty'];
            }
        }
        $newQty = $current + $qty;
        if ($newQty > $this->maxQty) {
            throw new ValidationError("Jumlah maksimal {$this->maxQty} per menu.");
        }
        $this->data->setCartItem($table, $menuId, $newQty);
        return $menu;
    }

    public function update(string $table, string $menuId, int $qty): void
    {
        $this->requireActiveTable($table);
        if ($this->data->getMenu($menuId) === null) {
            throw new ValidationError('Item keranjang tidak valid.');
        }
        if ($qty > $this->maxQty) {
            throw new ValidationError("Jumlah maksimal {$this->maxQty} per menu.");
        }
        $this->data->setCartItem($table, $menuId, $qty);
    }

    public function remove(string $table, string $menuId): void
    {
        $this->data->removeCartItem($table, $menuId);
    }

    /**
     * Keranjang lengkap dengan nama, harga dari server, dan tanda masalah (menu habis/hilang).
     * @return array{lines: list<array<string,mixed>>, subtotal:int, units:int, has_problem:bool}
     */
    public function detailed(string $table): array
    {
        $raw = $this->data->getCart($table);
        $lines = [];
        $subtotal = 0;
        $units = 0;
        $problem = false;
        foreach ($raw as $line) {
            $menu = $this->data->getMenu($line['id_menu']);
            $issue = null;
            if ($menu === null) {
                $issue = 'Menu ini tidak lagi tersedia. Silakan hapus.';
            } elseif (!$menu['tersedia']) {
                $issue = 'Menu ini baru saja habis. Hapus dari keranjang untuk melanjutkan.';
            }
            $price = $menu['harga'] ?? 0;
            $lineTotal = $issue === null ? $price * $line['qty'] : 0;
            $lines[] = [
                'id_menu'     => $line['id_menu'],
                'qty'         => $line['qty'],
                'nama_menu'   => $menu['nama_menu'] ?? $line['id_menu'],
                'nama_jp'     => $menu['nama_jp'] ?? '',
                'id_kategori' => $menu['id_kategori'] ?? '',
                'foto'        => $menu['foto'] ?? '',
                'harga'       => $price,
                'line_total'  => $lineTotal,
                'issue'       => $issue,
            ];
            if ($issue !== null) {
                $problem = true;
            } else {
                $subtotal += $lineTotal;
                $units += $line['qty'];
            }
        }
        return ['lines' => $lines, 'subtotal' => $subtotal, 'units' => $units, 'has_problem' => $problem];
    }

    public function count(string $table): int
    {
        return array_sum(array_column($this->data->getCart($table), 'qty'));
    }

    private function requireActiveTable(string $table): void
    {
        $t = $this->data->getTable($table);
        if ($t === null || !$t['aktif']) {
            throw new ValidationError('Meja ini sedang tidak aktif. Hubungi kasir.');
        }
    }
}
