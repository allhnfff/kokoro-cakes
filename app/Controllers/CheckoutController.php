<?php

declare(strict_types=1);

namespace App\Controllers;

use App\App;
use App\Data\DataServiceUnavailable;
use App\Data\DomainRejected;
use App\Domain\CheckoutService;
use App\Domain\ValidationError;
use App\Domain\Validator;
use App\Http\Csrf;
use App\Http\HttpException;
use App\Http\Session;
use App\Http\View;

/**
 * Alur checkout:
 *   GET  /checkout              form nama pemesan, catatan, metode bayar
 *   POST /checkout/tinjau       validasi form, buat request_id, simpan draf -> redirect
 *   GET  /checkout/tinjau       halaman tinjau + pembayaran (QRIS simulasi / bayar di kasir)
 *   POST /checkout/konfirmasi   panggil checkout atomik di Redis (pesanan dibuat di sini)
 *   POST /checkout/periksa      cari hasil request_id jika hasil sebelumnya belum diketahui
 *   POST /checkout/batal        batalkan draf (pesanan tidak dibuat)
 */
final class CheckoutController extends Controller
{
    private function service(): CheckoutService
    {
        return new CheckoutService(
            (array) App::config('order.payment_methods'),
            (int) App::config('order.tax_percent')
        );
    }

    private function access(): array
    {
        return ['type' => 'customer', 'owner_hash' => Session::ownerHash()];
    }

    public function show(): void
    {
        $table = $this->requireTable();
        // Jika ada checkout yang hasilnya belum diketahui, cari hasilnya dulu sebelum checkout baru.
        if (!empty($_SESSION['checkout_pending'])) {
            $this->resolvePending(false);
        }
        $cart = $this->cart()->detailed($table);
        if (!$cart['lines']) {
            Session::flash('info', 'Keranjang masih kosong.');
            View::redirect('/menu');
        }
        if ($cart['has_problem']) {
            Session::flash('error', 'Ada menu yang baru saja habis. Perbaiki keranjang terlebih dahulu.');
            View::redirect('/keranjang');
        }
        $old = Session::takeOld() ?: ($_SESSION['checkout_draft']['input'] ?? []);
        $errors = is_array($_SESSION['_field_errors'] ?? null) ? $_SESSION['_field_errors'] : [];
        unset($_SESSION['_field_errors']);
        $this->render('shop/checkout', [
            'title'   => 'Checkout',
            'cart'    => $cart,
            'quote'   => $this->service()->quote($cart),
            'old'     => $old,
            'errors'  => $errors,
            'methods' => (array) App::config('order.payment_methods'),
            'step'    => 1,
        ]);
    }

    public function review(): void
    {
        Csrf::verify();
        $table = $this->requireTable();
        $input = [
            'nama_pemesan' => $this->post('nama_pemesan'),
            'catatan'      => $this->post('catatan'),
            'metode_bayar' => $this->post('metode_bayar'),
        ];
        try {
            $form = $this->service()->validateForm($input);
        } catch (ValidationError $e) {
            Session::setOld($input);
            $errors = $this->service()->fieldErrors($input) ?: ['form' => $e->getMessage()];
            $_SESSION['_field_errors'] = $errors;
            Session::flash('error', count($errors) > 1
                ? 'Ada ' . count($errors) . ' kolom yang perlu diperbaiki.'
                : 'Periksa kembali: ' . reset($errors));
            View::redirect('/checkout');
        }
        $raw = $this->data()->getCart($table);
        if (!$raw) {
            Session::flash('info', 'Keranjang masih kosong.');
            View::redirect('/menu');
        }
        $_SESSION['checkout_draft'] = [
            'request_id'  => bin2hex(random_bytes(16)), // kunci idempotensi
            'table'       => $table,
            'form'        => $form,
            'input'       => $input,
            'fingerprint' => CheckoutService::cartFingerprint($raw),
            'created_at'  => time(),
        ];
        View::redirect('/checkout/tinjau');
    }

    public function reviewPage(): void
    {
        $table = $this->requireTable();
        $draft = $_SESSION['checkout_draft'] ?? null;
        if (!$draft || $draft['table'] !== $table) {
            View::redirect('/checkout');
        }
        $cart = $this->cart()->detailed($table);
        if (!$cart['lines']) {
            unset($_SESSION['checkout_draft']);
            View::redirect('/menu');
        }
        $raw = array_map(static fn ($l) => ['id_menu' => $l['id_menu'], 'qty' => $l['qty']], $cart['lines']);
        if (CheckoutService::cartFingerprint($raw) !== $draft['fingerprint'] || $cart['has_problem']) {
            unset($_SESSION['checkout_draft']);
            Session::flash('info', 'Keranjang meja berubah. Silakan tinjau ulang pesanan.');
            View::redirect('/checkout');
        }
        $this->render('shop/review', [
            'title'   => 'Tinjau & Bayar',
            'cart'    => $cart,
            'draft'   => $draft,
            'quote'   => $this->service()->quote($cart),
            'pending' => !empty($_SESSION['checkout_pending']),
            'step'    => 2,
        ]);
    }

