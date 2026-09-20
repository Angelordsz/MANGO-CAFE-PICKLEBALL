<?php
/**
 * Application bootstrap: autoloader, config, session, error handling.
 * Required by public/index.php and by the CLI scripts in database/.
 */

define('BASE_PATH', __DIR__);

// index.php defines this before requiring us. CLI scripts (seeder, tests)
// do not, so fall back to the in-project location.
if (!defined('PUBLIC_PATH')) {
    define('PUBLIC_PATH', BASE_PATH . '/public');
}

// ---- PSR-4 style autoloader: App\Core\Database -> app/Core/Database.php ----
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file     = BASE_PATH . '/app/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

use App\Core\Config;
use App\Core\Session;
use App\Core\Logger;

// ---- Configuration --------------------------------------------------------
Config::load(BASE_PATH . '/config/config.php');

date_default_timezone_set(Config::get('app.timezone', 'Asia/Manila'));
setlocale(LC_ALL, Config::get('app.locale', 'en_PH'));

/**
 * Resolve the base URL.
 *
 * With base_url set to 'auto' (the default) the app works unchanged whether it
 * sits at htdocs/pickleball/public under XAMPP, at the web root, or behind
 * PHP's built-in server. Set an explicit path in config.php to override.
 */
if (Config::get('app.base_url') === 'auto') {
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $base       = $scriptName !== '' ? str_replace('\\', '/', dirname($scriptName)) : '';

    Config::set('app.base_url', $base === '/' || $base === '.' ? '' : rtrim($base, '/'));
}

// ---- Error handling -------------------------------------------------------
$debug = (bool) Config::get('app.debug', false);

error_reporting(E_ALL);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');

set_exception_handler(static function (\Throwable $e) use ($debug): void {
    Logger::error($e->getMessage(), [
        'file'  => $e->getFile(),
        'line'  => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);

    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, 'Error: ' . $e->getMessage() . PHP_EOL . $e->getTraceAsString() . PHP_EOL);
        exit(1);
    }

    http_response_code(500);

    if ($debug) {
        echo '<div style="font-family:ui-monospace,monospace;padding:24px;line-height:1.6">';
        echo '<h1 style="color:#dc2626">' . get_class($e) . '</h1>';
        echo '<p style="font-size:16px"><strong>' . htmlspecialchars($e->getMessage(), ENT_QUOTES) . '</strong></p>';
        echo '<p style="color:#666">' . htmlspecialchars($e->getFile(), ENT_QUOTES) . ' : ' . $e->getLine() . '</p>';
        echo '<pre style="background:#f4f4f5;padding:16px;overflow:auto;font-size:12px">'
             . htmlspecialchars($e->getTraceAsString(), ENT_QUOTES) . '</pre>';
        echo '</div>';
    } else {
        $view = BASE_PATH . '/app/Views/errors/500.php';
        if (is_file($view)) {
            require $view;
        } else {
            echo '<h1>Something went wrong</h1><p>Please try again, or contact the administrator.</p>';
        }
    }
    exit;
});

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new \ErrorException($message, 0, $severity, $file, $line);
});

// ---- Helpers and session --------------------------------------------------
require BASE_PATH . '/app/Helpers/functions.php';
require BASE_PATH . '/app/Helpers/icons.php';

if (PHP_SAPI !== 'cli') {
    Session::start();
}
