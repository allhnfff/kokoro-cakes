<?php
/** @var list<array> $orders */
use App\Http\View;
?>
<div class="page-head">
    <h1>Pesanan Saya</h1>
    <p class="muted">Pesanan yang dikirim dari perangkat ini.</p>
</div>

<?php if (!$orders): ?>
    <div class="empty">
        <p>Belum ada pesanan dari perangkat ini.</p>
        <a class="btn btn-primary" href="/menu">Lihat menu</a>
    </div>
<?php else: ?>
    <ul class="order-list">
        <?php foreach ($orders as $o): ?>
            <li>
                <a class="order-row card" href="/pesanan/<?= (int) $o['id'] ?>">
                    <span>
                        <b><?= e($o['kode_pesanan']) ?></b>
                        <small class="muted">Meja <?= e($o['kode_meja']) ?> · <?= e(wib($o['waktu'])) ?> · <?= (int) $o['jumlah_item'] ?> item</small>
                    </span>
                    <span class="order-row-end">
                        <?= View::capture('partials/status-badge', ['status' => $o['status_pesanan']]) ?>
                        <b><?= rupiah($o['total']) ?></b>
                    </span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
