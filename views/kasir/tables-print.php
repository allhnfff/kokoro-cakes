<?php
/** Kartu QR meja siap cetak (A4, dua kolom). @var list<array> $tables @var bool $localOnly */
$store = (string) config('store.name');
$logo = owner_image('logo');
?>
<div class="no-print print-bar">
    <div>
        <h1>Cetak QR meja</h1>
        <p class="muted"><?= count($tables) ?> kartu. Gunakan kertas A4; setiap kartu bisa digunting dan ditempel di meja.</p>
        <?php if ($localOnly): ?>
            <p class="alert alert-info small">QR ini menunjuk ke localhost dan tidak bisa dibuka dari HP. Buka halaman ini lewat alamat IP laptop (lihat petunjuk di halaman Meja) sebelum mencetak.</p>
        <?php endif; ?>
    </div>
    <div class="print-bar-actions">
        <a class="btn" href="/kasir/meja">Kembali</a>
        <button type="button" class="btn btn-primary" data-print><?= icon('qr', 18) ?>Cetak</button>
    </div>
</div>

<div class="qr-sheet">
    <?php foreach ($tables as $t): ?>
        <section class="qr-card" aria-label="QR meja <?= e($t['kode']) ?>">
            <header class="qr-card-head">
                <?php if ($logo): ?><img src="<?= e($logo) ?>" alt="" width="34" height="34"><?php endif; ?>
                <span><?= e($store) ?></span>
            </header>
            <p class="qr-card-table">Meja <b><?= e($t['kode']) ?></b></p>
            <div class="qr-card-code"><?= $t['qr_svg'] /* SVG dibuat server dari tautan meja, bukan input pengguna */ ?></div>
            <p class="qr-card-cta">Scan untuk melihat menu dan memesan</p>
            <p class="qr-card-meta"><?= e($t['area']) ?> · <?= (int) $t['kapasitas'] ?> kursi · QR v<?= (int) $t['qr_versi'] ?></p>
        </section>
    <?php endforeach; ?>
</div>
