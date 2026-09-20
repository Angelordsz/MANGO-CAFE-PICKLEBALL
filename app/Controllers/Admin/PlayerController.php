<?php
namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Response;
use App\Models\Matching;
use App\Models\MatchResult;
use App\Models\Player;
use App\Models\Reservation;
use App\Models\SkillLevel;

/**
 * Use case A4 -- Manage Player Records.
 * Figures 3.5 and 3.12 -- Walk-in Registration by staff.
 *
 * A walk-in player gets a `players` row with `user_id = NULL`: a record the
 * café can book and bill against, without the person ever creating a login.
 */
final class PlayerController extends Controller
{
    public function index(): void
    {
        $filters = [
            'q'              => (string) $this->request->query('q', ''),
            'skill_level_id' => (string) $this->request->query('skill_level_id', ''),
            'is_walk_in'     => (string) $this->request->query('is_walk_in', ''),
            'status'         => (string) $this->request->query('status', ''),
        ];

        $page    = max(1, $this->request->integer('page', 1));
        $perPage = 25;

        $this->view('admin.players', [
            'title'    => 'Player records · Back office',
            'players'  => Player::search($filters, $perPage, ($page - 1) * $perPage),
            'total'    => Player::countSearch($filters),
            'page'     => $page,
            'perPage'  => $perPage,
            'filters'  => $filters,
            'levels'   => SkillLevel::all(),
        ], 'admin');
    }

    public function show(string $id): void
    {
        $player = $this->findOr404(Player::find((int) $id), 'Player not found.');

        $reservations = Database::select(
            "SELECT r.*,
                    CASE r.reservation_type WHEN 'court' THEN c.court_name ELSE h.hall_name END AS facility_name
               FROM reservations r
          LEFT JOIN courts c         ON r.reservation_type = 'court' AND c.id = r.facility_id
          LEFT JOIN function_halls h ON r.reservation_type = 'function_hall' AND h.id = r.facility_id
              WHERE r.player_id = :p
           ORDER BY r.reservation_date DESC
              LIMIT 20",
            ['p' => $id]
        );

        $this->view('admin.player_show', [
            'title'        => Player::fullName($player),
            'player'       => $player,
            'summary'      => Player::summary((int) $id),
            'history'      => Player::history((int) $id, 25),
            'reservations' => $reservations,
            'matches'      => array_slice(Matching::resultsForPlayer((int) $id), 0, 10),
            // ENHANCEMENT -- win/loss counters, see docs/ENHANCEMENTS.md
            'stats'        => MatchResult::statsFor((int) $id),
            'showStats'    => MatchResult::statsEnabled(),
        ], 'admin');
    }

    public function edit(string $id): void
    {
        $player = $this->findOr404(Player::find((int) $id), 'Player not found.');

        $this->view('admin.player_form', [
            'title'  => 'Edit ' . Player::fullName($player),
            'player' => $player,
            'levels' => SkillLevel::all(),
            'mode'   => 'edit',
        ], 'admin');
    }

    public function update(string $id): void
    {
        $player = $this->findOr404(Player::find((int) $id), 'Player not found.');

        $data = $this->validate([
            'first_name'     => 'required|max:60',
            'last_name'      => 'required|max:60',
            'phone_number'   => 'nullable|phone',
            'email'          => 'nullable|email|max:190',
            'gender'         => 'nullable|in:male,female,other,prefer_not_to_say',
            'birthdate'      => 'nullable|date|before:today',
            'address'        => 'nullable|max:255',
            'skill_level_id' => 'required|integer|exists:skill_levels,id',
            'preferred_time' => 'nullable|in:morning,afternoon,evening,any',
            'notes'          => 'nullable|max:1000',
        ]);

        $update = [
            'first_name'     => $data['first_name'],
            'last_name'      => $data['last_name'],
            'phone_number'   => $data['phone_number'] ?: null,
            'email'          => $data['email'] ?: null,
            'gender'         => $data['gender'] ?? 'prefer_not_to_say',
            'birthdate'      => $data['birthdate'] ?: null,
            'address'        => $data['address'] ?: null,
            'skill_level_id' => $data['skill_level_id'],
            'preferred_time' => $data['preferred_time'] ?? 'any',
            'notes'          => $data['notes'] ?: null,
        ];

        if (!empty($data['birthdate'])) {
            $update['age'] = Player::ageFromBirthdate($data['birthdate']);
        }

        Database::update('players', $update, ['id' => $id]);

        $this->log('player.update', 'Updated player ' . $player['player_code'], 'player', (int) $id);

        Response::redirectWith('/admin/players/' . $id, 'success', 'Player record updated.');
    }

