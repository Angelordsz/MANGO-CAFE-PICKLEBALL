<?php
namespace App\Models;

use App\Core\Auth;
use App\Core\Database;

/**
 * Class: Schedule -- createSchedule(), updateSchedule().
 * Use cases: A7 Manage Facility Schedules, C4 View Facility Schedules and
 * Availability.
 *
 * A slot is a physical row. Availability is therefore a lookup, not an
 * overlap calculation, which is what makes double booking impossible.
 */
final class Schedule
{
    /**
     * Generate hourly slots for one facility across a date range.
     * Existing slots are left alone (INSERT IGNORE on the unique key), so this
     * is safe to re-run.
     *
     * @return int number of slots created
     */
    public static function generate(
        string $facilityType,
        int $facilityId,
        string $fromDate,
        string $toDate,
        float $price,
        int $slotMinutes = 60
    ): int {
        $hours = self::operatingHours();
        $created = 0;

        $cursor = strtotime($fromDate);
        $end    = strtotime($toDate);

        while ($cursor <= $end) {
            $date = date('Y-m-d', $cursor);
            $dow  = (int) date('w', $cursor);

            $cursor = strtotime('+1 day', $cursor);

            if (!isset($hours[$dow]) || $hours[$dow]['is_closed']) {
                continue;
            }

            $open  = strtotime($date . ' ' . $hours[$dow]['open_time']);
            $close = strtotime($date . ' ' . $hours[$dow]['close_time']);

            for ($t = $open; $t + ($slotMinutes * 60) <= $close; $t += $slotMinutes * 60) {
                $affected = Database::execute(
                    'INSERT IGNORE INTO schedules
                        (facility_type, facility_id, slot_date, start_time, end_time, price, status, created_by)
                     VALUES (:ft, :fi, :d, :st, :et, :p, :s, :cb)',
                    [
                        'ft' => $facilityType,
                        'fi' => $facilityId,
                        'd'  => $date,
                        'st' => date('H:i:s', $t),
                        'et' => date('H:i:s', $t + ($slotMinutes * 60)),
                        'p'  => $price,
                        's'  => 'available',
                        'cb' => Auth::id(),
                    ]
                );
                $created += $affected;
            }
        }

        return $created;
    }

    /** Operating hours keyed by day of week. */
    public static function operatingHours(): array
    {
        $rows = Database::select('SELECT * FROM operating_hours');
        $map  = [];
        foreach ($rows as $row) {
            $map[(int) $row['day_of_week']] = $row;
        }
        return $map;
    }

    /**
     * Slots for a facility on a date, each flagged with whether it is taken.
     * This is the query behind both the availability calendar and the booking
     * form.
     */
    public static function forDate(string $facilityType, int $facilityId, string $date): array
    {
        return Database::select(
            // The state strings are constants, not user input, so they are
            // written inline: MySQL will not accept the same named parameter
            // twice in one statement when emulated prepares are off.
            "SELECT s.*,
                    rs.reservation_id,
                    r.status AS reservation_status,
                    CASE
                      WHEN s.status <> 'available' THEN s.status
                      WHEN rs.id IS NOT NULL       THEN 'booked'
                      ELSE 'available'
                    END AS slot_state
               FROM schedules s
          LEFT JOIN reservation_schedules rs ON rs.schedule_id = s.id
          LEFT JOIN reservations r ON r.id = rs.reservation_id
              WHERE s.facility_type = :ft
                AND s.facility_id   = :fi
                AND s.slot_date     = :d
           ORDER BY s.start_time",
            [
                'ft' => $facilityType,
                'fi' => $facilityId,
                'd'  => $date,
            ]
        );
    }

    /** Availability counts for every facility of a type, for one date. */
    public static function summaryForDate(string $facilityType, string $date): array
    {
        return Database::select(
            "SELECT s.facility_id,
                    COUNT(*) AS total,
                    SUM(CASE WHEN rs.id IS NULL AND s.status = 'available' THEN 1 ELSE 0 END) AS open_slots
               FROM schedules s
          LEFT JOIN reservation_schedules rs ON rs.schedule_id = s.id
              WHERE s.facility_type = :ft AND s.slot_date = :d
           GROUP BY s.facility_id",
            ['ft' => $facilityType, 'd' => $date]
        );
    }

    /** Fetch specific slots and confirm every one is still free. */
    public static function lockable(array $slotIds): array
    {
        if ($slotIds === []) {
            return [];
        }

        $in = implode(',', array_fill(0, count($slotIds), '?'));

        return Database::select(
            "SELECT s.*
               FROM schedules s
          LEFT JOIN reservation_schedules rs ON rs.schedule_id = s.id
              WHERE s.id IN ($in)
                AND s.status = 'available'
                AND rs.id IS NULL
           ORDER BY s.start_time",
            array_map('intval', $slotIds)
        );
    }

    public static function find(int $id): ?array
    {
        return Database::selectOne('SELECT * FROM schedules WHERE id = :id', ['id' => $id]);
    }

    /** Block a slot for maintenance or a private event (A7). */
    public static function block(int $id, string $reason): bool
    {
        $taken = Database::scalar(
            'SELECT COUNT(*) FROM reservation_schedules WHERE schedule_id = :id',
            ['id' => $id],
            0
        );

        if ((int) $taken > 0) {
            return false; // cannot block a slot that is already reserved
        }

        return Database::update('schedules', [
            'status'       => 'blocked',
            'block_reason' => $reason,
        ], ['id' => $id]) > 0;
    }

    public static function unblock(int $id): bool
    {
        return Database::update('schedules', [
            'status'       => 'available',
            'block_reason' => null,
        ], ['id' => $id]) > 0;
    }

    /** Bulk block a time range across a facility (maintenance windows). */
    public static function blockRange(
        string $facilityType,
        int $facilityId,
        string $date,
        string $startTime,
        string $endTime,
        string $reason
    ): int {
        return Database::execute(
            "UPDATE schedules s
                LEFT JOIN reservation_schedules rs ON rs.schedule_id = s.id
                SET s.status = 'blocked', s.block_reason = :reason
              WHERE s.facility_type = :ft
                AND s.facility_id = :fi
                AND s.slot_date = :d
                AND s.start_time >= :st
                AND s.end_time  <= :et
                AND rs.id IS NULL",
            [
                'reason' => $reason,
                'ft'     => $facilityType,
                'fi'     => $facilityId,
                'd'      => $date,
                'st'     => $startTime,
                'et'     => $endTime,
            ]
        );
    }

    /** Dates that still have at least one open slot, for the date strip. */
    public static function openDates(string $facilityType, int $days = 30): array
    {
        return Database::select(
            "SELECT s.slot_date, COUNT(*) AS open_slots
               FROM schedules s
          LEFT JOIN reservation_schedules rs ON rs.schedule_id = s.id
              WHERE s.facility_type = :ft
                AND s.slot_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL " . (int) $days . " DAY)
                AND s.status = 'available'
                AND rs.id IS NULL
           GROUP BY s.slot_date
           ORDER BY s.slot_date",
            ['ft' => $facilityType]
        );
    }
}