    public function confirm(): void
    {
        Csrf::verify();
        $table = $this->requireTable();
        try {
            $requestId = Validator::requestId($this->post('request_id'));
        } catch (ValidationError) {
            throw new HttpException(400, 'Permintaan checkout tidak valid.');
        }
        $draft = $_SESSION['checkout_draft'] ?? null;

        // Klik ganda / kirim ulang setelah sukses: draf sudah dihapus -> tampilkan pesanan yang sama.
        if (!$draft || $draft['request_id'] !== $requestId) {
            $existing = $this->data()->getCheckoutResult($requestId, $this->access());
            if ($existing !== null) {
                View::redirect('/pesanan/' . $existing['order']['id'] . '?baru=1');
            }
            Session::flash('info', 'Sesi konfirmasi sudah tidak berlaku. Silakan ulangi checkout.');
            View::redirect('/checkout');
        }

        $raw = $this->data()->getCart($table);
        if ($draft['table'] !== $table || CheckoutService::cartFingerprint($raw) !== $draft['fingerprint']) {
            unset($_SESSION['checkout_draft']);
            Session::flash('info', 'Keranjang meja berubah sebelum dikirim. Silakan tinjau ulang.');
            View::redirect('/checkout');
        }

        $request = $this->service()->buildRequest($requestId, $table, Session::ownerHash(), $draft['form'], $raw);
        try {
            $result = $this->data()->checkout($request);
        } catch (DomainRejected $e) {
            unset($_SESSION['checkout_draft']);
            Session::flash('error', $this->rejectionMessage($e));
            // UNPAID_EXISTS: keranjang tetap utuh; kembali ke checkout agar tamu bisa memilih QRIS.
            View::redirect(in_array($e->errorCode, ['IDEMPOTENCY_CONFLICT', 'UNPAID_EXISTS'], true) ? '/checkout' : '/keranjang');
        } catch (DataServiceUnavailable $e) {
            // Hasil belum diketahui: JANGAN anggap gagal atau berhasil. Simpan request_id untuk diperiksa.
            $_SESSION['checkout_pending'] = ['request_id' => $requestId, 'at' => time()];
            $this->render('shop/checkout-unknown', [
                'title'     => 'Hasil Pesanan Belum Diketahui',
                'requestId' => $requestId,
                'detail'    => App::config('app.debug') ? $e->getMessage() : '',
            ], 503);
            return;
        }
        $this->finish($result['order']);
    }

    /** Periksa hasil checkout yang sebelumnya belum diketahui. */
    public function verify(): void
    {
        Csrf::verify();
        if (empty($_SESSION['checkout_pending'])) {
            View::redirect('/checkout');
        }
        $this->resolvePending(true);
    }

    private function resolvePending(bool $explicit): void
    {
        $pending = $_SESSION['checkout_pending'];
        $found = $this->data()->getCheckoutResult((string) $pending['request_id'], $this->access());
        if ($found !== null) {
            $this->finish($found['order']);
        }
        unset($_SESSION['checkout_pending']);
        Session::flash('info', 'Pesanan dari percobaan sebelumnya belum tercatat. '
            . 'Kamu bisa menekan tombol kirim lagi tanpa membuat pesanan ganda.');
        if ($explicit) {
            View::redirect(!empty($_SESSION['checkout_draft']) ? '/checkout/tinjau' : '/checkout');
        }
    }

    public function cancel(): void
    {
        Csrf::verify();
        unset($_SESSION['checkout_draft']);
        Session::flash('info', 'Pembayaran dibatalkan. Pesanan tidak dibuat dan keranjang tetap tersimpan.');
        View::redirect('/keranjang');
    }

    /** Sukses: keranjang meja sudah dikosongkan oleh skrip checkout. Tampilkan pesanan. */
    private function finish(array $order): never
    {
        unset($_SESSION['checkout_draft'], $_SESSION['checkout_pending']);
        View::redirect('/pesanan/' . $order['id'] . '?baru=1');
    }

    private function rejectionMessage(DomainRejected $e): string
    {
        if ($e->errorCode !== 'MENU_UNAVAILABLE') {
            return $e->getMessage();
        }
        $names = [];
        foreach ((array) ($e->details['problems'] ?? []) as $p) {
            if (($p['code'] ?? '') === 'MENU_UNAVAILABLE') {
                $names[] = (string) ($p['nama_menu'] ?? $p['id_menu'] ?? '');
            }
        }
        return 'Pesanan belum dikirim: ' . implode(', ', array_filter($names)) . ' baru saja habis. '
            . 'Keranjang tetap tersimpan, silakan sesuaikan.';
    }
}
