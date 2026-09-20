<?php
namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Response;
use App\Models\Facility;

/**
 * Use case A6 -- Manage Function Hall. Administrator only.
 * Class: FunctionHall -- updateAvailability().
 */
final class HallController extends Controller
{
    public function index(): void
    {
        $halls = Database::select(
            "SELECT h.*,
                    (SELECT COUNT(*) FROM schedules s
                      WHERE s.facility_type='function_hall' AND s.facility_id=h.id
                        AND s.slot_date >= CURDATE()) AS future_slots,
                    (SELECT COUNT(*) FROM reservations r
                      WHERE r.reservation_type='function_hall' AND r.facility_id=h.id
                        AND r.status IN ('pending','approved')
                        AND r.reservation_date >= CURDATE()) AS upcoming_bookings
               FROM function_halls h
           ORDER BY h.sort_order, h.id"
        );

        $this->view('admin.halls', ['title' => 'Function halls · Back office', 'halls' => $halls], 'admin');
    }

    public function create(): void
    {
        $this->view('admin.hall_form', ['title' => 'Add a function hall', 'hall' => null], 'admin');
    }

    public function store(): void
    {
        $data = $this->rules();
        $id   = Database::insert('function_halls', $data);

        $this->log('hall.create', 'Added hall ' . $data['hall_name'], 'function_hall', $id);

        Response::redirectWith('/admin/halls', 'success', 'Function hall added. Generate its schedule next.');
    }

    public function edit(string $id): void
    {
        $hall = $this->findOr404(Facility::find(Facility::HALL, (int) $id), 'Function hall not found.');

        $this->view('admin.hall_form', ['title' => 'Edit ' . $hall['hall_name'], 'hall' => $hall], 'admin');
    }

    public function update(string $id): void
    {
        $this->findOr404(Facility::find(Facility::HALL, (int) $id), 'Function hall not found.');

        $data = $this->rules((int) $id);

        Database::update('function_halls', $data, ['id' => $id]);

        $this->log('hall.update', 'Updated hall ' . $data['hall_name'], 'function_hall', (int) $id);

        Response::redirectWith('/admin/halls', 'success', 'Function hall updated.');
    }

    public function destroy(string $id): void
    {
        $hall = $this->findOr404(Facility::find(Facility::HALL, (int) $id), 'Function hall not found.');

        if (Facility::hasReservations(Facility::HALL, (int) $id)) {
            Database::update('function_halls', ['status' => 'inactive'], ['id' => $id]);

            $this->log('hall.deactivate', 'Deactivated hall ' . $hall['hall_name'], 'function_hall', (int) $id);

            Response::redirectWith(
                '/admin/halls',
                'info',
                'This hall has booking history, so it was deactivated rather than deleted.'
            );
        }

        Database::delete('function_halls', ['id' => $id]);

        $this->log('hall.delete', 'Deleted hall ' . $hall['hall_name'], 'function_hall', (int) $id);

        Response::redirectWith('/admin/halls', 'success', 'Function hall deleted.');
    }

    private function rules(?int $ignoreId = null): array
    {
        $unique = 'unique:function_halls,hall_code' . ($ignoreId ? ',' . $ignoreId : '');

        $data = $this->validate([
            'hall_name'   => 'required|max:60',
            'hall_code'   => 'required|max:20|' . $unique,
            'capacity'    => 'required|integer|between:1,2000',
            'rental_fee'  => 'required|numeric|min:0',
            'min_hours'   => 'required|integer|between:1,12',
            'amenities'   => 'nullable|max:500',
            'description' => 'nullable|max:1000',
            'status'      => 'required|in:available,maintenance,inactive',
            'sort_order'  => 'nullable|integer|between:0,99',
        ]);

        $data['sort_order']  = $data['sort_order'] ?: 0;
        $data['amenities']   = $data['amenities'] ?: null;
        $data['description'] = $data['description'] ?: null;

        return $data;
    }
}
