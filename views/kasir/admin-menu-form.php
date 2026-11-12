<?php
/**
 * Form tambah/ubah menu (admin).
 * @var array|null $menu  null = tambah  @var array $values  @var array<string,string> $errors
 * @var list<array> $categories  @var list<array> $pairOptions
 */
$isNew = $menu === null;
$action = $isNew ? '/kasir/kelola-menu/baru' : '/kasir/kelola-menu/' . $menu['id'];
$photo = $isNew ? null : menu_photo($menu);
$v = static fn (string $k): string => (string) ($values[$k] ?? '');
$err = static fn (string $k): string => isset($errors[$k])
    ? '<p class="field-error" id="err-' . e($k) . '">' . e($errors[$k]) . '</p>' : '';
$aria = static fn (string $k): string => isset($errors[$k]) ? ' aria-invalid="true" aria-describedby="err-' . e($k) . '"' : '';
?>
<nav class="crumbs small" aria-label="Lokasi halaman"><a href="/kasir/kelola-menu">Kelola menu</a> / <span><?= $isNew ? 'Tambah menu' : e($menu['nama_menu']) ?></span></nav>

<div class="page-head">
    <h1><?= $isNew ? 'Tambah menu' : 'Ubah menu' ?></h1>
    <?php if (!$isNew): ?><p class="muted">Kode <?= e($menu['id']) ?>. Perubahan harga tidak mengubah pesanan yang sudah masuk.</p><?php endif; ?>
</div>

<?php if ($errors): ?>
    <div class="alert alert-error" role="alert"><?= isset($errors['_form']) ? e($errors['_form']) : 'Periksa lagi isian yang ditandai.' ?></div>
<?php endif; ?>

<form action="<?= e($action) ?>" method="post" enctype="multipart/form-data" class="card form admin-form" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="MAX_FILE_SIZE" value="<?= \App\Domain\MenuPhoto::MAX_BYTES ?>">

    <div class="form-cols">
        <div class="field">
            <label for="nama_menu">Nama menu</label>
            <input id="nama_menu" name="nama_menu" type="text" maxlength="60" required value="<?= e($v('nama_menu')) ?>"<?= $aria('nama_menu') ?>>
            <?= $err('nama_menu') ?>
        </div>
        <div class="field">
            <label for="nama_jp">Nama Jepang <span class="muted small">(opsional)</span></label>
            <input id="nama_jp" name="nama_jp" type="text" maxlength="40" lang="ja" value="<?= e($v('nama_jp')) ?>" placeholder="contoh: 抹茶ケーキ"<?= $aria('nama_jp') ?>>
            <?= $err('nama_jp') ?>
        </div>
        <div class="field">
            <label for="id_kategori">Kategori</label>
            <select id="id_kategori" name="id_kategori" required<?= $aria('id_kategori') ?>>
                <option value="">Pilih kategori</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= e($c['id']) ?>"<?= $v('id_kategori') === $c['id'] ? ' selected' : '' ?>><?= e($c['nama_kategori']) ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('id_kategori') ?>
        </div>
        <div class="field">
            <label for="harga">Harga (Rp)</label>
            <input id="harga" name="harga" type="text" inputmode="numeric" required value="<?= e($v('harga')) ?>" placeholder="28000"<?= $aria('harga') ?>>
            <?= $err('harga') ?>
        </div>
    </div>

    <div class="field">
        <label for="deskripsi">Deskripsi <span class="muted small">(rasa dan tekstur, maks. 240 karakter)</span></label>
        <textarea id="deskripsi" name="deskripsi" rows="3" maxlength="240"<?= $aria('deskripsi') ?>><?= e($v('deskripsi')) ?></textarea>
        <?= $err('deskripsi') ?>
    </div>

    <fieldset class="field">
        <legend>Status</legend>
        <?php foreach (['Tersedia', 'Habis'] as $s): ?>
            <label class="radio-line"><input type="radio" name="status" value="<?= $s ?>"<?= $v('status') === $s ? ' checked' : '' ?>> <?= $s ?></label>
        <?php endforeach; ?>
        <?= $err('status') ?>
    </fieldset>

    <div class="field">
        <label for="foto">Foto <span class="muted small">(JPG, PNG, atau WebP, maks. 3 MB, sebaiknya persegi)</span></label>
        <?php if ($photo): ?>
            <div class="photo-current">
                <img src="<?= e($photo) ?>" alt="Foto <?= e($menu['nama_menu']) ?> saat ini" width="96" height="96">
                <label class="radio-line"><input type="checkbox" name="hapus_foto" value="1"> Hapus foto ini</label>
            </div>
        <?php endif; ?>
        <input id="foto" name="foto" type="file" accept="image/jpeg,image/png,image/webp"<?= $aria('foto') ?>>
        <?php if (isset($errors['foto'])): ?>
            <?= $err('foto') ?>
        <?php elseif ($errors): ?>
            <p class="hint">Jika tadi memilih foto, pilih lagi sebelum menyimpan.</p>
        <?php endif; ?>
    </div>

    <div class="form-cols">
        <div class="field">
            <label for="pasangan">Saran pasangan <span class="muted small">(opsional)</span></label>
            <select id="pasangan" name="pasangan"<?= $aria('pasangan') ?>>
                <option value="">Tidak ada</option>
                <?php foreach ($pairOptions as $p): ?>
                    <option value="<?= e($p['id']) ?>"<?= $v('pasangan') === $p['id'] ? ' selected' : '' ?>><?= e($p['nama_menu']) ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('pasangan') ?>
        </div>
        <div class="field">
            <label for="pasangan_alasan">Alasan singkat</label>
            <input id="pasangan_alasan" name="pasangan_alasan" type="text" maxlength="120" value="<?= e($v('pasangan_alasan')) ?>" placeholder="contoh: Teh gurih membuat krimnya terasa lebih ringan."<?= $aria('pasangan_alasan') ?>>
            <?= $err('pasangan_alasan') ?>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= $isNew ? 'Tambah menu' : 'Simpan perubahan' ?></button>
        <a class="btn btn-ghost" href="/kasir/kelola-menu">Batal</a>
    </div>
</form>
