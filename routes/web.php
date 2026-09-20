<?php
/**
 * Route table.
 *
 * The comment above each group cites the use case it implements, so the file
 * doubles as a checklist against Figures 3.2 and 3.3. See docs/TRACEABILITY.md.
 */

use App\Core\Router;
use App\Controllers\AuthController;
use App\Controllers\AvailabilityController;
use App\Controllers\DashboardController;
use App\Controllers\EventController;
use App\Controllers\HomeController;
use App\Controllers\MatchingController;
use App\Controllers\NotificationController;
use App\Controllers\ProfileController;
use App\Controllers\ReservationController;
use App\Controllers\Admin;

$router = new Router();

// ---------------------------------------------------------------------------
// Public
// ---------------------------------------------------------------------------
$router->get('/',             [HomeController::class, 'index']);
$router->get('/about',        [HomeController::class, 'about']);

// C4 / A-shared: View Facility Schedules and Availability
$router->get('/availability', [AvailabilityController::class, 'index']);
$router->get('/availability/slots', [AvailabilityController::class, 'slots']); // JSON for the calendar

// Public event listing (Fig. 3.6 output, customer-facing)
$router->get('/events',       [EventController::class, 'index']);
$router->get('/events/{id}',  [EventController::class, 'show']);

// ---------------------------------------------------------------------------
// C1, C2 - Register / Create Account, Login
// ---------------------------------------------------------------------------
$router->get('/register',  [AuthController::class, 'showRegister'], ['guest']);
$router->post('/register', [AuthController::class, 'register'],     ['guest']);
$router->get('/login',     [AuthController::class, 'showLogin'],    ['guest']);
$router->post('/login',    [AuthController::class, 'login'],        ['guest']);
$router->post('/logout',   [AuthController::class, 'logout'],       ['auth']);

$router->get('/forgot-password',  [AuthController::class, 'showForgot'], ['guest']);
$router->post('/forgot-password', [AuthController::class, 'sendReset'],  ['guest']);
$router->get('/reset-password/{token}',  [AuthController::class, 'showReset'],  ['guest']);
$router->post('/reset-password/{token}', [AuthController::class, 'resetPassword'], ['guest']);

// ---------------------------------------------------------------------------
// Customer / Player area
// ---------------------------------------------------------------------------
$router->get('/dashboard', [DashboardController::class, 'index'], ['customer']);

// C3 - Manage Profile
$router->get('/profile',          [ProfileController::class, 'edit'],           ['customer']);
$router->post('/profile',         [ProfileController::class, 'update'],         ['customer']);
$router->post('/profile/password',[ProfileController::class, 'updatePassword'], ['customer']);
$router->post('/profile/avatar',  [ProfileController::class, 'updateAvatar'],   ['customer']);

// C5, C6, C7 - Reserve Court / Function Hall, Submit Reservation Request
$router->get('/reserve',        [ReservationController::class, 'chooseType'], ['customer']);
$router->get('/reserve/court',  [ReservationController::class, 'court'],      ['customer']);
$router->get('/reserve/hall',   [ReservationController::class, 'hall'],       ['customer']);
$router->get('/reserve/slots',  [ReservationController::class, 'slots'],      ['customer']); // JSON
$router->get('/reserve/review', [ReservationController::class, 'review'],     ['customer']);
$router->post('/reserve',       [ReservationController::class, 'store'],      ['customer']);

// C13, C8 - View My Reservations, Cancel / Reschedule
$router->get('/reservations',                    [ReservationController::class, 'index'],      ['customer']);
$router->get('/reservations/{code}',             [ReservationController::class, 'show'],       ['customer']);
$router->post('/reservations/{id}/cancel',       [ReservationController::class, 'cancel'],     ['customer']);
$router->get('/reservations/{id}/reschedule',    [ReservationController::class, 'rescheduleForm'], ['customer']);
$router->post('/reservations/{id}/reschedule',   [ReservationController::class, 'reschedule'], ['customer']);

// C9, C10, C11, C12 - Player Matching
$router->get('/matching',              [MatchingController::class, 'index'],    ['customer']);
$router->post('/matching/register',    [MatchingController::class, 'register'], ['customer']);
$router->post('/matching/{id}/cancel', [MatchingController::class, 'cancel'],   ['customer']);
$router->get('/matching/results',      [MatchingController::class, 'results'],  ['customer']);

// C14 - Receive Notifications
$router->get('/notifications',              [NotificationController::class, 'index'],   ['customer']);
$router->post('/notifications/{id}/read',   [NotificationController::class, 'read'],    ['customer']);
$router->post('/notifications/read-all',    [NotificationController::class, 'readAll'], ['customer']);

