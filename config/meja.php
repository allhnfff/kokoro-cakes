<?php
/**
 * DATA MEJA DEMO untuk seeder: 1A–2D Indoor, 3A–3D Outdoor (mengikuti wireframe pelanggan).
 * Kapasitas adalah asumsi contoh. Semua meja awal Tersedia dan aktif.
 * Hanya dibaca oleh scripts/seed-demo.php. Website membaca meja dari Redis.
 */

$meja = [];
for ($row = 1; $row <= 3; $row++) {
    foreach (['A', 'B', 'C', 'D'] as $column) {
        $meja[$row . $column] = [
            'kapasitas' => in_array($column, ['A', 'B'], true) ? '2' : '4',
            'status'    => 'Tersedia',
            'area'      => $row <= 2 ? 'Indoor' : 'Outdoor',
            'aktif'     => '1',
        ];
    }
}

return $meja;
