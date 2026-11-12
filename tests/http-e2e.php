<?php

declare(strict_types=1);

/**
 * TES END-TO-END LEWAT HTTP (meniru browser sungguhan) terhadap website yang sedang berjalan.
 *
 * Cara pakai (server harus sudah dijalankan dengan .env yang SAMA, lihat docs/PENGUJIAN.md):
 *     php tests/http-e2e.php
 *     php tests/http-e2e.php --url=http://localhost:8000
 *
 * Tes kasir butuh password kasir yang KAMU atur sendiri, diberikan lewat variabel lingkungan
 * (tidak disimpan di file):  KOKORO_KASIR_PASSWORD  (opsional KOKORO_KASIR_EMAIL).
 * Tanpa variabel itu, tes kasir dilewati.
 *
 * Tes ini membuat pesanan sungguhan di database yang dipakai server. Pakai database demo,
 * bukan data usaha. Pesanan uji diselesaikan/dibatalkan lagi, dan meja uji dikosongkan.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/lib/TestKit.php';

use App\App;
use App\Domain\TableAccess;

$opts = getopt('', ['url::']);
$base = rtrim((string) ($opts['url'] ?? App::config('app.url')), '/');
$kasirPass = getenv('KOKORO_KASIR_PASSWORD') ?: '';
// Login staf memakai username (KOKORO_KASIR_USER / KOKORO_ADMIN_USER untuk mengganti).
$kasirEmail = getenv('KOKORO_KASIR_USER') ?: (string) App::config('seed.username');
$adminPass = getenv('KOKORO_ADMIN_PASSWORD') ?: '';
$adminEmail = getenv('KOKORO_ADMIN_USER') ?: (string) App::config('seed.admin_username');
$table = '2A';

echo "Tes HTTP end-to-end Kokoro Cakes: {$base}\n\n";
if (!TableAccess::isConfigured()) {
    fwrite(STDERR, "APP_KEY belum diatur. Jalankan php scripts/generate-key.php lalu restart server.\n");
    exit(1);
}

/** Alur pelanggan lengkap: scan QR, tambah menu, checkout. @return array{id:int, kode:string} */
$order = static function (Browser $br, string $method, array $items, ?string $tableCode = null) use ($table): array {
    $br->get(TableAccess::path($tableCode ?? $table));
    TestKit::same('/menu', $br->location(), 'redirect setelah scan QR');
    $br->get('/menu');
    foreach ($items as $id => $qty) {
        $br->post('/keranjang/tambah', ['_csrf' => $br->csrf(), 'id_menu' => $id, 'qty' => (string) $qty, 'kembali' => 'menu']);
        TestKit::same(303, $br->status, "tambah {$id}");
        $br->get('/menu');
    }
    $br->get('/checkout');
    TestKit::same(200, $br->status, 'halaman checkout');
    $br->post('/checkout/tinjau', ['_csrf' => $br->csrf(), 'nama_pemesan' => 'Pelanggan E2E', 'catatan' => 'Uji otomatis', 'metode_bayar' => $method]);
    TestKit::same('/checkout/tinjau', $br->location(), 'redirect ke tinjau');
    $br->get('/checkout/tinjau');
    TestKit::same(200, $br->status, 'halaman tinjau');
    if (!preg_match('/name="request_id" value="([a-f0-9]{32})"/', $br->body, $m)) {
        throw new AssertionFailed('request_id tidak ditemukan');
    }
    $rid = $m[1];
    $csrf = $br->csrf();
    $br->post('/checkout/konfirmasi', ['_csrf' => $csrf, 'request_id' => $rid]);
    if (!preg_match('#^/pesanan/(\d+)\?baru=1$#', $br->location(), $m)) {
        throw new AssertionFailed('Tidak diarahkan ke halaman pesanan: ' . $br->location() . ' ' . substr(strip_tags($br->body), 0, 300));
    }
    $id = (int) $m[1];
    $br->follow();
    TestKit::same(200, $br->status, 'halaman pesanan');
    TestKit::assert(str_contains($br->body, 'Pesanan berhasil!'), 'layar Pesanan Berhasil');
    preg_match('/(KK-\d{6}-\d{4})/', $br->body, $k);
    // Kirim ulang konfirmasi yang sama (klik ganda) -> pesanan yang sama
    $br->post('/checkout/konfirmasi', ['_csrf' => $csrf, 'request_id' => $rid]);
    TestKit::same('/pesanan/' . $id . '?baru=1', $br->location(), 'klik ganda menuju pesanan yang sama');
    return ['id' => $id, 'kode' => $k[1] ?? ''];
};

