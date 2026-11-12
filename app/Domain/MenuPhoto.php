<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Foto menu yang diunggah admin. Disimpan di Redis (foto:menu:{id}) dan ditampilkan lewat
 * /foto/menu/{id}/{versi}, jadi tidak ada file yang ditulis ke folder web.
 * Pemeriksaan: ukuran maksimal 3 MB, isi file benar-benar gambar (finfo + getimagesize),
 * hanya JPEG/PNG/WebP, dimensi 200–5000 px. Tipe konten diambil dari isi file, bukan nama file.
 */
final class MenuPhoto
{
    public const MAX_BYTES = 3 * 1024 * 1024;
    private const TYPES = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
    private const MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    /** Apakah form membawa file? (tidak memilih file = foto lama dipertahankan) */
    public static function present(mixed $file): bool
    {
        return is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    }

    /** @return string|null pesan error, atau null jika file aman disimpan */
    public static function check(array $file): ?string
    {
        $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
            return 'Foto terlalu besar. Maksimal 3 MB.';
        }
        if ($err !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
            return 'Foto gagal diunggah. Coba pilih file lagi.';
        }
        if ((int) $file['size'] > self::MAX_BYTES) {
            return 'Foto terlalu besar. Maksimal 3 MB.';
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $info = @getimagesize($file['tmp_name']);
        if (!in_array($mime, self::MIMES, true) || $info === false || !isset(self::TYPES[$info[2]])) {
            return 'File harus berupa foto JPG, PNG, atau WebP.';
        }
        if ($info[0] < 200 || $info[1] < 200 || $info[0] > 5000 || $info[1] > 5000) {
            return 'Ukuran foto harus antara 200 dan 5000 piksel di setiap sisi.';
        }
        return null;
    }

    /**
     * Baca isi foto yang sudah lolos check(). @return array{mime:string, bytes:string}
     * Disimpan di Redis lewat DataService::setMenuPhoto (bukan folder), jadi tetap ada di Vercel.
     */
    public static function read(array $file): array
    {
        $info = getimagesize($file['tmp_name']);
        $mime = image_type_to_mime_type($info[2]);
        $bytes = (string) file_get_contents($file['tmp_name']);
        return ['mime' => $mime, 'bytes' => $bytes];
    }
}
