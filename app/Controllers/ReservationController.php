<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Controller;
use App\Core\Response;
use App\Core\Session;
use App\Models\Facility;
use App\Models\Notification;
use App\Models\Reservation;
use App\Models\Schedule;
use RuntimeException;

/**
 * Use cases C5 (Reserve Pickleball Court), C6 (Reserve Function Hall),
 * C7 (Submit Reservation Request), C8 (Cancel / Reschedule),
 * C13 (View My Reservations).
 *
 * Booking is a request, not an instant confirmation: status starts at
 * `pending` and an admin approves it (Figure 3.2, "Approve / Reject
 * Reservation"). Slots are held from the moment of submission so two people
 * cannot request the same slot.
 */
final class ReservationController extends Controller
{
    public function chooseType(): void
    {
        $today = date('Y-m-d');

        $this->view('customer.reserve_choose', [
            'title'  => 'Make a reservation',
            'courts' => Facility::withAvailability(Facility::COURT, $today),
            'halls'  => Facility::withAvailability(Facility::HALL, $today),
        ]);
    }

    public function court(): void
    {
        $this->showBookingForm(Facility::COURT);
    }

    public function hall(): void
    {
        $this->showBookingForm(Facility::HALL);
    }

    /**
     * Steps 1-3 on one screen: pick facility, pick date, pick slots.
     * Keeping it on one page means fewer taps on a phone.
     */
    private function showBookingForm(string $type): void
    {
        $facilities = Facility::all($type);

        if ($facilities === []) {
            Response::redirectWith('/reserve', 'warning', 'No facilities of that type are available right now.');
        }

        $facilityId = $this->request->integer('facility', (int) $facilities[0]['id']);

        // Make sure the requested facility actually exists and is bookable.
        $facilityRow = null;
        foreach ($facilities as $row) {
            if ((int) $row['id'] === $facilityId) {
                $facilityRow = $row;
                break;
            }
        }
        if ($facilityRow === null) {
            $facilityRow = $facilities[0];
            $facilityId  = (int) $facilityRow['id'];
        }

        $maxDays = (int) Config::get('booking.max_advance_days', 30);
        $date    = (string) $this->request->query('date', date('Y-m-d'));

        // Clamp the date into the bookable window.
        $minDate = date('Y-m-d');
        $maxDate = date('Y-m-d', strtotime("+{$maxDays} days"));
        if ($date < $minDate) { $date = $minDate; }
        if ($date > $maxDate) { $date = $maxDate; }

        $slots   = Schedule::forDate($type, $facilityId, $date);
        $minHour = (int) Config::get('booking.min_advance_hours', 2);

        // Flag slots that are too soon to book.
        foreach ($slots as &$slot) {
            $startsAt = strtotime($date . ' ' . $slot['start_time']);
            $slot['too_soon'] = $startsAt - time() < $minHour * 3600;
        }
        unset($slot);

        $this->view('customer.reserve_form', [
            'title'      => $type === Facility::HALL ? 'Reserve the function hall' : 'Reserve a court',
            'type'       => $type,
            'facilities' => array_map(static fn($f) => Facility::normalise($type, $f), $facilities),
            'facility'   => Facility::normalise($type, $facilityRow),
            'date'       => $date,
            'minDate'    => $minDate,
            'maxDate'    => $maxDate,
            'slots'      => $slots,
            'days'       => $this->buildDayStrip($minDate, $maxDays),
            'minHour'    => $minHour,
        ]);
    }

    /** Fourteen days of date chips for the horizontal picker. */
    private function buildDayStrip(string $from, int $maxDays): array
    {
        $days  = [];
        $limit = min(14, $maxDays);

        for ($i = 0; $i <= $limit; $i++) {
            $ts = strtotime("$from +$i days");
            $days[] = [
                'date'  => date('Y-m-d', $ts),
                'dow'   => date('D', $ts),
                'day'   => date('j', $ts),
                'month' => date('M', $ts),
            ];
        }

        return $days;
    }

    /** JSON slot feed, used if the page is enhanced with fetch(). */
    public function slots(): void
    {
        $type       = $this->request->query('type', Facility::COURT);
        $facilityId = $this->request->integer('facility');
        $date       = (string) $this->request->query('date', date('Y-m-d'));

        if (!in_array($type, [Facility::COURT, Facility::HALL], true) || $facilityId <= 0) {
            $this->json(['error' => 'Invalid facility.'], 422);
        }

        $this->json(['slots' => Schedule::forDate($type, $facilityId, $date)]);
    }

