<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

use Closure;
use RuntimeException;

final class Router
{
    /** @var array<string, array<string, Closure>> */
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, callable $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    public function dispatch(Request $request): int
    {
        $path = $request->path();
        $method = $request->method();
        $handler = $this->routes[$path][$method] ?? null;

        if ($handler !== null) {
            $handler($request);
            return 200;
        }

        if (isset($this->routes[$path])) {
            $allowedMethods = implode(', ', array_keys($this->routes[$path]));

            if (!headers_sent()) {
                header('Allow: ' . $allowedMethods);
            }

            return 405;
        }

        return 404;
    }

    private function add(string $method, string $path, callable $handler): void
    {
        $path = $this->normalizePath($path);

        if (isset($this->routes[$path][$method])) {
            throw new RuntimeException("Trasa {$method} {$path} została już zarejestrowana.");
        }

        $this->routes[$path][$method] = Closure::fromCallable($handler);
    }

    private function normalizePath(string $path): string
    {
        $path = '/' . ltrim(trim($path), '/');

        return $path !== '/' ? rtrim($path, '/') : '/';
    }
}
