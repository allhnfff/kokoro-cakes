<?php
/**
 * @var array<string,list<array>> $columns pesanan aktif per status (yang paling lama di atas)
 * @var array $today @var int $unpaid @var list<array> $tables @var int $pollMs
 */
use App\Domain\OrderRules;
use App\Http\View;

$activeCount = array_sum(array_map('count', $columns));
$occupied = count(array_filter($tables, static fn ($t) => $t['status'] === 'Terisi'));
?>
<div class="page-head row-between">
    <h1>Dashboard</h1>
    <p class="small muted" data-kasir-poll="<?= (int) $pollMs ?>" aria-live="polite">Diperbarui <?= e(wib(time(), 'H:i:s')) ?></p>
</div>
<div class="alert alert-info" data-kasir-banner hidden>
    Ada perubahan pesanan. <a href="/kasir">Muat ulang dashboard</a>
</div>

<div class="stats stats-4">
    <div class="stat"><span>Pesanan baru</span><b><?= count($columns['Masuk'] ?? []) ?></b></div>
    <div class="stat"><span>Sedang diproses</span><b><?= count($columns['Diproses'] ?? []) ?></b></div>
    <div class="stat"><span>Siap diantar</span><b><?= count($columns['Siap Diantar'] ?? []) ?></b></div>
    <div class="stat"><span>Selesai hari ini</span><b><?= (int) $today['selesai'] ?></b></div>
</div>
<p class="summary-line">
    <span><b><?= $unpaid ?></b> belum bayar</span>
    <span><b><?= $occupied ?>/<?= count($tables) ?></b> meja terisi</span>
    <span><b><?= rupiah($today['omzet']) ?></b> pendapatan hari ini</span>
</p>

<div class="board">
    <?php foreach ($columns as $status => $list): ?>
        <section class="board-col" aria-labelledby="col-<?= e(status_class($status)) ?>">
            <h2 id="col-<?= e(status_class($status)) ?>"><?= View::capture('partials/status-badge', ['status' => $status]) ?> <span class="muted">(<?= count($list) ?>)</span></h2>
            <?php if (!$list): ?>
                <p class="small muted">Tidak ada pesanan.</p>
            <?php endif; ?>
            <?php foreach ($list as $o):
                $next = OrderRules::nextStatus($o['status_pesanan']);
                $needPay = $next !== null && $o['status_bayar'] !== 'Lunas'; // bayar dulu, baru disiapkan
            ?>
                <article class="ticket">
                    <header>
                        <a href="/kasir/pesanan/<?= (int) $o['id'] ?>"><b>Meja <?= e($o['kode_meja']) ?></b> · <?= e($o['kode_pesanan']) ?></a>
                        <span class="small muted"><?= e(wib($o['waktu'], 'H:i')) ?></span>
                    </header>
                    <p class="small"><?= e($o['nama_pemesan']) ?> · <?= (int) $o['jumlah_item'] ?> item · <?= rupiah($o['total']) ?></p>
                    <p class="small"><?= View::capture('partials/status-badge', ['status' => $o['status_bayar']]) ?> <?= e(OrderRules::paymentLabel($o['metode_bayar'])) ?><?= OrderRules::awaitingPayment($o) && $o['batas_bayar'] > 0 ? ' · batal otomatis ' . e(wib($o['batas_bayar'], 'H:i')) : '' ?></p>
                    <div class="ticket-actions">
                        <?php if ($o['status_bayar'] === 'Belum Bayar'): ?>
                            <form action="/kasir/pesanan/<?= (int) $o['id'] ?>/bayar" method="post" data-once>
                                <?= csrf_field() ?><input type="hidden" name="kembali" value="dashboard">
                                <button type="submit" class="btn btn-sm btn-primary">Terima bayar</button>
                            </form>
                        <?php endif; ?>
                        <?php if ($next !== null): ?>
                            <form action="/kasir/pesanan/<?= (int) $o['id'] ?>/status" method="post" data-once>
                                <?= csrf_field() ?><input type="hidden" name="kembali" value="dashboard">
                                <input type="hidden" name="from" value="<?= e($o['status_pesanan']) ?>">
                                <input type="hidden" name="to" value="<?= e($next) ?>">
                                <button type="submit" class="btn btn-sm btn-primary"<?= $needPay ? ' disabled title="Terima bayar dulu"' : '' ?>><?= e(OrderRules::actionLabel($next)) ?></button>
                            </form>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endforeach; ?>
</div>

<section aria-labelledby="meja-head">
    <div class="section-head">
        <h2 id="meja-head">Status meja</h2>
        <a href="/kasir/meja">Kelola meja</a>
    </div>
    <div class="table-grid">
        <?php foreach ($tables as $t): ?>
            <a class="table-pill <?= e(status_class($t['aktif'] ? $t['status'] : 'Nonaktif')) ?>" href="<?= e(url('/kasir/pesanan', ['meja' => $t['kode']])) ?>">
                <b><?= e($t['kode']) ?></b>
                <small><?= $t['aktif'] ? e($t['status']) : 'Nonaktif' ?><?= $t['status'] === 'Terisi' && $t['terisi_sejak'] ? ' sejak ' . e(wib($t['terisi_sejak'], 'H.i')) : '' ?><?= $t['pesanan_aktif'] ? ' · ' . (int) $t['pesanan_aktif'] . ' pesanan' : '' ?></small>
            </a>
        <?php endforeach; ?>
    </div>
</section>
