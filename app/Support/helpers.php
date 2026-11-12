<?php

declare(strict_types=1);

use App\App;

/** Escape teks sebelum dicetak ke HTML (mencegah XSS). */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Format rupiah: 28000 -> "Rp28.000" */
function rupiah(int|float|string|null $amount): string
{
    return 'Rp' . number_format((float) $amount, 0, ',', '.');
}

/** Ambil nilai konfigurasi, contoh: config('store.name') */
function config(string $key, mixed $default = null): mixed
{
    return App::config($key, $default);
}

/** URL relatif aman untuk tautan internal. */
function url(string $path = '/', array $query = []): string
{
    $path = '/' . ltrim($path, '/');
    $query = array_filter($query, static fn ($v) => $v !== null && $v !== '');
    return $path . ($query ? '?' . http_build_query($query) : '');
}

/** URL aset statis dengan versi berdasarkan waktu ubah file (agar cache browser diperbarui). */
function asset(string $path): string
{
    $file = App::root() . '/public/' . ltrim($path, '/');
    $v = is_file($file) ? (string) filemtime($file) : '1';
    return '/' . ltrim($path, '/') . '?v=' . $v;
}

/**
 * Foto menu, atau null jika belum ada. Urutan pencarian:
 *   1. field foto = '-'                       -> foto dihapus admin (tanpa foto)
 *   2. field foto = /foto/menu/{id}/{versi}  -> foto unggahan admin, disimpan di Redis
 *   3. field foto berisi path lokal, misalnya /assets/img/menu/foto/m1.jpg
 *   4. file public/assets/img/menu/foto/{id}.webp|.jpg|.jpeg|.png (foto bawaan)
 */
function menu_photo(array $menu): ?string
{
    static $cache = [];
    $id = (string) ($menu['id'] ?? $menu['id_menu'] ?? '');
    $foto = (string) ($menu['foto'] ?? '');
    $key = $id . '|' . $foto;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $found = null;
    if ($foto === '-') {
        $found = null;
    } elseif (preg_match('#^/foto/menu/m[0-9]{1,4}/[a-f0-9]{12}$#', $foto) === 1) {
        $found = $foto;
    } elseif ($foto !== '' && preg_match('#^/assets/img/[A-Za-z0-9/_.-]+$#', $foto) && is_file(App::root() . '/public' . $foto)) {
        $found = $foto;
    } elseif (preg_match('/^m[0-9]{1,4}$/', $id)) {
        foreach (['webp', 'jpg', 'jpeg', 'png'] as $ext) {
            $rel = 'assets/img/menu/foto/' . $id . '.' . $ext;
            if (is_file(App::root() . '/public/' . $rel)) {
                $found = '/' . $rel . '?v=' . filemtime(App::root() . '/public/' . $rel);
                break;
            }
        }
    }
    return $cache[$key] = $found;
}

/**
 * File gambar milik pemilik di public/assets/img/{name}.svg|.webp|.png|.jpg, atau null.
 * Dipakai untuk logo (logo.*) dan foto beranda (hero.*). Tidak ada gambar pengganti buatan.
 */
function owner_image(string $name): ?string
{
    static $cache = [];
    if (array_key_exists($name, $cache)) {
        return $cache[$name];
    }
    foreach (['svg', 'webp', 'png', 'jpg', 'jpeg'] as $ext) {
        $file = App::root() . '/public/assets/img/' . $name . '.' . $ext;
        if (is_file($file)) {
            return $cache[$name] = '/assets/img/' . $name . '.' . $ext . '?v=' . filemtime($file);
        }
    }
    return $cache[$name] = null;
}

/**
 * Tanda merek: simbol logo (jika ada) + nama toko sebagai teks.
 * $variant 'logo' untuk latar terang, 'logo-light' untuk latar hijau gelap (kasir).
 */
function brand_mark(string $subtitle, string $variant = 'logo'): string
{
    $name = (string) config('store.name');
    $logo = owner_image($variant) ?? owner_image('logo');
    $mark = $logo !== null
        ? '<img class="brand-logo" src="' . e($logo) . '" alt="" width="40" height="40">'
        : '';
    return $mark . '<span class="brand-text"><b>' . e($name) . '</b><small>' . e($subtitle) . '</small></span>';
}

