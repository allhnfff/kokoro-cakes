<?php
/** @var string $requestId @var string $detail */
?>
<div class="card narrow">
    <h1>Hasil pesanan belum diketahui</h1>
    <p>Koneksi ke server data terputus saat pesanan dikirim. Pesanan <b>mungkin sudah tercatat</b>, mungkin juga belum.</p>
    <p>Jangan kirim ulang dari keranjang. Tekan tombol di bawah untuk memeriksa: jika sudah tercatat, kamu akan langsung dibawa ke halaman pesanan; jika belum, kamu bisa mengirim lagi tanpa membuat pesanan ganda.</p>
    <form action="/checkout/periksa" method="post">
        <?= csrf_field() ?>
        <button class="btn btn-primary" type="submit">Periksa status pesanan</button>
    </form>
    <p class="small muted">Kode permintaan: <code><?= e(substr($requestId, 0, 8)) ?></code><?= $detail !== '' ? ' · ' . e($detail) : '' ?></p>
</div>
