# Instalasi Kokoro Cakes

Panduan ini untuk menjalankan website di komputer sendiri (pengembangan lokal).

## 1. Kebutuhan

| Komponen | Versi | Catatan |
|---|---|---|
| PHP | 8.2 atau lebih baru | XAMPP sudah menyertakan PHP. Ekstensi `json`, `mbstring`, `openssl` aktif. |
| Composer | 2.x | Untuk memasang Predis. |
| Redis | 3.0 ke atas | Windows: Redis for Windows 3.0.504, Memurai, atau Redis di WSL. Linux/macOS: `redis-server`. |

Cek cepat di PowerShell:

```powershell
php -v
composer -V
redis-cli ping        # atau: & "C:\Program Files\Memurai\memurai-cli.exe" ping
```

Jawaban `PONG` berarti Redis sudah berjalan. XAMPP tidak menyertakan Redis Server.

## 2. Siapkan proyek

Di folder proyek:

```powershell
composer install
Copy-Item .env.example .env
php scripts/generate-key.php
```

Lalu buka `.env` dan atur:

- `SEED_KASIR_PASSWORD`: password kasir buatan sendiri, 8–72 karakter. Hanya dipakai saat seed; yang disimpan di Redis hanya hash-nya. Sesudah seed boleh dikosongkan lagi.
- `REDIS_HOST`, `REDIS_PORT`, `REDIS_DB`: sesuaikan bila Redis tidak di `127.0.0.1:6379`. Pakai database yang khusus untuk proyek ini.
- `REDIS_TEST_DB`: database untuk tes otomatis (bawaan 15). Harus berbeda dari `REDIS_DB`.
- `TAX_PERCENT`: pajak/biaya layanan dalam persen (bawaan 10, contoh dari wireframe).

Nilai variabel lingkungan sistem mengalahkan isi `.env`.

## 3. Isi data awal

```powershell
php scripts/seed-demo.php
php scripts/check-redis.php
```

Hasil seed pertama: 12 meja, 5 kategori, 39 menu, dan 1 akun kasir (username `kasir`), total 69 key. Seeder:

- hanya berjalan di `APP_ENV=local` atau `testing`;
- memeriksa semua key tujuan sebelum menulis, dan gagal tanpa menimpa apa pun jika ada benturan;
- jika marker `kokoro:seed:v1` sudah ada, seed awal dilewati dan hanya menu/kategori baru dari `config/menu.php` yang ditambahkan (sinkron), jadi password, harga, dan status yang sudah berubah tetap aman;
- tidak pernah menjalankan `FLUSHALL`/`FLUSHDB`.

Akun admin (kelola menu, ganti QR meja) dibuat terpisah: isi `SEED_ADMIN_PASSWORD` di `.env` (8–72 byte, berbeda dari password kasir), lalu jalankan `php scripts/create-admin.php`. Masuk lewat `/kasir/login` dengan username `admin`. Login staf memakai **username** (`SEED_KASIR_USERNAME`, `SEED_ADMIN_USERNAME`); akun lama bisa diberi username dengan `php scripts/set-username.php <email> <username>`. Setelah itu `SEED_ADMIN_PASSWORD` boleh dikosongkan lagi.

Untuk mengulang demo dari nol: `php scripts/reset-demo.php --confirm=RESET-DEMO`, lalu seed lagi. Reset hanya menghapus key berpola milik proyek ini.

## 4. Jalankan website

### Cara A: server bawaan PHP (paling mudah)

```powershell
php -S localhost:8000 -t public public/index.php
```

Buka `http://localhost:8000`. Jika port lain dipakai, samakan `APP_URL` di `.env` (dipakai untuk tautan QR di halaman kasir).

**Supaya bisa dibuka dari HP** (memindai QR meja yang dicetak):

```powershell
php -S 0.0.0.0:8000 -t public public/index.php
```

