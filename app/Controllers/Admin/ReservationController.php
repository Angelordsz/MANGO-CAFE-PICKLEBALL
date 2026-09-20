<?php
namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Response;
use App\Core\Session;
use App\Models\Facility;
use App\Models\Payment;
use App\Models\Player;
use App\Models\Reservation;
use App\Models\Schedule;
use RuntimeException;

/**
 * Use cases A8 (Manage Reservations), A9 (Approve / Reject Reservation),
 * A10 (Cancel / Reschedule Reservation), plus the walk-in booking half of
 * Figure 3.5.
 */
final class ReservationController extends Controller
{
    public function index(): void
    {
        $filters = [
            'status' => (string) $this->request->query('status', ''),
            'type'   => (string) $this->request->query('type', ''),
            'date'   => (string) $this->request->query('date', ''),
            'q'      => (string) $this->request->query('q', ''),
        ];

        $sql = "SELECT r.*,
                       CASE r.reservation_type WHEN 'court' THEN c.court_name ELSE h.hall_name END AS facility_name,
                       p.first_name, p.last_name, p.phone_number, p.player_code,
                       (SELECT COALESCE(SUM(pay.amount),0) FROM payments pay
                         WHERE pay.payable_type = 'reservation' AND pay.payable_id = r.id
                           AND pay.payment_status IN ('paid','verified','partial')) AS amount_paid
                  FROM reservations r
             LEFT JOIN courts c         ON r.reservation_type = 'court' AND c.id = r.facility_id
             LEFT JOIN function_halls h ON r.reservation_type = 'function_hall' AND h.id = r.facility_id
             LEFT JOIN players p        ON p.id = r.player_id
                 WHERE 1 = 1";
        $params = [];

        if ($filters['status'] !== '') {
            $sql .= ' AND r.status = :status';
            $params['status'] = $filters['status'];
        }
        if ($filters['type'] !== '') {
            $sql .= ' AND r.reservation_type = :type';
            $params['type'] = $filters['type'];
        }
        if ($filters['date'] !== '') {
            $sql .= ' AND r.reservation_date = :date';
            $params['date'] = $filters['date'];
        }
        if ($filters['q'] !== '') {
            $sql .= " AND (r.reservation_code LIKE :q
                        OR CONCAT(p.first_name,' ',p.last_name) LIKE :q2
                        OR p.phone_number LIKE :q3)";
            $like = '%' . $filters['q'] . '%';
            $params += ['q' => $like, 'q2' => $like, 'q3' => $like];
        }

        $reservations = Database::select(
            $sql . ' ORDER BY r.reservation_date DESC, r.start_time DESC LIMIT 200',
            $params
        );

        $counts = Database::selectOne(
            "SELECT
               SUM(status = 'pending')   AS pending,
               SUM(status = 'approved')  AS approved,
               SUM(status = 'completed') AS completed,
               SUM(status IN ('cancelled','rejected')) AS cancelled
             FROM reservations"
        );

