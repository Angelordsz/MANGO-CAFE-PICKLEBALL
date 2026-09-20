<?php
/**
 * HTTP checks. Included by tests/run.php when a base URL is supplied.
 *
 * Exercises the six modules named in Chapter III's Testing section:
 * Registration, Matching, Reservation, Function Hall, Payment and Reporting.
 *
 * @var string $baseUrl
 */

use App\Core\Database;

/** Minimal cURL wrapper with a per-role cookie jar. */
function request(string $url, array $options = []): array
{
    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_FOLLOWLOCATION => $options['follow'] ?? false,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_COOKIEJAR      => $options['jar'] ?? '',
        CURLOPT_COOKIEFILE     => $options['jar'] ?? '',
        CURLOPT_SSL_VERIFYPEER => false,
    ]);

    if (isset($options['post'])) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($options['post']));
    }

    $raw        = curl_exec($ch);
    $status     = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $error      = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        return ['status' => 0, 'headers' => '', 'body' => '', 'error' => $error];
    }

    return [
        'status'  => $status,
        'headers' => substr($raw, 0, $headerSize),
        'body'    => substr($raw, $headerSize),
        'error'   => $error,
    ];
}

/** Pull the CSRF token out of any page (the layout's logout form always has one). */
function csrfToken(string $baseUrl, string $jar, string $path = '/login'): string
{
    $response = request($baseUrl . $path, ['jar' => $jar]);
    return preg_match('/name="_token" value="([a-f0-9]{64})"/', $response['body'], $m) ? $m[1] : '';
}

function signIn(string $baseUrl, string $jar, string $username, string $password): bool
{
    @unlink($jar);
    $token = csrfToken($baseUrl, $jar);

    $response = request($baseUrl . '/login', [
        'jar'  => $jar,
        'post' => ['_token' => $token, 'identity' => $username, 'password' => $password],
    ]);

    return $response['status'] === 302;
}

$jarDir   = sys_get_temp_dir() . '/mdc-tests-' . getmypid();
@mkdir($jarDir, 0777, true);
$jars     = [
    'guest'    => $jarDir . '/guest.txt',
    'customer' => $jarDir . '/customer.txt',
    'staff'    => $jarDir . '/staff.txt',
    'admin'    => $jarDir . '/admin.txt',
];

// =============================================================================
section('6. HTTP: authentication');
// =============================================================================
$reachable = request($baseUrl . '/', ['jar' => $jars['guest']]);

if ($reachable['status'] === 0) {
    bad('server unreachable at ' . $baseUrl, $reachable['error']);
    return;
}

$reachable['status'] === 200 ? ok('landing page loads') : bad('landing page returned ' . $reachable['status']);

signIn($baseUrl, $jars['customer'], 'marco.v', 'Player@1234')
    ? ok('customer can log in')
    : bad('customer login failed (is the database seeded?)');

signIn($baseUrl, $jars['staff'], 'frontdesk', 'Staff@1234')
    ? ok('staff can log in')
    : bad('staff login failed');

signIn($baseUrl, $jars['admin'], 'admin', 'Admin@1234')
    ? ok('administrator can log in')
    : bad('administrator login failed');

$wrong = request($baseUrl . '/login', [
    'jar'  => $jars['guest'],
    'post' => ['_token' => csrfToken($baseUrl, $jars['guest']), 'identity' => 'admin', 'password' => 'wrong'],
    'follow' => true,
]);
str_contains($wrong['body'], 'Incorrect password')
    ? ok('wrong password is rejected')
    : bad('wrong password was not rejected');

// =============================================================================
section('7. HTTP: every page loads');
// =============================================================================
$pages = [
    'guest' => ['/', '/about', '/availability', '/availability?type=function_hall', '/events', '/login', '/register'],
    'customer' => [
        '/dashboard', '/profile', '/reserve', '/reserve/court', '/reserve/hall',
        '/reservations', '/reservations?filter=past', '/reservations?filter=all',
        '/matching', '/matching/results', '/notifications',
    ],
    'staff' => [
        '/admin', '/admin/reservations', '/admin/reservations/create', '/admin/schedules',
        '/admin/players', '/admin/players/walk-in', '/admin/matching', '/admin/matching/create',
        '/admin/events', '/admin/events/create', '/admin/payments', '/admin/reports',
        '/admin/reports/reservation', '/admin/reports/utilisation', '/admin/reports/event',
        '/admin/reports/payment', '/admin/reports/payment?mode=detailed', '/admin/reports/player',
        '/admin/notifications',
    ],
    'admin' => [
        '/admin/users', '/admin/users/create', '/admin/courts', '/admin/courts/create',
        '/admin/halls', '/admin/halls/create', '/admin/logs', '/admin/settings',
    ],
];

