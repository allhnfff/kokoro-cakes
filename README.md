# Kokoro Cakes: pemesanan menu dari meja

Website pemesanan untuk kafe **Kokoro Cakes** (tugas individu). Pelanggan scan QR di meja, memilih cake, wagashi, pastry, dan minuman (39 menu, 5 kategori), lalu mengirim pesanan ke dapur dan memantau statusnya. Kasir mengelola pesanan, pembayaran, ketersediaan menu, dan status meja. Semua data disimpan di **Redis** dan diakses lewat **PHP**.

Struktur proyek mengikuti proyek kelompok Alverse (front controller, Router, Controller, Domain, Data/Redis + Lua). Bedanya, proyek ini berdiri sendiri: tidak ada cabang, stok bersama, atau layanan pusat.

### Fitur per halaman

| Halaman | Isi |
|---|---|
| `/` Beranda | Cara memesan, kategori, pilihan menu. Saat `APP_ENV=local` ada daftar tautan meja sebagai pengganti scan QR fisik. |
| `/meja/{kode}/{token}` | Tautan dari QR meja. Token HMAC mencegah orang menebak kode meja untuk mengubah keranjang meja lain. |
| `/menu`, `/menu/{id}` | Menu per kategori dengan nama Jepang, filter dan pencarian, tombol tambah. Ketersediaan (Tersedia/Habis) diperbarui otomatis tiap 5 detik. |
| `/keranjang` | Keranjang per meja (`cart:{kode_meja}`), dipakai bersama oleh semua tamu di meja itu. |
| `/checkout` → `/checkout/tinjau` | Nama pemesan, catatan, metode bayar (QRIS simulasi / bayar di kasir), lalu tinjau total dengan pajak. |
| `/pesanan`, `/pesanan/{id}` | Status pesanan (Masuk → Diproses → Siap Diantar → Selesai) diperbarui otomatis, rincian harga, riwayat, dan pembatalan saat masih Masuk. |
| `/kasir` | Papan pesanan aktif per status, tombol proses/terima bayar, ringkasan hari ini, dan status meja. |
| `/kasir/pesanan` | Daftar pesanan dengan filter status, meja, dan pencarian; detail, ubah status, konfirmasi bayar, batal. |
| `/kasir/menu` | Ubah ketersediaan menu (Tersedia/Habis). Kasir tidak menambah atau menghapus menu. |
| `/kasir/meja` | Status meja, jumlah pesanan aktif, tautan QR tiap meja, kosongkan meja. |
| `/kasir/rekap` | Rekap pesanan Selesai per rentang tanggal dan menu terlaris. |

**Wireframe final (sesuai web):** [docs/wireframe/Wireframe_Kokoro_Cakes_Final.pdf](docs/wireframe/Wireframe_Kokoro_Cakes_Final.pdf) — halaman 1 pelanggan, halaman 2 kasir & admin (sumber: `docs/wireframe/wireframe.html`).

**Versi online:** https://kokoro-cakes.vercel.app (Vercel Container Runtime + Upstash Redis, gratis). Cara deploy dan perbedaannya dengan versi lokal: [docs/DEPLOY-VERCEL.md](docs/DEPLOY-VERCEL.md).

## 1. Teknologi

- **PHP 8.2+** murni (tanpa framework), autoload PSR-4 Composer.
- **Predis 3** untuk koneksi ke Redis (tidak butuh ekstensi PhpRedis).
- **Redis** sebagai database utama. Sudah diuji di Redis 3.0.504 for Windows (port 6379); juga cocok untuk Redis 7/8 atau Memurai.
- **Lua di Redis** untuk operasi yang harus atomik: checkout, ubah status, pembatalan, konfirmasi bayar, seed.
- HTML + CSS + JavaScript biasa (tanpa library). Tema wa-kafe dengan alasan tiap keputusan di [DESIGN.md](DESIGN.md): Shippori Mincho + Zen Kaku Gothic New, noren こころ di beranda, stempel 売切 untuk menu habis, nama Jepang pada menu dan kategori.
- Logo: ensō + stroberi berbentuk hati (`public/assets/img/logo.svg`, `logo-light.svg` untuk latar gelap, `favicon.svg`). Versi PNG untuk proposal/media sosial ada di `docs/logo/`. Mengganti logo cukup dengan menimpa file-file itu.
- Foto menu opsional di `public/assets/img/menu/foto/{kode}.jpg`; prompt untuk membuatnya di [docs/PROMPT-GAMBAR-MENU.md](docs/PROMPT-GAMBAR-MENU.md). Browser hanya memanggil PHP lewat `fetch`, tidak pernah terhubung ke Redis.

