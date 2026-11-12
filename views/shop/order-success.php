<?php
/** Konfirmasi tepat setelah pesanan dikirim (wireframe pelanggan no. 7). @var array $order */
use App\Domain\OrderRules;
use App\Http\View;

$awaitPay = OrderRules::awaitingPayment($order);
?>
<?= View::capture('partials/checkout-steps', ['step' => 3]) ?>

<section class="success card narrow">
    <div class="success-mark" aria-hidden="true"><?= icon('check', 44) ?></div>
    <h1>Pesanan berhasil!</h1>
    <?php if ($awaitPay): ?>
        <p class="muted">Pesananmu sudah tercatat. <b>Bayar dulu di kasir</b>, lalu pesanan langsung disiapkan dan diantar ke meja <?= e($order['kode_meja']) ?>.</p>
    <?php else: ?>
        <p class="muted">Pembayaran diterima dan pesanan sudah dikirim ke dapur. Tunggu di meja <?= e($order['kode_meja']) ?>, ya.</p>
    <?php endif; ?>

    <dl class="kv success-detail">
        <div><dt>Nomor pesanan</dt><dd><b><?= e($order['kode_pesanan']) ?></b></dd></div>
        <div><dt>Nomor meja</dt><dd><?= e($order['kode_meja']) ?></dd></div>
        <div><dt>Waktu pesan</dt><dd><?= e(wib($order['waktu'], 'H:i')) ?></dd></div>
        <div><dt>Metode pembayaran</dt><dd><?= e(OrderRules::paymentLabel($order['metode_bayar'])) ?></dd></div>
        <div><dt>Status bayar</dt><dd><?= e($order['status_bayar']) ?></dd></div>
        <div class="success-total"><dt>Total</dt><dd><?= rupiah($order['total']) ?></dd></div>
    </dl>
    <?php if ($awaitPay && $order['batas_bayar'] > 0): ?>
        <p class="alert alert-info small">Sebutkan nomor <b><?= e($order['kode_pesanan']) ?></b> ke kasir sebelum pukul <b><?= e(wib($order['batas_bayar'], 'H:i')) ?></b>; setelah itu pesanan dibatalkan otomatis.</p>
    <?php endif; ?>

    <div class="success-actions">
        <a class="btn btn-primary btn-block" href="/pesanan/<?= (int) $order['id'] ?>">Lihat status pesanan</a>
        <a class="btn btn-block" href="/">Kembali ke beranda</a>
    </div>
</section>
