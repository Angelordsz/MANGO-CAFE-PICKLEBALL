<?php
namespace App\Models;

use App\Core\Auth;
use App\Core\Database;
use RuntimeException;

/**
 * Class: PlayerMatching -- findOpponent(), matchPlayer(), cancelMatch().
 * Use cases: C9-C12, A11, A12.
 *
 * SCOPE NOTE. The approved scope states matching is "limited to players that
 * register for the selected date and the stated skill level of the players,
 * not incorporating statistical values or sophisticated ranking algorithm."
 *
 * So the algorithm is deliberately simple and deliberately random:
 *   1. Players sign up to a pool = (date, time block, skill level).
 *   2. An admin locks the pool, then generates.
 *   3. Sign-ups are shuffled with a Fisher-Yates shuffle.
 *   4. They are cut into groups of 4 (doubles) or 2 (singles).
 *   5. Leftovers that cannot fill a match are marked `unmatched`.
 *
 * There is no rating maths, no seeding and no historical weighting, by design.
 * Skill fairness comes from the pool itself: everyone in it declared the same
 * level.
 */
final class Matching
{
    public static function nextPoolCode(): string
    {
        $seq = (int) Database::scalar('SELECT COUNT(*) + 1 FROM matching_pools', [], 1);
        return sprintf('MP-%05d', $seq);
    }

    public static function createPool(array $data): int
    {
        $data['pool_code'] = $data['pool_code'] ?? self::nextPoolCode();
        $data['created_by'] = Auth::id();
        return Database::insert('matching_pools', $data);
    }

    public static function findPool(int $id): ?array
    {
        return Database::selectOne(
            'SELECT mp.*, s.level_name, s.level_code,
                    (SELECT COUNT(*) FROM matching_requests mr
                      WHERE mr.pool_id = mp.id AND mr.status <> :cancelled) AS signups
               FROM matching_pools mp
               JOIN skill_levels s ON s.id = mp.skill_level_id
              WHERE mp.id = :id',
            ['id' => $id, 'cancelled' => 'cancelled']
        );
    }

    /** Pools a customer can still join (C9). */
    public static function openPools(?int $playerId = null): array
    {
        return Database::select(
            "SELECT mp.*, s.level_name, s.level_code,
                    (SELECT COUNT(*) FROM matching_requests mr
                      WHERE mr.pool_id = mp.id AND mr.status <> 'cancelled') AS signups,
                    (SELECT COUNT(*) FROM matching_requests mr2
                      WHERE mr2.pool_id = mp.id AND mr2.player_id = :pid AND mr2.status <> 'cancelled') AS joined
               FROM matching_pools mp
               JOIN skill_levels s ON s.id = mp.skill_level_id
              WHERE mp.status = 'open'
                AND mp.match_date >= CURDATE()
           ORDER BY mp.match_date, mp.start_time",
            ['pid' => $playerId ?? 0]
        );
    }

    public static function listPools(array $filters = []): array
    {
        $sql = "SELECT mp.*, s.level_name,
                       (SELECT COUNT(*) FROM matching_requests mr
                         WHERE mr.pool_id = mp.id AND mr.status <> 'cancelled') AS signups,
                       (SELECT COUNT(*) FROM match_assignments ma WHERE ma.pool_id = mp.id) AS matches
                  FROM matching_pools mp
                  JOIN skill_levels s ON s.id = mp.skill_level_id
                 WHERE 1 = 1";
        $params = [];

        if (!empty($filters['status'])) {
            $sql .= ' AND mp.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['date'])) {
            $sql .= ' AND mp.match_date = :date';
            $params['date'] = $filters['date'];
        }

        return Database::select($sql . ' ORDER BY mp.match_date DESC, mp.start_time DESC LIMIT 100', $params);
    }

    /** C9 -- Register for Player Matching. */
    public static function register(int $poolId, int $playerId, ?int $registeredBy = null): array
    {
        $pool = self::findPool($poolId);

        if (!$pool) {
            return ['ok' => false, 'error' => 'That matching session no longer exists.'];
        }
        if ($pool['status'] !== 'open') {
            return ['ok' => false, 'error' => 'Registration for this session is closed.'];
        }
        if ((int) $pool['signups'] >= (int) $pool['max_players']) {
            return ['ok' => false, 'error' => 'This session is already full.'];
        }

        $existing = Database::selectOne(
            'SELECT * FROM matching_requests WHERE pool_id = :p AND player_id = :pl',
            ['p' => $poolId, 'pl' => $playerId]
        );

        if ($existing && $existing['status'] !== 'cancelled') {
            return ['ok' => false, 'error' => 'You are already registered for this session.'];
        }

        if ($existing) {
            Database::update('matching_requests', [
                'status'       => 'registered',
                'cancelled_at' => null,
            ], ['id' => $existing['id']]);
            $requestId = (int) $existing['id'];
        } else {
            $requestId = Database::insert('matching_requests', [
                'pool_id'       => $poolId,
                'player_id'     => $playerId,
                'registered_by' => $registeredBy,
            ]);
        }

        Player::addHistory(
            $playerId,
            'registration',
            $requestId,
            $pool['match_date'],
            $pool['start_time'],
            sprintf('Registered for %s player matching (%s)', $pool['level_name'], $pool['pool_code'])
        );

        return ['ok' => true, 'id' => $requestId];
    }

