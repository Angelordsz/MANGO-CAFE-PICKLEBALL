<?php
namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Response;
use App\Models\Event;
use App\Models\Payment;

/**
 * Use case A14 -- Generate Reports.
 * Figure 3.9 (Event Report) and Figure 3.10 (Payment Report, summary or
 * detailed) are implemented here, plus reservation, utilisation and player
 * reports that Objective 5 calls for.
 *
 * Every report supports ?export=csv and prints cleanly (see the @media print
 * rules in app.css).
 */
final class ReportController extends Controller
{
    public function index(): void
    {
        $this->view('admin.reports', [
            'title' => 'Reports · Back office',
            'from'  => date('Y-m-01'),
            'to'    => date('Y-m-d'),
        ], 'admin');
    }

    /** Reservation report: every booking in a date range. */
    public function reservation(): void
    {
        [$from, $to] = $this->range();
        $status = (string) $this->request->query('status', '');
        $type   = (string) $this->request->query('type', '');

        $sql = "SELECT r.reservation_code, r.reservation_date, r.start_time, r.end_time,
                       r.reservation_type, r.status, r.source, r.duration_hours, r.total_amount,
                       CASE r.reservation_type WHEN 'court' THEN c.court_name ELSE h.hall_name END AS facility_name,
                       CONCAT(COALESCE(p.first_name,''), ' ', COALESCE(p.last_name,'')) AS customer,
                       p.phone_number,
                       (SELECT COALESCE(SUM(pay.amount),0) FROM payments pay
                         WHERE pay.payable_type='reservation' AND pay.payable_id=r.id
                           AND pay.payment_status IN ('paid','verified','partial')) AS amount_paid
                  FROM reservations r
             LEFT JOIN courts c         ON r.reservation_type='court' AND c.id=r.facility_id
             LEFT JOIN function_halls h ON r.reservation_type='function_hall' AND h.id=r.facility_id
             LEFT JOIN players p        ON p.id=r.player_id
                 WHERE r.reservation_date BETWEEN :from AND :to";
        $params = ['from' => $from, 'to' => $to];

        if ($status !== '') {
            $sql .= ' AND r.status = :status';
            $params['status'] = $status;
        }
        if ($type !== '') {
            $sql .= ' AND r.reservation_type = :type';
            $params['type'] = $type;
        }

        $rows = Database::select($sql . ' ORDER BY r.reservation_date, r.start_time', $params);

        if ($this->request->query('export') === 'csv') {
            Response::csv(
                "reservation-report-{$from}-to-{$to}.csv",
                ['Code','Date','Start','End','Facility','Type','Customer','Phone','Hours','Status','Source','Amount','Paid'],
                array_map(static fn($r) => [
                    $r['reservation_code'], $r['reservation_date'], $r['start_time'], $r['end_time'],
                    $r['facility_name'], $r['reservation_type'], trim($r['customer']), $r['phone_number'],
                    $r['duration_hours'], $r['status'], $r['source'], $r['total_amount'], $r['amount_paid'],
                ], $rows)
            );
        }

        $totals = [
            'count'  => count($rows),
            'hours'  => array_sum(array_column($rows, 'duration_hours')),
            'gross'  => array_sum(array_map(static fn($r) => (float) $r['total_amount'], $rows)),
            'paid'   => array_sum(array_map(static fn($r) => (float) $r['amount_paid'], $rows)),
        ];

        $this->view('admin.report_reservation', [
            'title'  => 'Reservation report',
            'rows'   => $rows,
            'from'   => $from,
            'to'     => $to,
            'status' => $status,
            'type'   => $type,
            'totals' => $totals,
        ], 'admin');
    }

