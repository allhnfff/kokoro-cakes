<?php
/** @var array $order @var string|null $next */
use App\Domain\OrderRules;
use App\Http\View;

$status = $order['status_pesanan'];
// Bayar dulu, baru disiapkan: semua langkah maju terkunci sampai Lunas.
$needPay = $next !== null && $order['status_bayar'] !== 'Lunas';
?>
<nav class="crumbs small"><a href="/kasir/pesanan">Pesanan</a> / <?= e($order['kode_pesanan']) ?></nav>
<div class="page-head row-between">
    <div>
        <h1><?= e($order['kode_pesanan']) ?></h1>
        <p class="muted">Meja <b><?= e($order['kode_meja']) ?></b> · <?= e($order['nama_pemesan']) ?> · <?= e(wib($order['waktu'])) ?></p>
    </div>
    <div class="order-badges">
        <?= View::capture('partials/status-badge', ['status' => $status]) ?>
        <?= View::capture('partials/status-badge', ['status' => $order['status_bayar']]) ?>
    </div>
</div>

<div class="two-col">
    <section class="card">
        <h2>Rincian</h2>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>Menu</th><th class="num">Jumlah</th><th class="num">Harga saat pesan</th><th class="num">Subtotal</th></tr></thead>
                <tbody>
                <?php foreach ($order['items'] as $it): ?>
                    <tr><td><?= e($it['nama_menu']) ?></td><td class="num"><?= (int) $it['jumlah'] ?></td><td class="num"><?= rupiah($it['harga_satuan']) ?></td><td class="num"><?= rupiah($it['subtotal']) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= View::capture('partials/cost-summary', ['quote' => $order]) ?>
        <?php if ($order['catatan'] !== ''): ?>
            <p class="note-box"><b>Catatan:</b> <?= e($order['catatan']) ?></p>
        <?php endif; ?>
        <p class="small muted">Metode bayar: <?= e(OrderRules::paymentLabel($order['metode_bayar'])) ?><?= $order['dibayar_at'] ? ' · lunas ' . e(wib($order['dibayar_at'], 'H:i')) : '' ?></p>
    </section>

    <section class="card">
        <h2>Tindakan</h2>
        <?php if ($order['status_bayar'] === 'Belum Bayar' && $status !== 'Dibatalkan'): ?>
            <form action="/kasir/pesanan/<?= (int) $order['id'] ?>/bayar" method="post" class="action-row" data-once>
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-primary">Terima pembayaran <?= rupiah($order['total']) ?></button>
            </form>
        <?php endif; ?>
        <?php if ($next !== null): ?>
            <form action="/kasir/pesanan/<?= (int) $order['id'] ?>/status" method="post" class="action-row" data-once>
                <?= csrf_field() ?>
                <input type="hidden" name="from" value="<?= e($status) ?>">
                <input type="hidden" name="to" value="<?= e($next) ?>">
                <?php if (!$needPay): ?>
                    <label for="catatan-status" class="small">Catatan <span class="muted">(opsional, tampil di riwayat pesanan)</span></label>
                    <input id="catatan-status" type="text" name="catatan" maxlength="120" placeholder="Contoh: minuman menyusul 2 menit">
                <?php endif; ?>
                <button type="submit" class="btn btn-primary"<?= $needPay ? ' disabled' : '' ?>><?= e(OrderRules::actionLabel($next)) ?></button>
                <?php if ($needPay): ?><p class="small muted">Terima pembayaran dulu; pesanan baru boleh disiapkan setelah lunas.<?= $order['batas_bayar'] > 0 ? ' Batal otomatis pukul ' . e(wib($order['batas_bayar'], 'H:i')) . ' jika belum dibayar.' : '' ?></p><?php endif; ?>
            </form>
        <?php elseif ($status === 'Selesai'): ?>
            <p class="muted">Pesanan selesai <?= e(wib($order['selesai_at'], 'H:i')) ?>.</p>
        <?php endif; ?>

        <?php if (OrderRules::canCancel($status)): ?>
            <details class="cancel-box">
                <summary>Batalkan pesanan</summary>
                <form action="/kasir/pesanan/<?= (int) $order['id'] ?>/batal" method="post" data-once>
                    <?= csrf_field() ?>
                    <label for="reason">Alasan (wajib)</label>
                    <input id="reason" name="reason" minlength="5" maxlength="200" required placeholder="Contoh: bahan habis">
                    <button type="submit" class="btn btn-danger">Batalkan</button>
                </form>
            </details>
        <?php endif; ?>

        <h3>Riwayat</h3>
        <ol class="history">
            <?php foreach (array_reverse($order['history']) as $h): ?>
                <li><span class="small muted"><?= e(wib($h['at'], 'd/m H:i')) ?> · <?= e($h['by']) ?></span><br><?= e($h['note']) ?></li>
            <?php endforeach; ?>
        </ol>
    </section>
</div>
