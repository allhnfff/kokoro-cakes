<?php
/**
 * Beranda: tugasnya hanya mengantar tamu ke menu (DESIGN.md).
 * @var list<array> $index  kategori + jumlah menu, harga termurah, tiga menu contoh (nama + foto)
 * @var list<array> $picks  pilihan dapur hari ini yang sedang Tersedia (dipilih kasir)
 * @var string|null $table @var int|null $cartCount
 * @var string|null $heroPhoto
 * @var list<array> $slides foto beranda yang bisa digeser: suasana kafe + menu (src, menu|null, pick)
 * @var list<array> $activeOrders pesanan aktif dari perangkat ini (Masuk/Diproses/Siap Diantar)
 */
use App\Http\View;
?>
<?php
$wifiName = (string) config('store.wifi_name');
$wifiPass = (string) config('store.wifi_password');
?>
<section class="entrance<?= $heroPhoto !== null ? ' has-photo' : '' ?>">
    <?php /* Noren digantung di atas foto: tamu "melihat ke dalam kafe" lewat tirai pintu masuk. */ ?>
    <?php /* Foto bisa digeser (scroll-snap, jalan tanpa JavaScript). JavaScript menambah titik navigasi,
             tombol jeda, dan ganti otomatis tiap 5 detik. */ ?>
    <div class="entrance-media">
        <figure class="entrance-photo">
            <div class="noren" aria-hidden="true" lang="ja"><span>こ</span><span>こ</span><span>ろ</span></div>
            <?php if ($slides): ?>
                <div class="hero-track" data-carousel tabindex="0" role="region" aria-roledescription="carousel" aria-label="Foto <?= e((string) config('store.name')) ?>, geser untuk melihat menu lain">
                    <?php foreach ($slides as $i => $s): ?>
                        <?php $m = $s['menu']; $c = $s['cat']; $label = $c['nama_kategori'] ?? $m['nama_menu'] ?? ''; ?>
                        <div class="hero-slide" role="group" aria-roledescription="slide" aria-label="<?= $i + 1 ?> dari <?= count($slides) ?><?= $label !== '' ? ': ' . e($label) : '' ?>">
                            <?php /* Foto tampil bersih tanpa label; foto kategori/menu tetap bisa diklik ke menunya. */ ?>
                            <?php if ($c !== null): ?>
                                <a class="hero-link" href="/menu?kategori=<?= e($c['id']) ?>" aria-label="Lihat menu <?= e($c['nama_kategori']) ?>">
                                    <img src="<?= e($s['src']) ?>" alt="Pilihan <?= e($c['nama_kategori']) ?> di <?= e((string) config('store.name')) ?>" width="1440" height="1080" loading="lazy">
                                </a>
                            <?php elseif ($m !== null): ?>
                                <a class="hero-link" href="/menu/<?= e($m['id']) ?>" aria-label="Lihat <?= e($m['nama_menu']) ?>">
                                    <img src="<?= e($s['src']) ?>" alt="<?= e($m['nama_menu']) ?>" width="800" height="800" loading="lazy">
                                </a>
                            <?php else: ?>
                                <img src="<?= e($s['src']) ?>" alt="Suasana meja <?= e((string) config('store.name')) ?>: matcha, shortcake, dango, dan daifuku" width="880" height="660" fetchpriority="high">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </figure>
        <?php if (count($slides) > 1): ?><div class="hero-dots" data-carousel-dots hidden></div><?php endif; ?>
    </div>

    <div class="entrance-text">
        <?php if ($table !== null): ?>
            <h1>Selamat datang di meja <?= e($table) ?></h1>
            <p>Pilih menu, lalu kirim pesanan langsung ke dapur. Bayar dengan QRIS saat checkout atau nanti di kasir.</p>
        <?php else: ?>
            <?php $store = (string) config('store.name'); ?>
            <h1><?= e($store) ?> <span class="nowrap">e yōkoso</span></h1>
            <?php /* Tanpa petunjuk scan di sini: tamu datang lewat QR meja. Yang belum scan tetap diberi tahu
                     lewat label "Belum di meja" di header dan catatan di halaman Menu. */ ?>
        <?php endif; ?>

        <?php if ($activeOrders): ?>
            <div class="live-orders">
                <p class="live-orders-head">Pesananmu yang sedang berjalan</p>
                <?php foreach ($activeOrders as $o): ?>
                    <a class="live-order" href="/pesanan/<?= (int) $o['id'] ?>">
                        <b><?= e($o['kode_pesanan']) ?></b>
                        <?php $wait = \App\Domain\OrderRules::awaitingPayment($o); ?>
                        <span class="badge <?= e(status_class($wait ? 'Belum Bayar' : $o['status_pesanan'])) ?>"><?= $wait ? 'Bayar dulu di kasir' : e($o['status_pesanan']) ?></span>
                        <span class="live-order-go">Lihat status</span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($table !== null && $wifiName !== ''): ?>
            <p class="wifi"><?= icon('wifi', 18) ?>Wi-Fi <b><?= e($wifiName) ?></b><?php if ($wifiPass !== ''): ?> · kata sandi <b class="wifi-pass"><?= e($wifiPass) ?></b><?php endif; ?></p>
        <?php endif; ?>

        <div class="welcome-actions">
            <a class="btn btn-primary" href="/menu"><?= $table !== null ? 'Buka menu' : 'Lihat menu dulu' ?></a>
            <a class="btn btn-ghost" href="/panduan">Baru pertama kali? Lihat cara pesan</a>
        </div>
    </div>
</section>

<?php if ($picks): ?>
<section class="picks" aria-labelledby="pilihan-dapur">
    <div class="section-head">
        <h2 id="pilihan-dapur">Pilihan dapur hari ini <span class="ja" lang="ja">本日のおすすめ</span></h2>
    </div>
    <div class="menu-grid picks-grid">
        <?php foreach ($picks as $m): ?>
            <?= View::capture('partials/menu-card', ['m' => $m, 'table' => $table, 'back' => 'beranda']) ?>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>



<section class="index" aria-labelledby="isi-menu">
    <h2 id="isi-menu">Hari ini mau yang mana?</h2>
    <ul class="index-list">
        <?php foreach ($index as $c): ?>
            <li>
                <a href="<?= e(url('/menu', ['kategori' => $c['id']])) ?>">
                    <span class="index-name"><?= e($c['nama_kategori']) ?><?php if ($c['nama_jp'] !== ''): ?><span class="ja" lang="ja"><?= e($c['nama_jp']) ?></span><?php endif; ?></span>
                    <span class="index-meta"><?= (int) $c['count'] ?> menu<?= $c['min'] !== null ? ' · mulai ' . rupiah($c['min']) : '' ?></span>
                    <?php if ($c['sample']): ?>
                        <span class="index-sample">
                            <?php foreach ($c['sample'] as $s): ?><?php $p = menu_photo($s); if ($p): ?><img src="<?= e($p) ?>" alt="" width="56" height="56" loading="lazy"><?php endif; ?><?php endforeach; ?>
                            <span><?= e(implode(', ', array_column($c['sample'], 'nama_menu'))) ?></span>
                        </span>
                    <?php endif; ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
