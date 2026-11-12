<?php
/** Daftar menu untuk admin: tambah, ubah, hapus. @var array $groups */
$total = array_sum(array_map(static fn ($g) => count($g['items']), $groups));
?>
<div class="page-head page-head-row">
    <div>
        <h1>Kelola menu</h1>
        <p class="muted"><?= (int) $total ?> menu. Perubahan harga berlaku untuk pesanan berikutnya; pesanan yang sudah masuk tetap memakai harga lama.</p>
    </div>
    <a class="btn btn-primary" href="/kasir/kelola-menu/baru"><?= icon('plus', 18) ?>Tambah menu</a>
</div>

<?php foreach ($groups as $catId => $g): ?>
    <section class="card">
        <h2><?= e($g['label']) ?> <small class="muted">(<?= count($g['items']) ?>)</small></h2>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>Menu</th><th class="num">Harga</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($g['items'] as $m): ?>
                    <?php $photo = menu_photo($m); ?>
                    <tr id="<?= e($m['id']) ?>">
                        <td>
                            <?php if ($photo): ?><img class="menu-thumb" src="<?= e($photo) ?>" alt="" width="40" height="40" loading="lazy"><?php endif; ?>
                            <b><?= e($m['nama_menu']) ?></b>
                            <?php if ($m['nama_jp'] !== ''): ?><small class="muted" lang="ja"><?= e($m['nama_jp']) ?></small><?php endif; ?>
                            <small class="muted"><?= e($m['id']) ?><?= $photo ? '' : ' · belum ada foto' ?></small>
                        </td>
                        <td class="num"><?= rupiah($m['harga']) ?></td>
                        <td><span class="badge <?= e(status_class($m['status'])) ?>"><?= e($m['status']) ?></span></td>
                        <td class="row-actions">
                            <a class="btn btn-sm" href="/kasir/kelola-menu/<?= e($m['id']) ?>">Ubah</a>
                            <details class="confirm-box">
                                <summary class="btn btn-sm">Hapus</summary>
                                <div class="confirm-pop">
                                    <p class="small">Hapus <b><?= e($m['nama_menu']) ?></b> dari menu? Tamu tidak bisa memesannya lagi. Riwayat pesanan dan rekap lama tidak berubah.</p>
                                    <form action="/kasir/kelola-menu/<?= e($m['id']) ?>/hapus" method="post">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-sm btn-danger">Ya, hapus</button>
                                    </form>
                                </div>
                            </details>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endforeach; ?>
