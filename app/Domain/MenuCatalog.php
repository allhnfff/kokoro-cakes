<?php

declare(strict_types=1);

namespace App\Domain;

/** Bantuan tampilan menu: pengelompokan per kategori dan pencarian sederhana. */
final class MenuCatalog
{
    /** Jumlah maksimal "pilihan dapur hari ini". */
    public const PICK_LIMIT = 3;

    /** Waktu UNIX tengah malam berikutnya di zona waktu toko (pilihan dapur kedaluwarsa saat itu). */
    public static function endOfDay(string $timezone, ?int $now = null): int
    {
        $tz = new \DateTimeZone($timezone);
        return (new \DateTimeImmutable('@' . ($now ?? time())))->setTimezone($tz)->modify('tomorrow')->getTimestamp();
    }

    /**
     * @param list<array<string,mixed>> $menu
     * @param list<array{id:string,nama_kategori:string,urutan:int}> $categories
     * @return array<string, array{label:string, items:list<array<string,mixed>>}> hanya kategori yang punya item
     */
    public static function group(array $menu, array $categories): array
    {
        $groups = [];
        foreach ($categories as $c) {
            $groups[$c['id']] = ['label' => $c['nama_kategori'], 'items' => []];
        }
        foreach ($menu as $m) {
            if (isset($groups[$m['id_kategori']])) {
                $groups[$m['id_kategori']]['items'][] = $m;
            }
        }
        return array_filter($groups, static fn ($g) => $g['items'] !== []);
    }

    /** @param list<array<string,mixed>> $menu @return array<string,array<string,mixed>> menu per id */
    public static function byId(array $menu): array
    {
        return array_column($menu, null, 'id');
    }

    /**
     * Saran pasangan untuk satu menu, hanya jika menu pasangannya ada dan sedang Tersedia.
     * @param array<string,mixed> $item  @param array<string,array<string,mixed>> $menuById
     * @return array{menu:array<string,mixed>, alasan:string}|null
     */
    public static function pairingFor(array $item, array $menuById): ?array
    {
        $pair = $menuById[$item['pasangan'] ?? ''] ?? null;
        if ($pair === null || !$pair['tersedia'] || $pair['id'] === $item['id']) {
            return null;
        }
        return ['menu' => $pair, 'alasan' => (string) $item['pasangan_alasan']];
    }

    /**
     * Saran pasangan untuk isi keranjang: menu pasangan yang Tersedia dan BELUM ada di keranjang.
     * @param list<array<string,mixed>> $lines baris keranjang (id_menu, nama_menu)
     * @param array<string,array<string,mixed>> $menuById
     * @return list<array{untuk:string, menu:array<string,mixed>, alasan:string}>
     */
    public static function cartSuggestions(array $lines, array $menuById, int $limit = 2): array
    {
        $inCart = array_flip(array_column($lines, 'id_menu'));
        $out = [];
        foreach ($lines as $l) {
            $item = $menuById[$l['id_menu']] ?? null;
            $pair = $item !== null ? self::pairingFor($item, $menuById) : null;
            if ($pair === null || isset($inCart[$pair['menu']['id']])) {
                continue;
            }
            $inCart[$pair['menu']['id']] = true; // jangan sarankan menu yang sama dua kali
            $out[] = ['untuk' => (string) $item['nama_menu']] + $pair;
            if (count($out) >= $limit) {
                break;
            }
        }
        return $out;
    }

    /**
     * Menu yang paling sering dipesan menurut data penjualan nyata (pesanan Selesai).
     * Menu baru masuk daftar jika terjual minimal $minQty porsi, agar satu pesanan saja tidak
     * langsung dianggap "sering dipesan". Data kosong -> daftar kosong (tidak ada label).
     * @param list<array{id_menu:string,jumlah:int}> $salesMenu urut terbanyak dulu (getSalesSummary)
     * @return array<string,true> id menu => true
     */
    public static function popularIds(array $salesMenu, int $limit = 3, int $minQty = 3): array
    {
        $ids = [];
        foreach ($salesMenu as $row) {
            if (count($ids) >= $limit || (int) $row['jumlah'] < $minQty) {
                break;
            }
            $ids[(string) $row['id_menu']] = true;
        }
        return $ids;
    }

    /** @param list<array<string,mixed>> $menu */
    public static function search(array $menu, string $q): array
    {
        $q = lower(trim($q));
        if ($q === '') {
            return $menu;
        }
        return array_values(array_filter($menu, static fn ($m) => str_contains(lower($m['nama_menu'] . ' ' . $m['deskripsi']), $q)));
    }
}