// ---------------------------------------------------------------------------
// Admin / Staff back office
// ---------------------------------------------------------------------------

// A2 - Dashboard Overview
$router->get('/admin', [Admin\DashboardController::class, 'index'], ['staff']);

// A3 - Manage Users / Customers   (admin only)
$router->get('/admin/users',                 [Admin\UserController::class, 'index'],  ['admin']);
$router->get('/admin/users/create',          [Admin\UserController::class, 'create'], ['admin']);
$router->post('/admin/users',                [Admin\UserController::class, 'store'],  ['admin']);
$router->get('/admin/users/{id}/edit',       [Admin\UserController::class, 'edit'],   ['admin']);
$router->post('/admin/users/{id}',           [Admin\UserController::class, 'update'], ['admin']);
$router->post('/admin/users/{id}/status',    [Admin\UserController::class, 'toggleStatus'], ['admin']);
$router->post('/admin/users/{id}/reset',     [Admin\UserController::class, 'resetPassword'], ['admin']);

// A4 - Manage Player Records  + Fig. 3.5 / 3.12 Walk-in Registration
$router->get('/admin/players',               [Admin\PlayerController::class, 'index'],       ['staff']);
$router->get('/admin/players/walk-in',       [Admin\PlayerController::class, 'walkInForm'],  ['staff']);
$router->post('/admin/players/walk-in',      [Admin\PlayerController::class, 'walkInStore'], ['staff']);
$router->get('/admin/players/search',        [Admin\PlayerController::class, 'search'],      ['staff']); // JSON
$router->get('/admin/players/{id}',          [Admin\PlayerController::class, 'show'],        ['staff']);
$router->get('/admin/players/{id}/edit',     [Admin\PlayerController::class, 'edit'],        ['staff']);
$router->post('/admin/players/{id}',         [Admin\PlayerController::class, 'update'],      ['staff']);
$router->post('/admin/players/{id}/status',  [Admin\PlayerController::class, 'toggleStatus'],['admin']);

// A5 - Manage Courts   (admin only)
$router->get('/admin/courts',            [Admin\CourtController::class, 'index'],  ['admin']);
$router->get('/admin/courts/create',     [Admin\CourtController::class, 'create'], ['admin']);
$router->post('/admin/courts',           [Admin\CourtController::class, 'store'],  ['admin']);
$router->get('/admin/courts/{id}/edit',  [Admin\CourtController::class, 'edit'],   ['admin']);
$router->post('/admin/courts/{id}',      [Admin\CourtController::class, 'update'], ['admin']);
$router->post('/admin/courts/{id}/delete',[Admin\CourtController::class, 'destroy'],['admin']);

// A6 - Manage Function Hall   (admin only)
$router->get('/admin/halls',             [Admin\HallController::class, 'index'],  ['admin']);
$router->get('/admin/halls/create',      [Admin\HallController::class, 'create'], ['admin']);
$router->post('/admin/halls',            [Admin\HallController::class, 'store'],  ['admin']);
$router->get('/admin/halls/{id}/edit',   [Admin\HallController::class, 'edit'],   ['admin']);
$router->post('/admin/halls/{id}',       [Admin\HallController::class, 'update'], ['admin']);
$router->post('/admin/halls/{id}/delete',[Admin\HallController::class, 'destroy'],['admin']);

// A7 - Manage Facility Schedules
$router->get('/admin/schedules',            [Admin\ScheduleController::class, 'index'],    ['staff']);
$router->post('/admin/schedules/generate',  [Admin\ScheduleController::class, 'generate'], ['staff']);
$router->post('/admin/schedules/block',     [Admin\ScheduleController::class, 'block'],    ['staff']);
$router->post('/admin/schedules/{id}/unblock',[Admin\ScheduleController::class, 'unblock'],['staff']);

// A8, A9, A10 - Manage / Approve / Reject / Cancel / Reschedule Reservations
$router->get('/admin/reservations',                  [Admin\ReservationController::class, 'index'],   ['staff']);
$router->get('/admin/reservations/create',           [Admin\ReservationController::class, 'create'],  ['staff']); // walk-in booking
$router->post('/admin/reservations',                 [Admin\ReservationController::class, 'store'],   ['staff']);
$router->get('/admin/reservations/{id}',             [Admin\ReservationController::class, 'show'],    ['staff']);
$router->post('/admin/reservations/{id}/approve',    [Admin\ReservationController::class, 'approve'], ['staff']);
$router->post('/admin/reservations/{id}/reject',     [Admin\ReservationController::class, 'reject'],  ['staff']);
$router->post('/admin/reservations/{id}/cancel',     [Admin\ReservationController::class, 'cancel'],  ['staff']);
$router->post('/admin/reservations/{id}/complete',   [Admin\ReservationController::class, 'complete'],['staff']);
$router->get('/admin/reservations/{id}/reschedule',  [Admin\ReservationController::class, 'rescheduleForm'], ['staff']);
$router->post('/admin/reservations/{id}/reschedule', [Admin\ReservationController::class, 'reschedule'],     ['staff']);