### Staf: kasir dan admin

| Fitur | Kasir | Admin |
|---|---|---|
| Proses pesanan, terima bayar, rekap | ✓ | ✓ |
| Ketersediaan menu (Tersedia/Habis), pilihan dapur hari ini | ✓ | ✓ |
| Lihat, buka, dan **cetak kartu QR meja** (`/kasir/meja/cetak`) | ✓ | ✓ |
| **Kelola menu**: tambah, ubah nama/harga/deskripsi/kategori/pasangan, unggah foto, hapus | | ✓ |
| **Ganti QR meja**: QR lama dan tamu yang masuk lewat QR lama langsung tidak berlaku | | ✓ |

Akun admin dibuat sekali dengan `php scripts/create-admin.php` setelah `SEED_ADMIN_PASSWORD` diisi di `.env`. QR meja memakai `qr_versi` di `meja:{kode}`; QR yang sudah tercetak sebelum fitur ini (versi 1) tetap sah sampai admin menggantinya.

Agar QR bisa dipindai HP di Wi-Fi yang sama, jalankan `php -S 0.0.0.0:8000 -t public public/index.php` lalu buka halaman kasir lewat IP laptop (misalnya `http://192.168.1.14:8000/kasir/meja`). Halaman Meja menampilkan IP yang terdeteksi, dan QR otomatis memakai alamat itu.

### Sentuhan untuk tamu

- **Halaman Cara Pesan** (`/panduan`, tab kelima di navigasi): 6 langkah memesan sesuai aturan web (scan QR, pilih menu, keranjang bersama, bayar dulu, pantau status, pesan lagi) dan tanya-jawab untuk kasus yang membingungkan.
- **Tambah ke keranjang tanpa pindah halaman**: angka keranjang langsung naik dan muncul pesan singkat. Tanpa JavaScript, form biasa tetap berjalan.
- **Saran pasangan kue ↔ minuman** di detail menu dan keranjang (`pasangan` di `config/menu.php`), hanya jika pasangannya sedang Tersedia.
- **Label "Sering dipesan"** dari pesanan Selesai 30 hari terakhir (minimal 3 porsi). Tanpa data penjualan, label tidak muncul.
- **Pesan お待たせしました** saat pesanan siap diantar; ponsel bergetar jika browser mendukung.
- **Pilihan dapur hari ini** (maksimal 3) dipilih kasir di halaman Ketersediaan Menu; tampil di beranda dan sebagai label di kartu menu. Disimpan di `pilihan:hari-ini` dengan `EXPIREAT`, jadi kosong lagi otomatis tengah malam.
- **Indeks kategori bergambar** di beranda: tiga foto kecil dari menu yang namanya disebut.
- **Wi-Fi tamu** di beranda jika `STORE_WIFI_NAME` (dan `STORE_WIFI_PASSWORD`) diisi di `.env`.

## 2. Struktur folder