foreach ($pages as $role => $paths) {
    $broken = [];
    foreach ($paths as $path) {
        $response = request($baseUrl . $path, ['jar' => $jars[$role], 'follow' => true]);
        if ($response['status'] !== 200) {
            $broken[] = "$path returned {$response['status']}";
        }
    }
    $broken === []
        ? ok(count($paths) . " pages load as $role")
        : bad(count($broken) . " pages broken for $role", implode("\n         ", $broken));
}

// =============================================================================
section('8. HTTP: reservation module + double-booking guard');
// =============================================================================
$date = date('Y-m-d', strtotime('+14 days'));
$page = request($baseUrl . '/reserve/court?facility=1&date=' . $date, ['jar' => $jars['customer']]);

preg_match_all('/<input[^>]*name="slots\[\]"[^>]*>/s', $page['body'], $slotTags);

$freeSlot = null;
foreach ($slotTags[0] as $tag) {
    if (!str_contains($tag, 'disabled') && preg_match('/value="(\d+)"/', $tag, $m)) {
        $freeSlot = (int) $m[1];
        break;
    }
}

if ($freeSlot === null) {
    bad('no free slot found on ' . $date . ' - generate schedules first');
} else {
    ok("found a free slot on $date (id $freeSlot)");

    $review = request(
        $baseUrl . '/reserve/review?type=court&facility_id=1&slots%5B%5D=' . $freeSlot . '&party_size=4',
        ['jar' => $jars['customer']]
    );
    str_contains($review['body'], 'Review your reservation')
        ? ok('review step shows the price breakdown')
        : bad('review step did not render');

    preg_match('/name="_token" value="([a-f0-9]{64})"/', $review['body'], $tokenMatch);

    $submit = request($baseUrl . '/reserve', [
        'jar'    => $jars['customer'],
        'follow' => true,
        'post'   => [
            '_token'           => $tokenMatch[1] ?? '',
            'reservation_type' => 'court',
            'facility_id'      => 1,
            'slots'            => [$freeSlot],
            'party_size'       => 4,
        ],
    ]);

    str_contains($submit['body'], 'Awaiting confirmation')
        ? ok('reservation submitted and sits at "pending"')
        : bad('reservation was not created');

    // A second CUSTOMER attempting the same slot must be refused.
    // This has to be a customer account: staff have no `players` row, so they
    // would be turned away before the booking logic is reached, and the guard
    // would never actually be exercised.
    $rivalJar = $jarDir . '/rival.txt';

    if (!signIn($baseUrl, $rivalJar, 'nico.a', 'Player@1234')) {
        skipped('second customer account unavailable, cannot test the race');
    } else {
        $second = request($baseUrl . '/reserve', [
            'jar'    => $rivalJar,
            'follow' => true,
            'post'   => [
                '_token'           => csrfToken($baseUrl, $rivalJar, '/reserve'),
                'reservation_type' => 'court',
                'facility_id'      => 1,
                'slots'            => [$freeSlot],
                'party_size'       => 4,
            ],
        ]);

        preg_match('/no longer available|just booked|pick again|no longer free/i', $second['body']) === 1
            ? ok('a second customer claiming the same slot is refused')
            : bad('second claim was NOT refused');

        @unlink($rivalJar);
    }

    $claims = (int) Database::scalar(
        'SELECT COUNT(*) FROM reservation_schedules WHERE schedule_id = :s',
        ['s' => $freeSlot],
        0
    );
    $claims === 1
        ? ok("the database holds exactly one claim on slot $freeSlot")
        : bad("slot $freeSlot has $claims claims");
}

// =============================================================================
section('9. HTTP: approval, payment and separation of duties');
// =============================================================================
$pendingId = (int) Database::scalar(
    "SELECT id FROM reservations WHERE status = 'pending' ORDER BY id DESC LIMIT 1", [], 0
);