/** Tag favicon: favicon.* jika ada, selain itu logo.*; kosong jika keduanya tidak ada. */
function favicon_tag(): string
{
    $icon = owner_image('favicon') ?? owner_image('logo');
    return $icon === null ? '' : '<link rel="icon" href="' . e($icon) . '"' . (str_contains($icon, '.svg') ? ' type="image/svg+xml"' : '') . '>';
}

/** Input tersembunyi berisi token CSRF untuk setiap form POST. */
function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(\App\Http\Csrf::token()) . '">';
}

/** Format waktu UNIX ke zona waktu aplikasi. */
function wib(int|string|null $timestamp, string $format = 'd M Y, H:i'): string
{
    if ($timestamp === null || $timestamp === '' || (int) $timestamp <= 0) {
        return '-';
    }
    $tz = new DateTimeZone((string) App::config('app.timezone', 'Asia/Jakarta'));
    $dt = (new DateTimeImmutable('@' . (int) $timestamp))->setTimezone($tz);
    return $dt->format($format) . ' WIB';
}

/** Ubah huruf kecil yang aman untuk teks UTF-8. */
function lower(string $text): string
{
    return function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
}

/** Nama kelas CSS untuk badge status (huruf kecil, spasi jadi tanda hubung). */
function status_class(string $status): string
{
    return 'st-' . preg_replace('/[^a-z0-9]+/', '-', lower($status));
}

/** Ikon garis sederhana (SVG inline, dekoratif). Nama yang tidak dikenal menghasilkan string kosong. */
function icon(string $name, int $size = 22): string
{
    static $paths = [
        'cart'    => '<path d="M5 8h14l-1.2 11.2a2 2 0 0 1-2 1.8H8.2a2 2 0 0 1-2-1.8L5 8Z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/>',
        'receipt' => '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6"/>',
        'home'    => '<path d="M4 10.5 12 4l8 6.5V20a1 1 0 0 1-1 1h-5v-6h-4v6H5a1 1 0 0 1-1-1z"/>',
        'menu'    => '<path d="M4 6h16M4 12h16M4 18h10"/>',
        'table'   => '<path d="M3 9h18M5 9v10M19 9v10M8 9l1-4h6l1 4"/>',
        'check'   => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
        'arrow'   => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'close'   => '<path d="M6 6l12 12M18 6 6 18"/>',
        'clock'   => '<circle cx="12" cy="12" r="8"/><path d="M12 8v4l3 2"/>',
        'qr'      => '<rect x="4" y="4" width="6" height="6" rx="1"/><rect x="14" y="4" width="6" height="6" rx="1"/><rect x="4" y="14" width="6" height="6" rx="1"/><path d="M14 14h2v2h-2zM18 18h2v2h-2zM14 18h2M18 14h2"/>',
        'plus'    => '<path d="M12 5v14M5 12h14"/>',
        'minus'   => '<path d="M5 12h14"/>',
        'trash'   => '<path d="M5 7h14M10 7V5h4v2M7 7l1 12h8l1-12"/>',
        'logout'  => '<path d="M15 4h4v16h-4M10 8l-4 4 4 4M6 12h10"/>',
        'help'    => '<circle cx="12" cy="12" r="8.5"/><path d="M9.6 9.4a2.5 2.5 0 0 1 4.8 1c0 1.7-2.4 2.1-2.4 3.6"/><circle cx="12" cy="17" r=".6" fill="currentColor"/>',
        'wifi'    => '<path d="M4 9.5a12 12 0 0 1 16 0M7 13a7.5 7.5 0 0 1 10 0M10 16.5a3 3 0 0 1 4 0"/><circle cx="12" cy="19.5" r=".6" fill="currentColor"/>',
    ];
    if (!isset($paths[$name])) {
        return '';
    }
    return '<svg class="icon" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[$name] . '</svg>';
}