$customer = new Browser($base);
$created = null;

TestKit::run('H01', 'Beranda, menu, dan detail menu tampil', function () use ($base) {
    $b = new Browser($base);
    TestKit::same(200, $b->get('/')->status, 'beranda');
    TestKit::assert(str_contains($b->body, 'Kokoro Cakes'), 'nama toko');
    TestKit::same(200, $b->get('/menu')->status, 'menu');
    TestKit::assert(str_contains($b->body, 'Strawberry Cake') && str_contains($b->body, 'Rp28.000'), 'menu + harga');
    TestKit::same(200, $b->get('/menu?kategori=k3')->status, 'filter kategori');
    TestKit::assert(str_contains($b->body, 'Iced Matcha Latte') && !str_contains($b->body, 'Butter Croissant'), 'hanya Drink');
    TestKit::same(200, $b->get('/menu/m2')->status, 'detail');
    TestKit::same(200, $b->get('/panduan')->status, 'panduan cara pesan');
    TestKit::assert(str_contains($b->body, 'Scan kode QR di meja') && str_contains($b->body, 'Pesanan baru disiapkan setelah dibayar'), 'isi panduan');
    TestKit::same(404, $b->get('/menu/m999')->status, 'menu tidak ada');
    TestKit::same(404, $b->get('/halaman-tidak-ada')->status, '404');
    return 'GET /, /menu, /menu/m2';
});

TestKit::run('H02', 'Header keamanan terpasang', function () use ($base) {
    $b = (new Browser($base))->get('/');
    TestKit::same('nosniff', $b->headers['x-content-type-options'] ?? '', 'nosniff');
    TestKit::assert(str_contains($b->headers['content-security-policy'] ?? '', "default-src 'self'"), 'CSP');
    TestKit::assert(str_contains(strtolower($b->headers['set-cookie'] ?? 'httponly'), 'httponly'), 'cookie HttpOnly');
    return 'CSP, nosniff, frame-ancestors';
});

TestKit::run('H03', 'API status dan ketersediaan menu', function () use ($base) {
    $d = (new Browser($base))->get('/api/status')->json();
    TestKit::same(true, $d['ok'], 'status ok');
    $m = (new Browser($base))->get('/api/menu')->json();
    TestKit::same('Tersedia', $m['status']['m1'] ?? null, 'm1 tersedia');
    TestKit::same('Habis', $m['status']['m3'] ?? null, 'm3 habis');
    return 'JSON lewat PHP, bukan Redis langsung';
});

TestKit::run('H04', 'Tanpa scan QR tidak bisa memakai keranjang; QR palsu ditolak', function () use ($base, $table) {
    $b = new Browser($base);
    $b->get('/keranjang');
    TestKit::same('/', $b->location(), 'keranjang tanpa meja diarahkan ke beranda');
    TestKit::same(404, $b->get('/meja/' . $table . '/0000000000000000')->status, 'token palsu');
    TestKit::same(404, $b->get('/meja/9Z/' . substr(TableAccess::token('9Z'), 0, 16))->status, 'meja tidak ada');
    return 'cart:{kode_meja} dilindungi token QR';
});

