<?php
/**
 * Second half of the seeder: reservations, payments, matching, events,
 * notifications and logs. Included by seed.php, which defines the variables
 * used here.
 *
 * @var array $courtIds
 * @var array $hallIds
 * @var array $playerIds
 * @var array $levelIds
 * @var int   $adminId
 * @var int   $staffId
 */

use App\Core\Database;

// ---------------------------------------------------------------------------
// 9. Reservations, spread across statuses and dates
// ---------------------------------------------------------------------------

/** Claim the first N free contiguous slots for a facility on a date. */
$claimSlots = static function (string $type, int $facilityId, string $date, string $startTime, int $hours): array {
    return Database::select(
        "SELECT s.* FROM schedules s
       LEFT JOIN reservation_schedules rs ON rs.schedule_id = s.id
           WHERE s.facility_type = :t AND s.facility_id = :f AND s.slot_date = :d
             AND s.start_time >= :st AND s.status = 'available' AND rs.id IS NULL
        ORDER BY s.start_time
           LIMIT " . (int) $hours,
        ['t' => $type, 'f' => $facilityId, 'd' => $date, 'st' => $startTime]
    );
};

$resSeq = 0;
$makeReservation = static function (
    string $type, int $facilityId, int $playerIndex, string $date, string $startTime,
    int $hours, string $status, string $source, ?string $purpose = null
) use ($claimSlots, $playerIds, $adminId, $staffId, &$resSeq): ?int {
    $slots = $claimSlots($type, $facilityId, $date, $startTime, $hours);

    if (count($slots) < $hours) {
        return null; // facility closed or already busy that day
    }

    $playerId = $playerIds[$playerIndex];
    $userId   = Database::scalar('SELECT user_id FROM players WHERE id = :p', ['p' => $playerId]);
    $total    = array_sum(array_map(static fn($s) => (float) $s['price'], $slots));
    $last     = $slots[count($slots) - 1];

    $resSeq++;
    $reservationId = Database::insert('reservations', [
        'reservation_code' => sprintf('MDC-%s-%06d', date('Y'), $resSeq),
        'player_id'        => $playerId,
        'user_id'          => $userId ?: null,
        'reservation_type' => $type,
        'facility_id'      => $facilityId,
        'reservation_date' => $date,
        'start_time'       => $slots[0]['start_time'],
        'end_time'         => $last['end_time'],
        'duration_hours'   => $hours,
        'party_size'       => $type === 'function_hall' ? random_int(20, 60) : 4,
        'purpose'          => $purpose,
        'total_amount'     => $total,
        'status'           => $status,
        'source'           => $source,
        'reviewed_by'      => in_array($status, ['approved', 'completed'], true) ? $adminId : null,
        'reviewed_at'      => in_array($status, ['approved', 'completed'], true)
                                ? date('Y-m-d H:i:s', strtotime($date . ' -1 day'))
                                : null,
        'created_by'       => $source === 'online' ? ($userId ?: null) : $staffId,
        'created_at'       => date('Y-m-d H:i:s', strtotime($date . ' -2 days')),
    ]);

    // Cancelled and rejected bookings release their slots, so only hold them
    // for the states that actually occupy the court.
    if (in_array($status, ['pending', 'approved', 'completed'], true)) {
        foreach ($slots as $slot) {
            Database::insert('reservation_schedules', [
                'reservation_id' => $reservationId,
                'schedule_id'    => $slot['id'],
            ]);
        }
    }

    Database::insert('player_history', [
        'player_id'     => $playerId,
        'activity_type' => 'reservation',
        'reference_id'  => $reservationId,
        'activity_date' => $date,
        'activity_time' => $slots[0]['start_time'],
        'description'   => ucfirst(str_replace('_', ' ', $type)) . ' reservation (' . $status . ')',
        'payment'       => in_array($status, ['approved', 'completed'], true) ? $total : 0,
    ]);

    return $reservationId;
};

