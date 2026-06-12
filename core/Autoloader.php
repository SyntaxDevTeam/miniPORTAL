<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

final class Autoloader
{
    private const PREFIXES = [
        'SyntaxDevTeam\\Cms\\Core\\' => __DIR__,
        'SyntaxDevTeam\\Cms\\Modules\\' => __DIR__ . '/../modules',
        'SyntaxDevTeam\\Cms\\Templates\\' => __DIR__ . '/../templates',
    ];

    private const CLASS_MAP = [
        'SyntaxDevTeam\\Cms\\Database\\CrudApp' => __DIR__ . '/database/CrudApp.class.php',
    ];

    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }

        spl_autoload_register(self::load(...));
        self::$registered = true;
    }

    private static function load(string $class): void
    {
        if (isset(self::CLASS_MAP[$class])) {
            require_once self::CLASS_MAP[$class];
            return;
        }

        foreach (self::PREFIXES as $prefix => $directory) {
            if (!str_starts_with($class, $prefix)) {
                continue;
            }

            $relativeClass = substr($class, strlen($prefix));
            $file = $directory . '/' . str_replace('\\', '/', $relativeClass) . '.php';

            if (is_file($file)) {
                require_once $file;
            }

            return;
        }
    }
}