    /** Facility utilisation: slots offered vs slots taken. */
    public function utilisation(): void
    {
        [$from, $to] = $this->range();

        $rows = Database::select(
            "SELECT s.facility_type, s.facility_id,
                    CASE s.facility_type WHEN 'court' THEN c.court_name ELSE h.hall_name END AS facility_name,
                    COUNT(*) AS slots_offered,
                    SUM(CASE WHEN rs.id IS NOT NULL THEN 1 ELSE 0 END) AS slots_booked,
                    SUM(CASE WHEN s.status <> 'available' THEN 1 ELSE 0 END) AS slots_blocked,
                    ROUND(100 * SUM(CASE WHEN rs.id IS NOT NULL THEN 1 ELSE 0 END) / COUNT(*), 1) AS utilisation_pct,
                    SUM(CASE WHEN rs.id IS NOT NULL THEN s.price ELSE 0 END) AS booked_value
               FROM schedules s
          LEFT JOIN reservation_schedules rs ON rs.schedule_id = s.id
          LEFT JOIN courts c         ON s.facility_type='court' AND c.id=s.facility_id
          LEFT JOIN function_halls h ON s.facility_type='function_hall' AND h.id=s.facility_id
              WHERE s.slot_date BETWEEN :from AND :to
           GROUP BY s.facility_type, s.facility_id, facility_name
           ORDER BY utilisation_pct DESC",
            ['from' => $from, 'to' => $to]
        );

        // Busiest hours across the range -- useful for staffing decisions.
        $byHour = Database::select(
            "SELECT HOUR(s.start_time) AS hour,
                    COUNT(*) AS slots,
                    SUM(CASE WHEN rs.id IS NOT NULL THEN 1 ELSE 0 END) AS booked
               FROM schedules s
          LEFT JOIN reservation_schedules rs ON rs.schedule_id = s.id
              WHERE s.slot_date BETWEEN :from AND :to
           GROUP BY HOUR(s.start_time)
           ORDER BY hour",
            ['from' => $from, 'to' => $to]
        );

        if ($this->request->query('export') === 'csv') {
            Response::csv(
                "utilisation-report-{$from}-to-{$to}.csv",
                ['Facility','Type','Slots offered','Slots booked','Slots blocked','Utilisation %','Booked value'],
                array_map(static fn($r) => [
                    $r['facility_name'], $r['facility_type'], $r['slots_offered'],
                    $r['slots_booked'], $r['slots_blocked'], $r['utilisation_pct'], $r['booked_value'],
                ], $rows)
            );
        }

        $this->view('admin.report_utilisation', [
            'title'  => 'Facility utilisation report',
            'rows'   => $rows,
            'byHour' => $byHour,
            'from'   => $from,
            'to'     => $to,
        ], 'admin');
    }

    /** Figure 3.9 -- Admin Generate Event Report. */
    public function event(): void
    {
        [$from, $to] = $this->range();
        $status = (string) $this->request->query('status', '');

        $rows = Event::report(['from' => $from, 'to' => $to, 'status' => $status]);

        if ($this->request->query('export') === 'csv') {
            Response::csv(
                "event-report-{$from}-to-{$to}.csv",
                ['Code','Title','Type','Start','Status','Capacity','Registered','Attended','Paid','Expected','Collected'],
                array_map(static fn($r) => [
                    $r['event_code'], $r['title'], $r['event_type'], $r['start_datetime'], $r['status'],
                    $r['capacity'], $r['registered'], $r['attended'], $r['paid_count'],
                    $r['expected_revenue'], $r['collected_revenue'],
                ], $rows)
            );
        }

        $totals = [
            'events'     => count($rows),
            'registered' => array_sum(array_map(static fn($r) => (int) $r['registered'], $rows)),
            'expected'   => array_sum(array_map(static fn($r) => (float) $r['expected_revenue'], $rows)),
            'collected'  => array_sum(array_map(static fn($r) => (float) $r['collected_revenue'], $rows)),
        ];

        $this->view('admin.report_event', [
            'title'  => 'Event report',
            'rows'   => $rows,
            'from'   => $from,
            'to'     => $to,
            'status' => $status,
            'totals' => $totals,
        ], 'admin');
    }

