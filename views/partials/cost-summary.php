<?php
/** @var array $quote subtotal, pajak_persen, pajak, total (dari server) */
?>
<dl class="cost">
    <div><dt>Subtotal</dt><dd><?= rupiah($quote['subtotal']) ?></dd></div>
    <div><dt>Pajak &amp; layanan (<?= (int) $quote['pajak_persen'] ?>%)</dt><dd><?= rupiah($quote['pajak']) ?></dd></div>
    <div class="cost-total"><dt>Total</dt><dd><?= rupiah($quote['total']) ?></dd></div>
</dl>
