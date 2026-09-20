<?php
namespace App\Models;

use App\Core\Database;

/**
 * Classes: Court, FunctionHall -- updateAvailability().
 * Use cases: A5 Manage Courts, A6 Manage Function Hall.
 *
 * Both facility types share a shape (name, code, fee, status) so one class
 * serves both, keyed by type. This is what lets `schedules` and `reservations`
 * stay polymorphic.
 */
final class Facility
{
    public const COURT = 'court';
    public const HALL  = 'function_hall';

    /** Table name for a facility type. */
    public static function table(string $type): string
    {
        return $type === self::HALL ? 'function_halls' : 'courts';
    }

    /** The column holding the display name. */
    public static function nameColumn(string $type): string
    {
        return $type === self::HALL ? 'hall_name' : 'court_name';
    }

    public static function rateColumn(string $type): string
    {
        return $type === self::HALL ? 'rental_fee' : 'hourly_rate';
    }

    public static function all(string $type, bool $activeOnly = true): array
    {
        $table = self::table($type);
        $sql   = "SELECT * FROM `$table`";
        if ($activeOnly) {
            $sql .= " WHERE status = 'available'";
        }
        $sql .= ' ORDER BY sort_order, id';

        return Database::select($sql);
    }

    public static function find(string $type, int $id): ?array
    {
        $table = self::table($type);
        return Database::selectOne("SELECT * FROM `$table` WHERE id = :id", ['id' => $id]);
    }

    /** Normalised view of a facility, so views do not branch on type. */
    public static function normalise(string $type, array $row): array
    {
        return [
            'id'       => (int) $row['id'],
            'type'     => $type,
            'name'     => $row[self::nameColumn($type)],
            'code'     => $row[$type === self::HALL ? 'hall_code' : 'court_code'],
            'rate'     => (float) $row[self::rateColumn($type)],
            'capacity' => (int) ($row['capacity'] ?? 4),
            'status'   => $row['status'],
            'image'    => $row['image_path'] ?? null,
            'description' => $row['description'] ?? null,
            'amenities'   => $row['amenities'] ?? null,
            'surface'     => $row['surface'] ?? null,
            'court_type'  => $row['type'] ?? null,
            'min_hours'   => (int) ($row['min_hours'] ?? 1),
        ];
    }

    /** All facilities of a type, normalised, with today's open-slot counts. */
    public static function withAvailability(string $type, string $date): array
    {
        $facilities = self::all($type);
        $summary    = [];

        foreach (Schedule::summaryForDate($type, $date) as $row) {
            $summary[(int) $row['facility_id']] = [
                'total' => (int) $row['total'],
                'open'  => (int) $row['open_slots'],
            ];
        }

        return array_map(static function ($row) use ($type, $summary) {
            $normalised = self::normalise($type, $row);
            $counts     = $summary[$normalised['id']] ?? ['total' => 0, 'open' => 0];
            $normalised['slots_total'] = $counts['total'];
            $normalised['slots_open']  = $counts['open'];
            return $normalised;
        }, $facilities);
    }

    /** Is the facility bookable at all (A5/A6 status flag)? */
    public static function isBookable(string $type, int $id): bool
    {
        $table = self::table($type);
        return (int) Database::scalar(
            "SELECT COUNT(*) FROM `$table` WHERE id = :id AND status = 'available'",
            ['id' => $id],
            0
        ) > 0;
    }

    /** Refuse deletion when history exists; deactivate instead. */
    public static function hasReservations(string $type, int $id): bool
    {
        return (int) Database::scalar(
            'SELECT COUNT(*) FROM reservations WHERE reservation_type = :t AND facility_id = :id',
            ['t' => $type, 'id' => $id],
            0
        ) > 0;
    }
}