Lalu buka halaman kasir dari laptop lewat IP Wi-Fi laptop, misalnya `http://192.168.1.14:8000/kasir/meja` (alamat yang terdeteksi tampil di halaman Meja; cek juga dengan `ipconfig`). Saat dibuka lewat IP jaringan lokal, QR otomatis memakai alamat itu. Saat pertama kali, Windows Firewall mungkin bertanya apakah `php.exe` boleh menerima koneksi: izinkan untuk jaringan **Private** saja. HP dan laptop harus tersambung ke Wi-Fi yang sama.

### Cara B: Apache XAMPP

Arahkan DocumentRoot / VirtualHost ke folder `public/`, **bukan** akar proyek, dan pastikan `mod_rewrite` aktif serta `AllowOverride All`. Contoh `httpd-vhosts.conf`:

```apache
<VirtualHost *:8080>
    DocumentRoot "C:/xampp/htdocs/kokoro-cakes/public"
    <Directory "C:/xampp/htdocs/kokoro-cakes/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

`.htaccess` di akar proyek menolak semua akses sebagai lapisan tambahan bila document root salah arah.

## 5. Mencoba alur

1. Masuk ke `/kasir/login` → **Meja** → klik **Buka** di baris meja 1A. Tab baru terbuka sebagai tamu meja 1A (sama dengan scan QR meja 1A). Beranda pelanggan sengaja tidak punya pintas pilih meja agar terlihat seperti kafe sungguhan.
2. Tambah menu → Keranjang → Checkout → isi nama → pilih QRIS (simulasi) atau Bayar di kasir → Kirim pesanan.
3. Buka tab lain ke `/kasir/login`, masuk sebagai kasir, lalu proses pesanan: (Terima bayar bila bayar di kasir) → Mulai proses → Siap diantar → Selesai. Pesanan bayar di kasir tidak bisa diproses sebelum dibayar, dan dibatalkan otomatis jika belum dibayar dalam `ORDER_UNPAID_MINUTES` menit (bawaan 15). Halaman pesanan pelanggan ikut berubah otomatis.
4. `/kasir/meja` menampilkan tautan QR tiap meja. Ubah tautan itu menjadi kode QR dengan pembuat QR mana pun untuk dicetak, atau buka dari HP untuk mencoba sebagai tamu.

Lupa password kasir: `php scripts/set-kasir-password.php`.

Foto menu: buat dengan prompt di [PROMPT-GAMBAR-MENU.md](PROMPT-GAMBAR-MENU.md), simpan sebagai `public/assets/img/menu/foto/m1.jpg`, `m2.jpg`, dan seterusnya. Tidak perlu mengubah Redis.

## 6. Redis untuk Linux/macOS (opsional)

```bash
mkdir -p redis/data
redis-server redis/redis-dev.conf
```

`redis-dev.conf` mengikat Redis ke 127.0.0.1, mengaktifkan AOF (`everysec`) dan snapshot RDB, serta memakai `noeviction` agar data pesanan tidak dibuang saat memori penuh. AOF `everysec` bisa kehilangan sekitar satu detik perubahan saat gangguan tertentu; ini tidak menggantikan backup.

## 7. Masalah umum

| Gejala | Penyebab dan solusi |
|---|---|
| `Folder vendor/ belum ada` | Jalankan `composer install`. |
| Halaman 503 "Server data tidak tersedia" | Redis belum berjalan atau `REDIS_HOST/PORT` salah. Cek `php scripts/check-redis.php`. |
| `Kode QR meja tidak valid` | `APP_KEY` berubah sejak tautan dibuat, atau tautan terpotong. Ambil tautan baru di `/kasir/meja`. |
| Kolom Tautan QR di `/kasir/meja` kosong | `APP_KEY` belum diatur. Jalankan `php scripts/generate-key.php`. |
| `SEED_KASIR_PASSWORD wajib berisi 8–72 byte` | Isi password di `.env` sebelum seed. |
| `Seeder ditolak Redis (SEED_KEY_CONFLICT)` | Database sudah berisi key dengan nama yang sama dari aplikasi lain. Pakai `REDIS_DB` lain yang kosong. |
| Login kasir "Terlalu banyak percobaan" | Lima kali salah; tunggu 60 detik. |
