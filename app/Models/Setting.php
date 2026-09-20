<?php
namespace App\Models;

use App\Core\Database;

/**
 * Key/value platform settings, plus the skill-level reference table
 * (Class: SkillLevel, use case C11 Select Skill Level).
 */
final class Setting
{
    private static ?array $cache = null;

    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $rows = Database::select('SELECT setting_key, setting_value FROM settings');
        $map  = [];
        foreach ($rows as $row) {
            $map[$row['setting_key']] = $row['setting_value'];
        }

        return self::$cache = $map;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::all()[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        Database::execute(
            'INSERT INTO settings (setting_key, setting_value) VALUES (:k, :v)
             ON DUPLICATE KEY UPDATE setting_value = :v2',
            ['k' => $key, 'v' => (string) $value, 'v2' => (string) $value]
        );
        self::$cache = null;
    }

    public static function grouped(): array
    {
        $rows   = Database::select('SELECT * FROM settings ORDER BY setting_group, setting_key');
        $groups = [];
        foreach ($rows as $row) {
            $groups[$row['setting_group']][] = $row;
        }
        return $groups;
    }
}
