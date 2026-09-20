<?php
namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Response;
use App\Models\Matching;
use App\Models\MatchResult;
use App\Models\Player;
use App\Models\SkillLevel;
use RuntimeException;

/**
 * Use cases A11 (Manage Player Matching) and A12 (Generate Match Assignments).
 *
 * Workflow: create a pool -> players register (or staff add walk-ins) ->
 * lock -> generate. Generation is a random shuffle within the pool, which is
 * exactly what the approved scope specifies.
 */
final class MatchingController extends Controller
{
    public function index(): void
    {
        $filters = [
            'status' => (string) $this->request->query('status', ''),
            'date'   => (string) $this->request->query('date', ''),
        ];

        $this->view('admin.matching', [
            'title'   => 'Player matching · Back office',
            'pools'   => Matching::listPools($filters),
            'filters' => $filters,
            'levels'  => SkillLevel::all(),
        ], 'admin');
    }

    public function create(): void
    {
        $this->view('admin.matching_form', [
            'title'  => 'New matching session',
            'levels' => SkillLevel::all(),
        ], 'admin');
    }

    public function store(): void
    {
        $data = $this->validate([
            'match_date'     => 'required|date|after_or_equal:today',
            'time_block'     => 'required|in:morning,afternoon,evening',
            'start_time'     => 'required',
            'end_time'       => 'required',
            'skill_level_id' => 'required|integer|exists:skill_levels,id',
            'match_format'   => 'required|in:singles,doubles',
            'min_players'    => 'required|integer|between:2,64',
            'max_players'    => 'required|integer|between:2,64',
            'notes'          => 'nullable|max:255',
        ]);

        if (strtotime($data['end_time']) <= strtotime($data['start_time'])) {
            Response::redirectWith('/admin/matching/create', 'error', 'The end time must be after the start time.');
        }

        if ((int) $data['min_players'] > (int) $data['max_players']) {
            Response::redirectWith('/admin/matching/create', 'error', 'Minimum players cannot exceed the maximum.');
        }

        // One pool per (date, block, level) -- enforced by a unique key.
        $clash = Database::selectOne(
            'SELECT id FROM matching_pools WHERE match_date = :d AND time_block = :b AND skill_level_id = :s',
            ['d' => $data['match_date'], 'b' => $data['time_block'], 's' => $data['skill_level_id']]
        );

        if ($clash) {
            Response::redirectWith(
                '/admin/matching/' . $clash['id'],
                'warning',
                'A session already exists for that date, time block and skill level.'
            );
        }

        $poolId = Matching::createPool([
            'match_date'     => $data['match_date'],
            'time_block'     => $data['time_block'],
            'start_time'     => $data['start_time'],
            'end_time'       => $data['end_time'],
            'skill_level_id' => $data['skill_level_id'],
            'match_format'   => $data['match_format'],
            'min_players'    => $data['min_players'],
            'max_players'    => $data['max_players'],
            'notes'          => $data['notes'] ?: null,
        ]);

        $this->log('matching.create', 'Created matching pool #' . $poolId, 'matching_pool', $poolId);

        Response::redirectWith('/admin/matching/' . $poolId, 'success', 'Matching session created. Players can now register.');
    }

    public function show(string $id): void
    {
        $pool = $this->findOr404(Matching::findPool((int) $id), 'Matching session not found.');

        $this->view('admin.matching_show', [
            'title'       => 'Matching session ' . $pool['pool_code'],
            'pool'        => $pool,
            'signups'     => Matching::signups((int) $id, false),
            'assignments' => Matching::assignments((int) $id),
            'results'     => MatchResult::forPool((int) $id),
            'canScore'    => MatchResult::enabled(),
        ], 'admin');
    }

