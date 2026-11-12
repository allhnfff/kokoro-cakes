<?php
/**
 * @var array $item @var string $categoryName @var list<array> $related @var int $maxQty @var string|null $table
 * @var array{menu:array, alasan:string}|null $pairing saran pasangan (hanya jika pasangannya Tersedia)
 */
use App\Http\View;
?>
<nav class="crumbs small" aria-label="Lokasi halaman">
    <a href="/menu">Menu</a> / <a href="<?= e(url('/menu', ['kategori' => $item['id_kategori']])) ?>"><?= e($categoryName) ?></a> / <span><?= e($item['nama_menu']) ?></span>
</nav>

<?php $photo = menu_photo($item); ?>
<article class="item-detail<?= $photo ? '' : ' no-photo' ?>" data-menu-id="<?= e($item['id']) ?>">
    <?php if ($photo): ?>
        <div class="item-img">
            <img src="<?= e($photo) ?>" alt="<?= e($item['nama_menu']) ?>" width="560" height="560" fetchpriority="high">
        </div>
    <?php endif; ?>
    <div class="item-info">
        <span class="sold-stamp" data-sold-tag<?= $item['tersedia'] ? ' hidden' : '' ?>><span lang="ja">売切</span><small>Habis</small></span>
        <p class="meta"><?= e($categoryName) ?></p>
        <?php if ($item['nama_jp'] !== ''): ?><p class="item-jp" lang="ja"><?= e($item['nama_jp']) ?></p><?php endif; ?>
        <h1><?= e($item['nama_menu']) ?></h1>
        <p class="price price-lg"><?= rupiah($item['harga']) ?></p>
        <p><?= e($item['deskripsi']) ?></p>
        <?php if ($photo): ?><p class="small muted">Foto adalah ilustrasi penyajian; tampilan asli bisa sedikit berbeda.</p><?php endif; ?>

        <?php if ($table === null): ?>
            <div class="alert alert-info">Scan QR di mejamu untuk memesan menu ini.</div>
        <?php else: ?>
            <form action="/keranjang/tambah" method="post" class="qty-form" data-add-form>
                <?= csrf_field() ?>
                <input type="hidden" name="id_menu" value="<?= e($item['id']) ?>">
                <input type="hidden" name="kembali" value="detail">
                <label for="qty">Jumlah</label>
                <div class="stepper" data-stepper>
                    <button type="button" class="step-btn" data-step="-1" aria-label="Kurangi"><?= icon('minus', 18) ?></button>
                    <input id="qty" type="number" name="qty" value="1" min="1" max="<?= (int) $maxQty ?>" inputmode="numeric" required>
                    <button type="button" class="step-btn" data-step="1" aria-label="Tambah"><?= icon('plus', 18) ?></button>
                </div>
                <button type="submit" class="btn btn-primary btn-block" data-add-btn<?= $item['tersedia'] ? '' : ' disabled' ?>>
                    <?= $item['tersedia'] ? 'Tambah ke keranjang meja ' . e($table) : 'Sedang habis' ?>
                </button>
            </form>
        <?php endif; ?>

        <?php if ($pairing !== null): ?>
            <?= View::capture('partials/pairing', ['pair' => $pairing['menu'], 'alasan' => $pairing['alasan'], 'table' => $table, 'back' => 'detail', 'heading' => 'Enak dinikmati bersama']) ?>
        <?php endif; ?>
    </div>
</article>

<?php if ($related): ?>
<section aria-labelledby="lainnya">
    <h2 id="lainnya">Menu <?= e($categoryName) ?> lainnya</h2>
    <div class="menu-grid">
        <?php foreach ($related as $m): ?>
            <?= View::capture('partials/menu-card', ['m' => $m, 'table' => $table, 'back' => 'menu']) ?>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>
<p class="small muted live-note" data-menu-poll="<?= (int) config('poll.menu') ?>" aria-live="polite"></p>
