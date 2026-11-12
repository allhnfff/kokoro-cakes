<?php
/** @var array $cart @var array $draft @var array $quote @var bool $pending @var int $step @var string $table */
use App\Domain\OrderRules;
use App\Http\View;

$form = $draft['form'];
$isQris = $form['metode_bayar'] === 'qris';
?>
<?= View::capture('partials/checkout-steps', ['step' => $step]) ?>
<h1>Tinjau &amp; bayar</h1>

<?php if ($pending): ?>
    <div class="alert alert-info">Percobaan sebelumnya belum diketahui hasilnya. Menekan tombol di bawah aman: pesanan tidak akan tercatat dua kali.</div>
<?php endif; ?>

<div class="two-col">
    <section class="card receipt">
        <h2>Pesanan meja <?= e($table) ?></h2>
        <dl class="kv">
            <div><dt>Nama pemesan</dt><dd><?= e($form['nama_pemesan']) ?></dd></div>
            <div><dt>Catatan</dt><dd><?= $form['catatan'] !== '' ? e($form['catatan']) : '<span class="muted">-</span>' ?></dd></div>
            <div><dt>Pembayaran</dt><dd><?= e(OrderRules::paymentLabel($form['metode_bayar'])) ?></dd></div>
        </dl>
        <ul class="mini-lines">
            <?php foreach ($cart['lines'] as $l): ?>
                <li><span><?= (int) $l['qty'] ?>× <?= e($l['nama_menu']) ?></span><span><?= rupiah($l['line_total']) ?></span></li>
            <?php endforeach; ?>
        </ul>
        <?= View::capture('partials/cost-summary', ['quote' => $quote]) ?>
        <p class="small muted">Total akhir dihitung ulang oleh server dari harga menu saat pesanan dikirim.</p>
    </section>

    <section class="card pay-box">
        <?php if ($isQris): ?>
            <h2>Bayar dengan QRIS (simulasi)</h2>
            <div class="qris" aria-hidden="true">
                <img src="<?= asset('assets/img/qris-demo.svg') ?>" alt="" width="200" height="200">
            </div>
            <p class="small">Ini QR contoh untuk demo. Tidak ada transaksi uang sungguhan. Tekan tombol di bawah untuk menganggap pembayaran berhasil.</p>
            <p class="pay-amount"><?= rupiah($quote['total']) ?></p>
        <?php else: ?>
            <h2>Bayar di kasir</h2>
            <p>Setelah dikirim, datangi kasir dan bayar <b><?= rupiah($quote['total']) ?></b> dengan menyebut nomor pesanan. <b>Pesanan baru disiapkan setelah dibayar</b>, dan dibatalkan otomatis jika belum dibayar dalam <?= (int) config('order.unpaid_minutes') ?> menit.</p>
        <?php endif; ?>
        <form action="/checkout/konfirmasi" method="post" data-once>
            <?= csrf_field() ?>
            <input type="hidden" name="request_id" value="<?= e($draft['request_id']) ?>">
            <button type="submit" class="btn btn-shu btn-block" data-loading-text="Mengirim pesanan…">
                <?= $isQris ? 'Saya sudah bayar, kirim pesanan' : 'Kirim pesanan ke dapur' ?>
            </button>
        </form>
        <form action="/checkout/batal" method="post">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-ghost btn-block">Batal, kembali ke keranjang</button>
        </form>
        <a class="small" href="/checkout">Ubah data pemesan</a>
    </section>
</div>
