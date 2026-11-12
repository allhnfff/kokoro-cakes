<?php
/** @var array $old */
?>
<div class="card narrow login-card">
    <h1>Masuk kasir</h1>
    <p class="muted">Halaman ini khusus staf. Pelanggan memesan lewat QR di meja.</p>
    <form action="/kasir/login" method="post" class="form">
        <?= csrf_field() ?>
        <div class="field">
            <label for="login">Username</label>
            <input id="login" type="text" name="login" value="<?= e((string) ($old['login'] ?? '')) ?>" autocomplete="username" autocapitalize="none" autocorrect="off" spellcheck="false" maxlength="120" required autofocus>
        </div>
        <div class="field">
            <label for="password">Password</label>
            <input id="password" type="password" name="password" autocomplete="current-password" required>
        </div>
        <label class="radio-line"><input type="checkbox" name="ingat" value="1"> Ingat saya <span class="muted small">(tetap masuk hingga 12 jam di perangkat ini)</span></label>
        <button type="submit" class="btn btn-primary btn-block">Masuk</button>
    </form>
    <p class="small muted">Lupa password? Minta pemilik toko untuk mengatur ulang.</p>
</div>
