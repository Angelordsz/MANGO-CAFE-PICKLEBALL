<?php
namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Response;
use App\Models\Payment;
use App\Models\Reservation;

/**
 * Payment recording and verification.
 *
 * RECORD-ONLY by design: the approved scope excludes online payment. Staff
 * record what was handed over at the counter; an administrator verifies it.
 * That separation of duties is why `payments.verify` is admin-only.
 */
final class PaymentController extends Controller
{
    public function index(): void
    {
        $filters = [
            'from'         => (string) $this->request->query('from', date('Y-m-01')),
            'to'           => (string) $this->request->query('to', date('Y-m-d')),
            'method'       => (string) $this->request->query('method', ''),
            'status'       => (string) $this->request->query('status', ''),
            'payable_type' => (string) $this->request->query('payable_type', ''),
        ];

        $payments = Payment::ledger($filters, 200);

        $totals = ['count' => count($payments), 'amount' => 0.0];
        foreach ($payments as $payment) {
            if (in_array($payment['payment_status'], ['paid', 'verified', 'partial'], true)) {
                $totals['amount'] += (float) $payment['amount'];
            }
        }

        $this->view('admin.payments', [
            'title'    => 'Payments · Back office',
            'payments' => $payments,
            'filters'  => $filters,
            'totals'   => $totals,
            'summary'  => Payment::summary($filters),
        ], 'admin');
    }

    /** Record a payment taken at the counter. */
    public function store(): void
    {
        $data = $this->validate([
            'payable_type'   => 'required|in:reservation,event_participant',
            'payable_id'     => 'required|integer',
            'amount'         => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:cash,gcash,maya,bank_transfer,card,other',
            'amount_due'     => 'nullable|numeric',
            'reference_no'   => 'nullable|max:60',
        ]);

        $playerId = $this->request->integer('player_id');

        $paymentId = Payment::record(
            $data['payable_type'],
            (int) $data['payable_id'],
            (float) ($data['amount_due'] ?? 0),
            (float) $data['amount'],
            $data['payment_method'],
            $playerId > 0 ? $playerId : null,
            $data['reference_no'] ?: null,
            $this->request->input('remarks') ?: null
        );

        $this->log('payment.record',
            sprintf('Recorded %s via %s', money($data['amount']), $data['payment_method']),
            'payment', $paymentId);

        $redirect = (string) ($this->request->input('redirect') ?: '/admin/payments');

        Response::redirectWith($redirect, 'success', 'Payment recorded.');
    }

    /** Admin.verifyPayment() -- admin only (see routes/web.php). */
    public function verify(string $id): void
    {
        if (!Payment::verify((int) $id)) {
            Response::back('/admin/payments');
        }

        $this->log('payment.verify', "Verified payment #{$id}", 'payment', (int) $id);

        $this->back('/admin/payments');
    }

    public function void(string $id): void
    {
        $reason = (string) ($this->request->input('reason') ?: 'Voided by administrator');

        Payment::void((int) $id, $reason);

        $this->log('payment.void', "Voided payment #{$id}: {$reason}", 'payment', (int) $id);

        Response::redirectWith('/admin/payments', 'success', 'Payment voided.');
    }

    /** Printable receipt -- Payment.generateReceipt(). */
    public function receipt(string $id): void
    {
        $payment = $this->findOr404(Payment::find((int) $id), 'Payment not found.');

        $reservation = $payment['payable_type'] === 'reservation'
            ? Reservation::find((int) $payment['payable_id'])
            : null;

        $this->view('admin.receipt', [
            'title'       => 'Receipt ' . $payment['payment_code'],
            'payment'     => $payment,
            'reservation' => $reservation,
        ], 'admin');
    }
}