$plan = [
    // [type, facilityIdx, playerIdx, dayOffset, startTime, hours, status, source, purpose]
    ['court', 0, 0, -6, '18:00:00', 2, 'completed', 'online',  null],
    ['court', 1, 1, -5, '07:00:00', 1, 'completed', 'online',  null],
    ['court', 0, 4, -4, '19:00:00', 2, 'completed', 'walk_in', null],
    ['court', 2, 3, -3, '16:00:00', 1, 'completed', 'online',  null],
    ['court', 1, 9, -2, '08:00:00', 2, 'completed', 'walk_in', null],
    ['court', 3, 7, -1, '15:00:00', 1, 'no_show',   'online',  null],
    ['court', 0, 2,  0, '18:00:00', 2, 'approved',  'online',  null],
    ['court', 1, 5,  0, '19:00:00', 1, 'approved',  'walk_in', null],
    ['court', 2, 6,  1, '06:00:00', 2, 'approved',  'online',  null],
    ['court', 0, 1,  1, '17:00:00', 1, 'pending',   'online',  null],
    ['court', 3, 8,  2, '09:00:00', 2, 'pending',   'online',  null],
    ['court', 1, 10, 2, '16:00:00', 1, 'approved',  'phone',   null],
    ['court', 2, 11, 3, '18:00:00', 2, 'pending',   'online',  null],
    ['court', 0, 12, 4, '07:00:00', 1, 'approved',  'online',  null],
    ['court', 1, 13, 5, '17:00:00', 2, 'pending',   'online',  null],
    ['court', 3, 0,  6, '10:00:00', 1, 'cancelled', 'online',  null],
    ['court', 2, 4, -7, '19:00:00', 1, 'rejected',  'online',  null],

    ['function_hall', 0, 1,  3, '14:00:00', 4, 'approved',  'online',  'Birthday celebration'],
    ['function_hall', 1, 3,  6, '11:00:00', 3, 'pending',   'online',  'Team lunch'],
    ['function_hall', 0, 6, -5, '18:00:00', 4, 'completed', 'phone',   'Corporate meeting'],
    ['function_hall', 1, 9, 10, '15:00:00', 3, 'approved',  'walk_in', 'Family gathering'],
];

$created = [];
foreach ($plan as [$type, $fIdx, $pIdx, $offset, $start, $hours, $status, $source, $purpose]) {
    $facilityId = $type === 'court' ? $courtIds[$fIdx] : $hallIds[$fIdx];
    $date       = date('Y-m-d', strtotime(($offset >= 0 ? '+' : '') . $offset . ' days'));

    $id = $makeReservation($type, $facilityId, $pIdx, $date, $start, $hours, $status, $source, $purpose);

    if ($id !== null) {
        $created[] = ['id' => $id, 'status' => $status, 'player' => $playerIds[$pIdx]];
    }
}

echo "  reservations ......... " . count($created) . "\n";

// ---------------------------------------------------------------------------
// 10. Payments against completed and some approved reservations
// ---------------------------------------------------------------------------
$paySeq  = 0;
$methods = ['cash', 'cash', 'gcash', 'gcash', 'maya', 'bank_transfer'];
$payments = 0;

foreach ($created as $row) {
    if (!in_array($row['status'], ['completed', 'approved'], true)) {
        continue;
    }

    // Leave a few approved bookings unpaid, so the "due" state is visible.
    if ($row['status'] === 'approved' && random_int(1, 3) === 1) {
        continue;
    }

    $reservation = Database::selectOne(
        'SELECT total_amount, reservation_date FROM reservations WHERE id = :id',
        ['id' => $row['id']]
    );

    $method = $methods[array_rand($methods)];
    $paySeq++;
    $verified = $row['status'] === 'completed';

    Database::insert('payments', [
        'payment_code'   => sprintf('OR-%06d', $paySeq),
        'payable_type'   => 'reservation',
        'payable_id'     => $row['id'],
        'player_id'      => $row['player'],
        'amount_due'     => $reservation['total_amount'],
        'amount'         => $reservation['total_amount'],
        'payment_method' => $method,
        'reference_no'   => in_array($method, ['gcash', 'maya', 'bank_transfer'], true)
                            ? strtoupper(bin2hex(random_bytes(4)))
                            : null,
        'payment_date'   => $reservation['reservation_date'] . ' ' . sprintf('%02d:%02d:00', random_int(8, 20), random_int(0, 59)),
        'payment_status' => $verified ? 'verified' : 'paid',
        'recorded_by'    => $staffId,
        'verified_by'    => $verified ? $adminId : null,
        'verified_at'    => $verified ? $reservation['reservation_date'] . ' 21:00:00' : null,
    ]);

    $payments++;
}

echo "  payments ............. {$payments}\n";

// ---------------------------------------------------------------------------
// 11. Player matching pools (A11) and generated assignments (A12)
// ---------------------------------------------------------------------------
$pools = [
    // [dayOffset, block, start, end, levelCode, format, status]
    [-2, 'evening',   '18:00:00', '20:00:00', 'INT', 'doubles', 'generated'],
    [ 1, 'evening',   '18:00:00', '20:00:00', 'INT', 'doubles', 'open'],
    [ 2, 'morning',   '07:00:00', '09:00:00', 'BEG', 'doubles', 'open'],
    [ 3, 'afternoon', '16:00:00', '18:00:00', 'ADV', 'doubles', 'open'],
];

$poolSeq   = 0;
$poolIds   = [];
$generated = 0;

