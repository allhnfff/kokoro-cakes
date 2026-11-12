<?php
/** @var array $groups @var int $habis @var int $total @var array<string,true> $picks @var int $pickLimit */
?>
<div class="page-head">
    <h1>Stok menu</h1>
    <p class="muted"><?= (int) $habis ?> dari <?= (int) $total ?> menu sedang habis. Kasir hanya mengubah ketersediaan; menambah atau menghapus menu bukan wewenang kasir.</p>
    <p class="muted"><b>Pilihan dapur hari ini: <?= count($picks) ?> dari <?= (int) $pickLimit ?></b>. Tampil di beranda pelanggan dan kosong lagi otomatis pukul 00.00.</p>
</div>

<?php foreach ($groups as $catId => $g): ?>
    <section class="card">
        <h2><?= e($g['label']) ?></h2>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>Menu</th><th class="num">Harga</th><th>Status</th><th>Ubah</th><th>Pilihan dapur</th></tr></thead>
                <tbody>
                <?php foreach ($g['items'] as $m): ?>
                    <tr id="<?= e($m['id']) ?>">
                        <?php $photo = menu_photo($m); ?>
                        <td><?php if ($photo): ?><img class="menu-thumb" src="<?= e($photo) ?>" alt="" width="40" height="40" loading="lazy"><?php endif; ?><b><?= e($m['nama_menu']) ?></b> <small class="muted" lang="ja"><?= e($m['nama_jp']) ?></small> <small class="muted"><?= e($m['id']) ?></small><?= $photo ? '' : ' <small class="muted">· belum ada foto</small>' ?></td>
                        <td class="num"><?= rupiah($m['harga']) ?></td>
                        <td><span class="badge <?= e(status_class($m['status'])) ?>"><?= e($m['status']) ?></span></td>
                        <td>
                            <form action="/kasir/menu/<?= e($m['id']) ?>/status" method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="status" value="<?= $m['tersedia'] ? 'Habis' : 'Tersedia' ?>">
                                <button type="submit" class="btn btn-sm<?= $m['tersedia'] ? '' : ' btn-primary' ?>">
                                    <?= $m['tersedia'] ? 'Tandai habis' : 'Tandai tersedia' ?>
                                </button>
                            </form>
                        </td>
                        <td>
                            <?php $isPick = isset($picks[$m['id']]); ?>
                            <?php if ($isPick || $m['tersedia']): ?>
                            <form action="/kasir/menu/<?= e($m['id']) ?>/pilihan" method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="aksi" value="<?= $isPick ? 'hapus' : 'tambah' ?>">
                                <button type="submit" class="btn btn-sm<?= $isPick ? ' btn-primary' : '' ?>"<?= !$isPick && count($picks) >= $pickLimit ? ' disabled title="Sudah ' . (int) $pickLimit . ' pilihan"' : '' ?>>
                                    <?= $isPick ? 'Hapus pilihan' : 'Jadikan pilihan' ?>
                                </button>
                            </form>
                            <?php if ($isPick && !$m['tersedia']): ?><small class="muted">Habis, tidak tampil ke tamu</small><?php endif; ?>
                            <?php else: ?>
                                <small class="muted">–</small>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endforeach; ?>
