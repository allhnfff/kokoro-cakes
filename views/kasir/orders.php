<?php
/** @var list<array> $orders @var array $filter @var int $total @var int $page @var int $pages @var list<string> $tables */
use App\Domain\OrderRules;
use App\Http\View;
?>
<div class="page-head">
    <h1>Pesanan</h1>
</div>

<form class="filters card" action="/kasir/pesanan" method="get">
    <div class="field">
        <label for="f-q">Cari</label>
        <input id="f-q" type="search" name="q" value="<?= e($filter['q']) ?>" placeholder="Nomor pesanan atau nama">
    </div>
    <div class="field">
        <label for="f-status">Status</label>
        <select id="f-status" name="status">
            <option value="">Semua</option>
            <?php foreach (OrderRules::STATUSES as $s): ?>
                <option value="<?= e($s) ?>"<?= $filter['status'] === $s ? ' selected' : '' ?>><?= e($s) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label for="f-meja">Meja</label>
        <select id="f-meja" name="meja">
            <option value="">Semua</option>
            <?php foreach ($tables as $t): ?>
                <option value="<?= e($t) ?>"<?= $filter['meja'] === $t ? ' selected' : '' ?>><?= e($t) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field field-btn">
        <button type="submit" class="btn btn-primary">Terapkan</button>
        <a class="btn btn-ghost" href="/kasir/pesanan">Reset</a>
    </div>
</form>

<p class="small muted"><?= (int) $total ?> pesanan ditemukan.</p>

<?php if (!$orders): ?>
    <div class="empty"><p>Belum ada pesanan yang cocok.</p></div>
<?php else: ?>
    <?= View::capture('kasir/partials/order-table', ['orders' => $orders]) ?>
<?php endif; ?>

<?php if ($pages > 1): ?>
    <nav class="pager" aria-label="Halaman">
        <?php for ($i = 1; $i <= $pages; $i++): ?>
            <a href="<?= e(url('/kasir/pesanan', ['q' => $filter['q'], 'status' => $filter['status'], 'meja' => $filter['meja'], 'hal' => $i])) ?>"<?= $i === $page ? ' aria-current="page"' : '' ?>><?= $i ?></a>
        <?php endfor; ?>
    </nav>
<?php endif; ?>
