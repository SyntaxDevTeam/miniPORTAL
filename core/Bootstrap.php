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
        private array $config,
        private readonly ThemeInterface $theme,
        private readonly Request $request,
        private readonly Security $security,
        private readonly array $availableThemes,
    ) {
    }

    public static function boot(array $config): self
    {
        $timezone = (string) ($config['app']['timezone'] ?? 'UTC');
        date_default_timezone_set($timezone);

        $request = Request::fromGlobals();
        $security = new Security($config['session'] ?? []);
        $security->boot($request);

        $themeEngine = new ThemeEngine(dirname(__DIR__) . '/templates');
        $availableThemes = $themeEngine->available();
        $configuredTheme = (string) ($config['app']['theme'] ?? 'default');
        [$database, $databaseStatus] = self::connectDatabase($config);
        $config['app'] = array_replace(
            $config['app'] ?? [],
            self::themeSettings($database)
        );
        $themeName = (string) ($config['app']['theme'] ?? 'default');
        if (!isset($availableThemes[$themeName])) {
            $themeName = isset($availableThemes[$configuredTheme]) ? $configuredTheme : 'default';
            $config['app']['theme'] = $themeName;
        }
        $application = new self(
            $config,
            $themeEngine->load($themeName, $config['app'] ?? []),
            $request,
            $security,
            $availableThemes,
        );
        $application->database = $database;
        $application->databaseStatus = $databaseStatus;

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

    public function request(): Request
    {
        return $this->request;
    }

    public function security(): Security
    {
        return $this->security;
    }

    public function config(): array
    {
        return $this->config;
    }

    /**
     * @return array<string, string>
     */
    public function availableThemes(): array
    {
        return $this->availableThemes;
    }

    public function diagnostics(): array
    {
        return [
            ['Konfiguracja', 'config/config.php', 'Gotowa'],
            ['Warstwa CRUD', CrudApp::class, $this->databaseStatus],
            ['Autoloader', Autoloader::class, 'Gotowy'],
            ['Silnik startowy', self::class, 'Gotowy'],
            ['ThemeEngine', ThemeEngine::class, 'Gotowy'],
            ['Security', Security::class, 'Gotowy'],
            ['Request', Request::class, 'Gotowy'],
            ['Router', Router::class, 'Gotowy'],
            ['ModuleInterface', ModuleInterface::class, 'Gotowy'],
            ['Menu panelu', AdminMenuRegistry::class, 'Gotowy'],
            ['Szablon', $this->theme::class, 'Gotowy'],
            ['Stylebook', 'templates/default/stylebook.html', 'Gotowy'],
        ];
    }

    /**
     * @return array{0: ?CrudApp, 1: string}
     */
    private static function connectDatabase(array $config): array
    {
        $databaseConfig = $config['database'] ?? [];

        if (($databaseConfig['enabled'] ?? false) !== true) {
            return [null, 'Nie skonfigurowano'];
        }

        unset($databaseConfig['enabled']);

        try {
            return [CrudApp::getInstance($databaseConfig), 'Połączono przez CrudApp'];
        } catch (Throwable $exception) {
            $status = ($config['app']['debug'] ?? false)
                ? 'Błąd: ' . $exception->getMessage()
                : 'Błąd połączenia';

            return [null, $status];
        }
    }

    /**
     * @return array<string, string>
     */
    private static function themeSettings(?CrudApp $database): array
    {
        if ($database === null) {
            return [];
        }

        try {
            $rows = $database->read(
                'system_settings',
                ['setting_key', 'setting_value'],
                ['setting_key' => ['theme', 'public_name', 'public_eyebrow']]
            ) ?? [];
        } catch (Throwable) {
            return [];
        }

        $settings = [];
        foreach ($rows as $row) {
            $key = (string) ($row['setting_key'] ?? '');
            if (in_array($key, ['theme', 'public_name', 'public_eyebrow'], true)) {
                $settings[$key] = (string) ($row['setting_value'] ?? '');
            }
        }

        return $settings;
    }
}
