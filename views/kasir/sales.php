<?php
/** @var array $summary @var string $from @var string $to */
?>
<div class="page-head">
    <h1>Rekap penjualan</h1>
    <p class="muted">Hanya pesanan berstatus Selesai, dihitung berdasarkan waktu selesai.</p>
</div>

<form class="filters card" action="/kasir/rekap" method="get">
    <div class="field">
        <label for="dari">Dari</label>
        <input id="dari" type="date" name="dari" value="<?= e($from) ?>">
    </div>
    <div class="field">
        <label for="sampai">Sampai</label>
        <input id="sampai" type="date" name="sampai" value="<?= e($to) ?>">
    </div>
    <div class="field field-btn"><button type="submit" class="btn btn-primary">Tampilkan</button></div>
</form>

<div class="stats">
    <div class="stat"><span>Pesanan selesai</span><b><?= (int) $summary['orders'] ?></b></div>
    <div class="stat"><span>Item terjual</span><b><?= (int) $summary['items'] ?></b></div>
    <div class="stat"><span>Subtotal</span><b><?= rupiah($summary['subtotal']) ?></b></div>
    <div class="stat"><span>Pajak &amp; layanan</span><b><?= rupiah($summary['pajak']) ?></b></div>
    <div class="stat"><span>Total</span><b><?= rupiah($summary['total']) ?></b></div>
</div>

<section class="card">
    <h2>Menu terlaris</h2>
    <?php if (!$summary['menu']): ?>
        <p class="muted">Belum ada pesanan selesai pada rentang ini.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>#</th><th>Menu</th><th class="num">Terjual</th><th class="num">Pendapatan</th></tr></thead>
                <tbody>
                <?php foreach ($summary['menu'] as $i => $m): ?>
                    <tr><td><?= $i + 1 ?></td><td><?= e($m['nama_menu']) ?></td><td class="num"><?= (int) $m['jumlah'] ?></td><td class="num"><?= rupiah($m['pendapatan']) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
