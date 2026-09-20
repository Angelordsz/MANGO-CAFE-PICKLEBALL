<?php
namespace App\Core;

/**
 * Route table with {param} placeholders and per-route middleware.
 *
 *   $router->get('/reservations/{id}', [ReservationController::class, 'show'], ['auth']);
 *
 * Middleware names are resolved in dispatch(): 'auth', 'guest', 'admin', 'staff'.
 */
final class Router
{
    private array $routes = [];

    public function get(string $path, array $action, array $middleware = []): void
    {
        $this->add('GET', $path, $action, $middleware);
    }

    public function post(string $path, array $action, array $middleware = []): void
    {
        $this->add('POST', $path, $action, $middleware);
    }

    /** Register the same handler for GET and POST. */
    public function any(string $path, array $action, array $middleware = []): void
    {
        $this->add('GET', $path, $action, $middleware);
        $this->add('POST', $path, $action, $middleware);
    }

    private function add(string $method, string $path, array $action, array $middleware): void
    {
        $pattern = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', rtrim($path, '/') ?: '/');
        $this->routes[$method][] = [
            'path'       => $path,
            'regex'      => '#^' . $pattern . '$#',
            'action'     => $action,
            'middleware' => $middleware,
        ];
    }

    /**
     * Match the current request and run the handler.
     * Returns false when no route matched, so index.php can render a 404.
     */
    public function dispatch(Request $request): bool
    {
        $method = $request->method();
        $uri    = rtrim($request->path(), '/') ?: '/';

        foreach ($this->routes[$method] ?? [] as $route) {
            if (!preg_match($route['regex'], $uri, $matches)) {
                continue;
            }

            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

            foreach ($route['middleware'] as $name) {
                if (!Middleware::run($name)) {
                    return true; // middleware issued a redirect
                }
            }

            // Every POST must carry a valid CSRF token.
            if ($method === 'POST' && !Csrf::check($request->input('_token'))) {
                Session::flash('error', 'Your session expired. Please try again.');
                Response::back();
                return true;
            }

            [$class, $action] = $route['action'];
            $controller = new $class();
            $controller->$action(...array_values($params));
            return true;
        }

        return false;
    }
}
