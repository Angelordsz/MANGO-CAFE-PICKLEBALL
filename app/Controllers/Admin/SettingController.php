<?php
namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Response;
use App\Models\Setting;
use App\Models\SkillLevel;

/**
 * Platform settings: café details, operating hours (which drive schedule
 * generation) and booking rules. Administrator only.
 */
final class SettingController extends Controller
{
    public function index(): void
    {
        $this->view('admin.settings', [
            'title'    => 'Settings · Back office',
            'settings' => Setting::all(),
            'groups'   => Setting::grouped(),
            'hours'    => Database::select('SELECT * FROM operating_hours ORDER BY day_of_week'),
            'levels'   => SkillLevel::all(false),
        ], 'admin');
    }

    public function update(): void
    {
        // Free-text settings
        foreach ($this->request->array('settings') as $key => $value) {
            if (is_string($key) && preg_match('/^[a-z0-9_.]{1,60}$/', $key)) {
                Setting::set($key, is_scalar($value) ? (string) $value : '');
            }
        }

        // Operating hours -- these decide which slots Schedule::generate() creates.
        $days = $this->request->array('hours');

        foreach ($days as $dow => $row) {
            $dow = (int) $dow;

            if ($dow < 0 || $dow > 6) {
                continue;
            }

            $open   = $row['open_time']  ?? '06:00';
            $close  = $row['close_time'] ?? '22:00';
            $closed = !empty($row['is_closed']) ? 1 : 0;

            if (!$closed && strtotime($close) <= strtotime($open)) {
                Response::redirectWith(
                    '/admin/settings',
                    'error',
                    'Closing time must be after opening time for ' . date('l', strtotime("Sunday +{$dow} days")) . '.'
                );
            }

            Database::execute(
                'INSERT INTO operating_hours (day_of_week, open_time, close_time, is_closed)
                 VALUES (:d, :o, :c, :x)
                 ON DUPLICATE KEY UPDATE open_time = :o2, close_time = :c2, is_closed = :x2',
                [
                    'd'  => $dow,
                    'o'  => $open,  'c'  => $close,  'x'  => $closed,
                    'o2' => $open,  'c2' => $close,  'x2' => $closed,
                ]
            );
        }

        $this->log('settings.update', 'Updated platform settings');

        Response::redirectWith(
            '/admin/settings',
            'success',
            'Settings saved. Existing schedule slots are unchanged — regenerate them if you changed the operating hours.'
        );
    }
}
