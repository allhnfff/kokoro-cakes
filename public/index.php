<?php

declare(strict_types=1);

/**
 * PINTU MASUK SEMUA HALAMAN (front controller).
 * Jalankan: php -S localhost:8000 -t public public/index.php
 */

// Built-in server PHP: biarkan file statis (CSS/JS/gambar) dilayani langsung.
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if ($file !== __DIR__ . '/' && is_file($file)) {
        return false;
    }
}

use App\App;
use App\Controllers\AdminController;
use App\Controllers\ApiController;
use App\Controllers\CartController;
use App\Controllers\CheckoutController;
use App\Controllers\KasirController;
use App\Controllers\OrderController;
use App\Controllers\ShopController;
use App\Data\DataServiceUnavailable;
use App\Http\HttpException;
use App\Http\Router;
use App\Http\SecurityHeaders;
use App\Http\Session;
use App\Http\View;

$isApi = str_starts_with((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/api/');

try {
    require dirname(__DIR__) . '/app/bootstrap.php';
} catch (\Throwable $e) {
    http_response_code(500);
    echo '<h1>Konfigurasi bermasalah</h1><p>' . htmlspecialchars($e->getMessage()) . '</p>';
    exit;
}

// Header keamanan dasar (lihat App\Http\SecurityHeaders; mode preview lokal hanya jika APP_ENV=local)
foreach (SecurityHeaders::build((array) App::config('app'), (array) App::config('preview')) as $h) {
    header($h);
}

// Foto menu unggahan admin (disimpan di Redis): dilayani tanpa sesi dan tanpa kode lain.
if (\App\Controllers\PhotoController::serve((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH))) {
    exit;
}

Session::start();

$shop = new ShopController();
$cart = new CartController();
$checkout = new CheckoutController();
$orders = new OrderController();
$api = new ApiController();
$kasir = new KasirController();
$admin = new AdminController();

$r = new Router();
// ---- Pelanggan ----
$r->get('/', [$shop, 'home']);
$r->get('/menu', [$shop, 'menu']);
$r->get('/panduan', [$shop, 'guide']);
$r->get('/menu/{id}', [$shop, 'menuShow']);
$r->get('/meja/{kode}/{token}', [$shop, 'enterTable']);
$r->post('/meja/keluar', [$shop, 'leaveTable']);
$r->get('/keranjang', [$cart, 'show']);
$r->post('/keranjang/tambah', [$cart, 'add']);
$r->post('/keranjang/ubah', [$cart, 'update']);
$r->post('/keranjang/hapus', [$cart, 'remove']);
$r->get('/checkout', [$checkout, 'show']);
$r->post('/checkout/tinjau', [$checkout, 'review']);
$r->get('/checkout/tinjau', [$checkout, 'reviewPage']);
$r->post('/checkout/konfirmasi', [$checkout, 'confirm']);
$r->post('/checkout/periksa', [$checkout, 'verify']);
$r->post('/checkout/batal', [$checkout, 'cancel']);
$r->get('/pesanan', [$orders, 'index']);
$r->get('/pesanan/{id}', [$orders, 'show']);
$r->post('/pesanan/{id}/batal', [$orders, 'cancel']);
$r->post('/pesanan/{id}/pesan-lagi', [$orders, 'reorder']);
// ---- API untuk JavaScript (lewat server, bukan langsung ke Redis) ----
$r->get('/api/menu', [$api, 'menu']);
$r->get('/api/pesanan/{id}', [$api, 'order']);
$r->get('/api/kasir/ringkasan', [$api, 'kasirSummary']);
$r->get('/api/status', [$api, 'status']);
// ---- Kasir ----
$r->get('/kasir/login', [$kasir, 'loginForm']);
$r->post('/kasir/login', [$kasir, 'login']);
$r->post('/kasir/logout', [$kasir, 'logout']);
$r->get('/kasir', [$kasir, 'dashboard']);
$r->get('/kasir/pesanan', [$kasir, 'orders']);
$r->get('/kasir/pesanan/{id}', [$kasir, 'orderShow']);
$r->post('/kasir/pesanan/{id}/status', [$kasir, 'updateStatus']);
$r->post('/kasir/pesanan/{id}/bayar', [$kasir, 'confirmPayment']);
$r->post('/kasir/pesanan/{id}/batal', [$kasir, 'cancel']);
$r->get('/kasir/menu', [$kasir, 'menu']);
$r->post('/kasir/menu/{id}/status', [$kasir, 'menuStatus']);
$r->post('/kasir/menu/{id}/pilihan', [$kasir, 'menuPick']);
$r->get('/kasir/meja', [$kasir, 'tables']);
$r->get('/kasir/meja/cetak', [$kasir, 'tablesPrint']);
$r->post('/kasir/meja/{kode}/ganti-qr', [$admin, 'rotateQr']);
$r->post('/kasir/meja/{kode}/kosongkan', [$kasir, 'releaseTable']);
$r->get('/kasir/rekap', [$kasir, 'sales']);
// ---- Admin: kelola menu ('baru' didaftarkan sebelum {id}) ----
$r->get('/kasir/kelola-menu', [$admin, 'menuIndex']);
$r->get('/kasir/kelola-menu/baru', [$admin, 'menuCreateForm']);
$r->post('/kasir/kelola-menu/baru', [$admin, 'menuStore']);
$r->get('/kasir/kelola-menu/{id}', [$admin, 'menuEditForm']);
$r->post('/kasir/kelola-menu/{id}', [$admin, 'menuUpdate']);
$r->post('/kasir/kelola-menu/{id}/hapus', [$admin, 'menuDelete']);

$renderError = static function (int $status, string $title, string $message, bool $isApi): void {
    if ($isApi) {
        View::json(['ok' => false, 'message' => $message], $status);
        return;
    }
    try {
        View::render('shop/error', ['title' => $title, 'status' => $status, 'message' => $message, 'flash' => [],
            'source' => App::data()->describe(), 'table' => Session::table(), 'cartCount' => null], 'layout', $status);
    } catch (\Throwable) {
        http_response_code($status);
        echo '<h1>' . htmlspecialchars($title) . '</h1><p>' . htmlspecialchars($message) . '</p>';
    }
};

// Bayar dulu, baru disiapkan: pesanan "bayar di kasir" yang lewat batas bayar dibatalkan otomatis.
// Hanya satu ZRANGEBYSCORE pada idx:pesanan:belum-bayar, jadi ringan dijalankan di setiap permintaan
// (polling dashboard kasir ikut memicunya). Jika Redis gangguan, biarkan halaman yang menampilkan errornya.
try {
    App::data()->expireUnpaidOrders(time(), 'Tidak dibayar di kasir dalam ' . (int) App::config('order.unpaid_minutes') . ' menit');
} catch (DataServiceUnavailable) {
}

try {
    $r->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
} catch (HttpException $e) {
    $renderError($e->status, 'Terjadi kendala', $e->getMessage(), $isApi);
} catch (DataServiceUnavailable $e) {
    error_log('[kokoro] redis: ' . $e->getMessage());
    $msg = 'Data sedang tidak dapat dimuat karena server data (Redis) tidak dapat dihubungi. '
        . 'Tidak ada perubahan yang disimpan dari permintaan ini.';
    if (App::config('app.debug')) {
        $msg .= ' Detail: ' . $e->getMessage();
    }
    $renderError(503, 'Server data tidak tersedia', $msg, $isApi);
} catch (\Throwable $e) {
    error_log('[kokoro] ' . $e::class . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    $msg = App::config('app.debug')
        ? $e::class . ': ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')'
        : 'Terjadi kesalahan pada server.';
    $renderError(500, 'Kesalahan server', $msg, $isApi);
}