```text
kokoro-cakes/
├── app/
│   ├── App.php                  Penyimpan konfigurasi + layanan data
│   ├── bootstrap.php            Autoload, baca .env, validasi konfigurasi
│   ├── Controllers/             Shop, Cart, Checkout, Order, Api, Kasir, Admin (kelola menu, ganti QR)
│   ├── Data/                    DataService (antarmuka), RedisLocalAdapter, LuaScripts, RedisDemoSeeder
│   ├── Domain/                  Aturan bisnis: CartService, CheckoutService, OrderRules, Validator,
│   │                            KasirAuth, TableAccess (token QR berversi), MenuCatalog, MenuForm, MenuPhoto
│   ├── Http/                    Router, View, Session, Csrf, SecurityHeaders, HttpException
│   └── Support/                 Env (pembaca .env), helpers.php
├── config/
│   ├── app.php                  Konfigurasi dari .env
│   ├── menu.php                 Data master 5 kategori + 39 menu, termasuk nama Jepang (untuk seeder)
│   └── meja.php                 Data 12 meja (untuk seeder)
├── docs/
│   ├── INSTALASI.md             Langkah instalasi Windows/XAMPP dan Linux
│   ├── KONTRAK-DATA.md          Key Redis, bentuk data, kode error, keputusan desain
│   ├── PROMPT-GAMBAR-MENU.md    Prompt ChatGPT untuk foto 39 menu
│   └── PENGUJIAN.md             Cara menguji + hasil uji
├── public/                      Document root (satu-satunya folder yang boleh dibuka browser)
│   ├── index.php                Front controller + daftar rute
│   ├── .htaccess                Rewrite ke index.php untuk Apache
│   └── assets/                  css/app.css, js/app.js, img/ (logo, ilustrasi, menu/foto/ untuk foto menu)
├── redis/redis-dev.conf         Konfigurasi Redis lokal (bind 127.0.0.1, AOF + RDB, noeviction)
├── scripts/
│   ├── seed-demo.php            Isi data awal; jika sudah pernah, tambahkan menu baru saja
│   ├── check-redis.php          Cek koneksi + ringkasan data
│   ├── generate-key.php         Buat APP_KEY (untuk token QR meja)
│   ├── set-kasir-password.php   Ganti password kasir/admin
│   ├── create-admin.php         Buat akun admin dari SEED_ADMIN_* di .env
│   └── reset-demo.php           Hapus semua data proyek (perlu konfirmasi)
├── tests/
│   ├── run-tests.php            31 tes lapisan data (Redis database 15)
│   ├── http-e2e.php             17 tes lewat HTTP terhadap server yang berjalan
│   ├── checkout-worker.php      Proses anak untuk uji checkout bersamaan
│   └── lib/TestKit.php          Alat bantu tes tanpa PHPUnit
├── views/
│   ├── layout.php               Layout pelanggan (mobile-first, tab bar bawah)
│   ├── shop/                    home, menu, menu-item, cart, checkout, review, order, orders, error
│   ├── partials/                menu-card, status-badge, order-timeline, checkout-steps, cost-summary
│   └── kasir/                   layout, login, dashboard, orders, order, menu, tables, sales, partials/
├── .env.example                 Contoh konfigurasi (salin jadi .env)
├── .htaccess                    Menolak akses jika document root salah diarahkan ke akar proyek
└── composer.json / composer.lock
```

## 3. Menjalankan (ringkas)

Langkah lengkap ada di [docs/INSTALASI.md](docs/INSTALASI.md).

```powershell
composer install
Copy-Item .env.example .env          # jika .env belum ada
php scripts/generate-key.php         # isi APP_KEY
# Edit .env: isi SEED_KASIR_PASSWORD (8–72 karakter) buatan sendiri
php scripts/seed-demo.php            # 12 meja, 5 kategori, 39 menu, 1 kasir
php scripts/check-redis.php          # harus PING -> PONG dan Seed: v1 selesai
php -S localhost:8000 -t public public/index.php
```

Buka `http://localhost:8000`. Untuk memesan seperti tamu, masuk ke halaman kasir → **Meja** → klik **Buka** pada salah satu meja (sama dengan scan QR meja itu). Halaman kasir ada di `http://localhost:8000/kasir` dengan username `kasir` dan password yang kamu isi di `SEED_KASIR_PASSWORD` (admin: username `admin`).

### Menambah menu

Tulis baris baru di `config/menu.php`, lalu jalankan lagi `php scripts/seed-demo.php`. Pada database yang sudah di-seed, perintah ini **menambahkan** kategori/menu yang belum ada dan menyamakan teks menu (nama Jepang, deskripsi, saran pasangan di bagian `pasangan`); harga, status, pesanan, dan password yang sudah ada tidak diubah. Untuk foto, simpan `m{kode}.jpg` di `public/assets/img/menu/foto/`.

## 4. Aturan transaksi

