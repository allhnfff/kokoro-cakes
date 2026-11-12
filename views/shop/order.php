<?php
/** @var array $order @var bool $isNew @var array<string,string> $cancelReasons */
use App\Domain\OrderRules;
use App\Http\View;

$status = $order['status_pesanan'];
$cancelled = $status === 'Dibatalkan';
$active = OrderRules::isActive($status);
$awaitPay = OrderRules::awaitingPayment($order);
// Peringatan saat sisa waktu bayar <= 5 menit (atau separuh batas jika batasnya pendek).
$warnSeconds = min(300, intdiv(60 * (int) config('order.unpaid_minutes'), 2));
$left = max(0, $order['batas_bayar'] - time());
$urgent = $awaitPay && $order['batas_bayar'] > 0 && $left <= $warnSeconds;
$minutesLeft = (int) ceil($left / 60);
$countdown = $left <= 0 ? 'Waktu bayar habis. Memeriksa status pesanan…'
    : ($urgent ? 'Sisa ' . ($left < 60 ? 'kurang dari 1' : $minutesLeft) . ' menit, segera ke kasir!'
    : 'Sisa <b>' . $minutesLeft . ' menit</b> untuk membayar.');
?>
<?php if ($isNew): ?>
    <?= View::capture('partials/checkout-steps', ['step' => 3]) ?>
    <?php if (!$awaitPay): ?>
        <div class="alert alert-success">Pesanan terkirim ke dapur. Simpan nomor pesanan <b><?= e($order['kode_pesanan']) ?></b>.</div>
    <?php endif; ?>
<?php endif; ?>

<div class="order-head" data-order-poll="<?= (int) $order['id'] ?>" data-status="<?= e($status) ?>" data-pay="<?= e($order['status_bayar']) ?>" data-interval="<?= $active || $order['status_bayar'] === 'Belum Bayar' ? (int) config('poll.order') : 0 ?>">
    <div>
        <p class="meta">Meja <?= e($order['kode_meja']) ?> · <?= e(wib($order['waktu'])) ?></p>
        <h1><?= e($order['kode_pesanan']) ?></h1>
        <p class="status-text" data-status-text><?= $awaitPay ? 'Menunggu pembayaran di kasir.' : e(OrderRules::customerText($status)) ?></p>
    </div>
    <div class="order-badges">
        <?= View::capture('partials/status-badge', ['status' => $status]) ?>
        <?= View::capture('partials/status-badge', ['status' => $order['status_bayar']]) ?>
    </div>
</div>
<p class="small muted live-note" data-poll-note aria-live="polite"><?= $active ? 'Status diperbarui otomatis.' : '' ?></p>

<?php if ($awaitPay): ?>
    <div class="pay-first<?= $urgent ? ' is-urgent' : '' ?>"<?= $order['batas_bayar'] > 0 ? ' data-pay-deadline="' . (int) $order['batas_bayar'] . '" data-server-now="' . time() . '" data-warn="' . $warnSeconds . '"' : '' ?>>
        <p class="pay-first-head">Bayar dulu di kasir</p>
        <p class="pay-first-total"><?= rupiah($order['total']) ?></p>
        <p>Sebutkan nomor pesanan <b><?= e($order['kode_pesanan']) ?></b> ke kasir. Pesanan baru disiapkan setelah dibayar.</p>
        <?php if ($order['batas_bayar'] > 0): ?>
            <p class="pay-countdown" data-countdown><?= $countdown ?></p>
            <p class="small muted">Bayar sebelum pukul <b><?= e(wib($order['batas_bayar'], 'H:i')) ?></b>; setelah itu pesanan dibatalkan otomatis.</p>
            <p class="sr-only" data-countdown-alert aria-live="assertive"></p>
        <?php endif; ?>
    </div>
<?php elseif ($status === 'Siap Diantar'): ?>
    <div class="moment" data-moment tabindex="-1">
        <p class="moment-jp" lang="ja">お待たせしました</p>
        <p class="moment-romaji">omatase shimashita · terima kasih sudah menunggu</p>
        <p class="moment-text">Sebentar lagi sampai di meja <?= e($order['kode_meja']) ?>. Selamat menikmati.</p>
    </div>