// A11, A12 - Manage Player Matching, Generate Match Assignments
$router->get('/admin/matching',                 [Admin\MatchingController::class, 'index'],    ['staff']);
$router->get('/admin/matching/create',          [Admin\MatchingController::class, 'create'],   ['staff']);
$router->post('/admin/matching',                [Admin\MatchingController::class, 'store'],    ['staff']);
$router->get('/admin/matching/{id}',            [Admin\MatchingController::class, 'show'],     ['staff']);
$router->post('/admin/matching/{id}/add',       [Admin\MatchingController::class, 'addPlayer'],['staff']);
$router->post('/admin/matching/{id}/remove',    [Admin\MatchingController::class, 'removePlayer'], ['staff']);
$router->post('/admin/matching/{id}/lock',      [Admin\MatchingController::class, 'lock'],     ['staff']);
$router->post('/admin/matching/{id}/generate',  [Admin\MatchingController::class, 'generate'], ['staff']);
$router->post('/admin/matching/{id}/cancel',    [Admin\MatchingController::class, 'cancel'],   ['staff']);

// ENHANCEMENT (outside the approved scope) -- match score entry.
// Guarded by features.match_results in config/config.php; see docs/ENHANCEMENTS.md.
$router->post('/admin/matching/{id}/matches/{assignment}/result',
    [Admin\MatchingController::class, 'recordResult'], ['staff']);

// Fig. 3.6 - 3.9 - Event management
$router->get('/admin/events',                [Admin\EventController::class, 'index'],  ['staff']);
$router->get('/admin/events/create',         [Admin\EventController::class, 'create'], ['staff']);
$router->post('/admin/events',               [Admin\EventController::class, 'store'],  ['staff']);
$router->get('/admin/events/{id}',           [Admin\EventController::class, 'show'],   ['staff']);
$router->get('/admin/events/{id}/edit',      [Admin\EventController::class, 'edit'],   ['staff']);
$router->post('/admin/events/{id}',          [Admin\EventController::class, 'update'], ['staff']);
$router->post('/admin/events/{id}/cancel',   [Admin\EventController::class, 'cancel'], ['staff']);
$router->post('/admin/events/{id}/participants',              [Admin\EventController::class, 'addParticipant'],    ['staff']);
$router->post('/admin/events/{id}/participants/{pid}/remove', [Admin\EventController::class, 'removeParticipant'], ['staff']);
$router->post('/admin/events/{id}/participants/{pid}/payment',[Admin\EventController::class, 'recordPayment'],     ['staff']);

// Payments (record + verify)
$router->get('/admin/payments',              [Admin\PaymentController::class, 'index'],  ['staff']);
$router->post('/admin/payments',             [Admin\PaymentController::class, 'store'],  ['staff']);
$router->post('/admin/payments/{id}/verify', [Admin\PaymentController::class, 'verify'], ['admin']);
$router->post('/admin/payments/{id}/void',   [Admin\PaymentController::class, 'void'],   ['admin']);
$router->get('/admin/payments/{id}/receipt', [Admin\PaymentController::class, 'receipt'],['staff']);

// A13 - Send Notifications
$router->get('/admin/notifications',  [Admin\NotificationController::class, 'index'], ['staff']);
$router->post('/admin/notifications', [Admin\NotificationController::class, 'send'],  ['staff']);

// A14 - Generate Reports  (Fig. 3.9, 3.10)
$router->get('/admin/reports',              [Admin\ReportController::class, 'index'],       ['staff']);
$router->get('/admin/reports/reservation',  [Admin\ReportController::class, 'reservation'], ['staff']);
$router->get('/admin/reports/utilisation',  [Admin\ReportController::class, 'utilisation'], ['staff']);
$router->get('/admin/reports/event',        [Admin\ReportController::class, 'event'],       ['staff']);
$router->get('/admin/reports/payment',      [Admin\ReportController::class, 'payment'],     ['staff']);
$router->get('/admin/reports/player',       [Admin\ReportController::class, 'player'],      ['staff']);

// A15 - View System Logs   (admin only)
$router->get('/admin/logs', [Admin\LogController::class, 'index'], ['admin']);

// Platform settings   (admin only)
$router->get('/admin/settings',  [Admin\SettingController::class, 'index'],  ['admin']);
$router->post('/admin/settings', [Admin\SettingController::class, 'update'], ['admin']);

return $router;
