<?php
/**
 * Saran pasangan (kue ↔ minuman), seperti saran pelayan. Hanya dirender jika menu pasangannya Tersedia.
 * @var array $pair menu pasangan  @var string $alasan  @var string|null $table
 * @var string $back tujuan kembali setelah tambah tanpa JavaScript ('detail' | 'keranjang')
 * @var string $heading judul kecil, misalnya "Enak dinikmati bersama"
 */
$photo = menu_photo($pair);
?>
<div class="pairing<?= $photo ? ' has-photo' : '' ?>">
    <?php if ($photo): ?>
        <img src="<?= e($photo) ?>" alt="" width="64" height="64" loading="lazy">
    <?php endif; ?>
    <div class="pairing-info">
        <p class="pairing-head"><?= e($heading) ?></p>
        <a class="pairing-name" href="/menu/<?= e($pair['id']) ?>"><?= e($pair['nama_menu']) ?></a>
        <?php if ($alasan !== ''): ?><p class="pairing-why"><?= e($alasan) ?></p><?php endif; ?>
    </div>
    <div class="pairing-act">
        <span class="price"><?= rupiah($pair['harga']) ?></span>
        <?php if ($table !== null): ?>
            <?php /* Di keranjang: kirim biasa agar daftar & total ikut diperbarui. Di tempat lain: lewat JavaScript. */ ?>
            <form action="/keranjang/tambah" method="post"<?= $back === 'keranjang' ? '' : ' data-add-form' ?>>
                <?= csrf_field() ?>
                <input type="hidden" name="id_menu" value="<?= e($pair['id']) ?>">
                <input type="hidden" name="qty" value="1">
                <input type="hidden" name="kembali" value="<?= e($back) ?>">
                <button type="submit" class="btn btn-sm" data-add-btn aria-label="Tambah <?= e($pair['nama_menu']) ?> ke keranjang"><?= icon('plus', 16) ?>Tambah</button>
            </form>
        <?php endif; ?>
    </div>
</div>
