# Pengujian Kokoro Cakes

Ada dua tes otomatis (tanpa PHPUnit) dan beberapa pemeriksaan manual. Hasil tes otomatis juga disimpan sebagai Markdown di `tests/output/` (folder ini tidak masuk Git).

## A. Tes otomatis lapisan data

```powershell
php tests/run-tests.php
```

- Memakai Redis database **khusus tes** (`REDIS_TEST_DB`, bawaan 15), bukan data aplikasi (`REDIS_DB`).
- Database tes hanya dikosongkan jika kosong atau bertanda `kokoro:test:db` (dibuat oleh tes ini). Jika berisi data lain, tes berhenti tanpa menghapus apa pun.
- Pajak dipaku 10% dan APP_KEY tes dibuat sendiri, jadi hasilnya tidak bergantung pada isi `.env`.

| ID | Skenario |
|---|---|
| T01 | Redis menjawab PONG |
| T02 | Seeder menolak password pendek/terlalu panjang, APP_ENV production, email tidak valid, sebelum menulis apa pun |
| T03 | Benturan key membatalkan seed sebelum ada write; data lama utuh |
| T04 | Seed pertama: 12 meja, 5 kategori, 39 menu, 1 kasir = 68 key (dihitung dari config), tanpa TTL, relasi kategori benar |
| T05 | Password kasir tersimpan sebagai hash dan bisa diverifikasi |
| T06 | Seed ulang tidak mengubah status meja, status menu, maupun password |
| T07 | Menu/kategori dibaca dari Redis dengan urutan, nama Jepang, dan harga wireframe |
| T08 | Token QR meja: benar diterima, token meja lain/tebakan/format salah ditolak |
| T09 | Validator menolak qty 0, -1, 1.5, 1e2, teks, >20; kode menu/meja/metode/nomor pesanan aneh |
| T10 | Keranjang per meja: tambah, ubah, hapus; menu habis dan qty berlebih ditolak |
| T11 | Checkout: subtotal 80.000 + pajak 8.000 = 88.000 dihitung server; detail List; meja Terisi; keranjang dihapus |
| T12 | Klik ganda dengan request_id sama tidak membuat pesanan ganda; isi berbeda → IDEMPOTENCY_CONFLICT |
| T13 | Menu habis setelah masuk keranjang → MENU_UNAVAILABLE tanpa write |
| T14 | CART_CHANGED, EMPTY_ORDER, TABLE_UNAVAILABLE |
| T15 | Bayar dulu, baru disiapkan: Mulai proses ditolak `PAYMENT_REQUIRED` sebelum lunas, antrean batas bayar dan tagihan meja dilepas saat dibayar, lalu Diproses → Siap Diantar → Selesai, meja tetap Terisi |
| T16 | Meja dengan dua pesanan: tidak bisa dikosongkan selama ada pesanan berjalan, tetap Terisi setelah pesanan terakhir selesai/batal, Tersedia saat dikosongkan staf, `sesi` meja naik |
| T17 | Pembatalan: alasan wajib, klik ganda aman, QRIS → Dikembalikan, tidak bisa setelah Diproses |
| T18 | Perangkat lain tidak bisa melihat pesanan |
| T19 | Harga di detail pesanan tetap walau harga menu diubah |
| T20 | Kasir mengubah ketersediaan, tidak bisa membuat menu baru; kosongkan meja ikut menghapus keranjang |
| T21 | Rekap hanya menghitung pesanan Selesai |
| T22 | Rumus pajak PHP sama dengan Lua |
| T23 | 5 proses checkout serentak dengan request_id sama → 1 pesanan, 4 duplicate |
| T24 | 5 perangkat checkout serentak dari meja yang sama → 1 pesanan, 4 CART_CHANGED |
| T25 | Sinkron menu ke database versi lama (15 menu): +24 menu, +1 kategori, nama Jepang terisi, deskripsi & pasangan mengikuti config, harga & status yang diubah tetap, sinkron ulang tidak mengubah apa pun |
| T26 | Label "Sering dipesan" kosong tanpa data penjualan dan butuh minimal 3 porsi terjual; saran pasangan melewati menu Habis dan menu yang sudah ada di keranjang |
| T27 | Pilihan dapur: maksimal 3 (`PICK_LIMIT`), menu habis ditolak (`PICK_UNAVAILABLE`), klik ganda aman, TTL sampai tengah malam WIB |
| T28 | Ganti QR meja: versi 1 = rumus lama, versi 2 menolak QR lama, token beda per meja, meja tidak ada ditolak |
| T29 | Admin kelola menu: tambah (m40), pindah kategori, sinkron config tidak menimpa teks admin dan tidak menghidupkan menu terhapus, id terhapus tidak dipakai ulang, validasi form |
| T30 | Akun admin dibuat dengan hash password; email dobel ditolak |
| T31 | Bayar di kasir: pesanan kedua di meja yang sama ditolak `UNPAID_EXISTS` (keranjang utuh, QRIS tetap bisa), batal otomatis setelah batas bayar, pesanan yang sudah lunas tidak ikut dibatalkan |

