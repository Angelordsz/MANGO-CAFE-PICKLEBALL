<?php
namespace App\Controllers;

use App\Core\Config;
use App\Core\Controller;
use App\Models\Facility;
use App\Models\Schedule;

/**
 * Use case C4 -- View Facility Schedules and Availability.
 *
 * Public: anyone can see what is free before creating an account. This is the
 * direct answer to the Project Context problem that "there is no facility in
 * place for the customer to know the current availability".
 */
final class AvailabilityController extends Controller
{
    public function index(): void
    {
        $type = (string) $this->request->query('type', Facility::COURT);

        if (!in_array($type, [Facility::COURT, Facility::HALL], true)) {
            $type = Facility::COURT;
        }

        $maxDays = (int) Config::get('booking.max_advance_days', 30);
        $date    = (string) $this->request->query('date', date('Y-m-d'));
        $minDate = date('Y-m-d');
        $maxDate = date('Y-m-d', strtotime("+{$maxDays} days"));

        if ($date < $minDate) { $date = $minDate; }
        if ($date > $maxDate) { $date = $maxDate; }

        $facilities = Facility::all($type, false);
        $grid       = [];
        $times      = [];

        foreach ($facilities as $row) {
            $facility = Facility::normalise($type, $row);
            $slots    = Schedule::forDate($type, $facility['id'], $date);

            foreach ($slots as $slot) {
                $key = substr($slot['start_time'], 0, 5);
                $times[$key] = $slot['start_time'];
            }

            $grid[] = ['facility' => $facility, 'slots' => $slots];
        }

        ksort($times);

        $days = [];
        for ($i = 0; $i <= min(14, $maxDays); $i++) {
            $ts = strtotime("$minDate +$i days");
            $days[] = [
                'date'  => date('Y-m-d', $ts),
                'dow'   => date('D', $ts),
                'day'   => date('j', $ts),
                'month' => date('M', $ts),
            ];
        }

        $this->view('customer.availability', [
            'title'   => 'Facility availability · Mango Drive Pickleball',
            'type'    => $type,
            'date'    => $date,
            'minDate' => $minDate,
            'maxDate' => $maxDate,
            'grid'    => $grid,
            'times'   => $times,
            'days'    => $days,
            'hours'   => Schedule::operatingHours(),
        ]);
    }

    /** JSON feed for any future calendar widget. */
    public function slots(): void
    {
        $type = (string) $this->request->query('type', Facility::COURT);
        $date = (string) $this->request->query('date', date('Y-m-d'));
        $id   = $this->request->integer('facility');

        if ($id <= 0) {
            $this->json(['error' => 'facility is required'], 422);
        }

        $this->json(['date' => $date, 'slots' => Schedule::forDate($type, $id, $date)]);
    }
}