    public function toggleStatus(string $id): void
    {
        $player = $this->findOr404(Player::find((int) $id), 'Player not found.');
        $next   = $player['status'] === 'active' ? 'inactive' : 'active';

        Database::update('players', ['status' => $next], ['id' => $id]);

        $this->log('player.status', "Set {$player['player_code']} to {$next}", 'player', (int) $id);

        Response::redirectWith('/admin/players/' . $id, 'success', 'Player is now ' . $next . '.');
    }

    // ---- Figures 3.5 / 3.12: Walk-in registration -------------------------

    public function walkInForm(): void
    {
        $this->view('admin.player_walkin', [
            'title'  => 'Walk-in registration',
            'levels' => SkillLevel::all(),
        ], 'admin');
    }

    public function walkInStore(): void
    {
        $data = $this->validate([
            'first_name'     => 'required|max:60',
            'last_name'      => 'required|max:60',
            'phone_number'   => 'required|phone',
            'email'          => 'nullable|email|max:190',
            'gender'         => 'nullable|in:male,female,other,prefer_not_to_say',
            'age'            => 'nullable|integer|between:5,100',
            'skill_level_id' => 'required|integer|exists:skill_levels,id',
            'preferred_time' => 'nullable|in:morning,afternoon,evening,any',
            'notes'          => 'nullable|max:1000',
        ]);

        $playerId = Player::create([
            'user_id'        => null,               // no login: this is the walk-in marker
            'first_name'     => $data['first_name'],
            'last_name'      => $data['last_name'],
            'phone_number'   => $data['phone_number'],
            'email'          => $data['email'] ?: null,
            'gender'         => $data['gender'] ?? 'prefer_not_to_say',
            'age'            => $data['age'] ?: null,
            'skill_level_id' => $data['skill_level_id'],
            'preferred_time' => $data['preferred_time'] ?? 'any',
            'notes'          => $data['notes'] ?: null,
            'is_walk_in'     => 1,
            'registered_by'  => (int) Auth::id(),
            'status'         => 'active',
        ]);

        $player = Player::find($playerId);

        Player::addHistory(
            $playerId,
            'registration',
            $playerId,
            date('Y-m-d'),
            date('H:i:s'),
            'Walk-in registration recorded at the counter'
        );

        $this->log('player.walk_in', 'Registered walk-in ' . $player['player_code'], 'player', $playerId);

        // Fig. 3.5 branches here: register only, or continue to a booking.
        if ($this->request->boolean('then_book')) {
            Response::redirectWith(
                '/admin/reservations/create?player_id=' . $playerId,
                'success',
                'Player registered. Now pick a facility and time.'
            );
        }

        if ($this->request->boolean('then_match')) {
            Response::redirectWith(
                '/admin/matching?player_id=' . $playerId,
                'success',
                'Player registered. Add them to a matching session.'
            );
        }

        Response::redirectWith('/admin/players/' . $playerId, 'success', 'Walk-in player registered.');
    }

    /** Type-ahead used by Fig. 3.7 and the walk-in booking form. */
    public function search(): void
    {
        $q = (string) $this->request->query('q', '');

        if (mb_strlen($q) < 2) {
            $this->json(['players' => []]);
        }

        $players = Player::search(['q' => $q, 'status' => 'active'], 10);

        $this->json([
            'players' => array_map(static function (array $p): array {
                return [
                    'id'       => (int) $p['id'],
                    'name'     => trim($p['first_name'] . ' ' . $p['last_name']),
                    'initials' => strtoupper(mb_substr($p['first_name'], 0, 1) . mb_substr($p['last_name'], 0, 1)),
                    'meta'     => trim(
                        $p['player_code']
                        . ($p['phone_number'] ? ' · ' . $p['phone_number'] : '')
                        . ($p['level_name'] ? ' · ' . $p['level_name'] : '')
                    ),
                ];
            }, $players),
        ]);
    }
}
