<?php
/** @var int $status @var string $message */
?>
<div class="card narrow error-page">
    <p class="meta">Kode <?= (int) $status ?></p>
    <h1><?= e($title ?? 'Terjadi kendala') ?></h1>
    <p><?= e($message) ?></p>
    <p><a class="btn btn-primary" href="/">Kembali ke beranda</a> <a class="btn btn-ghost" href="/menu">Lihat menu</a></p>
</div>
