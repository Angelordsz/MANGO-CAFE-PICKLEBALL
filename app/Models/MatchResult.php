<?php
namespace App\Models;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Database;

/**
 * ENHANCEMENT LAYER -- NOT PART OF THE APPROVED SCOPE.
 *
 * Score entry for a generated match, and the win/loss counters it feeds.
 * Guarded by config `features.match_results` / `features.player_stats`.
 *
 * Important for the defense: these numbers are display-only. They are never
 * read by Matching::generate(), so the draw stays random exactly as the Scope
 * requires ("not incorporating statistical values or sophisticated ranking
 * algorithm"). See docs/ENHANCEMENTS.md.
 */
final class MatchResult
{
    public static function enabled(): bool
    {
        return Config::feature('enhancements') && Config::feature('match_results');
    }

    public static function statsEnabled(): bool
    {
        return Config::feature('enhancements') && Config::feature('player_stats');
    }

    /** Record or overwrite the score for a generated match. */
    public static function record(int $assignmentId, int $team1Score, int $team2Score): array
    {
        if (!self::enabled()) {
            return ['ok' => false, 'error' => 'Score entry is disabled.'];
        }

        $assignment = Database::selectOne(
            'SELECT ma.*, mp.match_date FROM match_assignments ma
               JOIN matching_pools mp ON mp.id = ma.pool_id
              WHERE ma.id = :id',
            ['id' => $assignmentId]
        );

        if (!$assignment) {
            return ['ok' => false, 'error' => 'Match not found.'];
        }

        if ($team1Score === $team2Score) {
            return ['ok' => false, 'error' => 'Pickleball games cannot end in a draw.'];
        }

        $winningTeam = $team1Score > $team2Score ? 1 : 2;

        Database::transaction(static function () use ($assignmentId, $team1Score, $team2Score, $winningTeam, $assignment): void {
            Database::execute(
                'INSERT INTO enh_match_results (assignment_id, team1_score, team2_score, winning_team, recorded_by)
                 VALUES (:a, :t1, :t2, :w, :by)
                 ON DUPLICATE KEY UPDATE
                     team1_score = :t1b, team2_score = :t2b, winning_team = :wb,
                     recorded_by = :byb, recorded_at = NOW()',
                [
                    'a'  => $assignmentId,
                    't1' => $team1Score,  't1b' => $team1Score,
                    't2' => $team2Score,  't2b' => $team2Score,
                    'w'  => $winningTeam, 'wb'  => $winningTeam,
                    'by' => Auth::id(),   'byb' => Auth::id(),
                ]
            );

            Database::update('match_assignments', ['status' => 'completed'], ['id' => $assignmentId]);

            // Log the result against each player's history.
            $players = Database::select(
                'SELECT player_id, team FROM match_assignment_players WHERE assignment_id = :a',
                ['a' => $assignmentId]
            );

            foreach ($players as $player) {
                $won = (int) $player['team'] === $winningTeam;

                Player::addHistory(
                    (int) $player['player_id'],
                    'match',
                    $assignmentId,
                    $assignment['match_date'],
                    $assignment['start_time'],
                    sprintf('Match %d result: %s %d–%d',
                        (int) $assignment['match_no'],
                        $won ? 'won' : 'lost',
                        max($team1Score, $team2Score),
                        min($team1Score, $team2Score)
                    )
                );

                if (self::statsEnabled()) {
                    self::recalculate((int) $player['player_id']);
                }
            }
        });

        return ['ok' => true, 'winning_team' => $winningTeam];
    }

    /**
     * Rebuild a player's counters from the result rows.
     *
     * Recomputed rather than incremented, so editing a score cannot drift the
     * totals out of step with the underlying results.
     */
    public static function recalculate(int $playerId): void
    {
        if (!self::statsEnabled()) {
            return;
        }

        $row = Database::selectOne(
            'SELECT COUNT(*) AS played,
                    SUM(CASE WHEN map.team = r.winning_team THEN 1 ELSE 0 END) AS wins,
                    MAX(mp.match_date) AS last_played
               FROM match_assignment_players map
               JOIN enh_match_results r ON r.assignment_id = map.assignment_id
               JOIN match_assignments ma ON ma.id = map.assignment_id
               JOIN matching_pools mp ON mp.id = ma.pool_id
              WHERE map.player_id = :p',
            ['p' => $playerId]
        );

        $played = (int) ($row['played'] ?? 0);
        $wins   = (int) ($row['wins'] ?? 0);

        Database::execute(
            'INSERT INTO enh_player_stats (player_id, games_played, wins, losses, last_played_at)
             VALUES (:p, :g, :w, :l, :d)
             ON DUPLICATE KEY UPDATE
                 games_played = :g2, wins = :w2, losses = :l2, last_played_at = :d2',
            [
                'p'  => $playerId,
                'g'  => $played,        'g2' => $played,
                'w'  => $wins,          'w2' => $wins,
                'l'  => $played - $wins,'l2' => $played - $wins,
                'd'  => $row['last_played'] ?? null,
                'd2' => $row['last_played'] ?? null,
            ]
        );
    }

    /** Counters for one player, with zeros when nothing is recorded yet. */
    public static function statsFor(int $playerId): array
    {
        $empty = ['games_played' => 0, 'wins' => 0, 'losses' => 0, 'last_played_at' => null, 'win_rate' => 0];

        if (!self::statsEnabled()) {
            return $empty;
        }

        $row = Database::selectOne('SELECT * FROM enh_player_stats WHERE player_id = :p', ['p' => $playerId]);

        if (!$row) {
            return $empty;
        }

        $row['win_rate'] = (int) $row['games_played'] > 0
            ? round(100 * (int) $row['wins'] / (int) $row['games_played'])
            : 0;

        return $row;
    }

    /** Results keyed by assignment id, for rendering a pool's match list. */
    public static function forPool(int $poolId): array
    {
        if (!self::enabled()) {
            return [];
        }

        $rows = Database::select(
            'SELECT r.* FROM enh_match_results r
               JOIN match_assignments ma ON ma.id = r.assignment_id
              WHERE ma.pool_id = :p',
            ['p' => $poolId]
        );

        $byAssignment = [];
        foreach ($rows as $row) {
            $byAssignment[(int) $row['assignment_id']] = $row;
        }

        return $byAssignment;
    }

    /** One result, or null. */
    public static function forAssignment(int $assignmentId): ?array
    {
        if (!self::enabled()) {
            return null;
        }

        return Database::selectOne(
            'SELECT * FROM enh_match_results WHERE assignment_id = :a',
            ['a' => $assignmentId]
        );
    }
}
