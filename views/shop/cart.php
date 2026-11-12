<?php
/**
 * Keranjang meja (wireframe pelanggan no. 5): jumlah − / +, catatan pesanan, subtotal + pajak + total.
 * @var array $cart lines, subtotal, units, has_problem @var string $table
 * @var array|null $quote subtotal, pajak_persen, pajak, total @var string $catatan
 * @var list<array{untuk:string, menu:array, alasan:string}> $suggestions saran pasangan untuk isi keranjang
 */
use App\Http\View;
?>
<div class="page-head">
    <h1>Keranjang meja <?= e($table) ?></h1>
    <p class="muted">Keranjang ini dipakai bersama oleh semua yang memesan dari meja <?= e($table) ?>.</p>
</div>

<?php if (!$cart['lines']): ?>
    <div class="empty">
        <p>Keranjang masih kosong.</p>
        <a class="btn btn-primary" href="/menu">Pilih menu</a>
    </div>
<?php else: ?>
<div class="two-col">
    <div class="cart-main">
    <ul class="cart-list">
        <?php foreach ($cart['lines'] as $l): ?>
            <?php $photo = menu_photo(['id' => $l['id_menu'], 'foto' => $l['foto']]); ?>
            <li class="cart-line<?= $photo ? ' has-photo' : '' ?><?= $l['issue'] ? ' has-issue' : '' ?>">
                <?php if ($photo): ?><img src="<?= e($photo) ?>" alt="" width="72" height="72" loading="lazy"><?php endif; ?>
                <div class="cart-info">
                    <?php if ($l['nama_jp'] !== ''): ?><span class="menu-jp" lang="ja"><?= e($l['nama_jp']) ?></span><?php endif; ?>
                    <a href="/menu/<?= e($l['id_menu']) ?>"><b><?= e($l['nama_menu']) ?></b></a>
                    <span class="small muted"><?= rupiah($l['harga']) ?> / porsi</span>
                    <?php if ($l['issue']): ?><span class="issue"><?= e($l['issue']) ?></span><?php endif; ?>
                </div>
                <div class="cart-actions">
                    <?php if (!$l['issue']): ?>
                    <?php $max = (int) config('order.max_qty_per_line'); $q = (int) $l['qty']; ?>
                    <div class="qty-step" role="group" aria-label="Jumlah <?= e($l['nama_menu']) ?>">
                        <form action="/keranjang/ubah" method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id_menu" value="<?= e($l['id_menu']) ?>">
                            <input type="hidden" name="qty" value="<?= max(1, $q - 1) ?>">
                            <button type="submit" class="step-btn" aria-label="Kurangi <?= e($l['nama_menu']) ?>"<?= $q <= 1 ? ' disabled' : '' ?>><?= icon('minus', 16) ?></button>
                        </form>
                        <span class="qty-value" aria-live="polite"><?= $q ?></span>
                        <form action="/keranjang/ubah" method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id_menu" value="<?= e($l['id_menu']) ?>">
                            <input type="hidden" name="qty" value="<?= min($max, $q + 1) ?>">
                            <button type="submit" class="step-btn" aria-label="Tambah <?= e($l['nama_menu']) ?>"<?= $q >= $max ? ' disabled' : '' ?>><?= icon('plus', 16) ?></button>
                        </form>
                    </div>
                    <?php endif; ?>
                    <form action="/keranjang/hapus" method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id_menu" value="<?= e($l['id_menu']) ?>">
                        <button type="submit" class="icon-btn" aria-label="Hapus <?= e($l['nama_menu']) ?>"><?= icon('trash', 20) ?></button>
                    </form>
                    <span class="line-total"><?= $l['issue'] ? '–' : rupiah($l['line_total']) ?></span>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>

    <?php if ($suggestions): ?>
        <section class="pair-suggest" aria-labelledby="saran-pasangan">
            <h2 id="saran-pasangan">Mau sekalian?</h2>
            <?php foreach ($suggestions as $s): ?>
                <?= View::capture('partials/pairing', ['pair' => $s['menu'], 'alasan' => $s['alasan'], 'table' => $table, 'back' => 'keranjang', 'heading' => 'Untuk ' . $s['untuk']]) ?>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
    </div>

    <aside class="card summary">
        <h2>Ringkasan</h2>
        <form action="/keranjang/lanjut" method="post">
            <?= csrf_field() ?>
            <div class="field">
                <label for="catatan">Catatan pesanan <span class="muted">(opsional)</span></label>
                <textarea id="catatan" name="catatan" rows="2" maxlength="200" placeholder="Contoh: kurang manis, tanpa es"><?= e($catatan) ?></textarea>
            </div>
            <dl class="cost">
                <div><dt>Subtotal (<?= (int) $cart['units'] ?> item)</dt><dd><?= rupiah($quote['subtotal'] ?? $cart['subtotal']) ?></dd></div>
                <?php if ($quote): ?>
                    <div><dt>Pajak &amp; layanan (<?= (int) $quote['pajak_persen'] ?>%)</dt><dd><?= rupiah($quote['pajak']) ?></dd></div>
                    <div class="cost-total"><dt>Total</dt><dd><?= rupiah($quote['total']) ?></dd></div>
                <?php endif; ?>
            </dl>
            <?php if ($cart['has_problem']): ?>
                <p class="issue">Hapus menu yang habis sebelum melanjutkan.</p>
                <button class="btn btn-primary btn-block" type="button" disabled>Lanjut ke checkout</button>
            <?php else: ?>
                <button class="btn btn-primary btn-block" type="submit">Lanjut ke checkout</button>
            <?php endif; ?>
        </form>
        <a class="btn btn-ghost btn-block" href="/menu">Tambah menu lain</a>
    </aside>
</div>
<?php endif; ?>
