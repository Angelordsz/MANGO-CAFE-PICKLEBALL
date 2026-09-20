<?php
namespace App\Core;

/**
 * Per-session CSRF token. Every POST route is checked in Router::dispatch().
 */
final class Csrf
{
    public static function token(): string
    {
        if (!Session::has('_csrf')) {
            Session::set('_csrf', bin2hex(random_bytes(32)));
        }
        return Session::get('_csrf');
    }

    public static function check(mixed $token): bool
    {
        return is_string($token)
            && Session::has('_csrf')
            && hash_equals(Session::get('_csrf'), $token);
    }

    /** Hidden input for forms. */
    public static function field(): string
    {
        return '<input type="hidden" name="_token" value="' . htmlspecialchars(self::token(), ENT_QUOTES) . '">';
    }
}
