<?php

declare(strict_types=1);

/**
 * TES OTOMATIS LAPISAN DATA + ATURAN BISNIS
 *
 *     php tests/run-tests.php
 *
 * - Memakai database Redis KHUSUS TES (REDIS_TEST_DB, default 15), bukan data aplikasi (REDIS_DB).
 * - Database tes hanya dikosongkan jika kosong atau bertanda kokoro:test:db (dibuat oleh tes ini).
 *   Database yang berisi data lain ditolak, sehingga data aplikasi tidak pernah terhapus.
 * - Hasil juga disimpan ke tests/output/hasil-tes-data.md sebagai bukti uji.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/lib/TestKit.php';

use App\App;
use App\Data\DomainRejected;
use App\Data\RedisDemoSeeder;
use App\Data\RedisLocalAdapter;
use App\Domain\CartService;
use App\Domain\CheckoutService;
use App\Domain\MenuCatalog;
use App\Domain\MenuForm;
use App\Domain\TableAccess;
use App\Domain\ValidationError;
use App\Domain\Validator;

const TEST_MARKER = 'kokoro:test:db';

$cfg = App::config('redis');
$testDb = (int) $cfg['test_database'];
if ($testDb === (int) $cfg['database']) {
    fwrite(STDERR, "REDIS_TEST_DB tidak boleh sama dengan REDIS_DB.\n");
    exit(1);
}
// Konfigurasi tes yang pasti: pajak 10%, APP_KEY tes (tidak menyentuh .env).
App::init(App::root(), array_replace_recursive(App::allConfig(), [
    'app'   => ['key' => str_repeat('ab', 32)],
    'order' => ['tax_percent' => 10],
]));

echo "Tes lapisan data Kokoro Cakes — Redis {$cfg['host']}:{$cfg['port']} database TES {$testDb}" . PHP_EOL . PHP_EOL;

try {
    $raw = RedisLocalAdapter::createClient($cfg, $testDb);
    $raw->ping();
} catch (\Predis\PredisException $e) {
    fwrite(STDERR, "Redis tidak dapat dihubungi. Jalankan Redis/Memurai dulu.\n");
    exit(1);
}
if ((int) $raw->dbsize() > 0 && $raw->get(TEST_MARKER) !== '1') {
    fwrite(STDERR, "Database {$testDb} berisi data lain (tanpa penanda tes). Tes dibatalkan agar tidak menghapus data.\n");
    exit(1);
}
$raw->flushdb();
$raw->set(TEST_MARKER, '1');

$data = RedisLocalAdapter::fromConfig(App::allConfig(), $testDb);
$menuData = require App::root() . '/config/menu.php';
$tables = require App::root() . '/config/meja.php';
$seedConfig = ['env' => 'testing', 'seed' => ['nama' => 'Kasir Pengujian', 'email' => 'kasir@kokoro.test', 'password' => bin2hex(random_bytes(16))]];
$seeder = new RedisDemoSeeder($raw);
$cart = new CartService($data, 20);
$checkoutSvc = new CheckoutService((array) App::config('order.payment_methods'), 10);
$rid = static fn (): string => bin2hex(random_bytes(16));
$owner = static fn (string $seed): string => hash('sha256', 'owner|' . $seed);
$order = static function (string $table, string $ownerHash, string $method = 'tunai', ?string $requestId = null) use ($data, $rid): array {
    return $data->checkout([
        'request_id' => $requestId ?? $rid(), 'kode_meja' => $table, 'owner_hash' => $ownerHash,
        'nama_pemesan' => 'Pelanggan Uji', 'catatan' => '', 'metode_bayar' => $method,
        'items' => $data->getCart($table),
    ]);
};
$customerA = $owner('perangkat-A');
$customerB = $owner('perangkat-B');
$kasir = ['type' => 'kasir'];

// =====================================================================
TestKit::run('T01', 'Redis memberi respons PONG', function () use ($raw) {
    TestKit::same('PONG', (string) $raw->ping(), 'PING');
    return 'PING -> PONG';
});

TestKit::run('T02', 'Seeder menolak konfigurasi tidak aman sebelum menulis', function () use ($seeder, $seedConfig, $menuData, $tables, $raw) {
    foreach ([
        'password pendek'   => ['seed' => ['password' => 'pendek'] + $seedConfig['seed']],
        'password > 72 byte' => ['seed' => ['password' => str_repeat('x', 73)] + $seedConfig['seed']],
        'APP_ENV production' => ['env' => 'production'],
        'email tidak valid' => ['seed' => ['email' => 'bukan-email'] + $seedConfig['seed']],
    ] as $label => $override) {
        TestKit::throws(\RuntimeException::class, fn () => $seeder->run(array_replace($seedConfig, $override), $menuData, $tables));
        TestKit::same(1, (int) $raw->dbsize(), "jumlah key setelah ditolak ({$label})");
    }
    return 'empat konfigurasi ditolak, database tetap kosong';
});

TestKit::run('T03', 'Benturan key membatalkan seed sebelum ada write', function () use ($seeder, $seedConfig, $menuData, $tables, $raw) {
    $raw->set('idx:menu', 'data-uji-jangan-diubah');
    TestKit::throws(\Predis\Response\ServerException::class, fn () => $seeder->run($seedConfig, $menuData, $tables));
    TestKit::same(2, (int) $raw->dbsize(), 'jumlah key (marker tes + key bentrok)');
    TestKit::same('data-uji-jangan-diubah', $raw->get('idx:menu'), 'data lama utuh');
    $raw->del(['idx:menu']);
    return 'key terakhir yang bentrok terdeteksi lebih dulu';
});

$nKat = count($menuData['kategori']);
$nMenu = count($menuData['menu']);
$nKeys = 12 + $nMenu + $nKat + 1 + 2 + $nKat + 4 + 1; // meja + menu + kategori + user + idx email & username + relasi + 4 indeks + marker
TestKit::run('T04', "Seed pertama: 12 meja, {$nKat} kategori, {$nMenu} menu, 1 kasir ({$nKeys} key)", function () use ($seeder, $seedConfig, $menuData, $tables, $raw, $nKat, $nMenu, $nKeys) {
    $res = $seeder->run($seedConfig, $menuData, $tables);
    TestKit::same('seeded', $res['status'], 'status');
    TestKit::same(12, (int) $raw->scard('idx:meja'), 'meja');
    TestKit::same($nKat, (int) $raw->scard('idx:kategori'), 'kategori');
    TestKit::same($nMenu, (int) $raw->scard('idx:menu'), 'menu');
    TestKit::same(1, (int) $raw->scard('idx:users'), 'staf');
    TestKit::same($nKeys, $res['keys'], 'key yang ditulis seeder');
    TestKit::same($nKeys + 1, (int) $raw->dbsize(), 'key total (+ penanda tes)');
    foreach ($tables as $code => $expected) {
        TestKit::same($expected, $raw->hgetall('meja:' . $code), "meja {$code}");
        TestKit::same(-1, (int) $raw->ttl('meja:' . $code), "TTL meja {$code}");
    }
    foreach ($menuData['menu'] as [$id, $cat]) {
        TestKit::assert((bool) $raw->sismember('kategori:' . $cat . ':menu', $id), "menu {$id} terhubung ke {$cat}");
    }
    return "{$nKeys} key sesuai skema, tanpa TTL";
});

TestKit::run('T05', 'Akun kasir: password tersimpan sebagai hash', function () use ($data, $seedConfig) {
    $staff = $data->findStaffByEmail('KASIR@kokoro.test');
    TestKit::assert($staff !== null, 'akun ditemukan lewat indeks email (tidak peka huruf besar)');
    TestKit::assert(password_verify($seedConfig['seed']['password'], $staff['password_hash']), 'password_verify cocok');
    TestKit::assert($staff['password_hash'] !== $seedConfig['seed']['password'], 'password asli tidak disimpan');
    TestKit::same('kasir', $staff['role'], 'role');
    TestKit::same(null, $data->findStaffByEmail('tidak-ada@kokoro.test'), 'email lain');
    // Login staf memakai username (email tetap diterima)
    TestKit::same($staff['id'], $data->findStaffByUsername('KASIR')['id'] ?? null, 'ditemukan lewat username (tidak peka huruf besar)');
    TestKit::same($staff['id'], $data->findStaffByLogin('kasir')['id'] ?? null, 'login dengan username');
    TestKit::same($staff['id'], $data->findStaffByLogin('kasir@kokoro.test')['id'] ?? null, 'login dengan email tetap bisa');
    TestKit::same(null, $data->findStaffByLogin('bukan-kasir'), 'username lain');
    return 'users:1 role kasir, username kasir, hash bcrypt';
});

TestKit::run('T06', 'Seed ulang tidak mengubah apa pun', function () use ($seeder, $seedConfig, $menuData, $tables, $raw) {
    $raw->hset('meja:1A', 'status', 'Terisi');
    $raw->hset('menu:m1', 'status', 'Habis');
    $hash = $raw->hget('users:1', 'password_hash');
    $before = (int) $raw->dbsize();
    $res = $seeder->run(array_replace_recursive($seedConfig, ['seed' => ['password' => bin2hex(random_bytes(16))]]), $menuData, $tables);
    TestKit::same('already_seeded', $res['status'], 'status');
    TestKit::same($before, (int) $raw->dbsize(), 'jumlah key');
    TestKit::same('Terisi', $raw->hget('meja:1A', 'status'), 'status meja dipertahankan');
    TestKit::same('Habis', $raw->hget('menu:m1', 'status'), 'status menu dipertahankan');
    TestKit::same($hash, $raw->hget('users:1', 'password_hash'), 'password dipertahankan');
    $raw->hset('meja:1A', 'status', 'Tersedia');
    $raw->hset('menu:m1', 'status', 'Tersedia');
    return 'marker kokoro:seed:v1 mencegah seed ganda';
});

TestKit::run('T07', 'Menu dan kategori dibaca dari Redis dengan urutan benar', function () use ($data, $nMenu) {
    $cats = $data->listCategories();
    TestKit::same(['Cake & Dessert', 'Pastry', 'Drink', 'Seasonal', 'Wagashi'], array_column($cats, 'nama_kategori'), 'urutan kategori');
    TestKit::same('和菓子', $cats[4]['nama_jp'], 'nama Jepang kategori');
    $menu = $data->listMenu();
    TestKit::same($nMenu, count($menu), 'jumlah menu');
    TestKit::same('抹茶ケーキ', $data->getMenu('m2')['nama_jp'], 'nama Jepang menu');
    TestKit::same('Wagashi', $cats[4]['nama_kategori'], 'kategori baru');
    TestKit::same('m1', $menu[0]['id'], 'menu pertama');
    TestKit::same(28000, $data->getMenu('m1')['harga'], 'harga Strawberry Cake');
    TestKit::same(32000, $data->getMenu('m2')['harga'], 'harga Matcha Cake');
    TestKit::same(24000, $data->getMenu('m9')['harga'], 'harga Iced Matcha Latte');
    TestKit::same(false, $data->getMenu('m3')['tersedia'], 'Tiramisu habis');
    TestKit::same(null, $data->getMenu('m99'), 'menu tidak ada');
    return 'harga sesuai wireframe';
});

TestKit::run('T08', 'Tautan QR meja: token benar diterima, token lain ditolak', function () {
    $token = TableAccess::token('1A');
    TestKit::assert(TableAccess::verify('1A', $token), 'token 1A sah');
    TestKit::assert(!TableAccess::verify('1B', $token), 'token 1A tidak berlaku untuk 1B');
    TestKit::assert(!TableAccess::verify('1A', str_repeat('0', 16)), 'token tebakan ditolak');
    TestKit::assert(!TableAccess::verify('1A', 'bukan-hex'), 'format salah ditolak');
    TestKit::same('/meja/1A/' . $token, TableAccess::path('1A'), 'path');
    return 'HMAC per meja';
});

TestKit::run('T09', 'Validator menolak input aneh dari browser', function () {
    foreach (['0', '-1', '1.5', '1e2', 'abc', '', '21'] as $bad) {
        TestKit::throws(ValidationError::class, fn () => Validator::quantity($bad));
    }
    TestKit::same(3, Validator::quantity('3'), 'qty 3');
    TestKit::throws(ValidationError::class, fn () => Validator::menuId('menu:m1'));
    TestKit::throws(ValidationError::class, fn () => Validator::tableCode('1A; DEL'));
    TestKit::same('1A', Validator::tableCode('1a'), 'kode meja dinormalkan');
    TestKit::throws(ValidationError::class, fn () => Validator::paymentMethod('gratis', ['qris' => 'Q', 'tunai' => 'T']));
    TestKit::throws(ValidationError::class, fn () => Validator::orderId('1 OR 1=1'));
    return 'qty, kode menu, kode meja, metode, nomor pesanan';
});

TestKit::run('T10', 'Keranjang meja: tambah, ubah, hapus; menu habis ditolak', function () use ($cart, $data) {
    $cart->add('1A', 'm1', 2);
    $cart->add('1A', 'm1', 1);
    TestKit::same([['id_menu' => 'm1', 'qty' => 3]], $data->getCart('1A'), 'qty bertambah');
    $cart->update('1A', 'm1', 1);
    TestKit::same(1, $data->getCart('1A')[0]['qty'], 'qty diubah');
    TestKit::same([], $data->getCart('1B'), 'keranjang meja lain terpisah');
    TestKit::throws(ValidationError::class, fn () => $cart->add('1A', 'm3', 1)); // Tiramisu habis
    TestKit::throws(ValidationError::class, fn () => $cart->add('1A', 'm1', 20)); // melebihi 20 per menu
    TestKit::throws(ValidationError::class, fn () => $cart->add('1A', 'm99', 1));
    $cart->remove('1A', 'm1');
    TestKit::same([], $data->getCart('1A'), 'keranjang kosong');
    return 'cart:{kode_meja} sesuai prompt';
});

$firstOrder = null;
TestKit::run('T11', 'Checkout: total dihitung server, pesanan + detail tersimpan, meja Terisi, keranjang kosong', function () use ($cart, $data, $order, $customerA, $raw, $kasir, &$firstOrder) {
    $cart->add('1A', 'm1', 2);  // 2 × 28.000
    $cart->add('1A', 'm9', 1);  // 1 × 24.000
    $res = $order('1A', $customerA, 'tunai');
    $o = $res['order'];
    $firstOrder = $o;
    TestKit::same('created', $res['status'], 'status');
    TestKit::same(80000, $o['subtotal'], 'subtotal');
    TestKit::same(8000, $o['pajak'], 'pajak 10%');
    TestKit::same(88000, $o['total'], 'total');
    TestKit::same('Masuk', $o['status_pesanan'], 'status awal');
    TestKit::same('Belum Bayar', $o['status_bayar'], 'status bayar tunai');
    TestKit::same(2, count($o['items']), 'jumlah baris detail');
    TestKit::same(['id_menu' => 'm1', 'nama_menu' => 'Strawberry Cake', 'jumlah' => 2, 'harga_satuan' => 28000, 'subtotal' => 56000], $o['items'][0], 'detail m1');
    TestKit::same('Terisi', $data->getTable('1A')['status'], 'meja terisi');
    TestKit::same(0, (int) $raw->exists('cart:1A'), 'keranjang dihapus');
    TestKit::same('list', (string) $raw->type('pesanan:' . $o['id'] . ':detail'), 'detail bertipe List');
    foreach (['kode_pesanan', 'kode_meja', 'waktu', 'total', 'status_pesanan', 'metode_bayar'] as $f) {
        TestKit::assert((bool) $raw->hexists('pesanan:' . $o['id'], $f), "field wajib {$f}");
    }
    TestKit::same(1, count($data->listOrders($kasir)), 'muncul di daftar kasir');
    return $o['kode_pesanan'] . ' total Rp88.000';
});

TestKit::run('T12', 'Klik ganda (request_id sama) tidak membuat pesanan ganda', function () use ($cart, $data, $customerA, $raw) {
    $cart->add('2A', 'm2', 1);
    $req = ['request_id' => bin2hex(random_bytes(16)), 'kode_meja' => '2A', 'owner_hash' => $customerA,
        'nama_pemesan' => 'Uji', 'catatan' => '', 'metode_bayar' => 'qris', 'items' => $data->getCart('2A')];
    $a = $data->checkout($req);
    $before = (int) $raw->zcard('idx:pesanan');
    $b = $data->checkout($req);
    TestKit::same('created', $a['status'], 'pertama');
    TestKit::same('duplicate', $b['status'], 'kedua');
    TestKit::same($a['order']['id'], $b['order']['id'], 'pesanan sama');
    TestKit::same($before, (int) $raw->zcard('idx:pesanan'), 'tidak ada pesanan baru');
    TestKit::same('Lunas', $a['order']['status_bayar'], 'QRIS simulasi langsung lunas');
    $other = $req;
    $other['catatan'] = 'isi berbeda';
    $e = TestKit::throws(DomainRejected::class, fn () => $data->checkout($other));
    TestKit::same('IDEMPOTENCY_CONFLICT', $e->errorCode, 'request_id dipakai untuk isi lain');
    $found = $data->getCheckoutResult($req['request_id'], ['type' => 'customer', 'owner_hash' => $customerA]);
    TestKit::same($a['order']['id'], $found['order']['id'], 'hasil bisa dicari ulang');
    return 'idempoten lewat checkout:request:{id}';
});

TestKit::run('T13', 'Menu habis setelah masuk keranjang: checkout ditolak tanpa write', function () use ($cart, $data, $customerA, $raw) {
    $cart->add('1B', 'm4', 1);
    $data->setMenuStatus('m4', 'Habis');
    $before = (int) $raw->dbsize();
    $e = TestKit::throws(DomainRejected::class, fn () => $data->checkout(['request_id' => bin2hex(random_bytes(16)), 'kode_meja' => '1B',
        'owner_hash' => $customerA, 'nama_pemesan' => 'Uji', 'catatan' => '', 'metode_bayar' => 'tunai', 'items' => $data->getCart('1B')]));
    TestKit::same('MENU_UNAVAILABLE', $e->errorCode, 'kode error');
    TestKit::same('Chocolate Cake', $e->details['problems'][0]['nama_menu'], 'nama menu di detail');
    TestKit::same($before, (int) $raw->dbsize(), 'tidak ada key baru (seq:pesanan sudah ada)');
    TestKit::same('Tersedia', $data->getTable('1B')['status'], 'meja tidak berubah');
    TestKit::same(1, count($data->getCart('1B')), 'keranjang tetap');
    $data->setMenuStatus('m4', 'Tersedia');
    $data->removeCartItem('1B', 'm4');
    return 'validasi sebelum write';
});

TestKit::run('T14', 'Keranjang berubah / meja nonaktif / keranjang kosong ditolak', function () use ($cart, $data, $customerA, $raw) {
    $cart->add('1C', 'm5', 2);
    $req = ['request_id' => bin2hex(random_bytes(16)), 'kode_meja' => '1C', 'owner_hash' => $customerA,
        'nama_pemesan' => 'Uji', 'catatan' => '', 'metode_bayar' => 'tunai', 'items' => [['id_menu' => 'm5', 'qty' => 1]]];
    TestKit::same('CART_CHANGED', TestKit::throws(DomainRejected::class, fn () => $data->checkout($req))->errorCode, 'qty tidak sama dengan keranjang');
    $req['items'] = [];
    TestKit::same('EMPTY_ORDER', TestKit::throws(DomainRejected::class, fn () => $data->checkout($req))->errorCode, 'item kosong');
    $raw->hset('meja:1C', 'aktif', '0');
    $req['items'] = $data->getCart('1C');
    TestKit::same('TABLE_UNAVAILABLE', TestKit::throws(DomainRejected::class, fn () => $data->checkout($req))->errorCode, 'meja nonaktif');
    TestKit::throws(ValidationError::class, fn () => $cart->add('1C', 'm1', 1));
    $raw->hset('meja:1C', 'aktif', '1');
    $data->removeCartItem('1C', 'm5');
    return 'CART_CHANGED, EMPTY_ORDER, TABLE_UNAVAILABLE';
});

TestKit::run('T15', 'Bayar dulu, baru disiapkan: Masuk > (bayar) > Diproses > Siap Diantar > Selesai; meja tetap Terisi', function () use ($data, $raw, &$firstOrder) {
    $id = $firstOrder['id'];
    $e = TestKit::throws(DomainRejected::class, fn () => $data->updateOrderStatus($id, 'Masuk', 'Selesai', 'kasir:uji'));
    TestKit::same('INVALID_TRANSITION', $e->errorCode, 'loncat status');
    $e = TestKit::throws(DomainRejected::class, fn () => $data->updateOrderStatus($id, 'Masuk', 'Diproses', 'kasir:uji'));
    TestKit::same('PAYMENT_REQUIRED', $e->errorCode, 'dapur dikunci sebelum bayar');
    TestKit::assert($raw->zscore('idx:pesanan:belum-bayar', (string) $id) !== null, 'masuk antrean batas bayar');
    TestKit::same((string) $id, $raw->hget('meja:1A', 'tagihan_terbuka'), 'tagihan terbuka di meja');
    TestKit::same('paid', $data->confirmPayment($id, 'kasir:uji'), 'konfirmasi bayar');
    TestKit::same('already_paid', $data->confirmPayment($id, 'kasir:uji'), 'konfirmasi ganda');
    TestKit::same(null, $raw->zscore('idx:pesanan:belum-bayar', (string) $id), 'keluar dari antrean batas bayar');
    TestKit::same(null, $raw->hget('meja:1A', 'tagihan_terbuka'), 'tagihan meja dilepas');
    TestKit::same('updated', $data->updateOrderStatus($id, 'Masuk', 'Diproses', 'kasir:uji')['status'], 'ke Diproses');
    TestKit::same('unchanged', $data->updateOrderStatus($id, 'Masuk', 'Diproses', 'kasir:uji')['status'], 'klik ganda');
    $e = TestKit::throws(DomainRejected::class, fn () => $data->updateOrderStatus($id, 'Masuk', 'Siap Diantar', 'kasir:uji'));
    TestKit::same('STATUS_CONFLICT', $e->errorCode, 'status asal basi');
    $data->updateOrderStatus($id, 'Diproses', 'Siap Diantar', 'kasir:uji');
    $res = $data->updateOrderStatus($id, 'Siap Diantar', 'Selesai', 'kasir:uji');
    TestKit::same(true, $res['table_idle'], 'tidak ada pesanan aktif lagi');
    TestKit::same('Terisi', $data->getTable('1A')['status'], 'meja tetap Terisi (tamu boleh duduk lama)');
    TestKit::assert($data->getTable('1A')['terisi_sejak'] > 0, 'terisi_sejak tercatat');
    $o = $data->getOrder($id, ['type' => 'kasir']);
    TestKit::same(['Masuk', 'Diproses', 'Siap Diantar', 'Selesai'], array_values(array_unique(array_column(array_filter($o['history'], fn ($h) => $h['jenis'] === 'status'), 'status'))), 'riwayat status');
    return 'transisi dicek atomik di Lua';
});

TestKit::run('T16', 'Meja tetap Terisi setelah semua pesanan selesai, baru Tersedia saat dikosongkan staf', function () use ($cart, $order, $data, $customerA, $customerB) {
    $cart->add('3A', 'm6', 1);
    $o1 = $order('3A', $customerA, 'qris')['order'];
    $cart->add('3A', 'm7', 1);
    $o2 = $order('3A', $customerB, 'qris')['order'];
    TestKit::same(2, $data->getTable('3A')['pesanan_aktif'], 'dua pesanan aktif');
    foreach (['Diproses', 'Siap Diantar', 'Selesai'] as $i => $to) {
        $data->updateOrderStatus($o1['id'], ['Masuk', 'Diproses', 'Siap Diantar'][$i], $to, 'kasir:uji');
    }
    TestKit::same('Terisi', $data->getTable('3A')['status'], 'masih terisi (pesanan kedua aktif)');
    TestKit::same('TABLE_HAS_ORDERS', TestKit::throws(DomainRejected::class, fn () => $data->releaseTable('3A'))->errorCode, 'tidak bisa dikosongkan saat ada pesanan berjalan');
    $res = $data->cancelOrder($o2['id'], 'Tamu berubah pikiran', 'kasir:uji');
    TestKit::same(true, $res['table_idle'], 'pesanan terakhir selesai/batal');
    TestKit::same('Terisi', $data->getTable('3A')['status'], 'tetap Terisi sampai tamu pergi');
    $sesi = $data->getTable('3A')['sesi'];
    TestKit::same('released', $data->releaseTable('3A'), 'staf mengosongkan');
    $t = $data->getTable('3A');
    TestKit::same('Tersedia', $t['status'], 'meja tersedia');
    TestKit::same($sesi + 1, $t['sesi'], 'sesi meja naik (sesi tamu lama berakhir)');
    TestKit::same(0, $t['terisi_sejak'], 'terisi_sejak dihapus');
    return 'meja:{kode}:aktif + sesi'; 
});

TestKit::run('T17', 'Pembatalan: hanya saat Masuk, alasan wajib, klik ganda aman, QRIS dikembalikan', function () use ($cart, $order, $data, $customerA) {
    $cart->add('2B', 'm10', 1);
    $o = $order('2B', $customerA, 'qris')['order'];
    TestKit::same('REASON_REQUIRED', TestKit::throws(DomainRejected::class, fn () => $data->cancelOrder($o['id'], '  ', 'pelanggan'))->errorCode, 'alasan kosong');
    TestKit::same('cancelled', $data->cancelOrder($o['id'], 'Salah pilih menu', 'pelanggan')['status'], 'batal');
    TestKit::same('already_cancelled', $data->cancelOrder($o['id'], 'Salah pilih menu', 'pelanggan')['status'], 'batal ganda');
    $after = $data->getOrder($o['id'], ['type' => 'kasir']);
    TestKit::same('Dibatalkan', $after['status_pesanan'], 'status');
    TestKit::same('Dikembalikan', $after['status_bayar'], 'pembayaran QRIS dikembalikan (simulasi)');
    TestKit::same('ALREADY_CANCELLED', TestKit::throws(DomainRejected::class, fn () => $data->confirmPayment($o['id'], 'kasir:uji'))->errorCode, 'tidak bisa dibayar');
    $cart->add('2B', 'm10', 1);
    $o2 = $order('2B', $customerA)['order'];
    $data->confirmPayment($o2['id'], 'kasir:uji');
    $data->updateOrderStatus($o2['id'], 'Masuk', 'Diproses', 'kasir:uji');
    TestKit::same('NOT_CANCELLABLE', TestKit::throws(DomainRejected::class, fn () => $data->cancelOrder($o2['id'], 'Terlambat', 'pelanggan'))->errorCode, 'sudah diproses');
    return 'aturan pembatalan';
});

TestKit::run('T18', 'Akses pesanan: perangkat lain tidak bisa melihat', function () use ($data, $customerA, $customerB, &$firstOrder) {
    $id = $firstOrder['id'];
    TestKit::assert($data->getOrder($id, ['type' => 'customer', 'owner_hash' => $customerA]) !== null, 'pemilik bisa');
    TestKit::same(null, $data->getOrder($id, ['type' => 'customer', 'owner_hash' => $customerB]), 'perangkat lain');
    TestKit::same(null, $data->getOrder($id, []), 'tanpa akses');
    $mine = array_column($data->listOrders(['type' => 'customer', 'owner_hash' => $customerB]), 'id');
    TestKit::assert(!in_array($id, $mine, true), 'tidak ada di daftar perangkat lain');
    return 'owner_hash dicocokkan dengan hash_equals';
});

TestKit::run('T19', 'Harga di pesanan adalah snapshot saat transaksi', function () use ($data, $raw, &$firstOrder) {
    $raw->hset('menu:m1', 'harga', '99000');
    $o = $data->getOrder($firstOrder['id'], ['type' => 'kasir']);
    TestKit::same(28000, $o['items'][0]['harga_satuan'], 'harga lama tetap');
    TestKit::same(88000, $o['total'], 'total tetap');
    $raw->hset('menu:m1', 'harga', '28000');
    return 'pesanan:{id}:detail tidak berubah';
});

TestKit::run('T20', 'Ketersediaan menu dan kosongkan meja', function () use ($data, $cart, $raw) {
    TestKit::same('updated', $data->setMenuStatus('m2', 'Habis'), 'ubah ke Habis');
    TestKit::same('unchanged', $data->setMenuStatus('m2', 'Habis'), 'ulang');
    TestKit::same('MENU_NOT_FOUND', TestKit::throws(DomainRejected::class, fn () => $data->setMenuStatus('m77', 'Habis'))->errorCode, 'menu baru tidak dibuat');
    TestKit::same(0, (int) $raw->exists('menu:m77'), 'menu:m77 tidak ada');
    $data->setMenuStatus('m2', 'Tersedia');
    $raw->hset('meja:3D', 'status', 'Terisi');
    $cart->add('3D', 'm1', 1);
    TestKit::same('released', $data->releaseTable('3D'), 'kosongkan');
    TestKit::same(0, (int) $raw->exists('cart:3D'), 'keranjang ikut dihapus');
    return 'kasir tidak menambah menu';
});

TestKit::run('T21', 'Rekap hanya menghitung pesanan Selesai', function () use ($data) {
    $s = $data->getSalesSummary(time() - 3600, time() + 3600);
    TestKit::same(2, $s['orders'], 'pesanan selesai (T15 + T16)');
    TestKit::same(88000 + 24200, $s['total'], 'total');
    TestKit::same('Strawberry Cake', $s['menu'][0]['nama_menu'], 'terlaris');
    return 'Rp' . number_format($s['total'], 0, ',', '.');
});

TestKit::run('T22', 'Rumus pajak PHP sama dengan Lua', function () use ($checkoutSvc) {
    TestKit::same(8000, CheckoutService::tax(80000, 10), '80.000');
    TestKit::same(2200, CheckoutService::tax(22000, 10), '22.000');
    TestKit::same(1, CheckoutService::tax(5, 10), 'pembulatan 0,5 ke atas');
    $q = $checkoutSvc->quote(['subtotal' => 56000, 'units' => 2]);
    TestKit::same(61600, $q['total'], 'pratinjau total');
    return 'intdiv(subtotal × persen + 50, 100)';
});

TestKit::run('T23', 'Checkout bersamaan: 5 proses, request_id sama -> 1 pesanan', function () use ($cart, $raw) {
    $cart->add('2C', 'm11', 1);
    $before = (int) $raw->zcard('idx:pesanan');
    $results = run_workers(5, static fn (int $i) => [bin2hex('samereq-' . 'xxxxxxxx'), '2C', 'perangkat-C']);
    $statuses = array_count_values(array_map(static fn ($r) => $r['ok'] ? $r['status'] : $r['code'], $results));
    TestKit::same(1, $statuses['created'] ?? 0, 'created');
    TestKit::same(4, $statuses['duplicate'] ?? 0, 'duplicate');
    TestKit::same(1, count(array_unique(array_column($results, 'order_id'))), 'satu nomor pesanan');
    TestKit::same($before + 1, (int) $raw->zcard('idx:pesanan'), 'satu pesanan baru');
    return json_encode($statuses);
});

TestKit::run('T24', 'Checkout bersamaan dari 5 perangkat di meja sama -> 1 pesanan, lainnya ditolak', function () use ($cart, $raw) {
    $cart->add('2D', 'm12', 2);
    $before = (int) $raw->zcard('idx:pesanan');
    $results = run_workers(5, static fn (int $i) => [bin2hex(random_bytes(16)), '2D', 'perangkat-' . $i]);
    $statuses = array_count_values(array_map(static fn ($r) => $r['ok'] ? $r['status'] : $r['code'], $results));
    TestKit::same(1, $statuses['created'] ?? 0, 'created');
    // Ditolak karena keranjang sudah kosong (CART_CHANGED) atau meja sudah punya tagihan terbuka (UNPAID_EXISTS).
    TestKit::same(4, ($statuses['CART_CHANGED'] ?? 0) + ($statuses['UNPAID_EXISTS'] ?? 0), 'ditolak');
    TestKit::same($before + 1, (int) $raw->zcard('idx:pesanan'), 'satu pesanan baru');
    return json_encode($statuses);
});

TestKit::run('T25', 'Sinkron menu ke database lama: menu baru masuk, harga & status lama tetap', function () use ($raw, $menuData, $tables, $seedConfig) {
    // Tiru database yang di-seed dengan versi lama: 4 kategori, 15 menu, tanpa field nama_jp.
    $raw->flushdb();
    $raw->set(TEST_MARKER, '1');
    $old = ['kategori' => array_slice($menuData['kategori'], 0, 4, true), 'menu' => array_values(array_filter(
        $menuData['menu'], static fn ($r) => (int) substr($r[0], 1) <= 15
    ))];
    $seeder = new RedisDemoSeeder($raw);
    $seeder->run($seedConfig, $old, $tables);
    foreach ($raw->smembers('idx:menu') as $id) {
        $raw->hdel('menu:' . $id, ['nama_jp']);
    }
    foreach ($raw->smembers('idx:kategori') as $id) {
        $raw->hdel('kategori:' . $id, ['nama_jp']);
    }
    $raw->hset('menu:m1', 'harga', '29500');      // harga diubah setelah seed
    $raw->hset('menu:m2', 'status', 'Habis');     // status diubah kasir
    $raw->hset('menu:m5', 'deskripsi', 'Deskripsi versi lama.');
    $res = $seeder->syncCatalog($menuData);
    TestKit::same(1, $res['kategori_baru'], 'kategori baru');
    TestKit::same(count($menuData['menu']) - 15, $res['menu_baru'], 'menu baru');
    TestKit::same(15 + 4, $res['field_baru'], 'field nama_jp diisi pada data lama');
    TestKit::same(count($menuData['menu']), (int) $raw->scard('idx:menu'), 'total menu');
    TestKit::same('29500', $raw->hget('menu:m1', 'harga'), 'harga lama tidak ditimpa');
    TestKit::same('Habis', $raw->hget('menu:m2', 'status'), 'status lama tidak ditimpa');
    // Teks menu ikut config: 1 deskripsi lama + 15 x 2 field pasangan yang tadinya kosong.
    TestKit::same(1 + 15 * 2, $res['field_diperbarui'], 'teks menu diperbarui');
    TestKit::same($menuData['menu'][4][5], $raw->hget('menu:m5', 'deskripsi'), 'deskripsi m5 mengikuti config');
    TestKit::same($menuData['pasangan']['m1'][0], $raw->hget('menu:m1', 'pasangan'), 'pasangan m1 terisi');
    TestKit::assert((bool) $raw->sismember('kategori:k5:menu', 'm36'), 'Dorayaki masuk kategori Wagashi');
    $again = $seeder->syncCatalog($menuData);
    TestKit::same(['kategori_baru' => 0, 'menu_baru' => 0, 'field_baru' => 0, 'field_diperbarui' => 0], $again, 'sinkron ulang tidak mengubah apa pun');
    return "+{$res['menu_baru']} menu, +{$res['kategori_baru']} kategori";
});

TestKit::run('T26', 'Label "Sering dipesan" & saran pasangan hanya dari data nyata', function () {
    // Tanpa penjualan -> tidak ada label; menu dengan < 3 porsi terjual tidak dianggap sering dipesan.
    TestKit::same([], MenuCatalog::popularIds([]), 'tanpa data penjualan');
    $sales = [['id_menu' => 'm1', 'jumlah' => 9], ['id_menu' => 'm9', 'jumlah' => 4], ['id_menu' => 'm2', 'jumlah' => 2]];
    TestKit::same(['m1' => true, 'm9' => true], MenuCatalog::popularIds($sales), 'ambang minimal 3 porsi');
    TestKit::same(['m1' => true], MenuCatalog::popularIds($sales, 1), 'batas jumlah label');

    $m = static fn (string $id, string $pair, bool $ok = true) => ['id' => $id, 'nama_menu' => 'Menu ' . $id,
        'tersedia' => $ok, 'pasangan' => $pair, 'pasangan_alasan' => 'alasan ' . $id];
    $byId = MenuCatalog::byId([$m('m1', 'm26'), $m('m2', 'm12'), $m('m26', 'm37'), $m('m12', 'm1'), $m('m5', 'm28'), $m('m28', 'm24', false)]);
    TestKit::same('m26', MenuCatalog::pairingFor($byId['m1'], $byId)['menu']['id'], 'pasangan m1');
    TestKit::same(null, MenuCatalog::pairingFor($byId['m5'], $byId), 'pasangan yang Habis tidak disarankan');
    // Keranjang berisi m1 dan m12: pasangan m12 (= m1) sudah ada di keranjang -> hanya m26 yang disarankan.
    $s = MenuCatalog::cartSuggestions([['id_menu' => 'm1'], ['id_menu' => 'm12']], $byId);
    TestKit::same(['m26'], array_map(static fn ($x) => $x['menu']['id'], $s), 'saran keranjang');
    TestKit::same('Menu m1', $s[0]['untuk'], 'saran menyebut menu asal');
    return 'popularIds + pairingFor + cartSuggestions';
});

TestKit::run('T27', 'Pilihan dapur hari ini: maks 3, menu habis ditolak, kedaluwarsa tengah malam', function () use ($data, $raw) {
    $exp = MenuCatalog::endOfDay('Asia/Jakarta');
    TestKit::same('added', $data->setTodayPick('m1', true, 3, $exp), 'tambah m1');
    TestKit::same('unchanged', $data->setTodayPick('m1', true, 3, $exp), 'klik ganda');
    $data->setTodayPick('m4', true, 3, $exp);
    $data->setTodayPick('m5', true, 3, $exp);
    try {
        $data->setTodayPick('m6', true, 3, $exp);
        throw new AssertionFailed('pilihan ke-4 seharusnya ditolak');
    } catch (DomainRejected $e) {
        TestKit::same('PICK_LIMIT', $e->errorCode, 'batas 3');
    }
    TestKit::same('removed', $data->setTodayPick('m5', false, 3, $exp), 'hapus m5');
    $raw->hset('menu:m6', 'status', 'Habis');
    try {
        $data->setTodayPick('m6', true, 3, $exp);
        throw new AssertionFailed('menu habis seharusnya ditolak');
    } catch (DomainRejected $e) {
        TestKit::same('PICK_UNAVAILABLE', $e->errorCode, 'menu habis');
    }
    $raw->hset('menu:m6', 'status', 'Tersedia');
    TestKit::same(['m1', 'm4'], $data->listTodayPicks(), 'isi pilihan');
    $ttl = (int) $raw->ttl('pilihan:hari-ini');
    TestKit::assert($ttl > 0 && $ttl <= 86400, 'TTL sampai tengah malam (' . $ttl . ' detik)');
    // endOfDay: 11 Nov 2026 23:30 WIB -> 12 Nov 2026 00:00 WIB
    TestKit::same(strtotime('2026-11-11T17:00:00Z'), MenuCatalog::endOfDay('Asia/Jakarta', strtotime('2026-11-11T16:30:00Z')), 'tengah malam WIB');
    return 'pilihan:hari-ini TTL ' . $ttl . ' detik';
});

TestKit::run('T28', 'Ganti QR meja: QR lama tidak berlaku, QR versi 1 lama tetap sah sebelum diganti', function () use ($data) {
    $old = TableAccess::token('1A');
    TestKit::same(substr(hash_hmac('sha256', 'meja|1A', (string) App::config('app.key')), 0, 16), $old, 'versi 1 = rumus lama');
    TestKit::same(1, $data->getTable('1A')['qr_versi'], 'versi awal');
    TestKit::same(2, $data->rotateTableQr('1A'), 'ganti -> versi 2');
    $v = $data->getTable('1A')['qr_versi'];
    TestKit::same(2, $v, 'tersimpan di meja:1A');
    TestKit::assert(!TableAccess::verify('1A', $old, $v), 'QR lama ditolak');
    TestKit::assert(TableAccess::verify('1A', TableAccess::token('1A', $v), $v), 'QR baru diterima');
    TestKit::assert(TableAccess::token('1A', 2) !== TableAccess::token('1B', 2), 'token beda per meja');
    try {
        $data->rotateTableQr('9Z');
        throw new AssertionFailed('meja tidak ada seharusnya ditolak');
    } catch (DomainRejected $e) {
        TestKit::same('TABLE_NOT_FOUND', $e->errorCode, 'meja tidak ada');
    }
    return 'qr_versi 1 -> 2';
});

TestKit::run('T29', 'Admin kelola menu: tambah, ubah, hapus; sinkron config tidak menimpa/menghidupkan lagi', function () use ($data, $raw, $menuData) {
    $seeder = new RedisDemoSeeder($raw);
    $base = ['id_kategori' => 'k1', 'nama_menu' => 'Kinako Roll', 'nama_jp' => 'きなこロール', 'harga' => 27000,
        'deskripsi' => 'Bolu gulung kinako.', 'status' => 'Tersedia', 'pasangan' => 'm26', 'pasangan_alasan' => 'Teh gurih.'];
    $id = $data->createMenu($base);
    TestKit::same('m40', $id, 'kode menu berikutnya');
    TestKit::assert((bool) $raw->sismember('kategori:k1:menu', $id), 'masuk kategori:k1:menu');
    TestKit::same(27000, $data->getMenu($id)['harga'], 'harga');

    // Ubah harga + pindah kategori + ubah deskripsi menu bawaan config
    $data->updateMenu($id, ['harga' => 29000, 'id_kategori' => 'k2'] + $base);
    TestKit::assert(!$raw->sismember('kategori:k1:menu', $id) && $raw->sismember('kategori:k2:menu', $id), 'pindah kategori');
    $m5 = $data->getMenu('m5');
    $data->updateMenu('m5', ['deskripsi' => 'Versi admin.', 'harga' => 36000] + $m5);
    $seeder->syncCatalog($menuData);
    TestKit::same('Versi admin.', $raw->hget('menu:m5', 'deskripsi'), 'sinkron config tidak menimpa teks admin');
    TestKit::same('36000', $raw->hget('menu:m5', 'harga'), 'harga admin tetap');

    // Hapus menu bawaan config: tidak dibuat lagi oleh sinkron, id tidak dipakai ulang
    $data->setTodayPick('m6', true, 3, MenuCatalog::endOfDay('Asia/Jakarta'));
    $data->deleteMenu('m6');
    TestKit::same(null, $data->getMenu('m6'), 'm6 terhapus');
    TestKit::assert(!$raw->sismember('idx:menu', 'm6') && !$raw->sismember('kategori:k2:menu', 'm6'), 'indeks dibersihkan');
    TestKit::assert(!in_array('m6', $data->listTodayPicks(), true), 'keluar dari pilihan dapur');
    $res = $seeder->syncCatalog($menuData);
    TestKit::same(0, $res['menu_baru'], 'sinkron tidak menghidupkan m6');
    TestKit::same(null, $data->getMenu('m6'), 'm6 tetap terhapus');
    $data->deleteMenu($id);
    TestKit::same('m41', $data->createMenu($base), 'id terhapus (m40) tidak dipakai ulang');

    // Validasi form
    $f = MenuForm::validate(['nama_menu' => 'X', 'id_kategori' => 'k9', 'harga' => '28.000', 'status' => 'Ada', 'pasangan' => 'm1'],
        ['k1', 'k2'], ['m1', 'm2'], 'm1');
    TestKit::same(['nama_menu', 'id_kategori', 'status', 'pasangan'], array_keys($f['errors']), 'field yang salah');
    TestKit::same(28000, $f['fields']['harga'], 'harga 28.000 dibaca 28000');
    $ok = MenuForm::validate(['nama_menu' => 'Yuzu Tart', 'id_kategori' => 'k1', 'harga' => '500', 'status' => 'Tersedia'], ['k1'], []);
    TestKit::assert(isset($ok['errors']['harga']), 'harga di bawah Rp1.000 ditolak');
    return 'm40/m41, idx:menu:dihapus, diubah_admin';
});

TestKit::run('T30', 'Akun admin: dibuat dengan hash, email & username tidak boleh dobel, username bisa diganti', function () use ($data) {
    $id = $data->createStaff('Admin Uji', 'Admin@Kokoro.test', password_hash('rahasia-uji-123', PASSWORD_DEFAULT), 'admin', 'admin');
    $s = $data->findStaffByEmail('admin@kokoro.test');
    TestKit::same('admin', $s['role'], 'role admin');
    TestKit::same($id, $s['id'], 'id');
    TestKit::assert(password_verify('rahasia-uji-123', $s['password_hash']) && !str_contains($s['password_hash'], 'rahasia'), 'hanya hash');
    try {
        $data->createStaff('Lain', 'admin@kokoro.test', 'x', 'admin');
        throw new AssertionFailed('email dobel seharusnya ditolak');
    } catch (DomainRejected $e) {
        TestKit::same('EMAIL_TAKEN', $e->errorCode, 'email dobel');
    }
    TestKit::same('USERNAME_TAKEN', TestKit::throws(DomainRejected::class,
        fn () => $data->createStaff('Lain', 'lain@kokoro.test', 'x', 'admin', 'kasir'))->errorCode, 'username dobel');
    TestKit::same($id, $data->findStaffByUsername('admin')['id'] ?? null, 'username admin');
    TestKit::same('updated', $data->setStaffUsername($id, 'pemilik'), 'ganti username');
    TestKit::same(null, $data->findStaffByUsername('admin'), 'username lama tidak berlaku');
    TestKit::same($id, $data->findStaffByUsername('pemilik')['id'] ?? null, 'username baru');
    TestKit::same('USERNAME_TAKEN', TestKit::throws(DomainRejected::class, fn () => $data->setStaffUsername($id, 'kasir'))->errorCode, 'tidak boleh memakai username orang lain');
    return 'users:' . $id;
});

TestKit::run('T31', 'Bayar di kasir: satu tagihan terbuka per meja, batal otomatis lewat batas bayar', function () use ($cart, $order, $data, $raw, $customerA) {
    $cart->add('3C', 'm13', 1);
    $o = $order('3C', $customerA, 'tunai')['order'];
    TestKit::assert($o['batas_bayar'] > time(), 'batas_bayar tercatat');
    $cart->add('3C', 'm9', 1);
    $e = TestKit::throws(DomainRejected::class, fn () => $order('3C', $customerA, 'tunai'));
    TestKit::same('UNPAID_EXISTS', $e->errorCode, 'tagihan kedua di meja yang sama ditolak');
    TestKit::same(1, count($data->getCart('3C')), 'keranjang tetap utuh');
    $q = $order('3C', $customerA, 'qris')['order'];
    TestKit::same('Lunas', $q['status_bayar'], 'QRIS tetap bisa (langsung lunas)');

    // Belum lewat batas: tidak dibatalkan
    TestKit::same(0, $data->expireUnpaidOrders(time(), 'uji'), 'belum lewat batas');
    // Lewat batas: dibatalkan otomatis, tagihan meja dilepas
    TestKit::same(1, $data->expireUnpaidOrders($o['batas_bayar'] + 1, 'Tidak dibayar di kasir dalam 15 menit'), 'dibatalkan otomatis');
    $after = $data->getOrder($o['id'], ['type' => 'kasir']);
    TestKit::same('Dibatalkan', $after['status_pesanan'], 'status');
    TestKit::same('Belum Bayar', $after['status_bayar'], 'tidak ada uang yang perlu dikembalikan');
    TestKit::same(null, $raw->hget('meja:3C', 'tagihan_terbuka'), 'tagihan meja dilepas');
    TestKit::same(0, (int) $raw->zcard('idx:pesanan:belum-bayar'), 'antrean kosong');

    // Pesanan yang sudah dibayar tidak ikut dibatalkan walau lewat batas
    $cart->add('3C', 'm10', 1);
    $p = $order('3C', $customerA, 'tunai')['order'];
    $data->confirmPayment($p['id'], 'kasir:uji');
    TestKit::same(0, $data->expireUnpaidOrders($p['batas_bayar'] + 60, 'uji'), 'yang sudah lunas aman');
    TestKit::same('Masuk', $data->getOrder($p['id'], ['type' => 'kasir'])['status_pesanan'], 'tetap Masuk, siap diproses');
    return 'tagihan_terbuka + idx:pesanan:belum-bayar';
});

TestKit::run('T32', 'Foto unggahan admin disimpan di Redis (tetap ada di hosting serverless)', function () use ($data, $raw) {
    $bytes = (string) file_get_contents(App::root() . '/public/assets/img/menu/foto/m2.webp');
    $url = $data->setMenuPhoto('m7', 'image/webp', $bytes);
    TestKit::assert(preg_match('#^/foto/menu/m7/[a-f0-9]{12}$#', $url) === 1, 'alamat foto berversi');
    TestKit::same($url, $data->getMenu('m7')['foto'], 'field foto menunjuk ke alamat itu');
    TestKit::same($url, menu_photo($data->getMenu('m7')), 'menu_photo memakai foto unggahan');
    $got = $data->getMenuPhoto('m7', substr($url, -12));
    TestKit::same('image/webp', $got['tipe'], 'tipe');
    TestKit::same(strlen($bytes), strlen($got['data']), 'isi file utuh (biner)');
    TestKit::same(null, $data->getMenuPhoto('m7', 'aaaaaaaaaaaa'), 'versi salah ditolak');
    $data->removeMenuPhoto('m7');
    TestKit::same('-', $data->getMenu('m7')['foto'], 'foto dihapus');
    TestKit::same(0, (int) $raw->exists('foto:menu:m7'), 'data foto dihapus');
    $data->setMenuPhoto('m8', 'image/webp', $bytes);
    $data->deleteMenu('m8');
    TestKit::same(0, (int) $raw->exists('foto:menu:m8'), 'hapus menu ikut menghapus foto');
    return strlen($bytes) . ' byte di foto:menu:{id}';
});

TestKit::run('T33', 'Sesi PHP di Redis: baca/tulis, TTL, ID palsu ditolak, perpanjang tanpa tulis ulang', function () use ($raw) {
    $h = new \App\Http\RedisSessionHandler(static fn () => $raw, 120);
    TestKit::assert(!$h->validateId('idpalsu123'), 'ID yang tidak ada ditolak');
    TestKit::same('', $h->read('abc123'), 'sesi baru kosong');
    TestKit::assert($h->write('abc123', 'table|a:1:{s:4:"code";s:2:"1A";}'), 'tulis');
    TestKit::assert($h->validateId('abc123'), 'ID ada');
    TestKit::same('table|a:1:{s:4:"code";s:2:"1A";}', $h->read('abc123'), 'baca');
    $ttl = (int) $raw->ttl('sess:abc123');
    TestKit::assert($ttl > 0 && $ttl <= 120, 'TTL sesi');
    $raw->expire('sess:abc123', 5);
    TestKit::assert($h->updateTimestamp('abc123', 'x'), 'perpanjang');
    TestKit::assert((int) $raw->ttl('sess:abc123') > 5, 'umur diperpanjang');
    TestKit::same('table|a:1:{s:4:"code";s:2:"1A";}', $h->read('abc123'), 'isi tidak berubah saat diperpanjang');
    $h->destroy('abc123');
    TestKit::same(0, (int) $raw->exists('sess:abc123'), 'hapus');
    return 'sess:{id}';
});

/** Jalankan N proses checkout-worker yang mulai serentak. @return list<array> */
function run_workers(int $n, callable $args): array
{
    $startAt = microtime(true) + 1.5;
    $procs = [];
    for ($i = 0; $i < $n; $i++) {
        $cmd = array_merge([PHP_BINARY, __DIR__ . '/checkout-worker.php', (string) $startAt], $args($i));
        $p = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $procs[] = [$p, $pipes];
    }
    $out = [];
    foreach ($procs as [$p, $pipes]) {
        $json = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        proc_close($p);
        $d = json_decode((string) $json, true);
        if (!is_array($d)) {
            throw new AssertionFailed('Worker gagal: ' . $json . $err);
        }
        $out[] = $d;
    }
    return $out;
}

$exit = TestKit::summary('Tes lapisan data');
TestKit::writeReport(App::root() . '/tests/output/hasil-tes-data.md', 'Hasil tes lapisan data Kokoro Cakes');
echo 'Laporan: tests/output/hasil-tes-data.md' . PHP_EOL;
exit($exit);