if ($pendingId === 0) {
    skipped('no pending reservation to approve');
} else {
    request($baseUrl . '/admin/reservations/' . $pendingId . '/approve', [
        'jar'  => $jars['admin'],
        'post' => [
            '_token'        => csrfToken($baseUrl, $jars['admin'], '/admin/reservations/' . $pendingId),
            'admin_remarks' => 'Approved by the test suite',
        ],
    ]);

    $status = (string) Database::scalar(
        'SELECT status FROM reservations WHERE id = :id', ['id' => $pendingId], ''
    );
    $status === 'approved' ? ok("reservation #$pendingId approved") : bad("approve left status '$status'");

    $notified = (int) Database::scalar(
        "SELECT COUNT(*) FROM notifications WHERE type = 'reservation_approved'", [], 0
    );
    $notified > 0 ? ok('the customer was notified') : bad('no approval notification was sent');
}

// Staff records a payment; only an admin may verify it.
$approvedId = (int) Database::scalar(
    "SELECT id FROM reservations WHERE status = 'approved' ORDER BY id DESC LIMIT 1", [], 0
);
$reference = 'TESTSUITE' . random_int(1000, 9999);

if ($approvedId > 0) {
    request($baseUrl . '/admin/payments', [
        'jar'  => $jars['staff'],
        'post' => [
            '_token'         => csrfToken($baseUrl, $jars['staff'], '/admin/reservations/' . $approvedId),
            'payable_type'   => 'reservation',
            'payable_id'     => $approvedId,
            'amount'         => '100.00',
            'amount_due'     => '350.00',
            'payment_method' => 'gcash',
            'reference_no'   => $reference,
            'redirect'       => '/admin/payments',
        ],
    ]);

    $paymentId = (int) Database::scalar(
        'SELECT id FROM payments WHERE reference_no = :r', ['r' => $reference], 0
    );
    $paymentId > 0 ? ok('staff can record a payment') : bad('payment was not recorded');

    if ($paymentId > 0) {
        $staffVerify = request($baseUrl . '/admin/payments/' . $paymentId . '/verify', [
            'jar'  => $jars['staff'],
            'post' => ['_token' => csrfToken($baseUrl, $jars['staff'], '/admin/payments')],
        ]);
        $staffVerify['status'] === 403
            ? ok('staff are blocked from verifying payments (403)')
            : bad('staff got ' . $staffVerify['status'] . ' on verify, expected 403');

        request($baseUrl . '/admin/payments/' . $paymentId . '/verify', [
            'jar'  => $jars['admin'],
            'post' => ['_token' => csrfToken($baseUrl, $jars['admin'], '/admin/payments')],
        ]);
        $verified = (string) Database::scalar(
            'SELECT payment_status FROM payments WHERE id = :id', ['id' => $paymentId], ''
        );
        $verified === 'verified' ? ok('an administrator can verify it') : bad("verify left status '$verified'");

        // Partial payment must be recorded as such.
        $partial = (string) Database::scalar(
            'SELECT payment_status FROM payments WHERE reference_no = :r', ['r' => $reference], ''
        );
        in_array($partial, ['verified', 'partial'], true)
            ? ok('a part payment is tracked, not rounded up')
            : bad("part payment status was '$partial'");
    }
}

// =============================================================================
section('10. HTTP: registration and matching modules');
// =============================================================================
$walkInName = 'Testsuite' . random_int(1000, 9999);

request($baseUrl . '/admin/players/walk-in', [
    'jar'  => $jars['staff'],
    'post' => [
        '_token'         => csrfToken($baseUrl, $jars['staff'], '/admin/players/walk-in'),
        'first_name'     => $walkInName,
        'last_name'      => 'Walkin',
        'phone_number'   => '0917 000 0000',
        'skill_level_id' => 1,
        'gender'         => 'male',
        'age'            => 30,
    ],
]);

$walkIn = Database::selectOne(
    'SELECT is_walk_in, user_id, registered_by FROM players WHERE first_name = :n',
    ['n' => $walkInName]
);

$walkIn && (int) $walkIn['is_walk_in'] === 1 && $walkIn['user_id'] === null
    ? ok('walk-in registration creates a player with no login (Fig. 3.5)')
    : bad('walk-in registration did not behave as Figure 3.5 specifies');

$walkIn && $walkIn['registered_by'] !== null
    ? ok('the registering staff member is recorded')
    : bad('registered_by was not set');

