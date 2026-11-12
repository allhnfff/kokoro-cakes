<?php
/**
 * @var array $cart @var array $quote @var array $old @var array<string,string> $errors
 * @var array<string,string> $methods @var int $step @var string $table
 */
use App\Http\View;

$val = static fn (string $k): string => (string) ($old[$k] ?? '');
$err = static fn (string $k): string => isset($errors[$k])
    ? '<p class="field-error" id="err-' . e($k) . '">' . e($errors[$k]) . '</p>' : '';
$aria = static fn (string $k): string => isset($errors[$k]) ? ' aria-invalid="true" aria-describedby="err-' . e($k) . '"' : '';
$method = $val('metode_bayar') ?: 'qris';
?>
<?= View::capture('partials/checkout-steps', ['step' => $step]) ?>
<h1>Checkout meja <?= e($table) ?></h1>

<div class="two-col">
    <form action="/checkout/tinjau" method="post" class="card form" novalidate>
        <?= csrf_field() ?>
        <div class="field">
            <label for="nama_pemesan">Nama pemesan</label>
            <input id="nama_pemesan" name="nama_pemesan" value="<?= e($val('nama_pemesan')) ?>" maxlength="40" autocomplete="given-name" required<?= $aria('nama_pemesan') ?>>
            <p class="hint">Dipanggil saat pesanan diantar.</p>
            <?= $err('nama_pemesan') ?>
        </div>
        <div class="field">
            <span class="label">Catatan pesanan</span>
            <input type="hidden" name="catatan" value="<?= e($val('catatan')) ?>">
            <p class="note-readonly"><?= $val('catatan') !== '' ? e($val('catatan')) : '<span class="muted">Tidak ada catatan.</span>' ?> <a href="/keranjang" class="small">Ubah di keranjang</a></p>
            <?= $err('catatan') ?>
        </div>
        <fieldset class="field">
            <legend>Metode pembayaran</legend>
            <?php foreach ($methods as $key => $label): ?>
                <label class="radio-card">
                    <input type="radio" name="metode_bayar" value="<?= e($key) ?>"<?= $method === $key ? ' checked' : '' ?>>
                    <span><b><?= e($label) ?></b>
                        <small class="muted"><?= $key === 'qris' ? 'Bayar sekarang dengan QRIS simulasi. Tidak ada uang yang ditarik.' : 'Bayar dulu di kasir dalam ' . (int) config('order.unpaid_minutes') . ' menit; pesanan baru disiapkan setelah dibayar.' ?></small>
                    </span>
                </label>
            <?php endforeach; ?>
            <?= $err('metode_bayar') ?>
        </fieldset>
        <button type="submit" class="btn btn-primary btn-block">Lanjut tinjau pesanan</button>
        <a class="btn btn-ghost btn-block" href="/keranjang">Kembali ke keranjang</a>
    </form>

    <aside class="card summary receipt">
        <h2>Pesanan meja <?= e($table) ?></h2>
        <ul class="mini-lines">
            <?php foreach ($cart['lines'] as $l): ?>
                <li><span><?= (int) $l['qty'] ?>× <?= e($l['nama_menu']) ?></span><span><?= rupiah($l['line_total']) ?></span></li>
            <?php endforeach; ?>
        </ul>
        <?= View::capture('partials/cost-summary', ['quote' => $quote]) ?>
    </aside>
</div>