    public static function cancelRegistration(int $requestId, int $playerId): bool
    {
        return Database::execute(
            "UPDATE matching_requests
                SET status = 'cancelled', cancelled_at = NOW()
              WHERE id = :id AND player_id = :p AND status = 'registered'",
            ['id' => $requestId, 'p' => $playerId]
        ) > 0;
    }

    /** Sign-ups in a pool. */
    public static function signups(int $poolId, bool $activeOnly = true): array
    {
        $sql = "SELECT mr.*, p.first_name, p.last_name, p.player_code, p.phone_number,
                       p.avatar_path, p.is_walk_in, s.level_name
                  FROM matching_requests mr
                  JOIN players p ON p.id = mr.player_id
             LEFT JOIN skill_levels s ON s.id = p.skill_level_id
                 WHERE mr.pool_id = :p";

        if ($activeOnly) {
            $sql .= " AND mr.status <> 'cancelled'";
        }

        return Database::select($sql . ' ORDER BY mr.registered_at', ['p' => $poolId]);
    }

    /** A11 -- lock a pool so no one else can join before generating. */
    public static function lock(int $poolId): bool
    {
        return Database::execute(
            "UPDATE matching_pools SET status = 'locked' WHERE id = :id AND status = 'open'",
            ['id' => $poolId]
        ) > 0;
    }

    /**
     * A12 -- Generate Match Assignments.
     *
     * Random pairing, as the scope requires. Returns a summary of what was
     * created so the controller can report it.
     *
     * @throws RuntimeException when the pool cannot be generated
     */
    public static function generate(int $poolId): array
    {
        $pool = self::findPool($poolId);

        if (!$pool) {
            throw new RuntimeException('Matching session not found.');
        }
        if (!in_array($pool['status'], ['open', 'locked'], true)) {
            throw new RuntimeException('Assignments have already been generated for this session.');
        }

        $perMatch = $pool['match_format'] === 'singles' ? 2 : 4;
        $signups  = self::signups($poolId);

        if (count($signups) < $perMatch) {
            throw new RuntimeException(
                sprintf('Need at least %d players to generate %s matches. Currently %d registered.',
                    $perMatch, $pool['match_format'], count($signups))
            );
        }

        // Courts to spread matches across.
        $courts = Facility::all(Facility::COURT);
        if ($courts === []) {
            throw new RuntimeException('No active courts are available to assign matches to.');
        }

        return Database::transaction(static function () use ($pool, $poolId, $signups, $perMatch, $courts): array {
            // Clear any previous attempt.
            Database::execute('DELETE FROM match_assignments WHERE pool_id = :p', ['p' => $poolId]);

            $players = $signups;
            self::shuffle($players);

            $matchCount = intdiv(count($players), $perMatch);
            $matched    = [];
            $created    = 0;

            $slotMinutes = self::poolDurationMinutes($pool);
            $perRound    = count($courts);

            for ($m = 0; $m < $matchCount; $m++) {
                $group = array_slice($players, $m * $perMatch, $perMatch);

                $round      = intdiv($m, $perRound) + 1;
                $court      = $courts[$m % $perRound];
                $roundStart = strtotime($pool['start_time']) + (($round - 1) * $slotMinutes * 60);

                $assignmentId = Database::insert('match_assignments', [
                    'pool_id'    => $poolId,
                    'court_id'   => $court['id'],
                    'match_no'   => $m + 1,
                    'round_no'   => $round,
                    'start_time' => date('H:i:s', $roundStart),
                    'end_time'   => date('H:i:s', $roundStart + $slotMinutes * 60),
                ]);

                foreach ($group as $i => $player) {
                    // First half of the group is team 1, second half team 2.
                    $team     = $i < ($perMatch / 2) ? 1 : 2;
                    $position = ($i % ($perMatch / 2)) + 1;

                    Database::insert('match_assignment_players', [
                        'assignment_id' => $assignmentId,
                        'player_id'     => $player['player_id'],
                        'team'          => $team,
                        'position'      => (int) $position,
                    ]);

                    $matched[] = (int) $player['player_id'];

                    Notification::sendToPlayer(
                        (int) $player['player_id'],
                        'match_assigned',
                        'Your match is set',
                        sprintf(
                            'Match %d on %s, %s at %s. You are on Team %d.',
                            $m + 1,
                            $court['court_name'],
                            date('M j', strtotime($pool['match_date'])),
                            date('g:i A', $roundStart),
                            $team
                        ),
                        '/matching/results'
                    );

                    Player::addHistory(
                        (int) $player['player_id'],
                        'match',
                        $assignmentId,
                        $pool['match_date'],
                        date('H:i:s', $roundStart),
                        sprintf('Match %d on %s (Team %d)', $m + 1, $court['court_name'], $team)
                    );
                }

                $created++;
            }

            // Mark who got a game and who did not.
            foreach ($signups as $signup) {
                $isMatched = in_array((int) $signup['player_id'], $matched, true);
                Database::update('matching_requests', [
                    'status' => $isMatched ? 'matched' : 'unmatched',
                ], ['id' => $signup['id']]);

                if (!$isMatched) {
                    Notification::sendToPlayer(
                        (int) $signup['player_id'],
                        'matching_registered',
                        'No match this round',
                        'There were not enough players to place you in a match. The front desk will try to fit you in on the day.',
                        '/matching/results'
                    );
                }
            }

            Database::update('matching_pools', [
                'status'       => 'generated',
                'generated_at' => date('Y-m-d H:i:s'),
                'generated_by' => Auth::id(),
            ], ['id' => $poolId]);

            return [
                'matches'   => $created,
                'matched'   => count($matched),
                'unmatched' => count($signups) - count($matched),
            ];
        });
    }

