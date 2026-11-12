<?php
/** @var int $step 1 = data pemesan, 2 = tinjau & bayar */
$labels = [1 => 'Data pemesan', 2 => 'Tinjau & bayar', 3 => 'Lacak pesanan'];
?>
<ol class="steps" aria-label="Langkah checkout">
    <?php foreach ($labels as $n => $label): ?>
        <li class="<?= $n < $step ? 'done' : ($n === $step ? 'now' : '') ?>"<?= $n === $step ? ' aria-current="step"' : '' ?>>
            <span class="step-num"><?= $n ?></span><?= e($label) ?>
        </li>
    <?php endforeach; ?>
</ol>
