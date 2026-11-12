<?php
/** @var array $order pesanan lengkap dengan history */
use App\Domain\OrderRules;

$steps = OrderRules::steps();
$current = $order['status_pesanan'];
$cancelled = $current === 'Dibatalkan';
$reachedAt = [];
foreach ($order['history'] as $h) {
    if ($h['jenis'] === 'status') {
        $reachedAt[$h['status']] = $h['at'];
    }
}
$currentIndex = array_search($current, $steps, true);
?>
<ol class="timeline<?= $cancelled ? ' is-cancelled' : '' ?>">
    <?php foreach ($steps as $i => $s):
        $done = !$cancelled && $currentIndex !== false && $i <= $currentIndex;
        $now = !$cancelled && $s === $current;
    ?>
        <li class="<?= $done ? 'done' : '' ?><?= $now ? ' now' : '' ?>"<?= $now ? ' aria-current="step"' : '' ?>>
            <span class="dot" aria-hidden="true"><?= $done ? icon('check', 14) : '' ?></span>
            <span class="tl-label"><?= e($s) ?></span>
            <span class="tl-time"><?= isset($reachedAt[$s]) ? e(wib($reachedAt[$s], 'H:i')) : '' ?></span>
        </li>
    <?php endforeach; ?>
</ol>
