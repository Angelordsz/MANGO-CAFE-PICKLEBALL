<?php
namespace App\Models;

use App\Core\Auth;
use App\Core\Database;

/**
 * Class: Payment -- recordPayment(), verifyPayment(), generateReceipt().
 * Figures 3.5 (Record Walk-in Payment), 3.8, 3.10.
 *
 * RECORD-ONLY. Money is taken at the counter through Mango Drive Cafe's
 * existing channels; this module writes down what was received and lets an
 * admin verify it. There is deliberately no gateway integration -- see
 * docs/DOCUMENT-GAPS.md gap #2.
 */
final class Payment
{
    public static function nextCode(): string
    {
        $seq = (int) Database::scalar('SELECT COUNT(*) + 1 FROM payments', [], 1);
        return sprintf('OR-%06d', $seq);
    }

    /**
     * Record a payment against a reservation or an event registration.
     * Staff may record; only an admin may later verify.
     */
    public static function record(
        string $payableType,
        int $payableId,
        float $amountDue,
        float $amount,
        string $method,
        ?int $playerId = null,
        ?string $referenceNo = null,
        ?string $remarks = null
    ): int {
        $status = match (true) {
            $amount <= 0             => 'unpaid',
            $amount >= $amountDue    => 'paid',
            default                  => 'partial',
        };

        $paymentId = Database::insert('payments', [
            'payment_code'   => self::nextCode(),
            'payable_type'   => $payableType,
            'payable_id'     => $payableId,
            'player_id'      => $playerId,
            'amount_due'     => $amountDue,
            'amount'         => $amount,
            'payment_method' => $method,
            'reference_no'   => $referenceNo,
            'payment_status' => $status,
            'recorded_by'    => Auth::id(),
            'remarks'        => $remarks,
        ]);

        // Keep the event registration's own status column in step (Fig. 3.8).
        if ($payableType === 'event_participant') {
            Database::update('event_participants', ['payment_status' => $status], ['id' => $payableId]);
        }

        if ($playerId) {
            Player::addHistory(
                $playerId,
                'payment',
                $paymentId,
                date('Y-m-d'),
                date('H:i:s'),
                sprintf('Payment %s recorded (%s)', self::nextCodePreview($paymentId), strtoupper($method)),
                $amount
            );

            Notification::sendToPlayer(
                $playerId,
                'payment_recorded',
                'Payment recorded',
                sprintf('We recorded your payment of %s. Thank you!', '₱' . number_format($amount, 2))
            );
        }

        return $paymentId;
    }

    private static function nextCodePreview(int $paymentId): string
    {
        return (string) Database::scalar('SELECT payment_code FROM payments WHERE id = :id', ['id' => $paymentId], '');
    }

    /** Admin.verifyPayment() -- admin only. */
    public static function verify(int $id): bool
    {
        $ok = Database::execute(
            "UPDATE payments
                SET payment_status = 'verified', verified_by = :by, verified_at = NOW()
              WHERE id = :id AND payment_status IN ('paid','partial')",
            ['by' => Auth::id(), 'id' => $id]
        ) > 0;

        if ($ok) {
            $payment = self::find($id);
            if ($payment && $payment['payable_type'] === 'event_participant') {
                Database::update('event_participants', ['payment_status' => 'paid'], ['id' => $payment['payable_id']]);
            }
            if ($payment && $payment['player_id']) {
                Notification::sendToPlayer(
                    (int) $payment['player_id'],
                    'payment_verified',
                    'Payment verified',
                    sprintf('Your payment %s has been verified.', $payment['payment_code'])
                );
            }
        }

        return $ok;
    }

    public static function void(int $id, string $reason): bool
    {
        return Database::execute(
            "UPDATE payments SET payment_status = 'void', remarks = CONCAT(COALESCE(remarks,''), ' | VOID: ', :r)
              WHERE id = :id AND payment_status <> 'void'",
            ['r' => $reason, 'id' => $id]
        ) > 0;
    }

