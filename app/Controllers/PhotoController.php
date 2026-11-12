<?php

declare(strict_types=1);

namespace App\Controllers;

use App\App;
use App\Data\DataServiceUnavailable;

/**
 * Menampilkan foto menu unggahan admin yang disimpan di Redis: /foto/menu/{id}/{versi}.
 * Dipanggil sebelum sesi dimulai (tanpa cookie), dan versi ada di alamat, jadi browser boleh
 * menyimpan cache selamanya: foto baru = versi baru = alamat baru. Hemat kuota Redis.
 */
final class PhotoController
{
    public static function serve(string $path): bool
    {
        if (preg_match('#^/foto/menu/(m[1-9][0-9]{0,3})/([a-f0-9]{12})$#', $path, $m) !== 1) {
            return false;
        }
        try {
            $photo = App::data()->getMenuPhoto($m[1], $m[2]);
        } catch (DataServiceUnavailable) {
            http_response_code(503);
            header('Retry-After: 30');
            return true;
        }
        if ($photo === null || !in_array($photo['tipe'], ['image/jpeg', 'image/png', 'image/webp'], true)) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Foto tidak ditemukan.';
            return true;
        }
        header('Content-Type: ' . $photo['tipe']);
        header('Content-Length: ' . strlen($photo['data']));
        header('Cache-Control: public, max-age=31536000, immutable');
        echo $photo['data'];
        return true;
    }
}
