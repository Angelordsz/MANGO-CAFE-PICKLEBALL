<?php
/**
 * Test runner for the Mango Drive Café pickleball application.
 *
 *   php tests/run.php                      -- static checks only
 *   php tests/run.php http://localhost/pickleball/public
 *   php tests/run.php http://127.0.0.1:8000
 *
 * Pass a base URL to also run the page-load and functional checks against a
 * running server. Without one, only the static checks run.
 *
 * The functional checks write to the database, so point this at a development
 * database seeded with `php database/seed.php`, never at production data.
 */

require dirname(__DIR__) . '/bootstrap.php';

use App\Core\Database;

if (PHP_SAPI !== 'cli') {
    exit("Run this from the command line.\n");
}

$baseUrl = $argv[1] ?? null;
$baseUrl = $baseUrl ? rtrim($baseUrl, '/') : null;

$pass = 0;
$fail = 0;
$skip = 0;

function ok(string $label): void    { global $pass; $pass++; echo "  \e[32mPASS\e[0m  $label\n"; }
function bad(string $label, string $detail = ''): void {
    global $fail; $fail++;
    echo "  \e[31mFAIL\e[0m  $label\n";
    if ($detail !== '') { echo "         $detail\n"; }
}
function skipped(string $label): void { global $skip; $skip++; echo "  \e[33mSKIP\e[0m  $label\n"; }
function section(string $title): void { echo "\n\e[1m$title\e[0m\n"; }

// =============================================================================
section('1. Static: PHP syntax');
// =============================================================================
$phpBinary = PHP_BINARY;
$files = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(BASE_PATH, FilesystemIterator::SKIP_DOTS)
);
foreach ($iterator as $file) {
    if ($file->getExtension() === 'php' && !str_contains($file->getPathname(), 'node_modules')) {
        $files[] = $file->getPathname();
    }
}

$syntaxErrors = 0;
foreach ($files as $file) {
    exec(escapeshellarg($phpBinary) . ' -l ' . escapeshellarg($file) . ' 2>&1', $out, $code);
    if ($code !== 0) {
        $syntaxErrors++;
        echo "         " . $file . ': ' . implode(' ', $out) . "\n";
    }
    $out = [];
}

$syntaxErrors === 0
    ? ok(count($files) . ' PHP files parse cleanly')
    : bad("$syntaxErrors of " . count($files) . ' files have syntax errors');

// =============================================================================
section('2. Static: every route resolves to a controller method');
// =============================================================================
$routeSource = file_get_contents(BASE_PATH . '/routes/web.php');
preg_match_all(
    '/\$router->(get|post|any)\(\s*\'([^\']+)\'\s*,\s*\[([A-Za-z_\\\\]+)::class\s*,\s*\'([^\']+)\'\]/',
    $routeSource,
    $matches,
    PREG_SET_ORDER
);

$routeProblems = [];
foreach ($matches as [$_, $verb, $path, $class, $method]) {
    $relative = str_replace('\\', '/', $class);
    $file     = BASE_PATH . '/app/Controllers/' . $relative . '.php';

    if (!is_file($file)) {
        $routeProblems[] = "$verb $path -> missing controller $class";
        continue;
    }
    if (!preg_match('/function\s+' . preg_quote($method, '/') . '\s*\(/', file_get_contents($file))) {
        $routeProblems[] = "$verb $path -> $class::$method() not defined";
    }
}

$routeProblems === []
    ? ok(count($matches) . ' routes all resolve')
    : bad(count($routeProblems) . ' broken routes', implode("\n         ", $routeProblems));

// =============================================================================
section('3. Static: every referenced view file exists');
// =============================================================================
$viewProblems = [];
foreach ($files as $file) {
    preg_match_all(
        '/(?:\$this->view|View::render|View::capture|View::partial)\(\s*\'([a-z0-9_.]+)\'/',
        file_get_contents($file),
        $viewMatches
    );
    foreach ($viewMatches[1] as $view) {
        $viewFile = BASE_PATH . '/app/Views/' . str_replace('.', '/', $view) . '.php';
        if (!is_file($viewFile)) {
            $viewProblems[] = "$view (referenced in " . basename($file) . ')';
        }
    }
}

