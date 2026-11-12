<?php
/** @var string $content @var array|null $kasir */
$path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$is = static fn (string $p): string => ($p === '/kasir' ? $path === '/kasir' : str_starts_with($path, $p)) ? ' aria-current="page"' : '';
?><!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?= e(($title ?? 'Kasir') . ' · Kasir ' . config('store.name')) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Shippori+Mincho:wght@600;700;800&family=Zen+Kaku+Gothic+New:wght@400;500;700&display=swap">
    <link rel="stylesheet" href="<?= asset('assets/css/app.css') ?>">
    <?= favicon_tag() ?>
</head>
<body class="kasir">
<a class="skip-link" href="#main">Lewati ke konten</a>
<header class="hdr hdr-dark">
    <div class="container-wide hdr-in">
        <a class="brand" href="/kasir">
            <?= brand_mark(($kasir['role'] ?? '') === 'admin' ? 'Admin' : 'Kasir', 'logo-light') ?>
        </a>
        <?php if (!empty($kasir)): ?>
            <nav class="main-nav kasir-nav" aria-label="Navigasi kasir">
                <a href="/kasir"<?= $is('/kasir') ?>>Dashboard</a>
                <a href="/kasir/pesanan"<?= $is('/kasir/pesanan') ?>>Pesanan</a>
                <a href="/kasir/menu"<?= $is('/kasir/menu') ?>>Menu</a>
                <a href="/kasir/meja"<?= $is('/kasir/meja') ?>>Meja</a>
                <a href="/kasir/rekap"<?= $is('/kasir/rekap') ?>>Rekap</a>
                <?php if (($kasir['role'] ?? '') === 'admin'): ?>
                    <a href="/kasir/kelola-menu"<?= $is('/kasir/kelola-menu') ?>>Kelola Menu</a>
                <?php endif; ?>
            </nav>
            <form action="/kasir/logout" method="post" class="kasir-user">
                <?= csrf_field() ?>
                <span class="small"><?= e($kasir['nama']) ?></span>
                <button class="btn btn-sm btn-on-dark" type="submit"><?= icon('logout', 16) ?>Keluar</button>
            </form>
        <?php endif; ?>
    </div>
</header>
<main id="main" class="container-wide page">
    <?php foreach (($flash ?? []) as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?>" role="<?= $f['type'] === 'error' ? 'alert' : 'status' ?>"><?= e($f['message']) ?></div>
    <?php endforeach; ?>
    <?= $content ?>
</main>
<footer class="ftr ftr-kasir">
    <div class="container-wide small muted">
        Halaman staf <?= e((string) config('store.name')) ?>. Sumber data: <?= e($source['label'] ?? '-') ?>.
        <a href="/" target="_blank" rel="noopener">Buka halaman pelanggan</a>
    </div>
</footer>
<script src="<?= asset('assets/js/app.js') ?>" defer></script>
</body>
</html>