    /** Figure 3.10 -- Admin Generate Payment Report (summary or detailed). */
    public function payment(): void
    {
        [$from, $to] = $this->range();
        $mode = (string) $this->request->query('mode', 'summary');

        if ($mode === 'detailed') {
            $rows = Payment::ledger([
                'from'   => $from,
                'to'     => $to,
                'method' => (string) $this->request->query('method', ''),
            ], 1000);

            if ($this->request->query('export') === 'csv') {
                Response::csv(
                    "payment-report-detailed-{$from}-to-{$to}.csv",
                    ['Receipt','Date','Player','For','Method','Reference','Amount due','Amount','Status','Recorded by','Verified by'],
                    array_map(static fn($r) => [
                        $r['payment_code'], $r['payment_date'],
                        trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')),
                        $r['payable_label'] ?? $r['payable_type'],
                        $r['payment_method'], $r['reference_no'], $r['amount_due'], $r['amount'],
                        $r['payment_status'], $r['recorded_by_name'], $r['verified_by_name'],
                    ], $rows)
                );
            }
        } else {
            $rows = Database::select(
                "SELECT DATE(payment_date) AS day,
                        payment_method,
                        payable_type,
                        COUNT(*) AS txn_count,
                        SUM(amount) AS total
                   FROM payments
                  WHERE DATE(payment_date) BETWEEN :from AND :to
                    AND payment_status IN ('paid','verified','partial')
               GROUP BY DATE(payment_date), payment_method, payable_type
               ORDER BY day DESC, total DESC",
                ['from' => $from, 'to' => $to]
            );

            if ($this->request->query('export') === 'csv') {
                Response::csv(
                    "payment-report-summary-{$from}-to-{$to}.csv",
                    ['Date','Method','For','Transactions','Total'],
                    array_map(static fn($r) => [
                        $r['day'], $r['payment_method'], $r['payable_type'], $r['txn_count'], $r['total'],
                    ], $rows)
                );
            }
        }

        $this->view('admin.report_payment', [
            'title'   => 'Payment report',
            'mode'    => $mode,
            'rows'    => $rows,
            'from'    => $from,
            'to'      => $to,
            'summary' => Payment::summary(['from' => $from, 'to' => $to]),
        ], 'admin');
    }

    /** Player and matching activity -- supports Objective 5. */
    public function player(): void
    {
        [$from, $to] = $this->range();

        $rows = Database::select(
            "SELECT p.player_code, p.first_name, p.last_name, p.phone_number,
                    p.is_walk_in, p.created_at, s.level_name,
                    (SELECT COUNT(*) FROM reservations r
                      WHERE r.player_id = p.id AND r.reservation_date BETWEEN :from1 AND :to1) AS reservations,
                    (SELECT COUNT(*) FROM matching_requests mr
                       JOIN matching_pools mp ON mp.id = mr.pool_id
                      WHERE mr.player_id = p.id AND mp.match_date BETWEEN :from2 AND :to2) AS matching_signups,
                    (SELECT COUNT(*) FROM match_assignment_players map
                       JOIN match_assignments ma ON ma.id = map.assignment_id
                       JOIN matching_pools mp2 ON mp2.id = ma.pool_id
                      WHERE map.player_id = p.id AND mp2.match_date BETWEEN :from3 AND :to3) AS matches,
                    (SELECT COALESCE(SUM(pay.amount),0) FROM payments pay
                      WHERE pay.player_id = p.id
                        AND DATE(pay.payment_date) BETWEEN :from4 AND :to4
                        AND pay.payment_status IN ('paid','verified','partial')) AS total_paid
               FROM players p
          LEFT JOIN skill_levels s ON s.id = p.skill_level_id
              WHERE p.status = 'active'
           ORDER BY reservations DESC, matches DESC
              LIMIT 300",
            [
                'from1' => $from, 'to1' => $to, 'from2' => $from, 'to2' => $to,
                'from3' => $from, 'to3' => $to, 'from4' => $from, 'to4' => $to,
            ]
        );

        if ($this->request->query('export') === 'csv') {
            Response::csv(
                "player-report-{$from}-to-{$to}.csv",
                ['Code','First name','Last name','Phone','Level','Type','Reservations','Matching sign-ups','Matches','Total paid','Registered'],
                array_map(static fn($r) => [
                    $r['player_code'], $r['first_name'], $r['last_name'], $r['phone_number'],
                    $r['level_name'], $r['is_walk_in'] ? 'Walk-in' : 'Online',
                    $r['reservations'], $r['matching_signups'], $r['matches'], $r['total_paid'], $r['created_at'],
                ], $rows)
            );
        }

        $this->view('admin.report_player', [
            'title' => 'Player activity report',
            'rows'  => $rows,
            'from'  => $from,
            'to'    => $to,
        ], 'admin');
    }

    /** Shared date range, defaulting to the current month to date. */
    private function range(): array
    {
        $from = (string) $this->request->query('from', date('Y-m-01'));
        $to   = (string) $this->request->query('to', date('Y-m-d'));

        if (strtotime($to) < strtotime($from)) {
            [$from, $to] = [$to, $from];
        }

        return [$from, $to];
    }
}
