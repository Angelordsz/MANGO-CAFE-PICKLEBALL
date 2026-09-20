<?php
namespace App\Core;

/**
 * Authentication and role checks.
 *
 * Roles (see docs/DOCUMENT-GAPS.md gap #5):
 *   admin    - full access
 *   staff    - counter operations: walk-ins, reservations, recording payments
 *   customer - the Customer/Player actor from Figure 3.3
 */
final class Auth
{
    private static ?array $user   = null;
    private static ?array $player = null;

    /** Verify credentials and start an authenticated session. */
    public static function attempt(string $identity, string $password, string $ip = ''): array
    {
        $user = Database::selectOne(
            'SELECT * FROM users WHERE username = :i OR email = :i2 LIMIT 1',
            ['i' => $identity, 'i2' => $identity]
        );

        if (!$user) {
            return ['ok' => false, 'error' => 'No account found with that username or email.'];
        }

        if ($user['locked_until'] !== null && strtotime($user['locked_until']) > time()) {
            $minutes = (int) ceil((strtotime($user['locked_until']) - time()) / 60);
            return ['ok' => false, 'error' => "Too many failed attempts. Try again in {$minutes} minute(s)."];
        }

        if (!password_verify($password, $user['password_hash'])) {
            self::registerFailure($user);
            return ['ok' => false, 'error' => 'Incorrect password.'];
        }

        if ($user['status'] === 'suspended') {
            return ['ok' => false, 'error' => 'This account is suspended. Please contact the administrator.'];
        }

        // Rehash if PHP's default cost has since increased.
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            Database::update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], ['id' => $user['id']]);
        }

        Database::update('users', [
            'failed_attempts' => 0,
            'locked_until'    => null,
            'last_login_at'   => date('Y-m-d H:i:s'),
            'last_login_ip'   => $ip,
        ], ['id' => $user['id']]);

        Session::regenerate();
        Session::set('user_id', (int) $user['id']);
        self::$user = $user;

        return ['ok' => true, 'user' => $user];
    }

    private static function registerFailure(array $user): void
    {
        $attempts = (int) $user['failed_attempts'] + 1;
        $data     = ['failed_attempts' => $attempts];

        if ($attempts >= 5) {
            $data['locked_until']    = date('Y-m-d H:i:s', time() + 900); // 15 minutes
            $data['failed_attempts'] = 0;
        }

        Database::update('users', $data, ['id' => $user['id']]);
    }

    public static function login(int $userId): void
    {
        Session::regenerate();
        Session::set('user_id', $userId);
        self::$user = null;
    }

    public static function logout(): void
    {
        self::$user   = null;
        self::$player = null;
        Session::destroy();
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function guest(): bool
    {
        return !self::check();
    }

    /** The logged-in user row, or null. */
    public static function user(): ?array
    {
        if (self::$user !== null) {
            return self::$user;
        }

        $id = Session::get('user_id');
        if (!$id) {
            return null;
        }

        $user = Database::selectOne('SELECT * FROM users WHERE id = :id AND status != :s LIMIT 1', [
            'id' => $id,
            's'  => 'suspended',
        ]);

        if (!$user) {
            Session::forget('user_id');
            return null;
        }

        return self::$user = $user;
    }

    public static function id(): ?int
    {
        $user = self::user();
        return $user ? (int) $user['id'] : null;
    }

    /** The player record attached to the logged-in account, or null. */
    public static function player(): ?array
    {
        if (self::$player !== null) {
            return self::$player;
        }

        $id = self::id();
        if (!$id) {
            return null;
        }

        $player = Database::selectOne(
            'SELECT p.*, s.level_name, s.level_code
               FROM players p
          LEFT JOIN skill_levels s ON s.id = p.skill_level_id
              WHERE p.user_id = :id LIMIT 1',
            ['id' => $id]
        );

        return self::$player = ($player ?: null);
    }

    public static function playerId(): ?int
    {
        $player = self::player();
        return $player ? (int) $player['id'] : null;
    }

    public static function role(): ?string
    {
        $user = self::user();
        return $user['role'] ?? null;
    }

    public static function isAdmin(): bool
    {
        return self::role() === 'admin';
    }

    /** True for admin and staff - anyone who may open the back office. */
    public static function isStaff(): bool
    {
        return in_array(self::role(), ['admin', 'staff'], true);
    }

    public static function isCustomer(): bool
    {
        return self::role() === 'customer';
    }

    /**
     * Admin-only capabilities. Staff are deliberately excluded from these
     * (separation of duties - staff record payments, admins verify them).
     */
    public static function can(string $ability): bool
    {
        $adminOnly = [
            'users.manage',
            'settings.manage',
            'logs.view',
            'payments.verify',
            'courts.manage',
            'halls.manage',
        ];

        if (in_array($ability, $adminOnly, true)) {
            return self::isAdmin();
        }

        return self::isStaff();
    }

    public static function hash(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }
}
