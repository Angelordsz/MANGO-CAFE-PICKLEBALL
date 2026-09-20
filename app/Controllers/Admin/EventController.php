<?php
namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Response;
use App\Models\Event;
use App\Models\Facility;
use App\Models\Payment;
use App\Models\Player;
use App\Models\SkillLevel;

/**
 * Figures 3.6 (Create Event), 3.7 (Add Player to Event),
 * 3.8 (Manage Participant Payment), 3.11 (Create Event sequence).
 *
 * See docs/DOCUMENT-GAPS.md gap #3 -- this module has activity diagrams but no
 * class or use case in Chapter III.
 */
final class EventController extends Controller
{
    public function index(): void
    {
        $filters = [
            'status' => (string) $this->request->query('status', ''),
            'type'   => (string) $this->request->query('type', ''),
            'from'   => (string) $this->request->query('from', ''),
            'to'     => (string) $this->request->query('to', ''),
        ];

        $this->view('admin.events', [
            'title'   => 'Events · Back office',
            'events'  => Event::all($filters, 100),
            'filters' => $filters,
        ], 'admin');
    }

    public function create(): void
    {
        $this->view('admin.event_form', [
            'title'  => 'Create event',
            'event'  => null,
            'courts' => Facility::all(Facility::COURT, false),
            'levels' => SkillLevel::all(),
        ], 'admin');
    }

    public function store(): void
    {
        $data = $this->validateEvent();

        $eventId = Event::create(array_merge($data, [
            'image_path' => $this->storeEventImage(),
        ]));

        $this->log('event.create', 'Created event: ' . $data['title'], 'event', $eventId);

        Response::redirectWith('/admin/events/' . $eventId, 'success', 'Event created successfully.');
    }

    public function show(string $id): void
    {
        $event = $this->findOr404(Event::find((int) $id), 'Event not found.');

        $this->view('admin.event_show', [
            'title'        => $event['title'],
            'event'        => $event,
            'participants' => Event::participants((int) $id),
        ], 'admin');
    }

    public function edit(string $id): void
    {
        $event = $this->findOr404(Event::find((int) $id), 'Event not found.');

        $this->view('admin.event_form', [
            'title'  => 'Edit ' . $event['title'],
            'event'  => $event,
            'courts' => Facility::all(Facility::COURT, false),
            'levels' => SkillLevel::all(),
        ], 'admin');
    }

    public function update(string $id): void
    {
        $event = $this->findOr404(Event::find((int) $id), 'Event not found.');
        $data  = $this->validateEvent();

        $image = $this->storeEventImage();
        if ($image !== null) {
            $data['image_path'] = $image;
        }

        Database::update('events', $data, ['id' => $id]);

        $this->log('event.update', 'Updated event: ' . $data['title'], 'event', (int) $id);

        Response::redirectWith('/admin/events/' . $id, 'success', 'Event updated.');
    }

    public function cancel(string $id): void
    {
        $reason = (string) ($this->request->input('reason') ?: 'Cancelled by the café');

        Event::cancel((int) $id, $reason);

        $this->log('event.cancel', "Cancelled event #{$id}", 'event', (int) $id);

        Response::redirectWith('/admin/events', 'success', 'Event cancelled and participants notified.');
    }

    /** Fig. 3.7 -- Add Player to Event. */
    public function addParticipant(string $id): void
    {
        $event    = $this->findOr404(Event::find((int) $id), 'Event not found.');
        $playerId = $this->request->integer('player_id');

        if ($playerId <= 0) {
            Response::redirectWith('/admin/events/' . $id, 'error', 'Search for and select a player first.');
        }

        $amountDue = $this->request->has('amount_due')
            ? (float) $this->request->input('amount_due')
            : (float) $event['fee'];

        $result = Event::addParticipant((int) $id, $playerId, $amountDue);

        if (!$result['ok']) {
            Response::redirectWith('/admin/events/' . $id, 'error', $result['error']);
        }

        $player = Player::find($playerId);

        $this->log('event.add_participant',
            sprintf('Added %s to event %s', $player['player_code'] ?? $playerId, $event['event_code']),
            'event', (int) $id);

        Response::redirectWith('/admin/events/' . $id, 'success', 'Player added to the event.');
    }

    public function removeParticipant(string $id, string $pid): void
    {
        Event::removeParticipant((int) $pid);

        $this->log('event.remove_participant', "Removed participant #{$pid}", 'event', (int) $id);

        Response::redirectWith('/admin/events/' . $id, 'success', 'Participant removed.');
    }

    /** Fig. 3.8 -- Manage Participant Payment for Event. */
    public function recordPayment(string $id, string $pid): void
    {
        $participant = $this->findOr404(Event::findParticipant((int) $pid), 'Participant not found.');

        $amount = (float) $this->request->input('amount', 0);

        if ($amount <= 0) {
            Response::redirectWith('/admin/events/' . $id, 'error', 'Enter the amount received.');
        }

        Payment::record(
            'event_participant',
            (int) $pid,
            (float) $participant['amount_due'],
            $amount,
            (string) $this->request->input('payment_method', 'cash'),
            (int) $participant['player_id'],
            $this->request->input('reference_no') ?: null,
            'Event: ' . $participant['title']
        );

        $this->log('event.payment',
            sprintf('Recorded %s for participant #%s', money($amount), $pid),
            'event', (int) $id);

        Response::redirectWith('/admin/events/' . $id, 'success', 'Payment recorded.');
    }

    // ---- helpers ----------------------------------------------------------

    /** Fields taken from Fig. 3.6 "Fill in Event Information". */
    private function validateEvent(): array
    {
        $data = $this->validate([
            'title'          => 'required|max:150',
            'event_type'     => 'required|in:open_play,clinic,league,exhibition,social,private',
            'description'    => 'nullable|max:5000',
            'location'       => 'required|max:150',
            'court_id'       => 'nullable|integer',
            'start_datetime' => 'required|date',
            'end_datetime'   => 'required|date',
            'capacity'       => 'required|integer|between:1,500',
            'fee'            => 'required|numeric|min:0',
            'skill_level_id' => 'nullable|integer',
            'status'         => 'required|in:draft,upcoming,active,ongoing,completed,cancelled',
        ]);

        if (strtotime($data['end_datetime']) <= strtotime($data['start_datetime'])) {
            Response::redirectWith(
                '/admin/events/create',
                'error',
                'The end date and time must be after the start.'
            );
        }

        $data['court_id']       = $data['court_id'] ?: null;
        $data['skill_level_id'] = $data['skill_level_id'] ?: null;
        $data['description']    = $data['description'] ?: null;

        return $data;
    }

    private function storeEventImage(): ?string
    {
        $file = $this->request->file('image');

        if (!$file || $file['error'] !== UPLOAD_ERR_OK || $file['size'] > 3 * 1024 * 1024) {
            return null;
        }

        $info = @getimagesize($file['tmp_name']);
        if ($info === false) {
            return null;
        }

        $allowed = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
        if (!isset($allowed[$info[2]])) {
            return null;
        }

        $name   = 'events/' . bin2hex(random_bytes(12)) . '.' . $allowed[$info[2]];
        $target = PUBLIC_PATH . '/uploads/' . $name;

        if (!is_dir(dirname($target))) {
            @mkdir(dirname($target), 0775, true);
        }

        return move_uploaded_file($file['tmp_name'], $target) ? $name : null;
    }
}