foreach ($pools as [$offset, $block, $start, $end, $levelCode, $format, $status]) {
    $poolSeq++;
    $poolIds[] = [
        'id' => Database::insert('matching_pools', [
            'pool_code'      => sprintf('MP-%05d', $poolSeq),
            'match_date'     => date('Y-m-d', strtotime(($offset >= 0 ? '+' : '') . $offset . ' days')),
            'time_block'     => $block,
            'start_time'     => $start,
            'end_time'       => $end,
            'skill_level_id' => $levelIds[$levelCode],
            'match_format'   => $format,
            'min_players'    => 4,
            'max_players'    => 16,
            'status'         => $status === 'generated' ? 'locked' : $status,
            'created_by'     => $adminId,
        ]),
        'level'  => $levelCode,
        'status' => $status,
    ];
}

// Register players into pools, matching their declared skill level.
foreach ($poolIds as $pool) {
    $eligible = Database::select(
        'SELECT id FROM players WHERE skill_level_id = :l AND status = :s',
        ['l' => $levelIds[$pool['level']], 's' => 'active']
    );

    foreach ($eligible as $player) {
        Database::insert('matching_requests', [
            'pool_id'       => $pool['id'],
            'player_id'     => $player['id'],
            'status'        => 'registered',
            'registered_at' => date('Y-m-d H:i:s', strtotime('-' . random_int(1, 5) . ' days')),
        ]);
    }
}

// Run the real generator on the historical pool, so the demo shows genuine
// randomly-drawn match-ups rather than hand-written ones.
foreach ($poolIds as $pool) {
    if ($pool['status'] !== 'generated') {
        continue;
    }

    try {
        $result = App\Models\Matching::generate($pool['id']);
        $generated += $result['matches'];
    } catch (RuntimeException $e) {
        echo "  (!) could not generate pool {$pool['id']}: {$e->getMessage()}\n";
    }
}

echo "  matching sessions .... " . count($poolIds) . " ({$generated} matches drawn)\n";

// ---------------------------------------------------------------------------
// 11b. ENHANCEMENT -- scores for matches that have already been played.
//      Outside the approved scope; skipped when the feature flag is off.
//      See docs/ENHANCEMENTS.md.
// ---------------------------------------------------------------------------
$scored = 0;

if (App\Models\MatchResult::enabled()) {
    $played = Database::select(
        "SELECT ma.id
           FROM match_assignments ma
           JOIN matching_pools mp ON mp.id = ma.pool_id
          WHERE mp.match_date < CURDATE()"
    );

    foreach ($played as $match) {
        // Realistic pickleball scores: first to 11, win by 2.
        $winner = random_int(11, 13);
        $loser  = $winner === 11 ? random_int(0, 9) : $winner - 2;

        $result = random_int(0, 1) === 1
            ? App\Models\MatchResult::record((int) $match['id'], $winner, $loser)
            : App\Models\MatchResult::record((int) $match['id'], $loser, $winner);

        if ($result['ok']) {
            $scored++;
        }
    }

    echo "  match results ........ {$scored} (enhancement layer)\n";
}

// ---------------------------------------------------------------------------
// 12. Events (Fig. 3.6 - 3.9)
// ---------------------------------------------------------------------------
$events = [
    ['Saturday Open Play',        'open_play',  2,  '08:00', '11:00', 16, 150.00, null,  'upcoming',
     'Casual rotating doubles. All levels welcome, paddles available to borrow.'],
    ['Beginner Clinic',           'clinic',     5,  '09:00', '11:00', 10, 500.00, 'BEG', 'upcoming',
     'Two-hour coached session covering the serve, the kitchen rule and basic strategy.'],
    ['Mango Drive Ladder Night',  'league',     7,  '18:00', '21:00', 16, 200.00, 'INT', 'upcoming',
     'Weekly ladder. Win your match and move up a rung.'],
    ['Community Social Play',     'social',    -6,  '16:00', '19:00', 20, 100.00, null,  'completed',
     'Monthly social with food from the cafe kitchen.'],
];

$eventSeq = 0;
$eventIds = [];

foreach ($events as [$title, $type, $offset, $start, $end, $capacity, $fee, $levelCode, $status, $desc]) {
    $date = date('Y-m-d', strtotime(($offset >= 0 ? '+' : '') . $offset . ' days'));
    $eventSeq++;

    $eventIds[] = Database::insert('events', [
        'event_code'     => sprintf('EV-%05d', $eventSeq),
        'title'          => $title,
        'event_type'     => $type,
        'description'    => $desc,
        'location'       => 'Mango Drive Cafe',
        'court_id'       => $courtIds[array_rand($courtIds)],
        'start_datetime' => $date . ' ' . $start . ':00',
        'end_datetime'   => $date . ' ' . $end . ':00',
        'capacity'       => $capacity,
        'fee'            => $fee,
        'skill_level_id' => $levelCode ? $levelIds[$levelCode] : null,
        'status'         => $status,
        'created_by'     => $adminId,
    ]);
}

