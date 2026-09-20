<?php
namespace App\Core;

/**
 * Route guards. Each returns true to continue, or redirects and returns false.
 */
final class Middleware
{
    public static function run(string $name): bool
    {
        return match ($name) {
            'auth'     => self::auth(),
            'guest'    => self::guest(),
            'admin'    => self::admin(),
            'staff'    => self::staff(),
            'customer' => self::customer(),
            default    => true,
        };
    }

    /** Must be logged in. Remembers where they were headed. */
    private static function auth(): bool
    {
        if (Auth::check()) {
            return true;
        }
        Session::set('_intended', $_SERVER['REQUEST_URI'] ?? '');
        Session::flash('error', 'Please log in to continue.');
        Response::redirect('/login');
    }

    /** Must NOT be logged in (login and register pages). */
    private static function guest(): bool
    {
        if (Auth::guest()) {
            return true;
        }
        Response::redirect(Auth::isStaff() ? '/admin' : '/dashboard');
    }

    /** Admin only. */
    private static function admin(): bool
    {
        if (!Auth::check()) {
            Session::set('_intended', $_SERVER['REQUEST_URI'] ?? '');
            Response::redirect('/login');
        }
        if (!Auth::isAdmin()) {
            Response::abort(403, 'Administrator access required.');
        }
        return true;
    }

    /** Admin or staff - the back office. */
    private static function staff(): bool
    {
        if (!Auth::check()) {
            Session::set('_intended', $_SERVER['REQUEST_URI'] ?? '');
            Response::redirect('/login');
        }
        if (!Auth::isStaff()) {
            Response::abort(403, 'Staff access required.');
        }
        return true;
    }

    /**
     * Customer-facing pages.
     *
     * Staff and administrators are sent to the back office instead: they have
     * no `players` row, so the booking and matching screens have nothing to
     * work with. Staff book on a customer's behalf through
     * /admin/reservations/create.
     */
    private static function customer(): bool
    {
        if (!Auth::check()) {
            Session::set('_intended', $_SERVER['REQUEST_URI'] ?? '');
            Session::flash('error', 'Please log in to continue.');
            Response::redirect('/login');
        }

        if (Auth::isStaff()) {
            Session::flash('info', 'Staff accounts book on a customer\'s behalf from the back office.');
            Response::redirect('/admin/reservations/create');
        }

        return true;
    }
}