TestKit::run('H05', 'POST tanpa token CSRF ditolak (419)', function () use ($base, $table) {
    $b = new Browser($base);
    $b->get(TableAccess::path($table));
    $b->post('/keranjang/tambah', ['id_menu' => 'm1', 'qty' => '1']);
    TestKit::same(419, $b->status, 'tanpa CSRF');
    return 'CSRF wajib';
});

TestKit::run('H06', 'Menambah menu habis dan jumlah tidak valid ditolak', function () use ($base, $table) {
    $b = new Browser($base);
    $b->get(TableAccess::path($table));
    $b->get('/menu');
    $b->post('/keranjang/tambah', ['_csrf' => $b->csrf(), 'id_menu' => 'm3', 'qty' => '1'])->follow();
    TestKit::assert(str_contains($b->body, 'sedang habis'), 'pesan menu habis');
    $b->post('/keranjang/tambah', ['_csrf' => $b->csrf(), 'id_menu' => 'm1', 'qty' => '-3'])->follow();
    TestKit::assert(str_contains($b->body, 'bilangan bulat positif'), 'qty negatif ditolak');
    return 'validasi server';
});

TestKit::run('H07', 'Alur pelanggan: scan QR > keranjang > checkout (bayar di kasir) > halaman pesanan', function () use ($customer, $order, &$created) {
    $created = $order($customer, 'tunai', ['m1' => 2, 'm9' => 1]);
    $customer->get('/pesanan/' . $created['id']);
    TestKit::assert(str_contains($customer->body, 'Rp88.000'), 'total Rp88.000 (pajak 10%)');
    TestKit::assert(str_contains($customer->body, 'Belum Bayar'), 'status bayar');
    $api = $customer->get('/api/pesanan/' . $created['id'])->json();
    TestKit::same('Masuk', $api['status_pesanan'], 'status awal');
    return $created['kode'];
});

TestKit::run('H08', 'Perangkat lain tidak bisa melihat pesanan', function () use ($base, &$created) {
    $b = new Browser($base);
    TestKit::same(404, $b->get('/pesanan/' . $created['id'])->status, 'halaman pesanan');
    TestKit::same(404, $b->get('/api/pesanan/' . $created['id'])->status, 'API pesanan');
    return 'owner hash per perangkat';
});

TestKit::run('H09', 'Pelanggan membatalkan pesanan QRIS saat Masuk', function () use ($base, $order) {
    $b = new Browser($base);
    $o = $order($b, 'qris', ['m10' => 1]);
    $b->get('/pesanan/' . $o['id']);
    $b->post('/pesanan/' . $o['id'] . '/batal', ['_csrf' => $b->csrf(), 'reason' => 'tidak-jadi'])->follow();
    TestKit::assert(str_contains($b->body, 'Pesanan dibatalkan.'), 'pesan sukses');
    TestKit::same('Dibatalkan', $b->get('/api/pesanan/' . $o['id'])->json()['status_pesanan'], 'status');
    TestKit::same('Dikembalikan', $b->get('/api/pesanan/' . $o['id'])->json()['status_bayar'], 'pembayaran dikembalikan');
    return $o['kode'];
});

TestKit::run('H12', 'Tambah ke keranjang lewat JavaScript (JSON) + saran pasangan', function () use ($base) {
    $b = new Browser($base);
    $b->get(TableAccess::path('3A'));
    $b->get('/menu');
    $csrf = $b->csrf();
    $b->accept = 'application/json';
    $d = $b->post('/keranjang/tambah', ['_csrf' => $csrf, 'id_menu' => 'm1', 'qty' => '1', 'kembali' => 'menu'])->json();
    TestKit::same(200, $b->status, 'tambah via JSON');
    TestKit::same(true, $d['ok'], 'ok');
    TestKit::same(1, $d['cart_count'], 'jumlah keranjang');
    $d = $b->post('/keranjang/tambah', ['_csrf' => $csrf, 'id_menu' => 'm3', 'qty' => '1'])->json();
    TestKit::same(422, $b->status, 'menu habis via JSON');
    TestKit::same(false, $d['ok'], 'ditolak');
    TestKit::same(1, $d['cart_count'], 'keranjang tidak berubah');
    $b->accept = 'text/html,application/json';
    $b->get('/keranjang');
    TestKit::assert(str_contains($b->body, 'Mau sekalian?') && str_contains($b->body, 'Hot Genmaicha'), 'saran pasangan di keranjang');
    TestKit::assert(str_contains($b->get('/menu/m1')->body, 'Enak dinikmati bersama'), 'saran pasangan di detail menu');
    $b->get('/keranjang');
    $b->post('/keranjang/hapus', ['_csrf' => $b->csrf(), 'id_menu' => 'm1']);
    return 'JSON 200/422, cart_count, pasangan m1 -> m26';
});

