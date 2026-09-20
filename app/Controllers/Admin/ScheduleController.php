<?php
namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Response;
use App\Models\Facility;
use App\Models\Schedule;

/**
 * Use case A7 -- Manage Facility Schedules.
 *
 * Slots are generated in bulk from operating hours, then individually blocked
 * for maintenance or private use. A slot that is already reserved cannot be
 * blocked -- staff must cancel the reservation first, which keeps the customer
 * informed.
 */
final class ScheduleController extends Controller
{
    public function index(): void
    {
        $type = (string) $this->request->query('type', Facility::COURT);

        if (!in_array($type, [Facility::COURT, Facility::HALL], true)) {
            $type = Facility::COURT;
        }

        $facilities = Facility::all($type, false);

        if ($facilities === []) {
            Response::redirectWith('/admin', 'warning', 'Add a facility before generating schedules.');
        }

        $facilityId = $this->request->integer('facility', (int) $facilities[0]['id']);
        $date       = (string) $this->request->query('date', date('Y-m-d'));

        $days = [];
        for ($i = 0; $i < 14; $i++) {
            $ts = strtotime("+$i days");
            $days[] = [
                'date'  => date('Y-m-d', $ts),
                'dow'   => date('D', $ts),
                'day'   => date('j', $ts),
                'month' => date('M', $ts),
            ];
        }

        $this->view('admin.schedules', [
            'title'      => 'Facility schedules · Back office',
            'type'       => $type,
            'facilities' => array_map(static fn($f) => Facility::normalise($type, $f), $facilities),
            'facilityId' => $facilityId,
            'date'       => $date,
            'slots'      => Schedule::forDate($type, $facilityId, $date),
            'days'       => $days,
            'hours'      => Schedule::operatingHours(),
            'coverage'   => Schedule::openDates($type, 30),
        ], 'admin');
    }

    /** Bulk-generate hourly slots from the café's operating hours. */
    public function generate(): void
    {
        $data = $this->validate([
            'facility_type' => 'required|in:court,function_hall',
            'facility_id'   => 'required|integer',
            'from_date'     => 'required|date',
            'to_date'       => 'required|date',
        ]);

        $facility = Facility::find($data['facility_type'], (int) $data['facility_id']);

        if (!$facility) {
            Response::redirectWith('/admin/schedules', 'error', 'Facility not found.');
        }

        if (strtotime($data['to_date']) < strtotime($data['from_date'])) {
            Response::redirectWith('/admin/schedules', 'error', 'The end date must be on or after the start date.');
        }

        // Cap the range so one click cannot generate years of rows.
        $days = (strtotime($data['to_date']) - strtotime($data['from_date'])) / 86400;
        if ($days > 92) {
            Response::redirectWith('/admin/schedules', 'error', 'Generate at most 3 months at a time.');
        }

        $rate = (float) $facility[Facility::rateColumn($data['facility_type'])];

        $created = Schedule::generate(
            $data['facility_type'],
            (int) $data['facility_id'],
            $data['from_date'],
            $data['to_date'],
            $rate
        );

        $this->log(
            'schedule.generate',
            sprintf('Generated %d slots for %s #%d', $created, $data['facility_type'], $data['facility_id']),
            'facility',
            (int) $data['facility_id']
        );

        Response::redirectWith(
            '/admin/schedules?type=' . $data['facility_type'] . '&facility=' . $data['facility_id'] . '&date=' . $data['from_date'],
            $created > 0 ? 'success' : 'info',
            $created > 0
                ? "Created {$created} time slots."
                : 'No new slots were needed — those dates are already generated.'
        );
    }

    /** Block a single slot, or a whole range, for maintenance. */
    public function block(): void
    {
        $reason = (string) ($this->request->input('block_reason') ?: 'Blocked by staff');
        $back   = '/admin/schedules?type=' . $this->request->input('facility_type', 'court')
                . '&facility=' . $this->request->integer('facility_id')
                . '&date=' . $this->request->input('slot_date', date('Y-m-d'));

        // Single slot
        if ($this->request->has('schedule_id')) {
            $id = $this->request->integer('schedule_id');

            if (!Schedule::block($id, $reason)) {
                Response::redirectWith($back, 'error', 'That slot is already reserved — cancel the reservation first.');
            }

            $this->log('schedule.block', "Blocked slot #{$id}: {$reason}", 'schedule', $id);
            Response::redirectWith($back, 'success', 'Slot blocked.');
        }

        // Range
        $data = $this->validate([
            'facility_type' => 'required|in:court,function_hall',
            'facility_id'   => 'required|integer',
            'slot_date'     => 'required|date',
            'start_time'    => 'required',
            'end_time'      => 'required',
        ]);

        $count = Schedule::blockRange(
            $data['facility_type'],
            (int) $data['facility_id'],
            $data['slot_date'],
            $data['start_time'],
            $data['end_time'],
            $reason
        );

        $this->log('schedule.block_range', "Blocked {$count} slots: {$reason}", 'facility', (int) $data['facility_id']);

        Response::redirectWith(
            $back,
            $count > 0 ? 'success' : 'warning',
            $count > 0
                ? "Blocked {$count} " . pluralise($count, 'slot') . '.'
                : 'Nothing was blocked — those slots are reserved or already blocked.'
        );
    }

    public function unblock(string $id): void
    {
        Schedule::unblock((int) $id);
        $this->log('schedule.unblock', "Unblocked slot #{$id}", 'schedule', (int) $id);
        $this->back('/admin/schedules');
    }
}
