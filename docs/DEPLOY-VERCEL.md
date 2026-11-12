# Deploy online: Vercel + Upstash Redis (gratis)

Alamat online: **https://kokoro-cakes.vercel.app**. Bisa dibuka dari jaringan mana pun, 24 jam, tanpa laptop menyala.

| Bagian | Layanan | Paket |
|---|---|---|
| Web (PHP 8.4, FrankenPHP) | Vercel **Container Runtime** (Beta), region Singapura `sin1` | Hobby (gratis, non-komersial) |
| Database | Upstash Redis, region Singapura, koneksi TLS | Free: 500.000 perintah/bulan, 256 MB |

Versi lokal di laptop tetap memakai `php -S` dan Redis/Memurai lokal seperti biasa.

## File yang dipakai Vercel

- `Dockerfile`: image PHP 8.4 (FrankenPHP), `composer install` dijalankan di dalam image.
- `docker/Caddyfile`: file statis dari `public/`, selain itu ke `public/index.php`.
- `docker/php-kokoro.ini`: batas unggah 4 MB, error ke log, zona waktu WIB.
- `vercel.json`: region `sin1`.
- `.vercelignore`: `.env`, `.env.*`, dan `vendor/` **tidak** ikut diunggah.

## Perbedaan versi online

| Hal | Lokal | Online |
|---|---|---|
| Sesi login & meja | file (`SESSION_DRIVER=file`) | Redis, key `sess:{id}` (`SESSION_DRIVER=redis`), karena folder Vercel tidak permanen |
| Foto unggahan admin | Redis `foto:menu:{id}`, alamat `/foto/menu/{id}/{versi}` | sama (foto bawaan tetap file di `public/assets/img/menu/foto/`) |
| HTTPS | tidak | ya, dikenali lewat `X-Forwarded-Proto` (`TRUST_PROXY=true`), cookie `Secure` |
| Polling | 5 detik | menu 60 detik, pesanan & kasir 15 detik (hemat kuota Upstash) |
| `APP_KEY` | dari `.env` | kunci berbeda (QR online ≠ QR lokal); cetak QR dari halaman kasir online |

## Environment Variables di Vercel (Production)

`APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://kokoro-cakes.vercel.app`, `APP_KEY` (sensitif),
`TRUST_PROXY=true`, `SESSION_DRIVER=redis`, `SESSION_NAME`, `REDIS_SCHEME=tls`, `REDIS_HOST`, `REDIS_PORT`,
`REDIS_USERNAME=default`, `REDIS_PASSWORD` (sensitif), `REDIS_DB=0`, `REDIS_TIMEOUT`, `TAX_PERCENT`,
`ORDER_UNPAID_MINUTES`, `MENU_POLL_MS`, `ORDER_POLL_MS`, `KASIR_POLL_MS`, `STORE_*`.

Nilai rahasia disimpan di file lokal `.env.upstash` (tidak ikut Git/Vercel): `REDIS_URL` (alamat `rediss://` dari Upstash) dan `APP_KEY_ONLINE`.

## Deploy ulang setelah mengubah kode

```powershell
npx vercel login                 # sekali saja
npx vercel deploy --prod
```

Mengubah environment variable: `npx vercel env add NAMA production --force`, lalu deploy ulang.

## Hasil uji online (12 November 2026)

- Uji koneksi Upstash: TLS, `EVAL` + `cjson`, `TYPE`, `ZRANGEBYSCORE`, foto biner 84 KB utuh.
- `php tests/http-e2e.php` ke `https://kokoro-cakes.vercel.app`: **17 lulus, 0 gagal** (pesan, bayar dulu, batal otomatis, pesan lagi, kasir, kosongkan meja, pilihan dapur, kelola menu + unggah foto, ganti & cetak QR).
- Catatan: foto unggahan di-cache CDN Vercel (alamat berversi, cache panjang) agar hemat kuota; setelah dihapus, datanya hilang dari Redis walau salinan cache lama di alamat lama bisa tersisa.

## Batasan

- Container Runtime Vercel masih **Beta**. Paket Hobby hanya untuk pemakaian non-komersial.
- Kuota Upstash gratis 500.000 perintah/bulan. Jika habis, web menampilkan "Server data tidak tersedia" sampai kuota direset bulan berikutnya. Jangan biarkan dashboard kasir terbuka berhari-hari.
- Pembukaan pertama setelah lama sepi sedikit lebih lambat (container dibangunkan).
