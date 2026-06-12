<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

use SyntaxDevTeam\Cms\Database\CrudApp;
use SyntaxDevTeam\Cms\Templates\DefaultTheme\Theme;
use Throwable;

require_once __DIR__ . '/ThemeInterface.php';
require_once dirname(__DIR__) . '/templates/default/theme.php';
require_once __DIR__ . '/database/CrudApp.class.php';

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

        $application = new self($config, new Theme());
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
            ['Silnik startowy', self::class, 'Gotowy'],
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
