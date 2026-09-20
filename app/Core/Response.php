<?php
namespace App\Core;

/**
 * Redirect and JSON helpers. Every method that sends output exits.
 */
final class Response
{
    /** Redirect to an app-relative path, e.g. redirect('/reservations'). */
    public static function redirect(string $path, int $status = 302): never
    {
        $base = rtrim(Config::get('app.base_url', ''), '/');
        $url  = str_starts_with($path, 'http') ? $path : $base . '/' . ltrim($path, '/');
        header('Location: ' . $url, true, $status);
        exit;
    }

    /** Redirect back to the referring page, or to a fallback. */
    public static function back(string $fallback = '/'): never
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        if ($referer !== '' && self::isSameHost($referer)) {
            header('Location: ' . $referer, true, 302);
            exit;
        }
        self::redirect($fallback);
    }

    /** Redirect with a flash message in one call. */
    public static function redirectWith(string $path, string $type, string $message): never
    {
        Session::flash($type, $message);
        self::redirect($path);
    }

    public static function json(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /** Stream a CSV download built from a header row and data rows. */
    public static function csv(string $filename, array $headers, array $rows): never
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');

        $out = fopen('php://output', 'wb');
        fwrite($out, "\xEF\xBB\xBF"); // BOM so Excel reads UTF-8 correctly
        fputcsv($out, $headers);
        foreach ($rows as $row) {
            fputcsv($out, is_array($row) ? array_values($row) : [$row]);
        }
        fclose($out);
        exit;
    }

    public static function abort(int $status, string $message = ''): never
    {
        http_response_code($status);
        $view = __DIR__ . '/../Views/errors/' . $status . '.php';
        if (is_file($view)) {
            $appName = Config::get('app.short_name');
            require $view;
        } else {
            echo '<h1>' . $status . '</h1><p>' . htmlspecialchars($message, ENT_QUOTES) . '</p>';
        }
        exit;
    }

    private static function isSameHost(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        return $host === null || $host === ($_SERVER['HTTP_HOST'] ?? '');
    }
}
