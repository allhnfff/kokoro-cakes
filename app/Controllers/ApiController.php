<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Data\DataServiceUnavailable;
use App\Domain\KasirAuth;
use App\Domain\OrderRules;
use App\Domain\ValidationError;
use App\Domain\Validator;
use App\Http\Session;
use App\Http\View;

/**
 * Endpoint JSON yang dipanggil JavaScript (polling ringan).
 * Browser TIDAK pernah terhubung langsung ke Redis; ia hanya memanggil server PHP ini.
 */
final class ApiController extends Controller
{
    /** Ketersediaan semua menu: {"m1":"Tersedia", ...}. */
    public function menu(): void
    {
        try {
            $menu = $this->data()->listMenu();
        } catch (DataServiceUnavailable) {
            View::json(['ok' => false, 'message' => 'Tidak dapat memperbarui ketersediaan menu.'], 503);
            return;
        }
        View::json([
            'ok'         => true,
            'status'     => (object) array_column($menu, 'status', 'id'),
            'fetched_at' => date('H:i:s'),
        ]);
    }

    /** Status satu pesanan milik perangkat ini. */
    public function order(string $id): void
    {
        try {
            $order = $this->data()->getOrder(Validator::orderId($id), ['type' => 'customer', 'owner_hash' => Session::ownerHash()]);
        } catch (ValidationError) {
            $order = null;
        } catch (DataServiceUnavailable) {
            View::json(['ok' => false, 'message' => 'Tidak dapat memperbarui status.'], 503);
            return;
        }
        if ($order === null) {
            View::json(['ok' => false, 'message' => 'Pesanan tidak ditemukan.'], 404);
            return;
        }
        View::json([
            'ok'             => true,
            'id'             => $order['id'],
            'status_pesanan' => $order['status_pesanan'],
            'status_bayar'   => $order['status_bayar'],
            'updated_at'     => $order['updated_at'],
            'text'           => OrderRules::customerText($order['status_pesanan']),
        ]);
    }

    /** Ringkasan untuk dashboard kasir: tanda berubah jika ada pesanan baru atau status berubah. */
    public function kasirSummary(): void
    {
        if (KasirAuth::user() === null) {
            View::json(['ok' => false, 'message' => 'Sesi kasir berakhir. Silakan masuk lagi.'], 401);
            return;
        }
        try {
            $orders = $this->data()->listOrders(KasirAuth::access(), ['limit' => 200]);
        } catch (DataServiceUnavailable) {
            View::json(['ok' => false, 'message' => 'Redis tidak dapat dihubungi.'], 503);
            return;
        }
        $active = array_values(array_filter($orders, static fn ($o) => OrderRules::isActive($o['status_pesanan'])));
        $signature = hash('sha256', implode('|', array_map(static fn ($o) => $o['id'] . ':' . $o['updated_at'], $orders)));
        View::json([
            'ok'        => true,
            'active'    => count($active),
            'masuk'     => count(array_filter($active, static fn ($o) => $o['status_pesanan'] === 'Masuk')),
            'signature' => substr($signature, 0, 16),
        ]);
    }

    public function status(): void
    {
        $health = $this->data()->health();
        View::json([
            'ok'     => $health['ok'],
            'source' => $this->data()->describe(),
            'health' => $health,
            'time'   => date('c'),
        ], $health['ok'] ? 200 : 503);
    }
}
