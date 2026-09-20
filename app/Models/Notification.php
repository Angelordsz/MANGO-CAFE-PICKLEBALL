<?php
namespace App\Models;

use App\Core\Auth;
use App\Core\Database;

/**
 * Class: Notification -- sendNotification(), markAsRead().
 * Use cases: A13 Send Notifications, C14 Receive Notifications.
 *
 * Delivery is in-app only. The capstone scope does not include email or SMS
 * gateways, so a notification is a row the customer sees when they log in.
 */
final class Notification
{
    /** Send to one account. Safe to call with a null user (walk-ins). */
    public static function send(
        ?int $userId,
        string $type,
        string $title,
        string $message,
        ?string $link = null,
        ?int $playerId = null
    ): ?int {
        if ($userId === null && $playerId === null) {
            return null;
        }

        return Database::insert('notifications', [
            'user_id'   => $userId,
            'player_id' => $playerId,
            'type'      => $type,
            'title'     => $title,
            'message'   => $message,
            'link'      => $link,
            'sent_by'   => Auth::id(),
        ]);
    }

    /** Send to the account attached to a player record, if it has one. */
    public static function sendToPlayer(
        int $playerId,
        string $type,
        string $title,
        string $message,
        ?string $link = null
    ): ?int {
        $userId = Database::scalar('SELECT user_id FROM players WHERE id = :id', ['id' => $playerId]);
        return self::send($userId ? (int) $userId : null, $type, $title, $message, $link, $playerId);
    }

    /** Broadcast to every account matching a role. Returns the number sent. */
    public static function broadcast(string $role, string $type, string $title, string $message, ?string $link = null): int
    {
        $sql = $role === 'all'
            ? "SELECT id FROM users WHERE status = 'active'"
            : "SELECT id FROM users WHERE status = 'active' AND role = :role";

        $users = Database::select($sql, $role === 'all' ? [] : ['role' => $role]);

        foreach ($users as $user) {
            self::send((int) $user['id'], $type, $title, $message, $link);
        }

        return count($users);
    }

    public static function forUser(int $userId, int $limit = 50): array
    {
        return Database::select(
            'SELECT * FROM notifications WHERE user_id = :u ORDER BY created_at DESC LIMIT ' . (int) $limit,
            ['u' => $userId]
        );
    }

    public static function unreadCount(int $userId): int
    {
        return (int) Database::scalar(
            "SELECT COUNT(*) FROM notifications WHERE user_id = :u AND status = 'unread'",
            ['u' => $userId],
            0
        );
    }

    public static function markRead(int $id, int $userId): bool
    {
        return Database::execute(
            "UPDATE notifications SET status = 'read', read_at = NOW() WHERE id = :id AND user_id = :u",
            ['id' => $id, 'u' => $userId]
        ) > 0;
    }

    public static function markAllRead(int $userId): int
    {
        return Database::execute(
            "UPDATE notifications SET status = 'read', read_at = NOW() WHERE user_id = :u AND status = 'unread'",
            ['u' => $userId]
        );
    }
}