## B. Tes HTTP end-to-end

Menguji website yang sedang berjalan seperti browser sungguhan (cookie, CSRF, redirect).

```powershell
# Terminal 1
php -S localhost:8000 -t public public/index.php

# Terminal 2 (tes kasir butuh password kasir lewat variabel lingkungan, tidak disimpan di file)
$env:KOKORO_KASIR_PASSWORD = 'password-kasir-kamu'
php tests/http-e2e.php --url=http://localhost:8000
```

Tanpa `KOKORO_KASIR_PASSWORD`, tes H11 dilewati. Tes ini membuat pesanan di database yang dipakai server; pakai data demo, bukan data usaha.

| ID | Skenario |
|---|---|
| H01 | Beranda, menu, filter kategori, detail, 404 |
| H02 | Header keamanan (CSP, nosniff, cookie HttpOnly) |
| H03 | API status dan ketersediaan menu |
| H04 | Keranjang tanpa scan QR dialihkan; token QR palsu dan meja tidak ada → 404 |
| H05 | POST tanpa CSRF → 419 |
| H06 | Menu habis dan qty negatif ditolak dengan pesan |
| H07 | Alur lengkap pelanggan, total Rp88.000, klik ganda menuju pesanan yang sama |
| H08 | Perangkat lain → 404 untuk halaman dan API pesanan |
| H09 | Pelanggan membatalkan pesanan QRIS → Dibatalkan, pembayaran Dikembalikan |
| H10 | Halaman/API kasir wajib login; password salah ditolak |
| H11 | Kasir: Mulai proses ditolak sebelum bayar, Terima bayar, proses sampai Selesai; meja tetap Terisi dan tamu masih bisa memesan; setelah Kosongkan, meja Tersedia dan HP tamu lama diarahkan keluar (sesi berakhir); rekap, QR, ketersediaan menu, logout |
| H12 | Tambah ke keranjang lewat JavaScript: balasan JSON 200 (jumlah keranjang) / 422 (menu habis), saran pasangan tampil di keranjang dan detail menu |
| H13 | Kasir menjadikan menu pilihan dapur → tampil di beranda; dihapus → hilang; tamu tanpa login diarahkan ke login kasir |
| H14 | Kasir ditolak (403) dari Kelola Menu dan tidak melihat Ganti QR; admin menambah, mengubah harga (harga tidak valid ditolak 422), dan menghapus menu, terlihat langsung di halaman pelanggan |
| H15 | Kartu QR 12 meja tercetak sebagai SVG; setelah Ganti QR, QR lama 404 dan tamu yang masuk lewat QR lama dikeluarkan; QR baru diterima; footer pelanggan tanpa tautan kasir |
| H16 | Meja dengan tagihan belum dibayar: pesanan bayar di kasir kedua dikembalikan ke checkout dengan pesan, keranjang tetap utuh |
| H17 | Hitung mundur tampil di pesanan bayar di kasir; setelah lewat batas pesanan batal otomatis dan tombol "Pesan lagi menu yang sama" mengisi ulang keranjang dengan menu dan jumlah yang sama |

## C. Pemeriksaan manual

1. **Redis PONG**: `php scripts/check-redis.php`.
2. **Alur pelanggan di ponsel**: buka website dari ponsel di jaringan yang sama atau mode responsif browser (lebar 360–420 px). Tab bar bawah tampil, kartu menu 2 kolom, tidak ada scroll ke samping.
3. **Polling**: buka halaman pesanan pelanggan dan dashboard kasir berdampingan. Ubah status di kasir; halaman pelanggan berubah dalam ±5 detik dan dashboard menampilkan "Ada perubahan pesanan".
4. **Ketersediaan menu**: tandai menu Habis di `/kasir/menu`; di halaman menu pelanggan tombol Tambah ikut nonaktif tanpa muat ulang.
5. **Redis mati**: hentikan Redis lalu buka beranda → halaman 503 "Server data tidak tersedia", API menjawab 503. Tidak ada data palsu.
6. **Data tetap setelah restart**: buat pesanan, restart Redis, pesanan masih ada (butuh persistensi AOF/RDB aktif).

## D. Hasil uji, 11 November 2026

Lingkungan: Windows 11, PHP 8.2.12 (XAMPP), Predis 3.7.0, Redis 3.0.504 for Windows di 127.0.0.1:6379.

| Pemeriksaan | Hasil |
|---|---|
| Sintaks semua file PHP (`php -l`) | Lulus |
| `composer validate` | Valid |
| `php tests/run-tests.php` (database 15) | **24 lulus, 0 gagal** |
| `php tests/http-e2e.php` (server lokal, database demo terpisah) | **11 lulus, 0 gagal** |
| Checkout bersamaan 5 proses | 1 pesanan; sisanya duplicate / CART_CHANGED |
| Redis tidak dapat dihubungi (port salah) | Beranda 503 dengan pesan jelas, `/api/menu` dan `/api/status` 503, `seed-demo.php` keluar dengan kode 1 |
| Log server selama tes | Tidak ada warning/error PHP |
| Tampilan (screenshot headless Edge) | Beranda desktop, menu dan pesanan di lebar ponsel, tinjau & bayar, dashboard kasir, halaman meja: rapi, tanpa elemen terpotong |

