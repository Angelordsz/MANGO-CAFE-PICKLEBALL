<?php
/**
 * Global view helpers. Loaded once from bootstrap.php.
 */

use App\Core\Auth;
use App\Core\Config;
use App\Core\Csrf;

if (!function_exists('e')) {
    /** Escape for HTML output. Use on EVERY dynamic value in a view. */
    function e(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('url')) {
    /** Build an app URL: url('/reservations') */
    function url(string $path = '/'): string
    {
        $base = rtrim(Config::get('app.base_url', ''), '/');
        return $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        return url('assets/' . ltrim($path, '/'));
    }
}

if (!function_exists('uploaded')) {
    /** URL for an uploaded file, or a placeholder when empty. */
    function uploaded(?string $path, string $fallback = 'img/placeholder.svg'): string
    {
        return $path ? url('uploads/' . ltrim($path, '/')) : asset($fallback);
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return Csrf::field();
    }
}

if (!function_exists('old')) {
    /** Re-fill a form field after a failed submit. */
    function old(string $key, mixed $default = ''): string
    {
        global $old;
        return e($old[$key] ?? $default);
    }
}

if (!function_exists('error_for')) {
    function error_for(string $key): string
    {
        global $errors;
        return isset($errors[$key])
            ? '<p class="field-error">' . e($errors[$key]) . '</p>'
            : '';
    }
}

if (!function_exists('has_error')) {
    function has_error(string $key): bool
    {
        global $errors;
        return isset($errors[$key]);
    }
}

if (!function_exists('money')) {
    /** Format an amount as Philippine pesos. */
    function money(mixed $amount, bool $withSymbol = true): string
    {
        $formatted = number_format((float) $amount, 2);
        return $withSymbol ? '₱' . $formatted : $formatted;
    }
}

if (!function_exists('fdate')) {
    function fdate(?string $date, string $format = 'M j, Y'): string
    {
        if (!$date) {
            return '—';
        }
        $ts = strtotime($date);
        return $ts ? date($format, $ts) : '—';
    }
}

if (!function_exists('ftime')) {
    /** 14:00:00 -> 2:00 PM */
    function ftime(?string $time): string
    {
        if (!$time) {
            return '—';
        }
        $ts = strtotime($time);
        return $ts ? date('g:i A', $ts) : '—';
    }
}

if (!function_exists('ftimerange')) {
    function ftimerange(?string $start, ?string $end): string
    {
        return ftime($start) . ' – ' . ftime($end);
    }
}

if (!function_exists('human_date')) {
    /** "Today", "Tomorrow", or a formatted date. */
    function human_date(?string $date): string
    {
        if (!$date) {
            return '—';
        }
        $d     = date('Y-m-d', strtotime($date));
        $today = date('Y-m-d');

        if ($d === $today) {
            return 'Today';
        }
        if ($d === date('Y-m-d', strtotime('+1 day'))) {
            return 'Tomorrow';
        }
        if ($d === date('Y-m-d', strtotime('-1 day'))) {
            return 'Yesterday';
        }
        return fdate($date);
    }
}

if (!function_exists('auth_user')) {
    function auth_user(): ?array
    {
        return Auth::user();
    }
}

if (!function_exists('auth_player')) {
    function auth_player(): ?array
    {
        return Auth::player();
    }
}

if (!function_exists('initials')) {
    function initials(string $first, string $last = ''): string
    {
        return strtoupper(mb_substr($first, 0, 1) . mb_substr($last, 0, 1));
    }
}

if (!function_exists('status_badge')) {
    /** Map a status string to a CSS badge class. */
    function status_badge(string $status): string
    {
        $map = [
            'pending'     => 'warn',
            'approved'    => 'ok',
            'confirmed'   => 'ok',
            'paid'        => 'ok',
            'verified'    => 'ok',
            'active'      => 'ok',
            'available'   => 'ok',
            'completed'   => 'info',
            'attended'    => 'info',
            'matched'     => 'info',
            'registered'  => 'info',
            'upcoming'    => 'info',
            'rejected'    => 'bad',
            'cancelled'   => 'bad',
            'no_show'     => 'bad',
            'unpaid'      => 'bad',
            'suspended'   => 'bad',
            'blacklisted' => 'bad',
            'partial'     => 'warn',
            'blocked'     => 'muted',
            'maintenance' => 'muted',
            'inactive'    => 'muted',
            'draft'       => 'muted',
        ];
        return $map[$status] ?? 'muted';
    }
}

if (!function_exists('label')) {
    /** open_play -> Open Play */
    function label(?string $value): string
    {
        return $value ? ucwords(str_replace('_', ' ', $value)) : '—';
    }
}

if (!function_exists('nav_active')) {
    /** 'active' when the current path starts with $prefix. */
    function nav_active(string $prefix): string
    {
        $base = rtrim(Config::get('app.base_url', ''), '/');
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
        }
        $path = '/' . ltrim($path, '/');

        if ($prefix === '/') {
            return $path === '/' ? 'active' : '';
        }
        return str_starts_with($path, $prefix) ? 'active' : '';
    }
}

if (!function_exists('nav_exact')) {
    /** 'active' only when the current path matches exactly. */
    function nav_exact(string $target): string
    {
        $base = rtrim(Config::get('app.base_url', ''), '/');
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
        }
        $path = '/' . trim($path, '/');

        return $path === '/' . trim($target, '/') ? 'active' : '';
    }
}

if (!function_exists('feature')) {
    function feature(string $name): bool
    {
        return Config::feature($name);
    }
}

if (!function_exists('pluralise')) {
    function pluralise(int $count, string $singular, ?string $plural = null): string
    {
        return $count === 1 ? $singular : ($plural ?? $singular . 's');
    }
}
