<?php
/** @var string $status  status penyajian atau pembayaran */
?><span class="badge <?= e(status_class($status)) ?>" data-status-badge><?= e($status) ?></span>
