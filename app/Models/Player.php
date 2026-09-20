<?php
namespace App\Models;

use App\Core\Database;

/**
 * Class: Player -- register(), updateProfile(), searchOpponent().
 * Use cases: C1, C3, A4, plus Fig. 3.5 / 3.12 walk-in registration.
 *
 * A player record can exist without a user account (walk-ins recorded at the
 * counter). `user_id IS NULL` is the marker.
 */
final class Player
{
    public static function nextCode(): string
    {
        $seq = (int) Database::scalar('SELECT COUNT(*) + 1 FROM players', [], 1);
        return sprintf('PL-%06d', $seq);
    }

    public static function create(array $data): int
    {
        $data['player_code'] = $data['player_code'] ?? self::nextCode();

        if (!empty($data['birthdate']) && empty($data['age'])) {
            $data['age'] = self::ageFromBirthdate($data['birthdate']);
        }

        return Database::insert('players', $data);
    }

    public static function ageFromBirthdate(string $birthdate): ?int
    {
        $ts = strtotime($birthdate);
        if (!$ts) {
            return null;
        }
        return (int) ((new \DateTime('@' . $ts))->diff(new \DateTime('now'))->y);
    }

    public static function find(int $id): ?array
    {
        return Database::selectOne(
            'SELECT p.*, s.level_name, s.level_code, u.username, u.email AS account_email, u.status AS account_status
               FROM players p
          LEFT JOIN skill_levels s ON s.id = p.skill_level_id
          LEFT JOIN users u        ON u.id = p.user_id
              WHERE p.id = :id',
            ['id' => $id]
        );
    }

    public static function findByUser(int $userId): ?array
    {
        return Database::selectOne(
            'SELECT p.*, s.level_name, s.level_code
               FROM players p
          LEFT JOIN skill_levels s ON s.id = p.skill_level_id
              WHERE p.user_id = :u',
            ['u' => $userId]
        );
    }

    public static function fullName(array $player): string
    {
        return trim(($player['first_name'] ?? '') . ' ' . ($player['last_name'] ?? ''));
    }

    /**
     * A4 -- Manage Player Records, with filters.
     * Also backs the type-ahead used by Fig. 3.7 (Add Player to Event).
     */
    public static function search(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $sql = 'SELECT p.*, s.level_name, u.username, u.email AS account_email
                  FROM players p
             LEFT JOIN skill_levels s ON s.id = p.skill_level_id
             LEFT JOIN users u        ON u.id = p.user_id
                 WHERE 1 = 1';
        $params = [];

        if (!empty($filters['q'])) {
            $sql .= " AND (CONCAT(p.first_name, ' ', p.last_name) LIKE :q
                        OR p.phone_number LIKE :q2
                        OR p.email LIKE :q3
                        OR p.player_code LIKE :q4)";
            $like = '%' . $filters['q'] . '%';
            $params += ['q' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like];
        }

        if (!empty($filters['skill_level_id'])) {
            $sql .= ' AND p.skill_level_id = :skill';
            $params['skill'] = $filters['skill_level_id'];
        }

        if (isset($filters['is_walk_in']) && $filters['is_walk_in'] !== '') {
            $sql .= ' AND p.is_walk_in = :walkin';
            $params['walkin'] = (int) $filters['is_walk_in'];
        }

        if (!empty($filters['status'])) {
            $sql .= ' AND p.status = :status';
            $params['status'] = $filters['status'];
        }

        $sql .= ' ORDER BY p.created_at DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;

        return Database::select($sql, $params);
    }

    public static function countSearch(array $filters = []): int
    {
        $sql = 'SELECT COUNT(*) FROM players p WHERE 1 = 1';
        $params = [];

        if (!empty($filters['q'])) {
            $sql .= " AND (CONCAT(p.first_name, ' ', p.last_name) LIKE :q
                        OR p.phone_number LIKE :q2
                        OR p.email LIKE :q3
                        OR p.player_code LIKE :q4)";
            $like = '%' . $filters['q'] . '%';
            $params += ['q' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like];
        }
        if (!empty($filters['skill_level_id'])) {
            $sql .= ' AND p.skill_level_id = :skill';
            $params['skill'] = $filters['skill_level_id'];
        }
        if (isset($filters['is_walk_in']) && $filters['is_walk_in'] !== '') {
            $sql .= ' AND p.is_walk_in = :walkin';
            $params['walkin'] = (int) $filters['is_walk_in'];
        }
        if (!empty($filters['status'])) {
            $sql .= ' AND p.status = :status';
            $params['status'] = $filters['status'];
        }

        return (int) Database::scalar($sql, $params, 0);
    }

    /** Class: History -- submitHistory(). */
    public static function addHistory(
        int $playerId,
        string $type,
        ?int $referenceId,
        string $date,
        ?string $time,
        string $description,
        float $payment = 0.0
    ): int {
        return Database::insert('player_history', [
            'player_id'     => $playerId,
            'activity_type' => $type,
            'reference_id'  => $referenceId,
            'activity_date' => $date,
            'activity_time' => $time,
            'description'   => mb_substr($description, 0, 255),
            'payment'       => $payment,
        ]);
    }

    public static function history(int $playerId, int $limit = 30): array
    {
        return Database::select(
            'SELECT * FROM player_history
              WHERE player_id = :p
           ORDER BY activity_date DESC, activity_time DESC, id DESC
              LIMIT ' . (int) $limit,
            ['p' => $playerId]
        );
    }

    /** Totals shown on the player record screen. */
    public static function summary(int $playerId): array
    {
        $row = Database::selectOne(
            "SELECT
                (SELECT COUNT(*) FROM reservations WHERE player_id = :p1) AS reservations,
                (SELECT COUNT(*) FROM matching_requests WHERE player_id = :p2) AS matching_signups,
                (SELECT COUNT(*) FROM match_assignment_players WHERE player_id = :p3) AS matches_played,
                (SELECT COUNT(*) FROM event_participants WHERE player_id = :p4) AS events_joined,
                (SELECT COALESCE(SUM(amount), 0) FROM payments
                   WHERE player_id = :p5 AND payment_status IN ('paid','verified','partial')) AS total_paid",
            ['p1' => $playerId, 'p2' => $playerId, 'p3' => $playerId, 'p4' => $playerId, 'p5' => $playerId]
        );

        return $row ?: [
            'reservations' => 0, 'matching_signups' => 0, 'matches_played' => 0,
            'events_joined' => 0, 'total_paid' => 0,
        ];
    }
}
