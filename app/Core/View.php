<?php
namespace App\Core;

use RuntimeException;

/**
 * Plain-PHP templating: render a view inside a layout.
 *
 * Views are .php files under app/Views. A view may set $title and then its
 * output is captured into $content, which the layout echoes.
 */
final class View
{
    private static array $shared = [];

    /** Data made available to every view (current user, flash messages...). */
    public static function share(string $key, mixed $value): void
    {
        self::$shared[$key] = $value;
    }

    public static function render(string $view, array $data = [], string $layout = 'app'): void
    {
        echo self::capture($view, $data, $layout);
    }

    public static function capture(string $view, array $data = [], ?string $layout = 'app'): string
    {
        $path = self::path($view);

        $data = array_merge(self::$shared, [
            'flashes' => Session::takeFlashes(),
            'errors'  => Session::takeErrors(),
            'old'     => Session::takeOld(),
        ], $data);

        $content = self::evaluate($path, $data);

        if ($layout === null) {
            return $content;
        }

        $layoutPath = self::path('layouts/' . $layout);
        return self::evaluate($layoutPath, array_merge($data, [
            'content' => $content,
            'title'   => $data['title'] ?? Config::get('app.short_name'),
        ]));
    }

    /** Render a partial inline (no layout), for use inside other views. */
    public static function partial(string $view, array $data = []): string
    {
        return self::evaluate(self::path($view), array_merge(self::$shared, $data));
    }

    private static function evaluate(string $path, array $data): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            require $path;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }

    private static function path(string $view): string
    {
        $path = dirname(__DIR__) . '/Views/' . str_replace('.', '/', $view) . '.php';
        if (!is_file($path)) {
            throw new RuntimeException("View not found: {$view} (looked in {$path})");
        }
        return $path;
    }
}
