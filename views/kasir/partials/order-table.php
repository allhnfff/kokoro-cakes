<?php
/** @var list<array> $orders */
use App\Domain\OrderRules;
use App\Http\View;
?>
<div class="table-wrap">
    <table class="data">
        <thead>
            <tr><th>Pesanan</th><th>Meja</th><th>Pemesan</th><th>Waktu</th><th class="num">Total</th><th>Status</th><th>Bayar</th></tr>
        </thead>
        <tbody>
            <?php foreach ($orders as $o): ?>
                <tr>
                    <td><a href="/kasir/pesanan/<?= (int) $o['id'] ?>"><?= e($o['kode_pesanan']) ?></a></td>
                    <td><?= e($o['kode_meja']) ?></td>
                    <td><?= e($o['nama_pemesan']) ?></td>
                    <td><?= e(wib($o['waktu'], 'd/m H:i')) ?></td>
                    <td class="num"><?= rupiah($o['total']) ?></td>
                    <td><?= View::capture('partials/status-badge', ['status' => $o['status_pesanan']]) ?></td>
                    <td><?= View::capture('partials/status-badge', ['status' => $o['status_bayar']]) ?> <small class="muted"><?= e(OrderRules::paymentLabel($o['metode_bayar'])) ?></small></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