    /** Fisher-Yates using random_int, so the draw is not predictable. */
    private static function shuffle(array &$items): void
    {
        for ($i = count($items) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
        }
    }

    /** Minutes per round: split the pool window across the expected rounds. */
    private static function poolDurationMinutes(array $pool): int
    {
        $total = (strtotime($pool['end_time']) - strtotime($pool['start_time'])) / 60;
        return max(30, (int) min(60, $total));
    }

    /** Generated matches in a pool, with their players. */
    public static function assignments(int $poolId): array
    {
        $matches = Database::select(
            'SELECT ma.*, c.court_name
               FROM match_assignments ma
          LEFT JOIN courts c ON c.id = ma.court_id
              WHERE ma.pool_id = :p
           ORDER BY ma.round_no, ma.match_no',
            ['p' => $poolId]
        );

        foreach ($matches as &$match) {
            $match['players'] = Database::select(
                'SELECT map.*, p.first_name, p.last_name, p.player_code, p.avatar_path
                   FROM match_assignment_players map
                   JOIN players p ON p.id = map.player_id
                  WHERE map.assignment_id = :a
               ORDER BY map.team, map.position',
                ['a' => $match['id']]
            );
        }

        return $matches;
    }

    /** C12 -- View Matching Results, for one player. */
    public static function resultsForPlayer(int $playerId): array
    {
        $matches = Database::select(
            "SELECT ma.*, c.court_name, mp.match_date, mp.pool_code, mp.match_format,
                    s.level_name, map.team AS my_team
               FROM match_assignment_players map
               JOIN match_assignments ma ON ma.id = map.assignment_id
               JOIN matching_pools mp    ON mp.id = ma.pool_id
          LEFT JOIN courts c             ON c.id = ma.court_id
          LEFT JOIN skill_levels s       ON s.id = mp.skill_level_id
              WHERE map.player_id = :p
           ORDER BY mp.match_date DESC, ma.match_no",
            ['p' => $playerId]
        );

        foreach ($matches as &$match) {
            $match['players'] = Database::select(
                'SELECT map.team, map.position, p.id AS player_id, p.first_name, p.last_name, p.avatar_path
                   FROM match_assignment_players map
                   JOIN players p ON p.id = map.player_id
                  WHERE map.assignment_id = :a
               ORDER BY map.team, map.position',
                ['a' => $match['id']]
            );
        }

        return $matches;
    }

    /** A player's pending sign-ups (shown on their dashboard). */
    public static function signupsForPlayer(int $playerId): array
    {
        return Database::select(
            "SELECT mr.*, mp.match_date, mp.start_time, mp.end_time, mp.time_block,
                    mp.status AS pool_status, mp.pool_code, mp.match_format, s.level_name
               FROM matching_requests mr
               JOIN matching_pools mp ON mp.id = mr.pool_id
          LEFT JOIN skill_levels s    ON s.id = mp.skill_level_id
              WHERE mr.player_id = :p AND mr.status <> 'cancelled'
                AND mp.match_date >= CURDATE()
           ORDER BY mp.match_date, mp.start_time",
            ['p' => $playerId]
        );
    }
}