Temuan yang diperbaiki saat pengujian:

- Redis 3.0 belum mendukung `HSET` dengan banyak field. Skrip Lua kini memakai `HMSET` sehingga jalan di Redis 3.x maupun 7.x.
- Predis 3 melempar `StreamInitException` saat Redis mati. Semua gangguan koneksi sekarang diterjemahkan menjadi halaman 503, bukan 500.

### Pembaruan 11 November 2026, malam: tema wa-kafe dan 39 menu

| Pemeriksaan | Hasil |
|---|---|
| `php tests/run-tests.php` | **25 lulus, 0 gagal** (termasuk T25 sinkron menu) |
| `php tests/http-e2e.php` | **11 lulus, 0 gagal** |
| `php scripts/seed-demo.php` pada database 0 yang sudah di-seed | 1 kategori baru, 24 menu baru, 19 field nama Jepang; harga, status, dan password lama tetap |
| Foto menu | File `public/assets/img/menu/foto/m1.png` langsung dipakai; dihapus → kembali ke ilustrasi |
| Tampilan (screenshot) | Beranda desktop/ponsel, menu ponsel, detail menu, pesanan ponsel: rapi, label Jepang tampil dengan font Shippori Mincho |

Belum diuji: Apache XAMPP dengan VirtualHost (hanya server bawaan PHP), Redis dengan TLS/ACL, dan pemulihan AOF/RDB setelah restart.

### Foto menu dan hero, 11 November 2026

| Pemeriksaan | Hasil |
|---|---|
| Isi `Kokoro_Cakes_39_Menu_dan_Hero.zip` | 39 foto menu (m1–m39) + 1 hero; setiap foto dicek lewat lembar kontak dan cocok dengan nama menunya |
| Konversi | PNG 1254 px (86,1 MB) → WebP 800 px kualitas 80; hero 1440×1080. Total 2,26 MB, foto menu 29–88 KB |
| Tampilan | 39 kartu menu memakai foto, hero tampil di beranda, foto juga di detail, keranjang, dan halaman kasir |
| `php tests/http-e2e.php` | 11 lulus, 0 gagal; log server tanpa warning |

### Sentuhan untuk tamu, 12 November 2026

Deskripsi menu baru, tambah ke keranjang tanpa pindah halaman, saran pasangan kue ↔ minuman, label "Sering dipesan" dari pesanan Selesai 30 hari terakhir, pesan お待たせしました saat pesanan siap (ponsel bergetar jika browser mendukung), Wi-Fi tamu opsional.

| Pemeriksaan | Hasil |
|---|---|
| `php tests/run-tests.php` | **26 lulus, 0 gagal** (T25 diperluas, T26 baru) |
| `php tests/http-e2e.php` (database demo terpisah) | **12 lulus, 0 gagal** (H12 baru); log server tanpa warning |
| Tampilan 500 px | Notifikasi "masuk keranjang", saran pasangan di keranjang dan detail, label "Sering dipesan" (hanya setelah ada pesanan Selesai), pesan pesanan siap, Wi-Fi di beranda |
| Sinkron ke database demo | 39 deskripsi diperbarui, 78 field pasangan diisi; harga, status, dan jumlah key (68) tidak berubah |
| Pilihan dapur + indeks bergambar | `run-tests.php` **27 lulus** (T27 baru), `http-e2e.php` **13 lulus** (H13 baru); log server tanpa warning. Tampilan dicek di 500 px (beranda, baris pilihan bisa digeser) dan 1200 px (halaman kasir). |
| Admin, kelola menu, QR berversi, cetak QR | `run-tests.php` **30 lulus** (T28–T30 baru), `http-e2e.php` **15 lulus** (H14–H15 baru); log server tanpa warning. Unggah foto: file PHP berekstensi .jpg ditolak (422), WebP asli tersimpan lalu terhapus bersama menunya. Tampilan kasir/admin dicek di 1280 px. |
| Bayar dulu, baru disiapkan | `run-tests.php` **31 lulus** (T15/T17/T24 disesuaikan, T31 baru), `http-e2e.php` **16 lulus** (H11 disesuaikan, H16 baru); log server tanpa warning. Tampilan: kotak "Bayar dulu di kasir" dengan total dan batas jam di HP, dashboard kasir menampilkan "batal otomatis" dan Mulai proses terkunci. |
| Pengingat bayar + pesan lagi | `http-e2e.php` **17 lulus** (H17 baru), `run-tests.php` 31 lulus; log server tanpa warning. Tampilan 500 px: kotak merah "Sisa 3 menit, segera ke kasir!", halaman pesanan batal dengan tombol Pesan lagi. |
| Meja dikosongkan staf | `run-tests.php` **31 lulus** (T15/T16 disesuaikan), `http-e2e.php` **17 lulus** (H11 diperluas); log server tanpa warning. Halaman Meja menampilkan "Terisi sejak 05.55" dan tombol Kosongkan hanya saat tidak ada pesanan berjalan. |