<?php elseif ($status === 'Selesai'): ?>
    <div class="moment moment-quiet" data-moment tabindex="-1">
        <p class="moment-jp" lang="ja">ありがとうございました</p>
        <p class="moment-romaji">arigatō gozaimashita · terima kasih banyak</p>
        <p class="moment-text">Terima kasih sudah mampir. Sampai jumpa lagi di <?= e((string) config('store.name')) ?>.</p>
    </div>
<?php endif; ?>

<?php if (!$cancelled): ?>
    <?= View::capture('partials/order-timeline', ['order' => $order]) ?>
<?php else: ?>
    <div class="alert alert-error"><?= e($order['alasan_batal'] !== '' ? $order['alasan_batal'] : 'Pesanan dibatalkan.') ?></div>
    <?php if ($order['items']): ?>
        <div class="reorder">
            <?php if (($table ?? null) !== null): ?>
                <form action="/pesanan/<?= (int) $order['id'] ?>/pesan-lagi" method="post" data-once>
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-primary" data-loading-text="Memasukkan ke keranjang…">Pesan lagi menu yang sama</button>
                </form>
                <p class="small muted">Menu dan jumlahnya dimasukkan lagi ke keranjang meja <?= e($table) ?>. Harga dan ketersediaan dicek ulang; belum ada yang dikirim ke dapur.</p>
            <?php else: ?>
                <p class="small muted">Untuk memesan lagi menu yang sama, scan QR di mejamu lalu buka pesanan ini.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<div class="two-col">
    <section class="card receipt">
        <h2>Rincian pesanan</h2>
        <ul class="mini-lines">
            <?php foreach ($order['items'] as $it): ?>
                <li><span><?= (int) $it['jumlah'] ?>× <?= e($it['nama_menu']) ?> <small class="muted">@ <?= rupiah($it['harga_satuan']) ?></small></span><span><?= rupiah($it['subtotal']) ?></span></li>
            <?php endforeach; ?>
        </ul>
        <?= View::capture('partials/cost-summary', ['quote' => $order]) ?>
        <dl class="kv">
            <div><dt>Nama pemesan</dt><dd><?= e($order['nama_pemesan']) ?></dd></div>
            <?php if ($order['catatan'] !== ''): ?><div><dt>Catatan</dt><dd><?= e($order['catatan']) ?></dd></div><?php endif; ?>
            <div><dt>Pembayaran</dt><dd><?= e(OrderRules::paymentLabel($order['metode_bayar'])) ?> · <?= e($order['status_bayar']) ?></dd></div>
        </dl>

    </section>

    <section class="card">
        <h2>Riwayat</h2>
        <ol class="history">
            <?php foreach (array_reverse($order['history']) as $h): ?>
                <li><span class="small muted"><?= e(wib($h['at'], 'H:i')) ?></span> <?= e($h['note']) ?></li>
            <?php endforeach; ?>
        </ol>

        <?php if (OrderRules::canCancel($status)): ?>
            <details class="cancel-box" id="batal">
                <summary>Batalkan pesanan</summary>
                <p class="small muted">Pembatalan hanya bisa sebelum pesanan mulai disiapkan.<?= $order['status_bayar'] === 'Lunas' ? ' Pembayaran QRIS simulasi akan dikembalikan.' : '' ?></p>
                <form action="/pesanan/<?= (int) $order['id'] ?>/batal" method="post" data-once>
                    <?= csrf_field() ?>
                    <fieldset>
                        <legend class="small">Alasan</legend>
                        <?php foreach ($cancelReasons as $key => $label): ?>
                            <label class="radio-line"><input type="radio" name="reason" value="<?= e($key) ?>" required> <?= e($label) ?></label>
                        <?php endforeach; ?>
                    </fieldset>
                    <button type="submit" class="btn btn-danger">Ya, batalkan</button>
                </form>
            </details>
        <?php endif; ?>
    </section>
</div>

<p><a href="/pesanan">Semua pesanan dari perangkat ini</a> · <a href="/menu">Lihat menu</a></p>
