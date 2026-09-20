<?php
namespace App\Core;

use RuntimeException;

/**
 * Loads config/config.php once and exposes it with dot-notation lookup.
 */
final class Config
{
    private static array $items = [];
    private static bool  $loaded = false;

    public static function load(string $path): void
    {
        if (!is_file($path)) {
            throw new RuntimeException(
                'Missing config/config.php. Copy config/config.example.php to config/config.php and set your database credentials.'
            );
        }
        self::$items  = require $path;
        self::$loaded = true;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (!self::$loaded) {
            throw new RuntimeException('Config accessed before load().');
        }

        $value = self::$items;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        return $value;
    }

    /** Override a value at runtime (used by bootstrap to resolve base_url). */
    public static function set(string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $ref      = &self::$items;

        foreach ($segments as $segment) {
            if (!isset($ref[$segment]) || !is_array($ref[$segment])) {
                $ref[$segment] = [];
            }
            $ref = &$ref[$segment];
        }

        $ref = $value;
    }

    /** True when a feature flag under `features.*` is enabled. */
    public static function feature(string $name): bool
    {
        return (bool) self::get("features.$name", false);
    }
}