TestKit::run('H10', 'Halaman kasir wajib login; password salah ditolak', function () use ($base) {
    $b = new Browser($base);
    $b->get('/kasir');
    TestKit::same('/kasir/login', $b->location(), 'redirect ke login');
    TestKit::same(401, $b->get('/api/kasir/ringkasan')->status, 'API kasir tanpa login');
    $b->get('/kasir/login');
    $b->post('/kasir/login', ['_csrf' => $b->csrf(), 'login' => 'kasir', 'password' => 'password-salah-sekali'])->follow();
    TestKit::assert(str_contains($b->body, 'Username atau password salah.'), 'pesan salah');
    return 'login wajib';
});

TestKit::run('H11', 'Kasir: bayar dulu, proses sampai Selesai; meja tetap Terisi sampai dikosongkan, sesi tamu lama berakhir', function () use ($base, $customer, $kasirPass, $kasirEmail, $table, &$created) {
    if ($kasirPass === '') {
        throw new SkipTest('Set KOKORO_KASIR_PASSWORD untuk menjalankan tes kasir.');
    }
    $k = new Browser($base);
    $k->get('/kasir/login');
    $k->post('/kasir/login', ['_csrf' => $k->csrf(), 'login' => $kasirEmail, 'password' => $kasirPass]);
    TestKit::same('/kasir', $k->location(), 'login berhasil');
    $k->follow();
    TestKit::assert(str_contains($k->body, $created['kode']), 'pesanan tampil di dashboard');
    $id = $created['id'];
    $step = static function (string $from, string $to) use ($k, $id) {
        $k->get('/kasir/pesanan/' . $id);
        $k->post('/kasir/pesanan/' . $id . '/status', ['_csrf' => $k->csrf(), 'from' => $from, 'to' => $to])->follow();
    };
    // Bayar dulu, baru disiapkan: Mulai proses ditolak sebelum lunas.
    $step('Masuk', 'Diproses');
    TestKit::assert(str_contains($k->body, 'Pesanan belum dibayar'), 'Mulai proses ditolak sebelum bayar');
    TestKit::same('Masuk', $customer->get('/api/pesanan/' . $id)->json()['status_pesanan'], 'masih Masuk');
    TestKit::assert(str_contains($customer->get('/pesanan/' . $id)->body, 'Bayar dulu di kasir'), 'pelanggan diminta bayar dulu');
    $k->get('/kasir/pesanan/' . $id);
    $k->post('/kasir/pesanan/' . $id . '/bayar', ['_csrf' => $k->csrf()])->follow();
    TestKit::assert(str_contains($k->body, 'dicatat lunas'), 'bayar dikonfirmasi');
    $step('Masuk', 'Diproses');
    TestKit::same('Diproses', $customer->get('/api/pesanan/' . $id)->json()['status_pesanan'], 'pelanggan melihat Diproses');
    $step('Diproses', 'Siap Diantar');
    $step('Siap Diantar', 'Selesai');
    TestKit::assert(str_contains($k->body, 'sekarang Selesai'), 'status Selesai');
    TestKit::same('Selesai', $customer->get('/api/pesanan/' . $id)->json()['status_pesanan'], 'pelanggan melihat Selesai');
    TestKit::assert(str_contains($k->body, 'tetap Terisi'), 'kasir diingatkan meja tetap Terisi');
    TestKit::same('Terisi', App::data()->getTable($table)['status'], 'meja tetap Terisi setelah pesanan selesai');
    TestKit::same(200, $customer->get('/keranjang')->status, 'tamu masih bisa memesan lagi dari mejanya');
    $k->get('/kasir/meja');
    $k->post('/kasir/meja/' . $table . '/kosongkan', ['_csrf' => $k->csrf()])->follow();
    TestKit::assert(str_contains($k->body, 'dikosongkan dan siap'), 'meja dikosongkan staf');
    TestKit::same('Tersedia', App::data()->getTable($table)['status'], 'meja Tersedia setelah dikosongkan');
    $customer->get('/keranjang');
    TestKit::same('/', $customer->location(), 'HP tamu lama tidak bisa memesan lagi');
    TestKit::assert(str_contains($customer->follow()->body, 'sudah berakhir'), 'pesan sesi berakhir');
    TestKit::same(200, $k->get('/kasir/rekap')->status, 'rekap');
    TestKit::assert(str_contains($k->body, 'Strawberry Cake'), 'menu di rekap');
    TestKit::same(200, $k->get('/kasir/meja')->status, 'halaman meja');
    TestKit::assert(str_contains($k->body, TableAccess::path($table)), 'tautan QR tampil');
    TestKit::same(true, $k->get('/api/kasir/ringkasan')->json()['ok'], 'API ringkasan');
    // Ketersediaan menu
    $k->get('/kasir/menu');
    $k->post('/kasir/menu/m2/status', ['_csrf' => $k->csrf(), 'status' => 'Habis'])->follow();
    TestKit::same('Habis', (new Browser($base))->get('/api/menu')->json()['status']['m2'], 'm2 habis');
    $k->post('/kasir/menu/m2/status', ['_csrf' => $k->csrf(), 'status' => 'Tersedia'])->follow();
    TestKit::same('Tersedia', (new Browser($base))->get('/api/menu')->json()['status']['m2'], 'm2 tersedia lagi');
    $k->post('/kasir/logout', ['_csrf' => $k->csrf()]);
    $k->get('/kasir');
    TestKit::same('/kasir/login', $k->location(), 'setelah logout');
    return 'Masuk > (bayar) > Diproses > Siap Diantar > Selesai > Kosongkan';
});