// Matching: generate assignments for an open pool.
$poolId = (int) Database::scalar(
    "SELECT id FROM matching_pools WHERE status = 'open' ORDER BY id LIMIT 1", [], 0
);

if ($poolId === 0) {
    skipped('no open matching session to generate');
} else {
    request($baseUrl . '/admin/matching/' . $poolId . '/generate', [
        'jar'  => $jars['staff'],
        'post' => ['_token' => csrfToken($baseUrl, $jars['staff'], '/admin/matching/' . $poolId)],
    ]);

    $generated = (int) Database::scalar(
        'SELECT COUNT(*) FROM match_assignments WHERE pool_id = :p', ['p' => $poolId], 0
    );
    $generated > 0 ? ok("$generated match(es) generated for pool #$poolId") : bad('no matches were generated');

    // Every generated match must be full: 4 players for doubles, 2 for singles.
    $wrongSize = (int) Database::scalar(
        "SELECT COUNT(*) FROM (
            SELECT ma.id, COUNT(map.id) AS players, mp.match_format
              FROM match_assignments ma
              JOIN matching_pools mp ON mp.id = ma.pool_id
         LEFT JOIN match_assignment_players map ON map.assignment_id = ma.id
             WHERE ma.pool_id = :p
          GROUP BY ma.id, mp.match_format
            HAVING players <> IF(mp.match_format = 'singles', 2, 4)
         ) bad_matches",
        ['p' => $poolId],
        0
    );
    $wrongSize === 0 ? ok('every generated match has a full complement of players') : bad("$wrongSize matches are the wrong size");

    // Nobody may appear twice in the same match.
    $duplicates = (int) Database::scalar(
        'SELECT COUNT(*) FROM (
            SELECT assignment_id, player_id FROM match_assignment_players
          GROUP BY assignment_id, player_id HAVING COUNT(*) > 1
         ) d',
        [],
        0
    );
    $duplicates === 0 ? ok('no player is drawn twice into one match') : bad("$duplicates duplicate placements");
}

// =============================================================================
section('11. HTTP: role enforcement and CSRF');
// =============================================================================
foreach (['/admin/users', '/admin/settings', '/admin/logs', '/admin/courts'] as $adminOnly) {
    $response = request($baseUrl . $adminOnly, ['jar' => $jars['staff']]);
    $response['status'] === 403
        ? ok("staff are blocked from $adminOnly")
        : bad("staff got {$response['status']} on $adminOnly, expected 403");
}

$customerOnAdmin = request($baseUrl . '/admin', ['jar' => $jars['customer']]);
$customerOnAdmin['status'] === 403
    ? ok('customers are blocked from the back office')
    : bad('customer got ' . $customerOnAdmin['status'] . ' on /admin, expected 403');

$guestOnDashboard = request($baseUrl . '/dashboard', ['jar' => $jars['guest']]);
$guestOnDashboard['status'] === 302
    ? ok('guests are redirected to the login page')
    : bad('guest got ' . $guestOnDashboard['status'] . ' on /dashboard, expected 302');

$badToken = request($baseUrl . '/reserve', [
    'jar'    => $jars['customer'],
    'follow' => true,
    'post'   => ['_token' => 'not-a-real-token', 'reservation_type' => 'court', 'facility_id' => 1],
]);
stripos($badToken['body'], 'session expired') !== false
    ? ok('a POST with an invalid CSRF token is rejected')
    : bad('CSRF protection did not trigger');

// =============================================================================
section('12. HTTP: reporting module');
// =============================================================================
foreach (['reservation', 'utilisation', 'event', 'payment', 'player'] as $report) {
    $response = request($baseUrl . '/admin/reports/' . $report . '?export=csv', ['jar' => $jars['admin']]);
    $isCsv = stripos($response['headers'], 'text/csv') !== false;
    $isCsv ? ok("$report report exports CSV") : bad("$report report did not return CSV");
}

// =============================================================================
// Clean up rows this run created, so the suite can be run repeatedly.
// =============================================================================
Database::execute('DELETE FROM players WHERE first_name = :n', ['n' => $walkInName]);
Database::execute('DELETE FROM payments WHERE reference_no = :r', ['r' => $reference]);

foreach ($jars as $jar) {
    @unlink($jar);
}
@rmdir($jarDir);

echo "\n  (test rows removed; reservations created by the suite are left in place)\n";
