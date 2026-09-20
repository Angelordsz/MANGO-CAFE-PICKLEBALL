<?php
namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;

/**
 * Use case A2 -- Dashboard Overview.
 */
final class DashboardController extends Controller
{
    public function index(): void
    {
        $today = date('Y-m-d');

        $stats = Database::selectOne(
            "SELECT
               (SELECT COUNT(*) FROM reservations WHERE status = 'pending')                       AS pending,
               (SELECT COUNT(*) FROM reservations WHERE reservation_date = :d1
                  AND status IN ('approved','completed'))                                         AS today_bookings,
               (SELECT COUNT(*) FROM players WHERE status = 'active')                             AS players,
               (SELECT COUNT(*) FROM players WHERE DATE(created_at) = :d2)                        AS new_players,
               (SELECT COALESCE(SUM(amount),0) FROM payments
                  WHERE DATE(payment_date) = :d3 AND payment_status IN ('paid','verified','partial')) AS today_revenue,
               (SELECT COALESCE(SUM(amount),0) FROM payments
                  WHERE payment_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    AND payment_status IN ('paid','verified','partial'))                          AS month_revenue,
               (SELECT COUNT(*) FROM payments WHERE payment_status IN ('paid','partial'))         AS unverified_payments,
               (SELECT COUNT(*) FROM matching_pools WHERE status = 'open' AND match_date >= CURDATE()) AS open_pools,
               (SELECT COUNT(*) FROM events WHERE status IN ('upcoming','active','ongoing'))      AS active_events",
            ['d1' => $today, 'd2' => $today, 'd3' => $today]
        );

        // Today's schedule across every facility.
        $todaySchedule = Database::select(
            "SELECT r.*,
                    CASE r.reservation_type WHEN 'court' THEN c.court_name ELSE h.hall_name END AS facility_name,
                    p.first_name, p.last_name, p.phone_number
               FROM reservations r
          LEFT JOIN courts c         ON r.reservation_type = 'court' AND c.id = r.facility_id
          LEFT JOIN function_halls h ON r.reservation_type = 'function_hall' AND h.id = r.facility_id
          LEFT JOIN players p        ON p.id = r.player_id
              WHERE r.reservation_date = :d
                AND r.status IN ('approved','completed','pending')
           ORDER BY r.start_time",
            ['d' => $today]
        );

        $pendingRequests = Database::select(
            "SELECT r.*,
                    CASE r.reservation_type WHEN 'court' THEN c.court_name ELSE h.hall_name END AS facility_name,
                    p.first_name, p.last_name
               FROM reservations r
          LEFT JOIN courts c         ON r.reservation_type = 'court' AND c.id = r.facility_id
          LEFT JOIN function_halls h ON r.reservation_type = 'function_hall' AND h.id = r.facility_id
          LEFT JOIN players p        ON p.id = r.player_id
              WHERE r.status = 'pending'
           ORDER BY r.created_at
              LIMIT 6"
        );

        // Last 14 days of bookings, for the bar chart.
        $chart = Database::select(
            "SELECT reservation_date AS day, COUNT(*) AS total
               FROM reservations
              WHERE reservation_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 13 DAY) AND CURDATE()
                AND status <> 'rejected'
           GROUP BY reservation_date
           ORDER BY reservation_date"
        );

        $chartMap = [];
        foreach ($chart as $row) {
            $chartMap[$row['day']] = (int) $row['total'];
        }

        $series = [];
        for ($i = 13; $i >= 0; $i--) {
            $day = date('Y-m-d', strtotime("-$i days"));
            $series[] = ['day' => $day, 'label' => date('j', strtotime($day)), 'value' => $chartMap[$day] ?? 0];
        }

        $utilisation = Database::select(
            "SELECT s.facility_type, s.facility_id,
                    CASE s.facility_type WHEN 'court' THEN c.court_name ELSE h.hall_name END AS name,
                    COUNT(*) AS slots,
                    SUM(CASE WHEN rs.id IS NOT NULL THEN 1 ELSE 0 END) AS booked
               FROM schedules s
          LEFT JOIN reservation_schedules rs ON rs.schedule_id = s.id
          LEFT JOIN courts c         ON s.facility_type = 'court' AND c.id = s.facility_id
          LEFT JOIN function_halls h ON s.facility_type = 'function_hall' AND h.id = s.facility_id
              WHERE s.slot_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND CURDATE()
           GROUP BY s.facility_type, s.facility_id, name
           ORDER BY booked DESC"
        );

        $this->view('admin.dashboard', [
            'title'       => 'Dashboard · Back office',
            'stats'       => $stats,
            'today'       => $todaySchedule,
            'pending'     => $pendingRequests,
            'series'      => $series,
            'utilisation' => $utilisation,
        ], 'admin');
    }
}