    /** Confirmation screen before submitting (price breakdown). */
    public function review(): void
    {
        $slotIds = array_map('intval', (array) $this->request->array('slots'));
        $type    = (string) $this->request->query('type', Facility::COURT);

        if ($slotIds === []) {
            Response::redirectWith('/reserve', 'error', 'Select at least one time slot.');
        }

        $slots = Schedule::lockable($slotIds);

        if (count($slots) !== count($slotIds)) {
            Response::redirectWith('/reserve', 'error', 'Some of those slots are no longer free. Please pick again.');
        }

        $facility = Facility::find($type, (int) $slots[0]['facility_id']);

        $this->view('customer.reserve_review', [
            'title'    => 'Review your reservation',
            'type'     => $type,
            'facility' => Facility::normalise($type, $facility),
            'slots'    => $slots,
            'total'    => array_sum(array_map(static fn($s) => (float) $s['price'], $slots)),
        ]);
    }

    /** C7 -- Submit Reservation Request. */
    public function store(): void
    {
        $user   = Auth::user();
        $player = Auth::player();

        if (!$player) {
            Response::redirectWith('/profile', 'error', 'Complete your player profile before booking.');
        }

        $type    = (string) $this->request->input('reservation_type', Facility::COURT);
        $slotIds = array_map('intval', (array) $this->request->array('slots'));

        if (!in_array($type, [Facility::COURT, Facility::HALL], true)) {
            Response::redirectWith('/reserve', 'error', 'Unknown reservation type.');
        }

        if ($slotIds === []) {
            Response::redirectWith('/reserve', 'error', 'Select at least one time slot.');
        }

        // Anti-spam: cap how many open requests one account can hold.
        $maxOpen = (int) Config::get('booking.max_open_reservations', 5);
        if (Reservation::openCountForUser((int) $user['id']) >= $maxOpen) {
            Response::redirectWith(
                '/reservations',
                'error',
                "You already have {$maxOpen} open reservations. Cancel one before booking again."
            );
        }

        $facilityId = (int) $this->request->input('facility_id');

        if (!Facility::isBookable($type, $facilityId)) {
            Response::redirectWith('/reserve', 'error', 'That facility is not available for booking.');
        }

        try {
            $reservationId = Reservation::create([
                'player_id'        => (int) $player['id'],
                'user_id'          => (int) $user['id'],
                'reservation_type' => $type,
                'facility_id'      => $facilityId,
                'party_size'       => max(1, $this->request->integer('party_size', 1)),
                'purpose'          => $this->request->input('purpose') ?: null,
                'customer_notes'   => $this->request->input('customer_notes') ?: null,
                'status'           => 'pending',
                'source'           => 'online',
                'created_by'       => (int) $user['id'],
            ], $slotIds);
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            $this->back('/reserve');
        }

        $reservation = Reservation::find($reservationId);

        // Tell the back office there is something to approve.
        Notification::broadcast(
            'admin',
            'reservation_submitted',
            'New reservation request',
            sprintf(
                '%s requested %s on %s at %s.',
                trim($player['first_name'] . ' ' . $player['last_name']),
                $reservation['facility_name'],
                date('M j', strtotime($reservation['reservation_date'])),
                date('g:i A', strtotime($reservation['start_time']))
            ),
            '/admin/reservations/' . $reservationId
        );

        Notification::send(
            (int) $user['id'],
            'reservation_submitted',
            'Reservation request received',
            sprintf(
                'We received your request %s for %s. You will be notified once the café confirms it.',
                $reservation['reservation_code'],
                $reservation['facility_name']
            ),
            '/reservations/' . $reservation['reservation_code']
        );

        $this->log('reservation.create', 'Submitted reservation ' . $reservation['reservation_code'], 'reservation', $reservationId);

        Response::redirectWith(
            '/reservations/' . $reservation['reservation_code'],
            'success',
            'Request submitted. The café will confirm shortly.'
        );
    }

    /** C13 -- View My Reservations. */
    public function index(): void
    {
        $filter = (string) $this->request->query('filter', 'upcoming');

        if (!in_array($filter, ['upcoming', 'past', 'cancelled', 'all'], true)) {
            $filter = 'upcoming';
        }

        $this->view('customer.reservations', [
            'title'        => 'My reservations',
            'filter'       => $filter,
            'reservations' => Reservation::forUser((int) Auth::id(), $filter === 'all' ? null : $filter),
        ]);
    }

