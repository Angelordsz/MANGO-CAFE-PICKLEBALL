<?php
namespace App\Models;

use App\Core\Database;

/**
 * Class: SkillLevel -- evaluatePlayer(), updateLevel().
 * Use case: C11 Select Skill Level.
 *
 * A controlled vocabulary, not free text, so the matching module can bucket
 * players reliably (Scope: matching uses "the stated skill level").
 */
final class SkillLevel
{
    private static ?array $cache = null;

    public static function all(bool $activeOnly = true): array
    {
        if (self::$cache !== null && $activeOnly) {
            return self::$cache;
        }

        $sql = 'SELECT * FROM skill_levels';
        if ($activeOnly) {
            $sql .= ' WHERE is_active = 1';
        }
        $rows = Database::select($sql . ' ORDER BY sort_order, id');

        if ($activeOnly) {
            self::$cache = $rows;
        }

        return $rows;
    }

    public static function find(int $id): ?array
    {
        return Database::selectOne('SELECT * FROM skill_levels WHERE id = :id', ['id' => $id]);
    }

    /** ['1' => 'Beginner (2.0 - 2.5)', ...] for <select> options. */
    public static function options(): array
    {
        $options = [];
        foreach (self::all() as $level) {
            $range = ($level['rating_low'] !== null && $level['rating_high'] !== null)
                ? sprintf(' (%.1f – %.1f)', $level['rating_low'], $level['rating_high'])
                : '';
            $options[(int) $level['id']] = $level['level_name'] . $range;
        }
        return $options;
    }
}
