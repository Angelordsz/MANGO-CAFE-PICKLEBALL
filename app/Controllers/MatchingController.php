<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Response;
use App\Models\Matching;
use App\Models\SkillLevel;

/**
 * Use cases C9 (Register for Player Matching), C10 (Select Playing Date and
 * Time), C11 (Select Skill Level), C12 (View Matching Results).
 *
 * C10 and C11 are not separate screens -- they are the two fields that define
 * a matching pool, so choosing a pool *is* choosing a date/time and a level.
 */
final class MatchingController extends Controller
{
    public function index(): void
    {
        $player = Auth::player();

        if (!$player) {
            Response::redirectWith('/profile', 'error', 'Complete your player profile first.');
        }

        $playerId = (int) $player['id'];

        $this->view('customer.matching', [
            'title'    => 'Player matching',
            'player'   => $player,
            'pools'    => Matching::openPools($playerId),
            'signups'  => Matching::signupsForPlayer($playerId),
            'levels'   => SkillLevel::all(),
        ]);
    }

    /** C9 -- Register for Player Matching. */
    public function register(): void
    {
        $player = Auth::player();
        $poolId = $this->request->integer('pool_id');

        if (!$player) {
            Response::redirectWith('/profile', 'error', 'Complete your player profile first.');
        }

        if ($poolId <= 0) {
            Response::redirectWith('/matching', 'error', 'Choose a matching session to join.');
        }

        $result = Matching::register($poolId, (int) $player['id']);

        if (!$result['ok']) {
            Response::redirectWith('/matching', 'error', $result['error']);
        }

        $this->log('matching.register', 'Registered for matching pool ' . $poolId, 'matching_pool', $poolId);

        Response::redirectWith(
            '/matching',
            'success',
            'You are registered. Match-ups are posted once the café generates them.'
        );
    }

    public function cancel(string $id): void
    {
        $player = Auth::player();

        if (!$player) {
            Response::abort(403);
        }

        $ok = Matching::cancelRegistration((int) $id, (int) $player['id']);

        Response::redirectWith(
            '/matching',
            $ok ? 'success' : 'error',
            $ok ? 'Registration cancelled.' : 'That registration could not be cancelled.'
        );
    }

    /** C12 -- View Matching Results. */
    public function results(): void
    {
        $player = Auth::player();

        if (!$player) {
            Response::redirectWith('/profile', 'error', 'Complete your player profile first.');
        }

        $this->view('customer.matching_results', [
            'title'   => 'My match results',
            'player'  => $player,
            'matches' => Matching::resultsForPlayer((int) $player['id']),
        ]);
    }
}