    /**
     * ENHANCEMENT -- record a match score.
     * Outside the approved scope; see docs/ENHANCEMENTS.md.
     */
    public function recordResult(string $id, string $assignment): void
    {
        if (!MatchResult::enabled()) {
            Response::abort(404, 'Score entry is not enabled.');
        }

        $result = MatchResult::record(
            (int) $assignment,
            max(0, $this->request->integer('team1_score')),
            max(0, $this->request->integer('team2_score'))
        );

        if (!$result['ok']) {
            Response::redirectWith('/admin/matching/' . $id, 'error', $result['error']);
        }

        $this->log('match.result', "Recorded score for match #{$assignment}", 'match_assignment', (int) $assignment);

        Response::redirectWith(
            '/admin/matching/' . $id,
            'success',
            'Score saved. Team ' . $result['winning_team'] . ' wins.'
        );
    }

    /** Staff adding a walk-in to a session. */
    public function addPlayer(string $id): void
    {
        $playerId = $this->request->integer('player_id');

        if ($playerId <= 0) {
            Response::redirectWith('/admin/matching/' . $id, 'error', 'Search for and select a player first.');
        }

        $result = Matching::register((int) $id, $playerId, (int) Auth::id());

        if (!$result['ok']) {
            Response::redirectWith('/admin/matching/' . $id, 'error', $result['error']);
        }

        $player = Player::find($playerId);

        $this->log('matching.add_player',
            sprintf('Added %s to pool #%s', $player['player_code'] ?? $playerId, $id),
            'matching_pool', (int) $id);

        Response::redirectWith('/admin/matching/' . $id, 'success', 'Player added to the session.');
    }

    public function removePlayer(string $id): void
    {
        $requestId = $this->request->integer('request_id');

        Database::execute(
            "UPDATE matching_requests SET status = 'cancelled', cancelled_at = NOW()
              WHERE id = :r AND pool_id = :p",
            ['r' => $requestId, 'p' => $id]
        );

        $this->log('matching.remove_player', "Removed signup #{$requestId}", 'matching_pool', (int) $id);

        Response::redirectWith('/admin/matching/' . $id, 'success', 'Player removed from the session.');
    }

    /** A11 -- lock registration. */
    public function lock(string $id): void
    {
        if (!Matching::lock((int) $id)) {
            Response::redirectWith('/admin/matching/' . $id, 'error', 'Only open sessions can be locked.');
        }

        $this->log('matching.lock', "Locked pool #{$id}", 'matching_pool', (int) $id);

        Response::redirectWith('/admin/matching/' . $id, 'success', 'Registration closed. You can now generate the match-ups.');
    }

    /** A12 -- Generate Match Assignments. */
    public function generate(string $id): void
    {
        try {
            $result = Matching::generate((int) $id);
        } catch (RuntimeException $e) {
            Response::redirectWith('/admin/matching/' . $id, 'error', $e->getMessage());
        }

        $this->log('matching.generate',
            sprintf('Generated %d matches for pool #%s', $result['matches'], $id),
            'matching_pool', (int) $id);

        $message = sprintf(
            'Generated %d %s. %d players matched%s.',
            $result['matches'],
            pluralise($result['matches'], 'match', 'matches'),
            $result['matched'],
            $result['unmatched'] > 0 ? ", {$result['unmatched']} left over" : ''
        );

        Response::redirectWith('/admin/matching/' . $id, 'success', $message);
    }

    public function cancel(string $id): void
    {
        $pool = $this->findOr404(Matching::findPool((int) $id));

        Database::update('matching_pools', ['status' => 'cancelled'], ['id' => $id]);

        foreach (Matching::signups((int) $id) as $signup) {
            \App\Models\Notification::sendToPlayer(
                (int) $signup['player_id'],
                'matching_cancelled',
                'Matching session cancelled',
                sprintf(
                    'The %s session on %s has been cancelled.',
                    $pool['level_name'],
                    date('M j', strtotime($pool['match_date']))
                )
            );
        }

        $this->log('matching.cancel', 'Cancelled pool ' . $pool['pool_code'], 'matching_pool', (int) $id);

        Response::redirectWith('/admin/matching', 'success', 'Session cancelled and players notified.');
    }
}
