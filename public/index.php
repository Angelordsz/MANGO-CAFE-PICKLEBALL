<?php
/**
 * Front controller. Every request enters here.
 *
 * Online Pickleball Player Matching and Function Hall and Court Reservation
 * Management Application -- Mango Drive Cafe.
 */

/**
 * The web-accessible directory. Defined here rather than assumed, because on
 * shared hosting the application files usually live one level ABOVE the
 * document root (e.g. InfinityFree, where this file becomes htdocs/index.php
 * and the rest of the app sits beside htdocs, out of reach of the web).
 */
define('PUBLIC_PATH', __DIR__);

require dirname(__DIR__) . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;

// Values every view can rely on.
View::share('appName',      Config::get('app.name'));
View::share('appShortName', Config::get('app.short_name'));
View::share('currentUser',  Auth::user());
View::share('currentPlayer',Auth::player());

/** @var App\Core\Router $router */
$router = require BASE_PATH . '/routes/web.php';

$request = new Request();

if (!$router->dispatch($request)) {
    Response::abort(404, 'The page you are looking for does not exist.');
}
