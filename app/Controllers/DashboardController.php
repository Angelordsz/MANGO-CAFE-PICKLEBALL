<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Response;
use App\Models\Event;
use App\Models\Facility;
use App\Models\Matching;
use App\Models\Player;
use App\Models\Reservation;

/**
 * Customer home feed. Pulls together the things a player needs on arrival:
 * their next booking, what is free today, their matching sign-ups, and
 * anything new.
 */
final class DashboardController extends Controller
{
    public function index(): void
    {
        // Staff have their own dashboard.
        if (Auth::isStaff()) {
            Response::redirect('/admin');
        }

        $user   = Auth::user();
        $player = Auth::player();

        if (!$player) {
            Response::redirectWith('/profile', 'warning', 'Please complete your player profile first.');
        }

        $today    = date('Y-m-d');
        $playerId = (int) $player['id'];

        $upcoming = Database::select(
            "SELECT r.*,
                    CASE r.reservation_type WHEN 'court' THEN c.court_name ELSE h.hall_name END AS facility_name
               FROM reservations r
          LEFT JOIN courts c         ON r.reservation_type = 'court' AND c.id = r.facility_id
          LEFT JOIN function_halls h ON r.reservation_type = 'function_hall' AND h.id = r.facility_id
              WHERE r.user_id = :u
                AND r.status IN ('pending','approved')
                AND r.reservation_date >= CURDATE()
           ORDER BY r.reservation_date, r.start_time
              LIMIT 5",
            ['u' => $user['id']]
        );

        $this->view('customer.dashboard', [
            'title'          => 'Home · Mango Drive Pickleball',
            'player'         => $player,
            'upcoming'       => $upcoming,
            'courtsToday'    => Facility::withAvailability(Facility::COURT, $today),
            'hallsToday'     => Facility::withAvailability(Facility::HALL, $today),
            'matchSignups'   => Matching::signupsForPlayer($playerId),
            'recentMatches'  => array_slice(Matching::resultsForPlayer($playerId), 0, 3),
            'events'         => Event::upcoming(3),
            'openPools'      => array_slice(Matching::openPools($playerId), 0, 4),
            'summary'        => Player::summary($playerId),
        ]);
    }
}
