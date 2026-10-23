<?php
declare(strict_types=1);

namespace App\Core;

final class Router
{
    private array $routes = [];

    /** Préfixes exemptés de CSRF : appels serveur-à-serveur authentifiés autrement. */
    private const CSRF_EXEMPT = ['/webhooks/', '/tache-planifiee/'];

    public function get(string $pattern, array $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, array $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    private function add(string $method, string $pattern, array $handler): void
    {
        $regex = preg_replace('#\{([a-z_]+)\}#', '(?P<$1>[^/]+)', $pattern);
        $this->routes[] = [$method, '#^' . $regex . '$#', $handler];
    }

    public function dispatch(string $method, string $path): void
    {
        $path = '/' . trim($path, '/');
        $allowed = false;

        foreach ($this->routes as [$routeMethod, $regex, $handler]) {
            if (!preg_match($regex, $path, $m)) {
                continue;
            }
            if ($routeMethod !== $method && !($routeMethod === 'GET' && $method === 'HEAD')) {
                $allowed = true;
                continue;
            }
            if ($method === 'POST' && !$this->isCsrfExempt($path)) {
                verify_csrf();
            }
            $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
            [$class, $action] = $handler;
            (new $class())->$action(...array_values(array_map('rawurldecode', $params)));
            return;
        }

        abort($allowed ? 405 : 404);
    }

    private function isCsrfExempt(string $path): bool
    {
        foreach (self::CSRF_EXEMPT as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }
        return false;
    }
}
