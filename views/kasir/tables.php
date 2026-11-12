<?php
/**
 * @var list<array> $tables @var bool $configured @var string $qrBase @var bool $localOnly
 * @var list<string> $lanIps @var bool $isAdmin
 */
?>
<div class="page-head page-head-row">
    <div>
        <h1>Meja</h1>
        <p class="muted">Meja menjadi Terisi saat ada pesanan masuk dan <b>tetap Terisi</b> walau pesanannya sudah selesai, karena tamu boleh duduk lama. Tekan <b>Kosongkan</b> setelah tamu pergi dan meja dibereskan.</p>
    </div>
    <?php if ($configured): ?>
        <a class="btn btn-primary" href="/kasir/meja/cetak" target="_blank" rel="noopener"><?= icon('qr', 18) ?>Cetak semua QR</a>
    <?php endif; ?>
</div>

<?php if (!$configured): ?>
    <div class="alert alert-error">APP_KEY belum diatur, jadi tautan QR belum bisa dibuat. Jalankan <code>php scripts/generate-key.php</code> lalu muat ulang.</div>
<?php elseif ($localOnly): ?>
    <div class="alert alert-info">
        <b>QR saat ini menunjuk ke <?= e($qrBase) ?></b>, jadi hanya bisa dibuka dari laptop ini. Agar bisa dipindai HP yang tersambung ke Wi-Fi yang sama:
        jalankan server dengan <code>php -S 0.0.0.0:8000 -t public public/index.php</code>, lalu buka halaman kasir lewat
        <?php if ($lanIps): ?>
            <?php foreach ($lanIps as $i => $ip): ?><?= $i ? ' atau ' : '' ?><code>http://<?= e($ip) ?>:8000/kasir/meja</code><?php endforeach; ?>
            (biasanya alamat 192.168.x.x adalah Wi-Fi).
        <?php else: ?>
            alamat IP laptop di jaringan Wi-Fi (lihat dengan perintah <code>ipconfig</code>).
        <?php endif; ?>
        QR akan otomatis memakai alamat itu.
    </div>
<?php endif; ?>

<div class="table-wrap card">
    <table class="data">
        <thead><tr><th>Meja</th><th>Area</th><th class="num">Kapasitas</th><th>Status</th><th class="num">Pesanan aktif</th><th>QR</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($tables as $t): ?>
            <tr id="meja-<?= e($t['kode']) ?>">
                <td><b><?= e($t['kode']) ?></b></td>
                <td><?= e($t['area']) ?></td>
                <td class="num"><?= (int) $t['kapasitas'] ?></td>
                <td>
                    <span class="badge <?= e(status_class($t['aktif'] ? $t['status'] : 'Nonaktif')) ?>"><?= $t['aktif'] ? e($t['status']) : 'Nonaktif' ?></span>
                    <?php if ($t['status'] === 'Terisi' && $t['terisi_sejak']): ?><small class="muted table-since">sejak <?= e(wib($t['terisi_sejak'], 'H.i')) ?></small><?php endif; ?>
                </td>
                <td class="num">
                    <?php if ($t['pesanan_aktif']): ?>
                        <a href="<?= e(url('/kasir/pesanan', ['meja' => $t['kode']])) ?>"><?= (int) $t['pesanan_aktif'] ?></a>
                    <?php else: ?>0<?php endif; ?>
                </td>
                <td class="qr-cell">
                    <?php if ($t['qr_url'] !== ''): ?>
                        <div class="qr-actions">
                            <input class="copy-input" type="text" value="<?= e($t['qr_url']) ?>" readonly aria-label="Tautan QR meja <?= e($t['kode']) ?>" data-copy>
                            <a class="btn btn-sm" href="<?= e($t['qr_path']) ?>" target="_blank" rel="noopener" aria-label="Buka halaman pemesanan meja <?= e($t['kode']) ?> di tab baru (sama seperti scan QR)">Buka</a>
                            <a class="btn btn-sm" href="<?= e(url('/kasir/meja/cetak', ['meja' => $t['kode']])) ?>" target="_blank" rel="noopener" aria-label="Cetak QR meja <?= e($t['kode']) ?>">Cetak</a>
                        </div>
                        <small class="muted">Versi QR <?= (int) $t['qr_versi'] ?></small>
                    <?php else: ?>-<?php endif; ?>
                </td>
                <td class="row-actions">
                    <?php if ($t['status'] === 'Terisi' && !$t['pesanan_aktif']): ?>
                        <form action="/kasir/meja/<?= e($t['kode']) ?>/kosongkan" method="post">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-primary" title="Tamu sudah pergi dan meja sudah dibereskan">Kosongkan</button>
                        </form>
                    <?php elseif ($t['status'] === 'Terisi'): ?>
                        <small class="muted">Masih ada pesanan berjalan</small>
                    <?php endif; ?>
                    <?php if ($isAdmin && $configured): ?>
                        <details class="confirm-box">
                            <summary class="btn btn-sm">Ganti QR</summary>
                            <div class="confirm-pop">
                                <p class="small">QR meja <?= e($t['kode']) ?> yang sekarang langsung tidak berlaku, termasuk tamu yang sedang memakainya. Cetak dan tempel QR baru setelah ini.</p>
                                <form action="/kasir/meja/<?= e($t['kode']) ?>/ganti-qr" method="post">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-sm btn-danger">Ya, ganti QR</button>
                                </form>
                            </div>
                        </details>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<p class="small muted">Tombol <b>Buka</b> membuka halaman pemesanan meja itu di tab baru, sama seperti tamu yang memindai QR-nya. <b>Cetak</b> membuat kartu QR siap tempel.
    <?php if ($isAdmin): ?>Gunakan <b>Ganti QR</b> jika QR meja difoto atau disebar orang: QR lama langsung tidak bisa dipakai memesan.<?php else: ?>Hanya admin yang bisa mengganti QR meja.<?php endif; ?>
</p>
