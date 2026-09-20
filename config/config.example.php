<?php
/**
 * Copy this file to config/config.php and edit for your machine.
 * config/config.php is git-ignored so local credentials never get committed.
 */
return [
    // ---- Database (XAMPP defaults) ------------------------------------
    'db' => [
        'host'     => '127.0.0.1',
        'port'     => 3306,
        'database' => 'pickleball_mdc',
        'username' => 'root',
        'password' => '',
        'charset'  => 'utf8mb4',
    ],

    // ---- Application ---------------------------------------------------
    'app' => [
        'name'      => 'Mango Drive Cafe Pickleball',
        'short_name'=> 'MDC Pickleball',
        // 'auto' detects the sub-directory the app is served from, so it works
        // under XAMPP (htdocs/anything/public), at a web root, or behind
        // `php -S`. Override with an explicit path like '/pickleball/public'.
        'base_url'  => 'auto',
        'timezone'  => 'Asia/Manila',
        'currency'  => 'PHP',
        'locale'    => 'en_PH',
        'debug'     => true,
    ],

    // ---- Business rules ------------------------------------------------
    'booking' => [
        'slot_minutes'          => 60,
        'max_advance_days'      => 30,
        'min_advance_hours'     => 2,
        'cancel_cutoff_hours'   => 12,
        'max_open_reservations' => 5,
    ],

    // ---- Feature flags ---------------------------------------------------
    // Everything under 'enhancements' is OUTSIDE the approved capstone scope.
    // Set to false for a strictly document-faithful demo.
    'features' => [
        'enhancements'   => true,
        'match_results'  => true,
        'player_stats'   => true,
        'dark_mode'      => true,
    ],
];
