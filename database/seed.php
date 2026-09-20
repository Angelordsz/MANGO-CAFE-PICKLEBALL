<?php
/**
 * Seed the database with realistic demo data.
 *
 *   php database/seed.php          -- seed (refuses if data already exists)
 *   php database/seed.php --force  -- wipe all data and reseed
 *
 * Passwords are hashed with password_hash() at run time, so the demo logins
 * always work regardless of PHP version or bcrypt cost settings.
 */

require dirname(__DIR__) . '/bootstrap.php';

use App\Core\Database;
use App\Models\Schedule;

if (PHP_SAPI !== 'cli') {
    exit("This script must be run from the command line.\n");
}

$force = in_array('--force', $argv, true);

$pdo = Database::pdo();

// ---------------------------------------------------------------------------
// Guard
// ---------------------------------------------------------------------------
$existing = (int) Database::scalar('SELECT COUNT(*) FROM users', [], 0);

if ($existing > 0 && !$force) {
    exit("Database already contains {$existing} users.\nRe-run with --force to wipe and reseed.\n");
}

if ($force) {
    echo "Wiping existing data...\n";
    Database::execute('SET FOREIGN_KEY_CHECKS = 0');
    foreach ([
        'enh_player_stats', 'enh_match_results',
        'system_logs', 'notifications', 'player_history',
        'payments', 'event_participants', 'events',
        'match_assignment_players', 'match_assignments',
        'matching_requests', 'matching_pools',
        'reservation_schedules', 'reservations', 'schedules',
        'courts', 'function_halls',
        'password_resets', 'players', 'users',
        'operating_hours', 'settings', 'skill_levels',
    ] as $table) {
        Database::execute("TRUNCATE TABLE `$table`");
    }
    Database::execute('SET FOREIGN_KEY_CHECKS = 1');
}

echo "Seeding Mango Drive Cafe pickleball database...\n\n";

// ---------------------------------------------------------------------------
// 1. Skill levels  (Class: SkillLevel, use case C11)
// ---------------------------------------------------------------------------
$levels = [
    ['Beginner',     'BEG', 'New to pickleball. Learning the serve, the rules and basic rallies.',      2.0, 2.5, 1],
    ['Intermediate', 'INT', 'Comfortable rallying. Understands positioning, dinking and the kitchen.',  3.0, 3.5, 2],
    ['Advanced',     'ADV', 'Consistent shot placement, strong third shot drop, plays competitively.',  4.0, 4.5, 3],
    ['Open',         'OPN', 'Tournament-level player. Full shot selection and strategy.',               5.0, 5.5, 4],
];

$levelIds = [];
foreach ($levels as [$name, $code, $desc, $low, $high, $order]) {
    $levelIds[$code] = Database::insert('skill_levels', [
        'level_name'  => $name,
        'level_code'  => $code,
        'description' => $desc,
        'rating_low'  => $low,
        'rating_high' => $high,
        'sort_order'  => $order,
        'is_active'   => 1,
    ]);
}
echo "  skill levels ......... " . count($levelIds) . "\n";

// ---------------------------------------------------------------------------
// 2. Operating hours -- drive schedule generation
// ---------------------------------------------------------------------------
$hours = [
    0 => ['07:00:00', '20:00:00', 0], // Sunday
    1 => ['06:00:00', '22:00:00', 0],
    2 => ['06:00:00', '22:00:00', 0],
    3 => ['06:00:00', '22:00:00', 0],
    4 => ['06:00:00', '22:00:00', 0],
    5 => ['06:00:00', '23:00:00', 0],
    6 => ['07:00:00', '23:00:00', 0], // Saturday
];

foreach ($hours as $dow => [$open, $close, $closed]) {
    Database::insert('operating_hours', [
        'day_of_week' => $dow,
        'open_time'   => $open,
        'close_time'  => $close,
        'is_closed'   => $closed,
    ]);
}
echo "  operating hours ...... 7 days\n";

// ---------------------------------------------------------------------------
// 3. Settings
// ---------------------------------------------------------------------------
$settings = [
    ['cafe_name',           'Mango Drive Cafe',                         'general'],
    ['cafe_address',        'Mango Drive, Barangay Poblacion, Philippines', 'general'],
    ['cafe_phone',          '(02) 8123 4567',                           'general'],
    ['cafe_email',          'reservations@mangodrivecafe.ph',           'general'],
    ['cafe_facebook',       'facebook.com/mangodrivecafe',              'general'],
    ['cancel_cutoff_hours', '12',                                       'booking'],
    ['max_advance_days',    '30',                                       'booking'],
    ['booking_policy',      'Reservations are confirmed by Mango Drive Cafe staff. Payment is settled at the counter on the day of play. Cancellations made less than 12 hours before the start time are not accepted online.', 'booking'],
];

