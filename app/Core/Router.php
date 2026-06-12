<?php

namespace App\Core;

class Router
{
    private array $routes = [];

    public function get(string $pattern, string $handler): void
    {
        $this->routes[] = ['GET', $pattern, $handler];
    }

    public function post(string $pattern, string $handler): void
    {
        $this->routes[] = ['POST', $pattern, $handler];
    }

    public function dispatch(string $method, string $uri): void
    {
        // Strip query string
        $uri = strtok($uri, '?');
        $uri = '/' . trim($uri, '/');

        foreach ($this->routes as [$routeMethod, $pattern, $handler]) {
            if ($routeMethod !== $method) continue;

            $regex = $this->patternToRegex($pattern);

            if (preg_match($regex, $uri, $matches)) {
                array_shift($matches);
                [$class, $action] = explode('@', $handler);
                $className = "App\\Controllers\\{$class}";
                $controller = new $className();
                call_user_func_array([$controller, $action], $matches);
                return;
            }
        }

        $this->notFound();
    }

    private function patternToRegex(string $pattern): string
    {
        $pattern = preg_replace('/\{(\w+)\}/', '([^/]+)', $pattern);
        return '#^' . $pattern . '$#';
    }

    private function notFound(): void
    {
        http_response_code(404);
        require VIEW_PATH . '/errors/404.php';
    }
}