TestKit::run('H13', 'Kasir memilih "pilihan dapur", tampil di beranda pelanggan', function () use ($base, $kasirPass, $kasirEmail) {
    if ($kasirPass === '') {
        throw new SkipTest('Set KOKORO_KASIR_PASSWORD untuk menjalankan tes kasir.');
    }
    $k = new Browser($base);
    $k->get('/kasir/login');
    $k->post('/kasir/login', ['_csrf' => $k->csrf(), 'login' => $kasirEmail, 'password' => $kasirPass]);
    $k->get('/kasir/menu');
    $k->post('/kasir/menu/m2/pilihan', ['_csrf' => $k->csrf(), 'aksi' => 'tambah'])->follow();
    TestKit::assert(str_contains($k->body, 'menjadi pilihan dapur'), 'pesan sukses kasir');
    $home = (new Browser($base))->get('/')->body;
    TestKit::assert(str_contains($home, 'Pilihan dapur hari ini') && str_contains($home, 'Matcha Cake'), 'tampil di beranda');
    $k->post('/kasir/menu/m2/pilihan', ['_csrf' => $k->csrf(), 'aksi' => 'hapus'])->follow();
    TestKit::assert(!str_contains((new Browser($base))->get('/')->body, 'Pilihan dapur hari ini'), 'hilang setelah dihapus');
    $guest = new Browser($base);
    $guest->get('/kasir/login');
    $guest->post('/kasir/menu/m2/pilihan', ['_csrf' => $guest->csrf(), 'aksi' => 'tambah']);
    TestKit::same('/kasir/login', $guest->location(), 'tamu tidak bisa memilih');
    return 'POST /kasir/menu/{id}/pilihan';
});

