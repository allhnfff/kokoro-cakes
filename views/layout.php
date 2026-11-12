<?php
/** @var string $content @var string|null $table @var int|null $cartCount */
$storeName = (string) config('store.name');
$path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$isActive = static fn (string $p): bool => $p === '/' ? $path === '/' : str_starts_with($path, $p);
$current = static fn (string $p): string => $isActive($p) ? ' aria-current="page"' : '';
$table = $table ?? null;
$cartCount = $cartCount ?? null;
$cartText = (string) (int) $cartCount;
$badgeHidden = (int) $cartCount > 0 ? '' : ' hidden';
$cartLabel = $table === null ? 'Keranjang (scan QR meja dulu)' : 'Keranjang, ' . (int) $cartCount . ' item';
$nav = [['/', 'Beranda'], ['/menu', 'Menu'], ['/pesanan', 'Pesanan Saya'], ['/panduan', 'Cara Pesan']];
?><!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(($title ?? 'Beranda') . ' · ' . $storeName) ?></title>
    <meta name="description" content="<?= e($storeName) ?>: kafe kue dan teh bergaya Jepang. Pesan cake, wagashi, pastry, dan minuman langsung dari meja.">
    <meta name="theme-color" content="#2F4A3A">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Shippori+Mincho:wght@600;700;800&family=Zen+Kaku+Gothic+New:wght@400;500;700&display=swap">
    <link rel="stylesheet" href="<?= asset('assets/css/app.css') ?>">
    <?= favicon_tag() ?>
</head>
<body class="shop">
<a class="skip-link" href="#main">Lewati ke konten</a>

<header class="hdr">
    <div class="container hdr-in">
        <a class="brand" href="/"<?= $current('/') ?>>
            <?= brand_mark((string) config('store.tagline')) ?>
        </a>
        <nav class="main-nav" aria-label="Navigasi utama">
            <?php foreach ($nav as [$href, $label]): ?>
                <a href="<?= $href ?>"<?= $current($href) ?>><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>
        <div class="hdr-actions">
            <?php if ($table !== null): ?>
                <span class="table-chip" title="Kamu memesan dari meja ini"><?= icon('table', 18) ?>Meja <?= e($table) ?></span>
            <?php else: ?>
                <span class="table-chip table-chip-empty"><?= icon('qr', 18) ?>Belum di meja</span>
            <?php endif; ?>
            <a class="cart-btn" href="/keranjang" aria-label="<?= e($cartLabel) ?>"<?= $current('/keranjang') ?>>
                <?= icon('cart', 24) ?><span class="cart-badge" aria-hidden="true" data-cart-count<?= $badgeHidden ?>><?= e($cartText) ?></span>
            </a>
        </div>
    </div>
</header>

<main id="main" class="container page">
    <?php foreach (($flash ?? []) as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?>" role="<?= $f['type'] === 'error' ? 'alert' : 'status' ?>"><?= e($f['message']) ?></div>
    <?php endforeach; ?>
    <?= $content ?>
</main>

<footer class="ftr">
        <div class="container ftr-in">
            <div>
                <b><?= e($storeName) ?></b>
                <?php $info = array_filter([(string) config('store.address'), (string) config('store.hours') !== '' ? 'Buka ' . config('store.hours') : '']); ?>
                <?php if ($info): ?><p class="muted small"><?= e(implode(' · ', $info)) ?></p><?php endif; ?>
            </div>
            <div class="small muted">
                <?php if ($table !== null): ?>
                    <form action="/meja/keluar" method="post" class="inline-form">
                        <?= csrf_field() ?>
                        Memesan dari meja <?= e($table) ?>.
                        <button type="submit" class="link-btn">Keluar dari meja</button>
                    </form>
                    <br>
                <?php endif; ?>
                <?php if ((string) config('store.developer') !== ''): ?>
                    Dibuat oleh <?= e((string) config('store.developer')) ?>
                <?php endif; ?>
            </div>
        </div>
</footer>

<nav class="tabbar" aria-label="Navigasi bawah">
    <a href="/"<?= $current('/') ?>><?= icon('home') ?><span>Beranda</span></a>
    <a href="/menu"<?= $current('/menu') ?>><?= icon('menu') ?><span>Menu</span></a>
    <a href="/keranjang" class="tab-cart" aria-label="<?= e($cartLabel) ?>"<?= $current('/keranjang') ?>><span class="tab-icon"><?= icon('cart') ?><span class="cart-badge" aria-hidden="true" data-cart-count<?= $badgeHidden ?>><?= e($cartText) ?></span></span><span>Keranjang</span></a>
    <a href="/pesanan"<?= $current('/pesanan') ?>><?= icon('receipt') ?><span>Pesanan</span></a>
    <a href="/panduan"<?= $current('/panduan') ?>><?= icon('help') ?><span>Cara Pesan</span></a>
</nav>

<?php if ($table !== null): ?>
<div class="toast" data-toast role="status" aria-live="polite" hidden>
    <span data-toast-text></span>
    <a href="/keranjang" data-toast-link>Lihat keranjang</a>
</div>
<?php endif; ?>

<script src="<?= asset('assets/js/app.js') ?>" defer></script>
</body>
</html>
