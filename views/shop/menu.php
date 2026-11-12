<?php
/**
 * @var list<array> $categories @var array $groups @var string $active @var string $q @var int $total
 * @var string|null $table @var array<string,true> $popular menu paling sering dipesan
 * @var array<string,true> $picks pilihan dapur hari ini
 */
use App\Http\View;

$jpNames = array_column($categories, 'nama_jp', 'id');
?>
<div class="page-head">
    <h1>Menu <span class="ja" lang="ja">お品書き</span></h1>
    <?php if ($table === null): ?>
        <p class="muted">Scan QR di meja untuk bisa menambahkan menu ke keranjang.</p>
    <?php else: ?>
        <p class="muted">Memesan untuk meja <b><?= e($table) ?></b>.</p>
    <?php endif; ?>
</div>

<div class="menu-tools">
    <nav class="chips" aria-label="Filter kategori">
        <a class="chip" href="<?= e(url('/menu', ['q' => $q])) ?>"<?= $active === '' ? ' aria-current="true"' : '' ?>>Semua</a>
        <?php foreach ($categories as $c): ?>
            <a class="chip" href="<?= e(url('/menu', ['kategori' => $c['id'], 'q' => $q])) ?>"<?= $active === $c['id'] ? ' aria-current="true"' : '' ?>><?= e($c['nama_kategori']) ?></a>
        <?php endforeach; ?>
    </nav>
    <form class="search" action="/menu" method="get" role="search">
        <?php if ($active !== ''): ?><input type="hidden" name="kategori" value="<?= e($active) ?>"><?php endif; ?>
        <label class="sr-only" for="q">Cari menu</label>
        <input id="q" type="search" name="q" value="<?= e($q) ?>" placeholder="Cari matcha, mochi, dorayaki…" maxlength="60">
        <button type="submit" class="btn btn-sm">Cari</button>
    </form>
</div>

<p class="small muted live-note" data-menu-poll="<?= (int) config('poll.menu') ?>" aria-live="polite">
    Ketersediaan menu diperbarui otomatis.
</p>

<?php if (!$groups): ?>
    <div class="empty">
        <p>Tidak ada menu yang cocok<?= $q !== '' ? ' dengan “' . e($q) . '”' : '' ?>.</p>
        <a class="btn" href="/menu">Lihat semua menu</a>
    </div>
<?php endif; ?>

<?php foreach ($groups as $catId => $g): ?>
    <section class="menu-section" aria-labelledby="cat-<?= e($catId) ?>">
        <h2 id="cat-<?= e($catId) ?>"><?= e($g['label']) ?><?php if (($jpNames[$catId] ?? '') !== ''): ?> <span class="ja" lang="ja"><?= e($jpNames[$catId]) ?></span><?php endif; ?> <small class="muted">(<?= count($g['items']) ?>)</small></h2>
        <div class="menu-grid">
            <?php foreach ($g['items'] as $m): ?>
                <?= View::capture('partials/menu-card', ['m' => $m, 'table' => $table, 'back' => 'menu', 'popular' => isset($popular[$m['id']]), 'pick' => isset($picks[$m['id']])]) ?>
            <?php endforeach; ?>
        </div>
    </section>
<?php endforeach; ?>