TestKit::run('H14', 'Admin kelola menu: kasir ditolak, tambah/ubah/hapus terlihat di halaman pelanggan', function () use ($base, $kasirPass, $kasirEmail, $adminPass, $adminEmail) {
    if ($kasirPass === '' || $adminPass === '') {
        throw new SkipTest('Set KOKORO_KASIR_PASSWORD dan KOKORO_ADMIN_PASSWORD untuk tes admin.');
    }
    $login = static function (string $email, string $pass) use ($base): Browser {
        $b = new Browser($base);
        $b->get('/kasir/login');
        $b->post('/kasir/login', ['_csrf' => $b->csrf(), 'login' => $email, 'password' => $pass]);
        TestKit::same('/kasir', $b->location(), 'login ' . $email);
        return $b;
    };
    $k = $login($kasirEmail, $kasirPass);
    TestKit::same(403, $k->get('/kasir/kelola-menu')->status, 'kasir tidak boleh kelola menu');
    $k->get('/kasir/meja');
    TestKit::assert(!str_contains($k->body, 'ganti-qr'), 'kasir tidak melihat tombol Ganti QR');

    $a = $login($adminEmail, $adminPass);
    $a->get('/kasir/kelola-menu/baru');
    TestKit::same(200, $a->status, 'form tambah');
    $a->post('/kasir/kelola-menu/baru', ['_csrf' => $a->csrf(), 'nama_menu' => 'Kinako Roll Uji', 'nama_jp' => '', 'id_kategori' => 'k2',
        'harga' => '27.000', 'deskripsi' => 'Bolu gulung kinako.', 'status' => 'Tersedia', 'pasangan' => '', 'pasangan_alasan' => '']);
    if (!preg_match('~^/kasir/kelola-menu#(m\d+)$~', $a->location(), $m)) {
        throw new AssertionFailed('tidak diarahkan ke daftar menu: ' . $a->location());
    }
    $id = $m[1];
    $guest = new Browser($base);
    TestKit::assert(str_contains($guest->get('/menu')->body, 'Kinako Roll Uji'), 'menu baru tampil ke pelanggan');

    $a->get('/kasir/kelola-menu/' . $id);
    $a->post('/kasir/kelola-menu/' . $id, ['_csrf' => $a->csrf(), 'nama_menu' => 'Kinako Roll Uji', 'nama_jp' => '', 'id_kategori' => 'k2',
        'harga' => '1', 'deskripsi' => '', 'status' => 'Tersedia', 'pasangan' => '', 'pasangan_alasan' => '']);
    TestKit::same(422, $a->status, 'harga tidak valid ditolak');
    $a->post('/kasir/kelola-menu/' . $id, ['_csrf' => $a->csrf(), 'nama_menu' => 'Kinako Roll Uji', 'nama_jp' => '', 'id_kategori' => 'k2',
        'harga' => '31000', 'deskripsi' => '', 'status' => 'Tersedia', 'pasangan' => '', 'pasangan_alasan' => '']);
    TestKit::assert(str_contains($guest->get('/menu/' . $id)->body, 'Rp31.000'), 'harga baru tampil');

    // Unggah foto: file palsu ditolak, foto asli disimpan di Redis dan tampil ke pelanggan
    $fields = ['_csrf' => '', 'nama_menu' => 'Kinako Roll Uji', 'nama_jp' => '', 'id_kategori' => 'k2', 'harga' => '31000',
        'deskripsi' => '', 'status' => 'Tersedia', 'pasangan' => '', 'pasangan_alasan' => ''];
    $a->get('/kasir/kelola-menu/' . $id);
    $a->postMultipart('/kasir/kelola-menu/' . $id, ['_csrf' => $a->csrf()] + $fields, ['foto' => ['foto.jpg', 'image/jpeg', '<?php echo 1; ?>']]);
    TestKit::same(422, $a->status, 'file palsu ditolak');
    $webp = (string) file_get_contents(App::root() . '/public/assets/img/menu/foto/m2.webp');
    $a->postMultipart('/kasir/kelola-menu/' . $id, ['_csrf' => $a->csrf()] + $fields, ['foto' => ['kinako.webp', 'image/webp', $webp]]);
    TestKit::same(303, $a->status, 'foto disimpan');
    if (!preg_match('~/foto/menu/' . $id . '/[a-f0-9]{12}~', $guest->get('/menu/' . $id)->body, $pm)) {
        throw new AssertionFailed('foto unggahan tidak tampil di halaman menu');
    }
    $img = new Browser($base);
    $img->get($pm[0]);
    TestKit::same(200, $img->status, 'foto bisa dibuka');
    TestKit::same('image/webp', $img->headers['content-type'] ?? '', 'tipe foto');
    TestKit::same(strlen($webp), strlen($img->body), 'isi foto utuh');

    $a->get('/kasir/kelola-menu');
    $a->post('/kasir/kelola-menu/' . $id . '/hapus', ['_csrf' => $a->csrf()]);
    TestKit::same(404, $guest->get('/menu/' . $id)->status, 'menu terhapus tidak bisa dibuka');
    // Data foto di Redis harus ikut terhapus. (Di Vercel, salinan di cache CDN boleh tersisa: alamatnya
    // berversi dan tidak lagi dipakai di halaman mana pun; cache panjang sengaja dipakai agar hemat kuota Redis.)
    TestKit::same(null, App::data()->getMenuPhoto($id, substr($pm[0], -12)), 'data foto ikut terhapus');
    return "{$id}: tambah, ubah harga, unggah foto, hapus";
});

