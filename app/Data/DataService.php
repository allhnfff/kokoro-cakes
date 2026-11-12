<?php

declare(strict_types=1);

namespace App\Data;

/**
 * SATU ANTARMUKA LAYANAN DATA untuk website Kokoro Cakes.
 * Controller dan Domain hanya bicara lewat antarmuka ini, tidak langsung ke Redis.
 * Implementasinya: RedisLocalAdapter. Bentuk data lengkap: docs/KONTRAK-DATA.md
 *
 * Aturan umum:
 *   - Gangguan koneksi  -> DataServiceUnavailable (JANGAN dianggap kosong / sukses).
 *   - Penolakan aturan  -> DomainRejected (kode error, pesan, detail).
 *   - $access untuk pesanan:
 *       ['type' => 'customer', 'owner_hash' => '<sha256>']   pelanggan, hanya pesanannya sendiri
 *       ['type' => 'kasir']                                   kasir, semua pesanan
 *   - $actor untuk tindakan: string pendek, misalnya 'kasir:kasir@kokoro.test' atau 'pelanggan'.
 */
interface DataService
{
    /** Info sumber data untuk ditampilkan (tanpa akses jaringan). @return array{driver:string,label:string} */
    public function describe(): array;

    /** Cek koneksi. Tidak melempar exception. @return array{ok:bool,detail:string,seeded?:bool} */
    public function health(): array;

    // ---------------- Menu & kategori ----------------

    /** @return list<array{id:string,nama_kategori:string,nama_jp:string,urutan:int}> urut berdasarkan urutan */
    public function listCategories(): array;

    /** @return list<array<string,mixed>> semua menu, urut kategori lalu nomor menu */
    public function listMenu(): array;

    /** @return array<string,mixed>|null */
    public function getMenu(string $menuId): ?array;

    /** Ubah ketersediaan menu (Tersedia/Habis). @return 'updated'|'unchanged' */
    public function setMenuStatus(string $menuId, string $status): string;

    /**
     * Admin menambah menu. Id dibuat otomatis (m + angka berikutnya, tidak memakai ulang id terhapus).
     * @param array{id_kategori:string,nama_menu:string,nama_jp:string,harga:int,deskripsi:string,status:string,pasangan:string,pasangan_alasan:string} $fields
     * @return string id menu baru
     */
    public function createMenu(array $fields): string;

    /** Admin mengubah menu. @param array<string,string|int> $fields sama seperti createMenu */
    public function updateMenu(string $menuId, array $fields): void;

    /** Admin menghapus menu (pesanan lama tetap utuh; foto unggahan ikut dihapus). */
    public function deleteMenu(string $menuId): void;

    /** Simpan foto unggahan admin di Redis. @return string alamat foto (/foto/menu/{id}/{versi}) */
    public function setMenuPhoto(string $menuId, string $mime, string $bytes): string;

    /** Hapus foto menu (juga menyembunyikan foto bawaan berbentuk file). */
    public function removeMenuPhoto(string $menuId): void;

    /** @return array{tipe:string, data:string}|null foto unggahan jika versinya cocok */
    public function getMenuPhoto(string $menuId, string $version): ?array;

    /** Tambah akun staf (username untuk login; bawaan: bagian email sebelum @). @return string id staf */
    public function createStaff(string $nama, string $email, string $passwordHash, string $role, string $username = ''): string;

    /** @return array{id:string,nama:string,username:string,email:string,password_hash:string,role:string,status:string}|null */
    public function findStaffByUsername(string $username): ?array;

    /** Cari staf dari isian login: username, atau email bila berisi '@'. */
    public function findStaffByLogin(string $login): ?array;

    /** Ganti username login staf. @return 'updated'|'unchanged' */
    public function setStaffUsername(string $staffId, string $username): string;

    /** Id menu "pilihan dapur hari ini" (key pilihan:hari-ini, kosong lagi tengah malam). @return list<string> */
    public function listTodayPicks(): array;

