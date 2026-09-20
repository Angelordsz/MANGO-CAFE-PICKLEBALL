<?php
namespace App\Models;

use App\Core\Auth;
use App\Core\Database;
use PDOException;
use RuntimeException;

/**
 * Class: Reservation -- createReservation(), cancelReservation(),
 * confirmReservation().
 *
 * Use cases: C5, C6, C7, C8, C13, A8, A9, A10.
 *
 * Lifecycle:
 *   pending --approve--> approved --complete--> completed
 *           --reject--> rejected
 *           approved --cancel--> cancelled
 *
 * Slots are held the moment a request is submitted (not at approval), so two
 * customers can never have a pending request for the same slot. Rejecting or
 * cancelling deletes the hold and returns the slot to the pool.
 */
final class Reservation
{
    /**
     * Create a reservation and atomically claim its slots.
     *
     * @param array $data     reservation columns
     * @param int[] $slotIds  schedules.id values, must be contiguous
     * @throws RuntimeException when a slot was taken between page load and submit
     */
    public static function create(array $data, array $slotIds): int
    {
        if ($slotIds === []) {
            throw new RuntimeException('Select at least one time slot.');
        }

        return Database::transaction(static function () use ($data, $slotIds): int {
            // Re-check under a row lock: the availability the customer saw may
            // be seconds stale.
            $in    = implode(',', array_fill(0, count($slotIds), '?'));
            $slots = Database::select(
                "SELECT s.* FROM schedules s
                  WHERE s.id IN ($in) AND s.status = 'available'
                  ORDER BY s.start_time
                  FOR UPDATE",
                array_map('intval', $slotIds)
            );

            if (count($slots) !== count($slotIds)) {
                throw new RuntimeException('One of those time slots is no longer available. Please pick again.');
            }

            // Contiguity: a reservation is one unbroken block.
            for ($i = 1; $i < count($slots); $i++) {
                if ($slots[$i]['start_time'] !== $slots[$i - 1]['end_time']) {
                    throw new RuntimeException('Selected time slots must be back to back.');
                }
            }

            $first  = $slots[0];
            $last   = $slots[count($slots) - 1];
            $total  = array_sum(array_map(static fn($s) => (float) $s['price'], $slots));

            $data = array_merge($data, [
                'reservation_code' => self::nextCode(),
                'reservation_date' => $first['slot_date'],
                'start_time'       => $first['start_time'],
                'end_time'         => $last['end_time'],
                'duration_hours'   => count($slots),
                'total_amount'     => $total,
            ]);

            $reservationId = Database::insert('reservations', $data);

            // The UNIQUE key on schedule_id is the real guarantee here.
            try {
                foreach ($slots as $slot) {
                    Database::insert('reservation_schedules', [
                        'reservation_id' => $reservationId,
                        'schedule_id'    => $slot['id'],
                    ]);
                }
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    throw new RuntimeException('Someone just booked one of those slots. Please pick another time.');
                }
                throw $e;
            }

            if (!empty($data['player_id'])) {
                Player::addHistory(
                    (int) $data['player_id'],
                    'reservation',
                    $reservationId,
                    $first['slot_date'],
                    $first['start_time'],
                    sprintf(
                        '%s reservation %s (%s - %s)',
                        ucfirst(str_replace('_', ' ', $data['reservation_type'])),
                        $data['reservation_code'],
                        date('g:i A', strtotime($first['start_time'])),
                        date('g:i A', strtotime($last['end_time']))
                    ),
                    $total
                );
            }

            return $reservationId;
        });
    }

    /** MDC-2026-000431 */
    public static function nextCode(): string
    {
        $year = date('Y');
        $seq  = (int) Database::scalar(
            'SELECT COUNT(*) + 1 FROM reservations WHERE YEAR(created_at) = :y',
            ['y' => $year],
            1
        );
        return sprintf('MDC-%s-%06d', $year, $seq);
    }

    public static function find(int $id): ?array
    {
        return Database::selectOne(self::baseQuery() . ' WHERE r.id = :id', ['id' => $id]);
    }

    public static function findByCode(string $code): ?array
    {
        return Database::selectOne(self::baseQuery() . ' WHERE r.reservation_code = :c', ['c' => $code]);
    }

    /** Joined query that resolves the polymorphic facility name in SQL. */
    private static function baseQuery(): string
    {
        return "SELECT r.*,
                       CASE r.reservation_type
                         WHEN 'court' THEN c.court_name
                         ELSE h.hall_name
                       END AS facility_name,
                       CASE r.reservation_type
                         WHEN 'court' THEN c.court_code
                         ELSE h.hall_code
                       END AS facility_code,
                       p.first_name, p.last_name, p.phone_number, p.email AS player_email,
                       p.player_code,
                       u.username, u.email AS account_email,
                       rev.username AS reviewed_by_name,
                       (SELECT COALESCE(SUM(pay.amount), 0)
                          FROM payments pay
                         WHERE pay.payable_type = 'reservation'
                           AND pay.payable_id = r.id
                           AND pay.payment_status IN ('paid','verified','partial')) AS amount_paid
                  FROM reservations r
             LEFT JOIN courts c         ON r.reservation_type = 'court' AND c.id = r.facility_id
             LEFT JOIN function_halls h ON r.reservation_type = 'function_hall' AND h.id = r.facility_id
             LEFT JOIN players p        ON p.id = r.player_id
             LEFT JOIN users u          ON u.id = r.user_id
             LEFT JOIN users rev        ON rev.id = r.reviewed_by";
    }

    /** Reservations belonging to one account (C13 View My Reservations). */
    public static function forUser(int $userId, ?string $filter = null): array
    {
        $sql    = self::baseQuery() . ' WHERE r.user_id = :u';
        $params = ['u' => $userId];

        $sql .= match ($filter) {
            'upcoming' => " AND r.status IN ('pending','approved') AND r.reservation_date >= CURDATE()",
            'past'     => " AND (r.reservation_date < CURDATE() OR r.status IN ('completed','no_show'))",
            'cancelled'=> " AND r.status IN ('cancelled','rejected')",
            default    => '',
        };

        return Database::select($sql . ' ORDER BY r.reservation_date DESC, r.start_time DESC LIMIT 100', $params);
    }

    /** The slot rows a reservation holds. */
    public static function slots(int $reservationId): array
    {
        return Database::select(
            'SELECT s.* FROM reservation_schedules rs
               JOIN schedules s ON s.id = rs.schedule_id
              WHERE rs.reservation_id = :r
           ORDER BY s.start_time',
            ['r' => $reservationId]
        );
    }

    /** A9 -- Approve. */
    public static function approve(int $id, string $remarks = ''): bool
    {
        $reservation = self::find($id);
        if (!$reservation || $reservation['status'] !== 'pending') {
            return false;
        }

        Database::update('reservations', [
            'status'        => 'approved',
            'admin_remarks' => $remarks ?: null,
            'reviewed_by'   => Auth::id(),
            'reviewed_at'   => date('Y-m-d H:i:s'),
        ], ['id' => $id]);

        Notification::send(
            $reservation['user_id'] ? (int) $reservation['user_id'] : null,
            'reservation_approved',
            'Reservation approved',
            sprintf(
                'Your %s reservation for %s at %s has been approved. Reference %s.',
                str_replace('_', ' ', $reservation['reservation_type']),
                date('M j, Y', strtotime($reservation['reservation_date'])),
                date('g:i A', strtotime($reservation['start_time'])),
                $reservation['reservation_code']
            ),
            '/reservations/' . $reservation['reservation_code'],
            $reservation['player_id'] ? (int) $reservation['player_id'] : null
        );

        return true;
    }

    /** A9 -- Reject. Frees the held slots. */
    public static function reject(int $id, string $reason): bool
    {
        $reservation = self::find($id);
        if (!$reservation || $reservation['status'] !== 'pending') {
            return false;
        }

        Database::transaction(static function () use ($id, $reason, $reservation): void {
            Database::update('reservations', [
                'status'        => 'rejected',
                'admin_remarks' => $reason,
                'reviewed_by'   => Auth::id(),
                'reviewed_at'   => date('Y-m-d H:i:s'),
            ], ['id' => $id]);

            Database::delete('reservation_schedules', ['reservation_id' => $id]);
        });

        Notification::send(
            $reservation['user_id'] ? (int) $reservation['user_id'] : null,
            'reservation_rejected',
            'Reservation declined',
            sprintf('Your request %s was declined. Reason: %s', $reservation['reservation_code'], $reason),
            '/reservations',
            $reservation['player_id'] ? (int) $reservation['player_id'] : null
        );

        return true;
    }

    /** A10 / C8 -- Cancel. Frees the held slots. */
    public static function cancel(int $id, string $reason, ?int $byUserId = null): bool
    {
        $reservation = self::find($id);
        if (!$reservation || in_array($reservation['status'], ['cancelled', 'rejected', 'completed'], true)) {
            return false;
        }

        Database::transaction(static function () use ($id, $reason, $byUserId): void {
            Database::update('reservations', [
                'status'        => 'cancelled',
                'cancel_reason' => $reason,
                'cancelled_by'  => $byUserId ?? Auth::id(),
                'cancelled_at'  => date('Y-m-d H:i:s'),
            ], ['id' => $id]);

            Database::delete('reservation_schedules', ['reservation_id' => $id]);
        });

        Notification::send(
            $reservation['user_id'] ? (int) $reservation['user_id'] : null,
            'reservation_cancelled',
            'Reservation cancelled',
            sprintf('Reservation %s has been cancelled. %s', $reservation['reservation_code'], $reason),
            '/reservations',
            $reservation['player_id'] ? (int) $reservation['player_id'] : null
        );

        return true;
    }

    public static function complete(int $id): bool
    {
        return Database::execute(
            "UPDATE reservations SET status = 'completed' WHERE id = :id AND status = 'approved'",
            ['id' => $id]
        ) > 0;
    }

    /**
     * Can this customer still cancel? Business rule from config:
     * no cancelling within N hours of the start time.
     */
    public static function isCancellable(array $reservation, int $cutoffHours): bool
    {
        if (!in_array($reservation['status'], ['pending', 'approved'], true)) {
            return false;
        }
        $startsAt = strtotime($reservation['reservation_date'] . ' ' . $reservation['start_time']);
        return $startsAt - time() > $cutoffHours * 3600;
    }

    /** How many open requests a customer already has (anti-spam rule). */
    public static function openCountForUser(int $userId): int
    {
        return (int) Database::scalar(
            "SELECT COUNT(*) FROM reservations
              WHERE user_id = :u AND status IN ('pending','approved') AND reservation_date >= CURDATE()",
            ['u' => $userId],
            0
        );
    }
}