foreach ($settings as [$key, $value, $group]) {
    Database::insert('settings', [
        'setting_key'   => $key,
        'setting_value' => $value,
        'setting_group' => $group,
    ]);
}
echo "  settings ............. " . count($settings) . "\n";

// ---------------------------------------------------------------------------
// 4. Staff accounts
// ---------------------------------------------------------------------------
$adminId = Database::insert('users', [
    'role'          => 'admin',
    'username'      => 'admin',
    'email'         => 'admin@mangodrivecafe.ph',
    'password_hash' => password_hash('Admin@1234', PASSWORD_DEFAULT),
    'status'        => 'active',
]);

$staffId = Database::insert('users', [
    'role'          => 'staff',
    'username'      => 'frontdesk',
    'email'         => 'frontdesk@mangodrivecafe.ph',
    'password_hash' => password_hash('Staff@1234', PASSWORD_DEFAULT),
    'status'        => 'active',
]);

echo "  staff accounts ....... 2\n";

// ---------------------------------------------------------------------------
// 5. Courts and function halls
// ---------------------------------------------------------------------------
$courts = [
    ['Court 1 — Center',  'CT-01', 'outdoor', 'Acrylic',   350.00, 4, 'Our main show court, with LED lighting for night play.'],
    ['Court 2 — Garden',  'CT-02', 'outdoor', 'Acrylic',   350.00, 4, 'Shaded by mango trees. The coolest court in the afternoon.'],
    ['Court 3 — Covered', 'CT-03', 'covered', 'Synthetic', 400.00, 4, 'Roofed court, playable in the rain.'],
    ['Court 4 — Practice','CT-04', 'outdoor', 'Concrete',  250.00, 4, 'Budget court with a practice wall. Ideal for beginners.'],
];

$courtIds = [];
foreach ($courts as $i => [$name, $code, $type, $surface, $rate, $cap, $desc]) {
    $courtIds[] = Database::insert('courts', [
        'court_name'  => $name,
        'court_code'  => $code,
        'type'        => $type,
        'surface'     => $surface,
        'hourly_rate' => $rate,
        'capacity'    => $cap,
        'description' => $desc,
        'status'      => 'available',
        'sort_order'  => $i + 1,
    ]);
}

$halls = [
    ['Mango Function Hall', 'FH-01', 120, 1800.00, 3,
     'Projector, sound system, air conditioning, catering available, parking',
     'Our main event space, good for birthdays, corporate meetings and team celebrations.'],
    ['Garden Pavilion',     'FH-02', 45,  1200.00, 2,
     'Open air, sound system, catering available, parking',
     'Semi-open pavilion beside the courts. Popular for casual gatherings and after-game meals.'],
];

$hallIds = [];
foreach ($halls as $i => [$name, $code, $cap, $fee, $min, $amenities, $desc]) {
    $hallIds[] = Database::insert('function_halls', [
        'hall_name'   => $name,
        'hall_code'   => $code,
        'capacity'    => $cap,
        'rental_fee'  => $fee,
        'min_hours'   => $min,
        'amenities'   => $amenities,
        'description' => $desc,
        'status'      => 'available',
        'sort_order'  => $i + 1,
    ]);
}

echo "  courts ............... " . count($courtIds) . "\n";
echo "  function halls ....... " . count($hallIds) . "\n";

// ---------------------------------------------------------------------------
// 6. Customers with accounts
// ---------------------------------------------------------------------------
$customers = [
    ['Marco',    'Villanueva', 'marco.v',    'marco.villanueva@example.ph',  '0917 555 0101', 'male',   34, 'INT', 'evening'],
    ['Liza',     'Ramos',      'liza.r',     'liza.ramos@example.ph',        '0918 555 0102', 'female', 29, 'ADV', 'morning'],
    ['Job',      'Mercado',    'job.m',      'job.mercado@example.ph',       '0919 555 0103', 'male',   41, 'BEG', 'evening'],
    ['Carmela',  'Dizon',      'carmela.d',  'carmela.dizon@example.ph',     '0920 555 0104', 'female', 26, 'INT', 'afternoon'],
    ['Rafael',   'Santos',     'rafael.s',   'rafael.santos@example.ph',     '0921 555 0105', 'male',   38, 'ADV', 'evening'],
    ['Bea',      'Ocampo',     'bea.o',      'bea.ocampo@example.ph',        '0922 555 0106', 'female', 31, 'INT', 'any'],
    ['Nico',     'Aguilar',    'nico.a',     'nico.aguilar@example.ph',      '0923 555 0107', 'male',   22, 'OPN', 'morning'],
    ['Trina',    'Bautista',   'trina.b',    'trina.bautista@example.ph',    '0924 555 0108', 'female', 35, 'BEG', 'afternoon'],
];