    /**
     * Tambah/hapus pilihan dapur hari ini (maksimal $limit menu, hanya menu Tersedia).
     * @return 'added'|'removed'|'unchanged'
     */
    public function setTodayPick(string $menuId, bool $on, int $limit, int $expireAt): string;

    // ---------------- Meja ----------------

    /** @return list<array<string,mixed>> semua meja, urut kode */
    public function listTables(): array;

    /** @return array<string,mixed>|null */
    public function getTable(string $code): ?array;

    /** Kosongkan meja setelah tamu pergi (hanya jika tidak ada pesanan aktif); sesi tamu lama berakhir. @return 'released'|'unchanged' */
    public function releaseTable(string $code): string;

    /** Ganti QR meja (qr_versi + 1). QR lama tidak berlaku lagi. @return int versi baru */
    public function rotateTableQr(string $code): int;

    // ---------------- Keranjang per meja (key wajib cart:{kode_meja}) ----------------

    /** @return list<array{id_menu:string, qty:int}> */
    public function getCart(string $tableCode): array;

    /** Set jumlah (bukan menambah). */
    public function setCartItem(string $tableCode, string $menuId, int $qty): void;

    public function removeCartItem(string $tableCode, string $menuId): void;

    // ---------------- Pesanan ----------------

    /**
     * Checkout atomik + idempoten.
     * @param array{
     *   request_id:string, kode_meja:string, owner_hash:string,
     *   nama_pemesan:string, catatan:string, metode_bayar:string,
     *   items: list<array{id_menu:string, qty:int}>
     * } $request   (TIDAK berisi harga: harga, pajak, dan total dihitung di Redis)
     * @return array{status:'created'|'duplicate', order: array<string,mixed>}
     */
    public function checkout(array $request): array;

    /** Cari hasil checkout lama berdasarkan request_id. @return array{status:string, order:array<string,mixed>}|null */
    public function getCheckoutResult(string $requestId, array $access): ?array;

    /** @return array<string,mixed>|null null jika tidak ada ATAU tidak berhak */
    public function getOrder(int $orderId, array $access): ?array;

    /**
     * @param array{status?:string, meja?:string, q?:string, limit?:int} $filter
     * @return list<array<string,mixed>> header pesanan terbaru dulu, tanpa item
     */
    public function listOrders(array $access, array $filter = []): array;

    /** table_idle: meja tidak punya pesanan aktif lagi (tetap Terisi sampai dikosongkan staf). @return array{status:'updated'|'unchanged', table_idle:bool} */
    public function updateOrderStatus(int $orderId, string $from, string $to, string $actor): array;

    /** @return 'paid'|'already_paid' */
    public function confirmPayment(int $orderId, string $actor): string;

    /** @return array{status:'cancelled'|'already_cancelled', table_idle:bool} */
    public function cancelOrder(int $orderId, string $reason, string $actor): array;

    /**
     * Batalkan otomatis pesanan "bayar di kasir" yang lewat batas bayar dan masih Belum Bayar
     * (antrean idx:pesanan:belum-bayar). Aman dipanggil sering. @return int jumlah yang dibatalkan
     */
    public function expireUnpaidOrders(int $now, string $reason): int;

    /**
     * Rekap pesanan SELESAI berdasarkan waktu selesai dalam rentang [from, to).
     * @return array{from:int, to:int, orders:int, items:int, subtotal:int, pajak:int, total:int,
     *               menu: list<array{id_menu:string,nama_menu:string,jumlah:int,pendapatan:int}>}
     */
    public function getSalesSummary(int $fromTs, int $toTs): array;

    // ---------------- Staf ----------------

    /** @return array{id:string,nama:string,email:string,password_hash:string,role:string,status:string}|null */
    public function findStaffByEmail(string $email): ?array;

    public function setStaffPasswordHash(string $staffId, string $hash): void;
}
