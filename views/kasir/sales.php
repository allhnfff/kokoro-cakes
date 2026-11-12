<?php
/** Laporan penjualan (wireframe kasir no. 9). @var array $summary @var string $from @var string $to @var int $transaksi @var list<array> $riwayat */
?>
<div class="page-head">
    <h1>Laporan penjualan</h1>
    <p class="muted">Pendapatan dihitung dari pesanan berstatus Selesai, berdasarkan waktu selesai.</p>
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

<div class="stats stats-4">
    <div class="stat"><span>Total transaksi</span><b><?= (int) $transaksi ?></b></div>
    <div class="stat"><span>Total pendapatan</span><b><?= rupiah($summary['total']) ?></b></div>
    <div class="stat"><span>Pesanan selesai</span><b><?= (int) $summary['orders'] ?></b></div>
    <div class="stat"><span>Rata-rata transaksi</span><b><?= rupiah($summary['orders'] ? intdiv($summary['total'], $summary['orders']) : 0) ?></b></div>
</div>
<p class="summary-line">
    <span><b><?= (int) $summary['items'] ?></b> item terjual</span>
    <span><b><?= rupiah($summary['subtotal']) ?></b> subtotal</span>
    <span><b><?= rupiah($summary['pajak']) ?></b> pajak &amp; layanan</span>
</p>
</div>

<section class="card">
    <h2>Menu terlaris</h2>
    <?php if (!$summary['menu']): ?>
        <p class="muted">Belum ada pesanan selesai pada rentang ini.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>#</th><th>Menu</th><th class="bar-col">Porsi terjual</th><th class="num">Terjual</th><th class="num">Pendapatan</th></tr></thead>
                <tbody>
                <?php $top = max(1, (int) ($summary['menu'][0]['jumlah'] ?? 1)); ?>
                <?php foreach ($summary['menu'] as $i => $m): ?>
                    <tr><td><?= $i + 1 ?></td><td><?= e($m['nama_menu']) ?></td>
                        <td class="bar-col"><span class="sale-bar" style="--w: <?= round(100 * (int) $m['jumlah'] / $top) ?>%" aria-hidden="true"></span></td>
                        <td class="num"><?= (int) $m['jumlah'] ?></td><td class="num"><?= rupiah($m['pendapatan']) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="card">
    <h2>Riwayat transaksi</h2>
    <?php if (!$riwayat): ?>
        <p class="muted">Belum ada pesanan selesai pada rentang ini.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>No. pesanan</th><th>Meja</th><th>Selesai</th><th class="num">Total</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($riwayat as $o): ?>
                    <tr><td><a href="/kasir/pesanan/<?= (int) $o['id'] ?>"><?= e($o['kode_pesanan']) ?></a></td><td><?= e($o['kode_meja']) ?></td>
                        <td><?= e(wib($o['selesai_at'], 'd/m H:i')) ?></td><td class="num"><?= rupiah($o['total']) ?></td><td><span class="badge st-selesai">Selesai</span></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
