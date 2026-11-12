<?php
/**
 * Panduan memesan untuk tamu. Isinya mengikuti aturan web yang sebenarnya (bayar dulu, batas bayar,
 * keranjang satu meja, meja dikosongkan staf), jadi kalau aturan berubah, halaman ini ikut diperbarui.
 * @var string|null $table @var int $unpaidMinutes
 */
$wifiName = (string) config('store.wifi_name');
?>
<div class="page-head guide-head">
    <h1>Cara memesan</h1>
    <p class="muted">Pesan dari mejamu lewat HP, tanpa antre di kasir. Ini langkah lengkapnya.</p>
    <?php if ($table !== null): ?>
        <p class="guide-now">Kamu sedang memesan dari <b>meja <?= e($table) ?></b>. Langsung ke langkah 2.</p>
    <?php endif; ?>
</div>

<ol class="guide-steps">
    <li>
        <h2>Scan kode QR di meja</h2>
        <p>Duduk di meja yang kosong, lalu scan kode QR yang ditempel di meja dengan kamera HP. Web akan terbuka dan tahu kamu duduk di meja mana. Tanpa scan QR, kamu tetap bisa melihat menu, tetapi belum bisa memesan.</p>
    </li>
    <li>
        <h2>Pilih menu</h2>
        <p>Buka <a href="/menu">Menu</a>, pilih kategori atau cari nama menu, lalu tekan <b>Tambah</b>. Menu langsung masuk keranjang tanpa pindah halaman.</p>
        <ul class="guide-notes">
            <li><b>Pilihan dapur</b>: menu yang dipilih dapur untuk hari ini.</li>
            <li><b>Sering dipesan</b>: menu yang paling banyak dipesan tamu lain belakangan ini.</li>
            <li>Stempel merah <span lang="ja">売切</span> berarti menu sedang habis.</li>
        </ul>
    </li>
    <li>
        <h2>Periksa keranjang</h2>
        <p>Di <a href="/keranjang">Keranjang</a> kamu bisa mengubah jumlah atau menghapus menu. Satu meja memakai <b>satu keranjang bersama</b>, jadi teman semeja yang scan QR yang sama bisa ikut menambah menu. Biasanya ada saran minuman atau kue yang cocok di bawah daftar.</p>
    </li>
    <li>
        <h2>Checkout dan pilih cara bayar</h2>
        <p>Isi nama pemesan (dan catatan bila perlu), lalu pilih:</p>
        <ul class="guide-notes">
            <li><b>QRIS</b>: bayar saat itu juga. Pesanan langsung dikirim ke dapur.</li>
            <li><b>Bayar di kasir</b>: datang ke kasir dan sebutkan nomor pesanan. <b>Pesanan baru disiapkan setelah dibayar</b>, dan dibatalkan otomatis jika belum dibayar dalam <?= (int) $unpaidMinutes ?> menit. Halaman pesananmu menampilkan sisa waktunya.</li>
        </ul>
    </li>
    <li>
        <h2>Pantau pesananmu</h2>
        <p>Status di halaman <a href="/pesanan">Pesanan</a> berubah sendiri: <b>Masuk</b>, <b>Diproses</b>, <b>Siap Diantar</b>, lalu <b>Selesai</b>. Saat pesanan siap diantar, muncul tulisan <span lang="ja">お待たせしました</span> dan HP bergetar (jika didukung). Pesanan diantar ke mejamu.</p>
    </li>
    <li>
        <h2>Mau pesan lagi? Silakan</h2>
        <p>Selama masih di meja, kamu boleh memesan lagi kapan saja dari HP yang sama, tanpa scan ulang. Santai saja, meja tidak akan dianggap kosong selama kamu masih di sini.</p>
    </li>
</ol>

<section class="guide-faq" aria-labelledby="faq-head">
    <h2 id="faq-head">Pertanyaan yang sering muncul</h2>
    <details>
        <summary>Tombol Tambah tidak ada atau keranjang tidak bisa dibuka</summary>
        <p>Kamu belum scan QR meja, atau sesi mejamu sudah berakhir (misalnya lebih dari 4 jam, meja sudah dikosongkan staf, atau QR meja baru diganti). Scan lagi QR yang ada di meja.</p>
    </details>
    <details>
        <summary>Saya memilih bayar di kasir, tetapi lewat <?= (int) $unpaidMinutes ?> menit</summary>
        <p>Pesanan dibatalkan otomatis dan tidak ada yang ditagih. Buka pesanan itu lalu tekan <b>Pesan lagi menu yang sama</b>; menunya masuk lagi ke keranjang dan tinggal dikirim ulang.</p>
    </details>
    <details>
        <summary>Kenapa tidak bisa memilih bayar di kasir lagi?</summary>
        <p>Mejamu masih punya pesanan bayar di kasir yang belum dibayar. Bayar dulu pesanan itu di kasir, atau pilih QRIS untuk pesanan yang baru.</p>
    </details>
    <details>
        <summary>Bisakah pesanan dibatalkan?</summary>
        <p>Bisa, selama statusnya masih <b>Masuk</b> (belum mulai disiapkan). Buka pesanan lalu pilih <b>Batalkan pesanan</b>. Jika sudah mulai disiapkan, hubungi kasir.</p>
    </details>
    <details>
        <summary>Apakah teman semeja bisa melihat pesanan saya?</summary>
        <p>Keranjang dipakai bersama satu meja, tetapi halaman pesanan hanya bisa dibuka dari HP yang mengirim pesanan itu.</p>
    </details>
    <?php if ($wifiName !== ''): ?>
        <details>
            <summary>Ada Wi-Fi?</summary>
            <p>Ada. Nama dan kata sandinya tampil di <a href="/">Beranda</a> setelah kamu scan QR meja.</p>
        </details>
    <?php endif; ?>
</section>

<p class="guide-foot">Masih bingung? Tanyakan saja ke staf di kasir.</p>
