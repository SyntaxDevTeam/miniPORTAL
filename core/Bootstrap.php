<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

use SyntaxDevTeam\Cms\Database\CrudApp;
use Throwable;

final class Bootstrap
{
    private ?CrudApp $database = null;

    private string $databaseStatus = 'Nie skonfigurowano';

    private function __construct(
        private readonly array $config,
        private readonly ThemeInterface $theme,
    ) {
    }

    public static function boot(array $config): self
    {
        $timezone = (string) ($config['app']['timezone'] ?? 'UTC');
        date_default_timezone_set($timezone);

        $themeName = (string) ($config['app']['theme'] ?? 'default');
        $themeEngine = new ThemeEngine(dirname(__DIR__) . '/templates');
        $application = new self($config, $themeEngine->load($themeName));
        $application->bootDatabase();

        return $application;
    }

    public function theme(): ThemeInterface
    {
        return $this->theme;
    }

    public function database(): ?CrudApp
    {
        return $this->database;
    }

    public function diagnostics(): array
    {
        return [
            ['Konfiguracja', 'config/config.php', 'Gotowa'],
            ['Warstwa CRUD', CrudApp::class, $this->databaseStatus],
            ['Autoloader', Autoloader::class, 'Gotowy'],
            ['Silnik startowy', self::class, 'Gotowy'],
            ['ThemeEngine', ThemeEngine::class, 'Gotowy'],
            ['Szablon', $this->theme::class, 'Gotowy'],
            ['Stylebook', 'templates/default/stylebook.html', 'Gotowy'],
        ];
    }

    private function bootDatabase(): void
    {
        $databaseConfig = $this->config['database'] ?? [];

        if (($databaseConfig['enabled'] ?? false) !== true) {
            return;
        }

        unset($databaseConfig['enabled']);

        try {
            $this->database = CrudApp::getInstance($databaseConfig);
            $this->databaseStatus = 'Połączono przez CrudApp';
        } catch (Throwable $exception) {
            $this->databaseStatus = ($this->config['app']['debug'] ?? false)
                ? 'Błąd: ' . $exception->getMessage()
                : 'Błąd połączenia';
        }
    }
}