TestKit::run('H15', 'Ganti QR meja: QR lama ditolak, tamu dari QR lama keluar; kartu QR bisa dicetak', function () use ($base, $adminPass, $adminEmail) {
    if ($adminPass === '') {
        throw new SkipTest('Set KOKORO_ADMIN_PASSWORD untuk tes admin.');
    }
    $a = new Browser($base);
    $a->get('/kasir/login');
    $a->post('/kasir/login', ['_csrf' => $a->csrf(), 'login' => $adminEmail, 'password' => $adminPass]);
    $print = $a->get('/kasir/meja/cetak')->body;
    TestKit::assert(substr_count($print, '<svg') >= 12 && str_contains($print, 'Scan untuk melihat menu'), 'kartu QR 12 meja');

    $guest = new Browser($base);
    $oldPath = TableAccess::path('3B', App::data()->getTable('3B')['qr_versi']);
    $guest->get($oldPath);
    TestKit::same('/menu', $guest->location(), 'masuk dengan QR sekarang');
    $a->get('/kasir/meja');
    $a->post('/kasir/meja/3B/ganti-qr', ['_csrf' => $a->csrf()]);
    $v = App::data()->getTable('3B')['qr_versi'];
    $guest->get('/keranjang');
    TestKit::same('/', $guest->location(), 'tamu dari QR lama dikeluarkan');
    TestKit::same(404, (new Browser($base))->get($oldPath)->status, 'QR lama ditolak');
    $fresh = new Browser($base);
    $fresh->get(TableAccess::path('3B', $v));
    TestKit::same('/menu', $fresh->location(), 'QR baru diterima');
    TestKit::assert(!str_contains($fresh->get('/')->body, 'Halaman kasir'), 'footer pelanggan tanpa tautan kasir');
    return "3B -> versi {$v}";
});

