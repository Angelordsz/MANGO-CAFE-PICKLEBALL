<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\Event;

/**
 * Customer-facing event listing.
 *
 * Events come from Figures 3.6-3.9. Registration is admin-driven (Fig. 3.7
 * "Admin Add Player to Event"), so customers browse and see who is playing,
 * but sign-up happens at the counter.
 */
final class EventController extends Controller
{
    public function index(): void
    {
        $this->view('customer.events', [
            'title'  => 'Events · Mango Drive Pickleball',
            'events' => Event::all(['public' => true], 30),
        ]);
    }

    public function show(string $id): void
    {
        $event = $this->findOr404(Event::find((int) $id), 'Event not found.');

        $this->view('customer.event_show', [
            'title'        => $event['title'],
            'event'        => $event,
            'participants' => Event::participants((int) $event['id']),
        ]);
    }
}
