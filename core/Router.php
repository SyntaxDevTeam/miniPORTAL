<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

use Closure;
use RuntimeException;

final class Router
{
    /** @var array<string, array<string, Closure>> */
    private array $routes = [];

    /** @var list<array{method: string, path: string, regex: string, parameters: list<string>, handler: Closure}> */
    private array $parameterizedRoutes = [];

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

        $pathExists = isset($this->routes[$path]);
        foreach ($this->parameterizedRoutes as $route) {
            if (preg_match($route['regex'], $path, $matches) !== 1) {
                continue;
            }
            $pathExists = true;
            if ($route['method'] !== $method) {
                continue;
            }

            $parameters = [];
            foreach ($route['parameters'] as $index => $name) {
                $decoded = rawurldecode((string) ($matches[$index + 1] ?? ''));
                if ($decoded === '' || str_contains($decoded, '/') || str_contains($decoded, "\0")) {
                    return 404;
                }
                $parameters[$name] = $decoded;
            }
            ($route['handler'])($request->withRouteParameters($parameters));
            return 200;
        }

        if ($pathExists) {
            $allowedMethods = array_keys($this->routes[$path] ?? []);
            foreach ($this->parameterizedRoutes as $route) {
                if (preg_match($route['regex'], $path) === 1) {
                    $allowedMethods[] = $route['method'];
                }
            }
            $allowedMethods = implode(', ', array_values(array_unique($allowedMethods)));

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

        if (str_contains($path, '{')) {
            $this->addParameterized($method, $path, $handler);
            return;
        }

        if (isset($this->routes[$path][$method])) {
            throw new RuntimeException("Trasa {$method} {$path} została już zarejestrowana.");
        }

        $this->routes[$path][$method] = Closure::fromCallable($handler);
    }

    private function addParameterized(string $method, string $path, callable $handler): void
    {
        $parameters = [];
        $quoted = preg_quote($path, '#');
        $regex = preg_replace_callback(
            '/\\\{([a-z][a-z0-9_]*)\\\}/',
            static function (array $matches) use (&$parameters): string {
                if (in_array($matches[1], $parameters, true)) {
                    throw new RuntimeException("Parametr trasy {$matches[1]} został użyty więcej niż raz.");
                }
                $parameters[] = $matches[1];
                return '([^/]+)';
            },
            $quoted,
        );
        if ($regex === null || $parameters === [] || str_contains($regex, '\\{') || str_contains($regex, '\\}')) {
            throw new RuntimeException("Wzorzec trasy {$path} jest nieprawidłowy.");
        }

        foreach ($this->parameterizedRoutes as $route) {
            if ($route['method'] === $method && $route['regex'] === '#^' . $regex . '$#D') {
                throw new RuntimeException("Trasa {$method} {$path} została już zarejestrowana.");
            }
        }
        $this->parameterizedRoutes[] = [
            'method' => $method,
            'path' => $path,
            'regex' => '#^' . $regex . '$#D',
            'parameters' => $parameters,
            'handler' => Closure::fromCallable($handler),
        ];
    }

    private function normalizePath(string $path): string
    {
        $path = '/' . ltrim(trim($path), '/');

        return $path !== '/' ? rtrim($path, '/') : '/';
    }
}