// Participants, with a realistic mix of paid and unpaid.
$participants = 0;
foreach ($eventIds as $index => $eventId) {
    $event   = Database::selectOne('SELECT * FROM events WHERE id = :id', ['id' => $eventId]);
    $howMany = min((int) $event['capacity'], random_int(4, 9));
    $chosen  = (array) array_rand(array_flip($playerIds), $howMany);

    foreach ($chosen as $playerId) {
        $paid = random_int(1, 10) > 3;

        $participantId = Database::insert('event_participants', [
            'event_id'       => $eventId,
            'player_id'      => $playerId,
            'amount_due'     => $event['fee'],
            'payment_status' => $paid ? 'paid' : 'unpaid',
            'status'         => $event['status'] === 'completed' ? 'attended' : 'registered',
            'added_by'       => $staffId,
            'registered_at'  => date('Y-m-d H:i:s', strtotime($event['start_datetime'] . ' -' . random_int(1, 9) . ' days')),
        ]);

        if ($paid && (float) $event['fee'] > 0) {
            $paySeq++;
            Database::insert('payments', [
                'payment_code'   => sprintf('OR-%06d', $paySeq),
                'payable_type'   => 'event_participant',
                'payable_id'     => $participantId,
                'player_id'      => $playerId,
                'amount_due'     => $event['fee'],
                'amount'         => $event['fee'],
                'payment_method' => ['cash', 'gcash', 'maya'][random_int(0, 2)],
                'payment_date'   => date('Y-m-d H:i:s', strtotime($event['start_datetime'] . ' -' . random_int(0, 3) . ' days')),
                'payment_status' => $event['status'] === 'completed' ? 'verified' : 'paid',
                'recorded_by'    => $staffId,
                'verified_by'    => $event['status'] === 'completed' ? $adminId : null,
                'verified_at'    => $event['status'] === 'completed' ? $event['start_datetime'] : null,
            ]);
        }

        $participants++;
    }
}

echo "  events ............... " . count($eventIds) . " ({$participants} registrations)\n";

// ---------------------------------------------------------------------------
// 13. Notifications and a starter audit trail
// ---------------------------------------------------------------------------
$customerUsers = Database::select("SELECT id FROM users WHERE role = 'customer'");

foreach ($customerUsers as $user) {
    Database::insert('notifications', [
        'user_id' => $user['id'],
        'type'    => 'system',
        'title'   => 'Welcome to Mango Drive Pickleball',
        'message' => 'Your account is ready. Reserve a court, or sign up for player matching to get paired with players at your level.',
        'link'    => '/dashboard',
        'status'  => random_int(0, 1) ? 'read' : 'unread',
        'sent_by' => $adminId,
    ]);
}

Database::insert('notifications', [
    'user_id' => null,
    'type'    => 'announcement',
    'title'   => 'Court 4 closed for resurfacing',
    'message' => 'Court 4 will be closed for resurfacing on ' . date('F j', strtotime('+3 days')) . ' from 8 AM to 11 AM. Sorry for the inconvenience.',
    'status'  => 'unread',
    'sent_by' => $adminId,
]);

foreach ([
    ['auth.login',           'Signed in',                              null,          null],
    ['court.create',         'Added court Court 1 — Center',           'court',       $courtIds[0]],
    ['schedule.generate',    'Generated schedule slots for all courts','facility',    $courtIds[0]],
    ['player.walk_in',       'Registered walk-in PL-000009',           'player',      $playerIds[8] ?? null],
    ['reservation.approve',  'Approved a court reservation',           'reservation', $created[0]['id'] ?? null],
    ['matching.generate',    'Generated match assignments',            'matching_pool', $poolIds[0]['id'] ?? null],
    ['payment.verify',       'Verified a payment',                     'payment',     1],
] as [$action, $description, $entityType, $entityId]) {
    Database::insert('system_logs', [
        'user_id'     => $adminId,
        'action'      => $action,
        'entity_type' => $entityType,
        'entity_id'   => $entityId,
        'description' => $description,
        'ip_address'  => '127.0.0.1',
        'user_agent'  => 'Seeder',
        'created_at'  => date('Y-m-d H:i:s', strtotime('-' . random_int(1, 14) . ' days')),
    ]);
}

echo "  notifications ........ " . (count($customerUsers) + 1) . "\n";
echo "  system logs .......... 7\n";

// ---------------------------------------------------------------------------
echo "\nDone.\n\n";
echo "Demo logins (all on the same login page):\n";
echo "  Administrator   admin       / Admin@1234\n";
echo "  Staff           frontdesk   / Staff@1234\n";
echo "  Customer        marco.v     / Player@1234\n";
echo "                  liza.r      / Player@1234   (and 6 more, see database/seed.php)\n\n";
echo "Change these before deploying anywhere public.\n";
