<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Response;
use App\Models\Event;
use App\Models\Facility;
use App\Models\Matching;

/**
 * Public landing page. Logged-in users are sent straight to their own area.
 */
final class HomeController extends Controller
{
    public function index(): void
    {
        if (Auth::check()) {
            Response::redirect(Auth::isStaff() ? '/admin' : '/dashboard');
        }

        $today = date('Y-m-d');

        $this->view('home.index', [
            'title'      => 'Mango Drive Café Pickleball — book a court, find a game',
            'courts'     => Facility::withAvailability(Facility::COURT, $today),
            'halls'      => Facility::withAvailability(Facility::HALL, $today),
            'events'     => Event::upcoming(3),
            'openPools'  => array_slice(Matching::openPools(), 0, 3),
        ]);
    }

    public function about(): void
    {
        $this->view('home.about', ['title' => 'About · Mango Drive Café Pickleball']);
    }
}