    public function show(string $code): void
    {
        $reservation = $this->findOwnedByCode($code);

        $this->view('customer.reservation_show', [
            'title'       => 'Reservation ' . $reservation['reservation_code'],
            'reservation' => $reservation,
            'slots'       => Reservation::slots((int) $reservation['id']),
            'cancellable' => Reservation::isCancellable($reservation, (int) Config::get('booking.cancel_cutoff_hours', 12)),
            'cutoffHours' => (int) Config::get('booking.cancel_cutoff_hours', 12),
        ]);
    }

    /** C8 -- Cancel. */
    public function cancel(string $id): void
    {
        $reservation = $this->findOwned((int) $id);
        $cutoff      = (int) Config::get('booking.cancel_cutoff_hours', 12);

        if (!Reservation::isCancellable($reservation, $cutoff)) {
            Response::redirectWith(
                '/reservations/' . $reservation['reservation_code'],
                'error',
                "Reservations can only be cancelled more than {$cutoff} hours before the start time. Please call the café."
            );
        }

        $reason = (string) ($this->request->input('cancel_reason') ?: 'Cancelled by customer');

        Reservation::cancel((int) $reservation['id'], $reason, (int) Auth::id());

        Notification::broadcast(
            'admin',
            'reservation_cancelled',
            'Reservation cancelled by customer',
            sprintf('%s was cancelled. Reason: %s', $reservation['reservation_code'], $reason),
            '/admin/reservations'
        );

        $this->log('reservation.cancel', 'Cancelled ' . $reservation['reservation_code'], 'reservation', (int) $reservation['id']);

        Response::redirectWith('/reservations', 'success', 'Reservation cancelled.');
    }

    /** C8 -- Reschedule: cancel the old one and book a new slot. */
    public function rescheduleForm(string $id): void
    {
        $reservation = $this->findOwned((int) $id);
        $cutoff      = (int) Config::get('booking.cancel_cutoff_hours', 12);

        if (!Reservation::isCancellable($reservation, $cutoff)) {
            Response::redirectWith(
                '/reservations/' . $reservation['reservation_code'],
                'error',
                'This reservation is too close to its start time to reschedule.'
            );
        }

        $type = $reservation['reservation_type'];
        $date = (string) $this->request->query('date', $reservation['reservation_date']);

        if ($date < date('Y-m-d')) {
            $date = date('Y-m-d');
        }

        $this->view('customer.reserve_reschedule', [
            'title'       => 'Reschedule ' . $reservation['reservation_code'],
            'reservation' => $reservation,
            'type'        => $type,
            'date'        => $date,
            'slots'       => Schedule::forDate($type, (int) $reservation['facility_id'], $date),
            'days'        => $this->buildDayStrip(date('Y-m-d'), (int) Config::get('booking.max_advance_days', 30)),
            'minHour'     => (int) Config::get('booking.min_advance_hours', 2),
        ]);
    }

    public function reschedule(string $id): void
    {
        $reservation = $this->findOwned((int) $id);
        $slotIds     = array_map('intval', (array) $this->request->array('slots'));

        if ($slotIds === []) {
            Response::redirectWith('/reservations/' . $reservation['id'] . '/reschedule', 'error', 'Pick a new time slot.');
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
                'status'           => 'pending',
                'source'           => 'online',
                'rescheduled_from' => (int) $reservation['id'],
                'created_by'       => (int) Auth::id(),
            ], $slotIds);
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            $this->back('/reservations/' . $reservation['id'] . '/reschedule');
        }

        // Only release the old slots once the new booking is safely created.
        Reservation::cancel((int) $reservation['id'], 'Rescheduled by customer', (int) Auth::id());

        $new = Reservation::find($newId);

        $this->log('reservation.reschedule',
            sprintf('Rescheduled %s to %s', $reservation['reservation_code'], $new['reservation_code']),
            'reservation', $newId);

        Response::redirectWith(
            '/reservations/' . $new['reservation_code'],
            'success',
            'Rescheduled. Your new request is awaiting confirmation.'
        );
    }

    // ---- Ownership guards -------------------------------------------------

    private function findOwned(int $id): array
    {
        $reservation = Reservation::find($id);

        if (!$reservation || (int) $reservation['user_id'] !== (int) Auth::id()) {
            Response::abort(404, 'Reservation not found.');
        }

        return $reservation;
    }

    private function findOwnedByCode(string $code): array
    {
        $reservation = Reservation::findByCode($code);

        if (!$reservation || (int) $reservation['user_id'] !== (int) Auth::id()) {
            Response::abort(404, 'Reservation not found.');
        }

        return $reservation;
    }
}