        $this->view('admin.reservations', [
            'title'        => 'Reservations · Back office',
            'reservations' => $reservations,
            'filters'      => $filters,
            'counts'       => $counts,
        ], 'admin');
    }

    public function show(string $id): void
    {
        $reservation = $this->findOr404(Reservation::find((int) $id), 'Reservation not found.');

        $this->view('admin.reservation_show', [
            'title'       => 'Reservation ' . $reservation['reservation_code'],
            'reservation' => $reservation,
            'slots'       => Reservation::slots((int) $reservation['id']),
            'payments'    => Payment::forPayable('reservation', (int) $reservation['id']),
            'history'     => $reservation['player_id']
                                ? Player::history((int) $reservation['player_id'], 5)
                                : [],
        ], 'admin');
    }

    /** A9 -- Approve. */
    public function approve(string $id): void
    {
        $reservation = $this->findOr404(Reservation::find((int) $id));

        if (!Reservation::approve((int) $id, (string) $this->request->input('admin_remarks', ''))) {
            Response::redirectWith('/admin/reservations/' . $id, 'error', 'Only pending reservations can be approved.');
        }

        $this->log('reservation.approve', 'Approved ' . $reservation['reservation_code'], 'reservation', (int) $id);

        Response::redirectWith('/admin/reservations/' . $id, 'success', 'Reservation approved and the customer notified.');
    }

    /** A9 -- Reject. */
    public function reject(string $id): void
    {
        $reservation = $this->findOr404(Reservation::find((int) $id));
        $reason      = (string) $this->request->input('admin_remarks', '');

        if (trim($reason) === '') {
            Response::redirectWith('/admin/reservations/' . $id, 'error', 'Give a reason so the customer knows why.');
        }

        if (!Reservation::reject((int) $id, $reason)) {
            Response::redirectWith('/admin/reservations/' . $id, 'error', 'Only pending reservations can be rejected.');
        }

        $this->log('reservation.reject', 'Rejected ' . $reservation['reservation_code'], 'reservation', (int) $id);

        Response::redirectWith('/admin/reservations', 'success', 'Reservation declined and the slot released.');
    }

    /** A10 -- Cancel. */
    public function cancel(string $id): void
    {
        $reservation = $this->findOr404(Reservation::find((int) $id));
        $reason      = (string) ($this->request->input('cancel_reason') ?: 'Cancelled by the café');

        if (!Reservation::cancel((int) $id, $reason, (int) Auth::id())) {
            Response::redirectWith('/admin/reservations/' . $id, 'error', 'This reservation cannot be cancelled.');
        }

        $this->log('reservation.cancel', 'Cancelled ' . $reservation['reservation_code'], 'reservation', (int) $id);

        Response::redirectWith('/admin/reservations', 'success', 'Reservation cancelled and the slot released.');
    }

    public function complete(string $id): void
    {
        $reservation = $this->findOr404(Reservation::find((int) $id));

        if (!Reservation::complete((int) $id)) {
            Response::redirectWith('/admin/reservations/' . $id, 'error', 'Only approved reservations can be completed.');
        }

        $this->log('reservation.complete', 'Completed ' . $reservation['reservation_code'], 'reservation', (int) $id);

        Response::redirectWith('/admin/reservations/' . $id, 'success', 'Marked as completed.');
    }

    /** A10 -- Reschedule on the customer's behalf. */
    public function rescheduleForm(string $id): void
    {
        $reservation = $this->findOr404(Reservation::find((int) $id));
        $date        = (string) $this->request->query('date', $reservation['reservation_date']);

        if ($date < date('Y-m-d')) {
            $date = date('Y-m-d');
        }

        $this->view('admin.reservation_reschedule', [
            'title'       => 'Reschedule ' . $reservation['reservation_code'],
            'reservation' => $reservation,
            'date'        => $date,
            'slots'       => Schedule::forDate($reservation['reservation_type'], (int) $reservation['facility_id'], $date),
        ], 'admin');
    }

    public function reschedule(string $id): void
    {
        $reservation = $this->findOr404(Reservation::find((int) $id));
        $slotIds     = array_map('intval', (array) $this->request->array('slots'));

        if ($slotIds === []) {
            Response::redirectWith('/admin/reservations/' . $id . '/reschedule', 'error', 'Pick at least one slot.');
        }

        try {
            $newId = Reservation::create([
                'player_id'        => $reservation['player_id'],
                'user_id'          => $reservation['user_id'],
                'reservation_type' => $reservation['reservation_type'],
                'facility_id'      => $reservation['facility_id'],
                'party_size'       => $reservation['party_size'],
                'purpose'          => $reservation['purpose'],
                'customer_notes'   => $reservation['customer_notes'],
                'status'           => 'approved',   // staff-made changes are pre-approved
                'source'           => $reservation['source'],
                'rescheduled_from' => (int) $reservation['id'],
                'reviewed_by'      => (int) Auth::id(),
                'reviewed_at'      => date('Y-m-d H:i:s'),
                'created_by'       => (int) Auth::id(),
            ], $slotIds);
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            $this->back('/admin/reservations/' . $id . '/reschedule');
        }

        Reservation::cancel((int) $reservation['id'], 'Rescheduled by the café', (int) Auth::id());

        $new = Reservation::find($newId);

        $this->log('reservation.reschedule',
            sprintf('Rescheduled %s to %s', $reservation['reservation_code'], $new['reservation_code']),
            'reservation', $newId);

        Response::redirectWith('/admin/reservations/' . $newId, 'success', 'Rescheduled.');
    }

    // ---- Walk-in / phone booking (Fig. 3.5) -------------------------------

    public function create(): void
    {
        $type       = (string) $this->request->query('type', Facility::COURT);
        $facilities = Facility::all($type);

        if ($facilities === []) {
            Response::redirectWith('/admin/reservations', 'error', 'No bookable facilities of that type.');
        }

        $facilityId = $this->request->integer('facility', (int) $facilities[0]['id']);
        $date       = (string) $this->request->query('date', date('Y-m-d'));

        $this->view('admin.reservation_create', [
            'title'      => 'New walk-in booking',
            'type'       => $type,
            'facilities' => array_map(static fn($f) => Facility::normalise($type, $f), $facilities),
            'facilityId' => $facilityId,
            'date'       => $date,
            'slots'      => Schedule::forDate($type, $facilityId, $date),
        ], 'admin');
    }

    public function store(): void
    {
        $slotIds  = array_map('intval', (array) $this->request->array('slots'));
        $playerId = $this->request->integer('player_id');
        $type     = (string) $this->request->input('reservation_type', Facility::COURT);

        if ($playerId <= 0) {
            Response::redirectWith('/admin/reservations/create', 'error', 'Search for and select a player first.');
        }
        if ($slotIds === []) {
            Response::redirectWith('/admin/reservations/create', 'error', 'Select at least one time slot.');
        }

        $player = $this->findOr404(Player::find($playerId), 'Player not found.');

        try {
            $reservationId = Reservation::create([
                'player_id'        => $playerId,
                'user_id'          => $player['user_id'] ? (int) $player['user_id'] : null,
                'reservation_type' => $type,
                'facility_id'      => $this->request->integer('facility_id'),
                'party_size'       => max(1, $this->request->integer('party_size', 1)),
                'purpose'          => $this->request->input('purpose') ?: null,
                'customer_notes'   => $this->request->input('customer_notes') ?: null,
                'status'           => 'approved',   // counter bookings are confirmed on the spot
                'source'           => (string) $this->request->input('source', 'walk_in'),
                'reviewed_by'      => (int) Auth::id(),
                'reviewed_at'      => date('Y-m-d H:i:s'),
                'created_by'       => (int) Auth::id(),
            ], $slotIds);
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            $this->back('/admin/reservations/create');
        }

        $reservation = Reservation::find($reservationId);

        // Optional: record the payment straight away (Fig. 3.5).
        $amount = (float) $this->request->input('amount_paid', 0);
        if ($amount > 0) {
            Payment::record(
                'reservation',
                $reservationId,
                (float) $reservation['total_amount'],
                $amount,
                (string) $this->request->input('payment_method', 'cash'),
                $playerId,
                $this->request->input('reference_no') ?: null,
                'Recorded at booking'
            );
        }

        $this->log('reservation.walk_in', 'Created walk-in ' . $reservation['reservation_code'], 'reservation', $reservationId);

        Response::redirectWith('/admin/reservations/' . $reservationId, 'success', 'Booking created.');
    }
}