- **Harga dan total selalu dari server.** Browser hanya mengirim kode menu dan jumlah. Subtotal, pajak (`TAX_PERCENT`, bawaan 10%), dan total dihitung ulang di skrip Lua saat checkout.
- **Checkout atomik dan idempoten.** Satu skrip Lua memeriksa meja aktif, semua menu Tersedia, dan isi keranjang sama persis, baru kemudian menulis pesanan, detail, riwayat, indeks, status meja, dan mengosongkan keranjang. Klik ganda memakai `request_id` yang sama sehingga tidak membuat pesanan ganda.
- **Validasi sebelum menulis.** Redis tidak punya rollback otomatis, jadi setiap skrip Lua membaca dan memeriksa semuanya dulu; penulisan hanya terjadi jika semua valid.
- **Snapshot harga.** `pesanan:{id}:detail` menyimpan harga saat transaksi; perubahan harga menu tidak mengubah riwayat.
- **Bayar dulu, baru disiapkan.** Pesanan QRIS langsung lunas dan masuk dapur. Pesanan "bayar di kasir" berstatus Belum Bayar dan **tidak bisa diproses** (Mulai proses ditolak di Lua) sampai kasir menekan Terima bayar. Ini mencegah pesanan iseng yang tidak dibayar saat kafe ramai.
- **Batas bayar dan satu tagihan per meja.** Pesanan bayar di kasir yang belum dibayar dalam `ORDER_UNPAID_MINUTES` (bawaan 15 menit) dibatalkan otomatis lewat antrean `idx:pesanan:belum-bayar` (sorted set, skor = batas bayar). Meja yang masih punya tagihan terbuka (`tagihan_terbuka` di `meja:{kode}`) tidak bisa membuat pesanan bayar di kasir lagi; QRIS tetap bisa.
- **Tamu diingatkan dan bisa memesan ulang.** Halaman pesanan menampilkan hitung mundur waktu bayar; saat sisa 5 menit kotaknya berubah merah ("Sisa 5 menit, segera ke kasir!") dan HP bergetar bila didukung. Pesanan yang batal punya tombol **Pesan lagi menu yang sama** yang mengisi ulang keranjang meja (ketersediaan dan harga dicek ulang).
- **Status penyajian terpisah dari status bayar.** Pembatalan hanya saat Masuk; jika sudah dibayar QRIS, status bayar menjadi Dikembalikan (simulasi).
- **Meja dikosongkan oleh staf.** Meja menjadi Terisi saat pesanan pertama masuk (tercatat `terisi_sejak`) dan **tetap Terisi** walau pesanannya sudah selesai, karena tamu boleh duduk berjam-jam. Meja baru Tersedia saat kasir menekan **Kosongkan** setelah tamu pergi; saat itu keranjang dihapus dan `sesi` meja naik, sehingga HP tamu sebelumnya tidak bisa lagi memesan ke meja itu.
- **Keamanan.** CSRF di semua form POST, sesi HttpOnly + SameSite, regenerasi ID sesi saat login kasir, batas 5 kali salah login, header CSP/nosniff/anti-iframe, pesanan pelanggan hanya terlihat dari perangkat pemesannya.

## 5. Pengujian

```powershell
php tests/run-tests.php                 # 31 tes lapisan data, memakai Redis database 15
php tests/http-e2e.php                  # 17 tes HTTP; server harus berjalan (tes admin butuh KOKORO_ADMIN_PASSWORD)
```

Detail skenario, cara menjalankan tes kasir, dan hasil uji terakhir: [docs/PENGUJIAN.md](docs/PENGUJIAN.md).

## 6. Batasan

- Pembayaran QRIS hanya simulasi; tidak ada payment gateway atau transaksi uang.
- Foto 39 menu dan foto beranda adalah gambar hasil AI (WebP di `public/assets/img/`), ditandai sebagai ilustrasi penyajian di halaman detail. Menu baru tanpa foto tampil sebagai kartu teks.
- Alamat, jam buka, kapasitas meja, dan tarif pajak adalah data demo.
- Kategori dan meja belum bisa ditambah dari halaman admin (diatur di `config/menu.php` dan `config/meja.php`).
- Foto yang diunggah admin disimpan apa adanya (JPG/PNG/WebP, maks. 3 MB) karena ekstensi GD tidak aktif di XAMPP; tidak dikompres otomatis.