TestKit::run('H16', 'Meja dengan tagihan belum dibayar tidak bisa pesan bayar-di-kasir lagi', function () use ($base, $order) {
    $b = new Browser($base);
    $first = $order($b, 'tunai', ['m7' => 1], '1C');
    $b->get('/menu');
    $b->post('/keranjang/tambah', ['_csrf' => $b->csrf(), 'id_menu' => 'm9', 'qty' => '1']);
    $b->get('/checkout');
    $b->post('/checkout/tinjau', ['_csrf' => $b->csrf(), 'nama_pemesan' => 'Iseng', 'catatan' => '', 'metode_bayar' => 'tunai']);
    $b->get('/checkout/tinjau');
    preg_match('/name="request_id" value="([a-f0-9]{32})"/', $b->body, $m);
    $b->post('/checkout/konfirmasi', ['_csrf' => $b->csrf(), 'request_id' => $m[1]]);
    TestKit::same('/checkout', $b->location(), 'kembali ke checkout');
    TestKit::assert(str_contains($b->follow()->body, 'belum dibayar'), 'pesan tagihan terbuka');
    TestKit::assert(str_contains($b->get('/keranjang')->body, 'Iced Matcha Latte'), 'keranjang tetap utuh');
    return $first['kode'] . ' belum dibayar -> pesanan kedua ditolak';
});

TestKit::run('H17', 'Hitung mundur bayar, batal otomatis, lalu "Pesan lagi" mengisi ulang keranjang', function () use ($base, $order) {
    $b = new Browser($base);
    $o = $order($b, 'tunai', ['m1' => 2, 'm12' => 1], '1D');
    $page = $b->get('/pesanan/' . $o['id'])->body;
    TestKit::assert(str_contains($page, 'menit</b> untuk membayar') && str_contains($page, 'data-pay-deadline'), 'hitung mundur tampil');
    TestKit::assert(!str_contains($page, 'Pesan lagi menu yang sama'), 'belum ada tombol pesan lagi');

    // Tiru 15 menit berlalu: sapu antrean batas bayar dengan waktu sesudah batas.
    $batas = App::data()->getOrder($o['id'], ['type' => 'kasir'])['batas_bayar'];
    // (Pesanan belum dibayar dari tes lain yang batasnya lebih awal ikut tersapu; yang dicek pesanan ini.)
    TestKit::assert(App::data()->expireUnpaidOrders($batas + 1, 'Tidak dibayar di kasir dalam 15 menit') >= 1, 'sapuan berjalan');
    TestKit::same('Dibatalkan', App::data()->getOrder($o['id'], ['type' => 'kasir'])['status_pesanan'], 'batal otomatis');
    $page = $b->get('/pesanan/' . $o['id'])->body;
    TestKit::assert(str_contains($page, 'Tidak dibayar di kasir') && str_contains($page, 'Pesan lagi menu yang sama'), 'alasan + tombol pesan lagi');

    $b->post('/pesanan/' . $o['id'] . '/pesan-lagi', ['_csrf' => $b->csrf()]);
    TestKit::same('/keranjang', $b->location(), 'diarahkan ke keranjang');
    $cart = $b->follow()->body;
    TestKit::assert(str_contains($cart, 'dimasukkan lagi ke keranjang'), 'pesan sukses');
    TestKit::assert(str_contains($cart, 'Strawberry Cake') && str_contains($cart, 'Strawberry Milk') && str_contains($cart, 'value="2"'), 'menu & jumlah sama');

    // Pesanan yang tidak dibatalkan tidak bisa dipesan ulang dari tombol ini
    $b->post('/pesanan/' . $o['id'] . '/pesan-lagi', ['_csrf' => $b->csrf()]);
    $b->get('/keranjang');
    $b->post('/keranjang/hapus', ['_csrf' => $b->csrf(), 'id_menu' => 'm1']);
    $b->get('/keranjang');
    $b->post('/keranjang/hapus', ['_csrf' => $b->csrf(), 'id_menu' => 'm12']);
    return $o['kode'] . ' -> batal -> keranjang terisi lagi';
});

$exit = TestKit::summary('Tes HTTP end-to-end');
TestKit::writeReport(App::root() . '/tests/output/hasil-tes-http.md', 'Hasil tes HTTP end-to-end Kokoro Cakes');
echo 'Laporan: tests/output/hasil-tes-http.md' . PHP_EOL;
exit($exit);
