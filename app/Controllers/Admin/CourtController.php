<?php
namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Response;
use App\Models\Facility;

/**
 * Use case A5 -- Manage Courts. Administrator only.
 * Class: Court -- updateAvailability().
 */
final class CourtController extends Controller
{
    public function index(): void
    {
        $courts = Database::select(
            "SELECT c.*,
                    (SELECT COUNT(*) FROM schedules s
                      WHERE s.facility_type='court' AND s.facility_id=c.id
                        AND s.slot_date >= CURDATE()) AS future_slots,
                    (SELECT COUNT(*) FROM reservations r
                      WHERE r.reservation_type='court' AND r.facility_id=c.id
                        AND r.status IN ('pending','approved')
                        AND r.reservation_date >= CURDATE()) AS upcoming_bookings
               FROM courts c
           ORDER BY c.sort_order, c.id"
        );

        $this->view('admin.courts', ['title' => 'Courts · Back office', 'courts' => $courts], 'admin');
    }

    public function create(): void
    {
        $this->view('admin.court_form', ['title' => 'Add a court', 'court' => null], 'admin');
    }

    public function store(): void
    {
        $data = $this->validate([
            'court_name'  => 'required|max:60',
            'court_code'  => 'required|max:20|unique:courts,court_code',
            'type'        => 'required|in:indoor,outdoor,covered',
            'surface'     => 'nullable|max:40',
            'hourly_rate' => 'required|numeric|min:0',
            'capacity'    => 'required|integer|between:2,20',
            'description' => 'nullable|max:1000',
            'status'      => 'required|in:available,maintenance,inactive',
            'sort_order'  => 'nullable|integer|between:0,99',
        ]);

        $data['sort_order']  = $data['sort_order'] ?: 0;
        $data['description'] = $data['description'] ?: null;
        $data['surface']     = $data['surface'] ?: null;

        $id = Database::insert('courts', $data);

        $this->log('court.create', 'Added court ' . $data['court_name'], 'court', $id);

        Response::redirectWith('/admin/courts', 'success', 'Court added. Generate its schedule next.');
    }

    public function edit(string $id): void
    {
        $court = $this->findOr404(Facility::find(Facility::COURT, (int) $id), 'Court not found.');

        $this->view('admin.court_form', ['title' => 'Edit ' . $court['court_name'], 'court' => $court], 'admin');
    }

    public function update(string $id): void
    {
        $this->findOr404(Facility::find(Facility::COURT, (int) $id), 'Court not found.');

        $data = $this->validate([
            'court_name'  => 'required|max:60',
            'court_code'  => 'required|max:20|unique:courts,court_code,' . $id,
            'type'        => 'required|in:indoor,outdoor,covered',
            'surface'     => 'nullable|max:40',
            'hourly_rate' => 'required|numeric|min:0',
            'capacity'    => 'required|integer|between:2,20',
            'description' => 'nullable|max:1000',
            'status'      => 'required|in:available,maintenance,inactive',
            'sort_order'  => 'nullable|integer|between:0,99',
        ]);

        $data['sort_order']  = $data['sort_order'] ?: 0;
        $data['description'] = $data['description'] ?: null;
        $data['surface']     = $data['surface'] ?: null;

        Database::update('courts', $data, ['id' => $id]);

        $this->log('court.update', 'Updated court ' . $data['court_name'], 'court', (int) $id);

        Response::redirectWith('/admin/courts', 'success', 'Court updated.');
    }

    /**
     * Courts with booking history are never deleted -- that would orphan
     * reservations and break the reports. They are deactivated instead.
     */
    public function destroy(string $id): void
    {
        $court = $this->findOr404(Facility::find(Facility::COURT, (int) $id), 'Court not found.');

        if (Facility::hasReservations(Facility::COURT, (int) $id)) {
            Database::update('courts', ['status' => 'inactive'], ['id' => $id]);

            $this->log('court.deactivate', 'Deactivated court ' . $court['court_name'], 'court', (int) $id);

            Response::redirectWith(
                '/admin/courts',
                'info',
                'This court has booking history, so it was deactivated rather than deleted. Its records stay intact.'
            );
        }

        Database::delete('courts', ['id' => $id]);

        $this->log('court.delete', 'Deleted court ' . $court['court_name'], 'court', (int) $id);

        Response::redirectWith('/admin/courts', 'success', 'Court deleted.');
    }
}
