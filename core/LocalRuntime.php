<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

use RuntimeException;

/** Installation-owned extensions, outside the platform release lifecycle. */
final class LocalRuntime
{
    public static function definitions(string $root, array $base): array
    {
        $file = $root . '/config/modules/local.php';
        if (!is_file($file)) {
            return $base;
        }
        $local = require $file;
        if (!is_array($local)) {
            throw new RuntimeException('Lokalne definicje modułów muszą być tablicą.');
        }
        $directories = array_column($local, 'directory');
        return [...array_values(array_filter($base, static fn (array $definition): bool =>
            !in_array($definition['directory'] ?? '', $directories, true))), ...$local];
    }

    /** @return list<string> Relative file paths or directory prefixes ending in /. */
    public static function protectedPaths(string $root): array
    {
        $paths = [];
        foreach (glob($root . '/modules/*/info.json') ?: [] as $manifest) {
            $data = json_decode((string) file_get_contents($manifest), true, 32, JSON_THROW_ON_ERROR);
            if (($data['distribution']['access'] ?? 'public') !== 'public') {
                $paths[] = 'modules/' . basename(dirname($manifest)) . '/';
            }
        }
        $file = $root . '/config/modules/protected-runtime.json';
        if (is_file($file)) {
            $local = json_decode((string) file_get_contents($file), true, 32, JSON_THROW_ON_ERROR);
            if (!is_array($local) || !array_is_list($local)) {
                throw new RuntimeException('Nieprawidłowa lista ochrony lokalnego runtime.');
            }
            foreach ($local as $path) {
                if (!is_string($path) || preg_match('~^(?:modules|templates|bin|tools)/[a-zA-Z0-9_./-]+$~D', $path) !== 1
                    || in_array('..', explode('/', $path), true) || str_contains($path, '//')) {
                    throw new RuntimeException('Nieprawidłowa ścieżka ochrony lokalnego runtime.');
                }
                $paths[] = $path;
            }
        }
        return array_values(array_unique($paths));
    }

    public static function isProtected(string $path, array $protected): bool
    {
        foreach ($protected as $prefix) {
            if ($path === rtrim($prefix, '/') || (str_ends_with($prefix, '/') && str_starts_with($path, $prefix))) {
                return true;
            }
        }
        return false;
    }
}
