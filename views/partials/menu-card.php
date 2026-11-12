<?php
/**
 * Kartu menu. Foto tampil hanya jika file fotonya memang ada (public/assets/img/menu/foto/{id}.*);
 * tanpa foto, kartu berisi teks saja (tidak ada gambar pengganti yang berulang).
 * @var array $m  menu (id, nama_menu, nama_jp, harga, deskripsi, status, tersedia, id_kategori, foto)
 * @var string|null $table  kode meja aktif (null = belum scan QR)
 * @var string $back  tujuan kembali setelah tambah ('menu' | 'beranda')
 * @var bool $popular  termasuk menu yang paling sering dipesan (data penjualan nyata)
 * @var bool $pick  pilihan dapur hari ini (dipilih kasir)
 */
$back = $back ?? 'menu';
$popular = $popular ?? false;
$pick = $pick ?? false;
$photo = menu_photo($m);
$stamp = '<span class="sold-stamp" data-sold-tag' . ($m['tersedia'] ? ' hidden' : '') . '><span lang="ja">売切</span><small>Habis</small></span>';
?>
<article class="menu-card<?= $photo ? ' has-photo' : '' ?><?= $m['tersedia'] ? '' : ' is-habis' ?>" id="<?= e($m['id']) ?>" data-menu-id="<?= e($m['id']) ?>">
    <?php if ($photo): ?>
        <a class="menu-img" href="/menu/<?= e($m['id']) ?>" tabindex="-1" aria-hidden="true">
            <img src="<?= e($photo) ?>" alt="" width="300" height="300" loading="lazy">
            <?= $stamp ?>
        </a>
    <?php endif; ?>
    <div class="menu-body">
        <?= $photo ? '' : $stamp ?>
        <?php if ($pick || $popular): ?>
            <p class="menu-flags">
                <?php if ($pick): ?><span class="menu-flag is-pick" title="Dipilih dapur untuk hari ini">Pilihan dapur</span><?php endif; ?>
                <?php if ($popular): ?><span class="menu-flag" title="Termasuk menu yang paling banyak dipesan dalam 30 hari terakhir">Sering dipesan</span><?php endif; ?>
            </p>
        <?php endif; ?>
        <?php if (($m['nama_jp'] ?? '') !== ''): ?>
            <p class="menu-jp" lang="ja"><?= e($m['nama_jp']) ?></p>
        <?php endif; ?>
        <h3 class="menu-name"><a href="/menu/<?= e($m['id']) ?>"><?= e($m['nama_menu']) ?></a></h3>
        <p class="menu-desc"><?= e($m['deskripsi']) ?></p>
        <p class="stock<?= $m['tersedia'] ? '' : ' is-out' ?>" data-stock-text>Stok: <?= $m['tersedia'] ? 'Tersedia' : 'Habis' ?></p>
        <div class="menu-foot">
            <span class="price"><?= rupiah($m['harga']) ?></span>
            <?php if ($table !== null): ?>
                <form action="/keranjang/tambah" method="post" class="add-form" data-add-form>
                    <?= csrf_field() ?>
                    <input type="hidden" name="id_menu" value="<?= e($m['id']) ?>">
                    <input type="hidden" name="qty" value="1">
                    <input type="hidden" name="kembali" value="<?= e($back) ?>">
                    <button type="submit" class="btn btn-sm" data-add-btn<?= $m['tersedia'] ? '' : ' disabled' ?> aria-label="Tambah <?= e($m['nama_menu']) ?> ke keranjang"><?= icon('plus', 16) ?>Tambah</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</article>