$playerIds = [];
$seq = 0;

foreach ($customers as [$first, $last, $username, $email, $phone, $gender, $age, $level, $time]) {
    $userId = Database::insert('users', [
        'role'          => 'customer',
        'username'      => $username,
        'email'         => $email,
        'password_hash' => password_hash('Player@1234', PASSWORD_DEFAULT),
        'status'        => 'active',
        'last_login_at' => date('Y-m-d H:i:s', strtotime('-' . random_int(1, 10) . ' days')),
    ]);

    $seq++;
    $playerIds[] = Database::insert('players', [
        'user_id'        => $userId,
        'player_code'    => sprintf('PL-%06d', $seq),
        'first_name'     => $first,
        'last_name'      => $last,
        'gender'         => $gender,
        'age'            => $age,
        'phone_number'   => $phone,
        'email'          => $email,
        'skill_level_id' => $levelIds[$level],
        'preferred_time' => $time,
        'preferred_days' => implode(',', array_rand(array_flip([0, 1, 2, 3, 4, 5, 6]), 3)),
        'is_walk_in'     => 0,
        'status'         => 'active',
        'created_at'     => date('Y-m-d H:i:s', strtotime('-' . random_int(20, 120) . ' days')),
    ]);
}

// ---------------------------------------------------------------------------
// 7. Walk-in players (Fig. 3.5 / 3.12 -- no login)
// ---------------------------------------------------------------------------
$walkIns = [
    ['Ramon',  'Cruz',     '0925 555 0201', 'male',   47, 'BEG'],
    ['Divina', 'Salazar',  '0926 555 0202', 'female', 33, 'INT'],
    ['Paolo',  'Reyes',    '0927 555 0203', 'male',   28, 'INT'],
    ['Mimi',   'Torres',   '0928 555 0204', 'female', 52, 'BEG'],
    ['Enzo',   'Lim',      '0929 555 0205', 'male',   19, 'ADV'],
    ['Grace',  'Fernandez','0930 555 0206', 'female', 44, 'INT'],
];

foreach ($walkIns as [$first, $last, $phone, $gender, $age, $level]) {
    $seq++;
    $playerIds[] = Database::insert('players', [
        'user_id'        => null,
        'player_code'    => sprintf('PL-%06d', $seq),
        'first_name'     => $first,
        'last_name'      => $last,
        'gender'         => $gender,
        'age'            => $age,
        'phone_number'   => $phone,
        'skill_level_id' => $levelIds[$level],
        'preferred_time' => 'any',
        'is_walk_in'     => 1,
        'registered_by'  => $staffId,
        'notes'          => 'Registered at the counter.',
        'status'         => 'active',
        'created_at'     => date('Y-m-d H:i:s', strtotime('-' . random_int(1, 40) . ' days')),
    ]);
}

echo "  players .............. " . count($playerIds) . " (" . count($customers) . " online, " . count($walkIns) . " walk-in)\n";

// ---------------------------------------------------------------------------
// 8. Schedules -- 7 days back and 30 days forward, every facility
// ---------------------------------------------------------------------------
$from = date('Y-m-d', strtotime('-7 days'));
$to   = date('Y-m-d', strtotime('+30 days'));

$slotCount = 0;
foreach ($courtIds as $i => $courtId) {
    $slotCount += Schedule::generate('court', $courtId, $from, $to, (float) $courts[$i][4]);
}
foreach ($hallIds as $i => $hallId) {
    $slotCount += Schedule::generate('function_hall', $hallId, $from, $to, (float) $halls[$i][3]);
}

echo "  schedule slots ....... " . number_format($slotCount) . " ({$from} to {$to})\n";

// A couple of blocked slots, so the maintenance state is visible in the UI.
Database::execute(
    "UPDATE schedules SET status = 'maintenance', block_reason = 'Court resurfacing'
      WHERE facility_type = 'court' AND facility_id = :c
        AND slot_date = :d AND start_time BETWEEN '08:00:00' AND '11:00:00'",
    ['c' => $courtIds[3], 'd' => date('Y-m-d', strtotime('+3 days'))]
);

require __DIR__ . '/seed_activity.php';