$viewProblems === []
    ? ok('all referenced views exist')
    : bad(count($viewProblems) . ' missing views', implode("\n         ", $viewProblems));

// =============================================================================
section('4. Static: no SQL statement reuses a named parameter');
// =============================================================================
// MySQL rejects a repeated :name when PDO emulation is off, so each occurrence
// needs its own placeholder. This caught a real bug during development.
$dupeProblems = [];
foreach ($files as $file) {
    $source = file_get_contents($file);
    preg_match_all('/([\'"])((?:(?!\1).)*)\1/s', $source, $literals, PREG_SET_ORDER);

    foreach ($literals as $literal) {
        $sql = $literal[2];
        if (!preg_match('/\b(SELECT|INSERT|UPDATE|DELETE)\b/i', $sql)) {
            continue;
        }
        preg_match_all('/(?<![:\w]):([a-zA-Z_][a-zA-Z0-9_]*)/', $sql, $params);
        $counts = array_count_values($params[1]);
        foreach ($counts as $name => $count) {
            if ($count > 1) {
                $dupeProblems[] = basename($file) . ": :$name used {$count}x";
            }
        }
    }
}

$dupeProblems === []
    ? ok('no repeated named parameters')
    : bad(count($dupeProblems) . ' statements reuse a parameter', implode("\n         ", array_unique($dupeProblems)));

// =============================================================================
section('5. Database: schema and seed data');
// =============================================================================
try {
    $tables = Database::select('SHOW TABLES');
    $count  = count($tables);
    $count >= 23 ? ok("$count tables present") : bad("only $count tables (expected 23+)");

    // The constraint that makes double booking impossible.
    $index = Database::selectOne(
        "SHOW INDEX FROM reservation_schedules WHERE Key_name = 'uq_slot_taken'"
    );
    $index && (int) $index['Non_unique'] === 0
        ? ok('reservation_schedules.schedule_id is UNIQUE (double-booking guard)')
        : bad('the unique index guarding against double booking is missing');

    foreach (['users' => 1, 'players' => 1, 'courts' => 1, 'schedules' => 1] as $table => $minimum) {
        $rows = (int) Database::scalar("SELECT COUNT(*) FROM `$table`", [], 0);
        $rows >= $minimum
            ? ok("$table has $rows rows")
            : bad("$table is empty - run: php database/seed.php");
    }

    // Integrity: no slot may ever be claimed twice.
    $doubles = (int) Database::scalar(
        'SELECT COUNT(*) FROM (SELECT schedule_id FROM reservation_schedules
           GROUP BY schedule_id HAVING COUNT(*) > 1) d',
        [],
        0
    );
    $doubles === 0 ? ok('no slot is claimed by two reservations') : bad("$doubles slots double-claimed");

    // Walk-in players must have no login.
    $badWalkIns = (int) Database::scalar(
        'SELECT COUNT(*) FROM players WHERE is_walk_in = 1 AND user_id IS NOT NULL', [], 0
    );
    $badWalkIns === 0 ? ok('walk-in players have no user account') : bad("$badWalkIns walk-ins have a login");

    // Payments never exceed what was owed.
    $overpaid = (int) Database::scalar(
        "SELECT COUNT(*) FROM payments WHERE amount > amount_due AND amount_due > 0", [], 0
    );
    $overpaid === 0 ? ok('no payment exceeds the amount due') : bad("$overpaid payments exceed the amount due");

} catch (Throwable $e) {
    bad('database checks could not run', $e->getMessage());
}

// =============================================================================
// HTTP checks -- only when a base URL was supplied
// =============================================================================
if ($baseUrl === null) {
    section('6-8. HTTP checks');
    skipped('no base URL given - pass one, e.g. php tests/run.php http://localhost/pickleball/public');
} elseif (!extension_loaded('curl')) {
    section('6-8. HTTP checks');
    skipped('the curl extension is not enabled');
} else {
    require __DIR__ . '/http_checks.php';
}

// =============================================================================
echo "\n" . str_repeat('=', 52) . "\n";
printf("  passed: %d   failed: %d   skipped: %d\n", $pass, $fail, $skip);
echo str_repeat('=', 52) . "\n";

exit($fail > 0 ? 1 : 0);
