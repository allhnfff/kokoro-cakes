<?php

declare(strict_types=1);

/**
 * Proses anak untuk uji checkout BERSAMAAN (dipanggil oleh tests/run-tests.php, bukan manual).
 * Argumen: <waktu_mulai_microtime> <request_id> <kode_meja> <owner_seed>
 * Setiap proses membuka koneksi Redis sendiri, membaca keranjang meja, menunggu waktu mulai
 * yang sama, lalu checkout.
 */

require __DIR__ . '/../app/bootstrap.php';

use App\App;
use App\Data\DomainRejected;
use App\Data\RedisLocalAdapter;

[, $startAt, $requestId, $table, $ownerSeed] = $argv;

$adapter = RedisLocalAdapter::fromConfig(App::allConfig(), (int) App::config('redis.test_database'));
$items = $adapter->getCart($table); // memanaskan koneksi sebelum garis start

while (microtime(true) < (float) $startAt) {
    usleep(200);
}

try {
    $res = $adapter->checkout([
        'request_id' => $requestId, 'kode_meja' => $table, 'owner_hash' => hash('sha256', $ownerSeed),
        'nama_pemesan' => 'Uji Bersamaan', 'catatan' => '', 'metode_bayar' => 'tunai', 'items' => $items,
    ]);
    echo json_encode(['ok' => true, 'status' => $res['status'], 'order_id' => $res['order']['id']]);
} catch (DomainRejected $e) {
    echo json_encode(['ok' => false, 'code' => $e->errorCode]);
} catch (\Throwable $e) {
    echo json_encode(['ok' => false, 'code' => 'ERROR', 'message' => $e->getMessage()]);
}