    public static function find(int $id): ?array
    {
        return Database::selectOne(
            "SELECT pay.*,
                    p.first_name, p.last_name, p.player_code,
                    rec.username AS recorded_by_name,
                    ver.username AS verified_by_name
               FROM payments pay
          LEFT JOIN players p ON p.id = pay.player_id
          LEFT JOIN users rec ON rec.id = pay.recorded_by
          LEFT JOIN users ver ON ver.id = pay.verified_by
              WHERE pay.id = :id",
            ['id' => $id]
        );
    }

    /** Payments recorded against one payable. */
    public static function forPayable(string $type, int $id): array
    {
        return Database::select(
            'SELECT pay.*, rec.username AS recorded_by_name, ver.username AS verified_by_name
               FROM payments pay
          LEFT JOIN users rec ON rec.id = pay.recorded_by
          LEFT JOIN users ver ON ver.id = pay.verified_by
              WHERE pay.payable_type = :t AND pay.payable_id = :id
           ORDER BY pay.payment_date DESC',
            ['t' => $type, 'id' => $id]
        );
    }

    public static function totalPaid(string $type, int $id): float
    {
        return (float) Database::scalar(
            "SELECT COALESCE(SUM(amount), 0) FROM payments
              WHERE payable_type = :t AND payable_id = :id
                AND payment_status IN ('paid','verified','partial')",
            ['t' => $type, 'id' => $id],
            0
        );
    }

    /** Ledger with filters, for the payments screen and Fig. 3.10 report. */
    public static function ledger(array $filters = [], int $limit = 100): array
    {
        $sql = "SELECT pay.*,
                       p.first_name, p.last_name, p.player_code,
                       rec.username AS recorded_by_name,
                       ver.username AS verified_by_name,
                       CASE pay.payable_type
                         WHEN 'reservation' THEN r.reservation_code
                         ELSE e.title
                       END AS payable_label
                  FROM payments pay
             LEFT JOIN players p        ON p.id = pay.player_id
             LEFT JOIN users rec        ON rec.id = pay.recorded_by
             LEFT JOIN users ver        ON ver.id = pay.verified_by
             LEFT JOIN reservations r   ON pay.payable_type = 'reservation' AND r.id = pay.payable_id
             LEFT JOIN event_participants ep ON pay.payable_type = 'event_participant' AND ep.id = pay.payable_id
             LEFT JOIN events e         ON e.id = ep.event_id
                 WHERE 1 = 1";
        $params = [];

        if (!empty($filters['from'])) {
            $sql .= ' AND DATE(pay.payment_date) >= :from';
            $params['from'] = $filters['from'];
        }
        if (!empty($filters['to'])) {
            $sql .= ' AND DATE(pay.payment_date) <= :to';
            $params['to'] = $filters['to'];
        }
        if (!empty($filters['method'])) {
            $sql .= ' AND pay.payment_method = :method';
            $params['method'] = $filters['method'];
        }
        if (!empty($filters['status'])) {
            $sql .= ' AND pay.payment_status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['payable_type'])) {
            $sql .= ' AND pay.payable_type = :ptype';
            $params['ptype'] = $filters['payable_type'];
        }

        return Database::select($sql . ' ORDER BY pay.payment_date DESC LIMIT ' . (int) $limit, $params);
    }

    /** Totals by method for the payment report (Fig. 3.10 summary mode). */
    public static function summary(array $filters = []): array
    {
        $sql = "SELECT payment_method,
                       COUNT(*) AS txn_count,
                       SUM(amount) AS total
                  FROM payments
                 WHERE payment_status IN ('paid','verified','partial')";
        $params = [];

        if (!empty($filters['from'])) {
            $sql .= ' AND DATE(payment_date) >= :from';
            $params['from'] = $filters['from'];
        }
        if (!empty($filters['to'])) {
            $sql .= ' AND DATE(payment_date) <= :to';
            $params['to'] = $filters['to'];
        }

        return Database::select($sql . ' GROUP BY payment_method ORDER BY total DESC', $params);
    }
}
