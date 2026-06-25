<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\System;

use SyntaxDevTeam\Cms\Core\AdminMenuRegistry;
use SyntaxDevTeam\Cms\Core\DashboardRegistry;
use SyntaxDevTeam\Cms\Core\BrandIconGenerator;
use SyntaxDevTeam\Cms\Core\CoreMigrationRunner;
use SyntaxDevTeam\Cms\Core\ModuleInterface;
use SyntaxDevTeam\Cms\Core\ModuleArchiveImporter;
use SyntaxDevTeam\Cms\Core\ModuleManagerService;
use SyntaxDevTeam\Cms\Core\PlatformReleaseRepository;
use SyntaxDevTeam\Cms\Core\PlatformReleasePublisher;
use SyntaxDevTeam\Cms\Core\PlatformUpdater;
use SyntaxDevTeam\Cms\Core\PublicNavigationRegistry;
use SyntaxDevTeam\Cms\Core\Request;
use SyntaxDevTeam\Cms\Core\Router;
use SyntaxDevTeam\Cms\Core\Security;
use SyntaxDevTeam\Cms\Core\ThemeInterface;
use SyntaxDevTeam\Cms\Core\TemplateCacheInterface;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AdminAccessGate;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuthService;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuditLogService;

final class SystemAdminModule implements ModuleInterface
{
    public function __construct(
        private readonly ThemeInterface $theme,
        private readonly AdminMenuRegistry $menu,
        private readonly AuthService $auth,
        private readonly AdminAccessGate $access,
        private readonly Security $security,
        private readonly AuditLogService $audit,
        private readonly ?ModuleManagerService $moduleManager,
        private readonly ModuleArchiveImporter $moduleArchiveImporter,
        private readonly ?SystemSettingsRepository $settings,
        private readonly ?SystemLogRepository $logs,
        private readonly array $config,
        private readonly array $diagnostics,
        private readonly array $availableThemes,
        private readonly TemplateCacheInterface $templateCache,
        private readonly array $trustedModulePublishers,
        private readonly PublicNavigationRegistry $publicNavigation,
        private readonly DashboardRegistry $dashboard,
        private readonly BrandIconGenerator $brandIconGenerator,
        private readonly PlatformReleaseRepository $platformReleases,
        private readonly PlatformUpdater $platformUpdater,
        private readonly ?CoreMigrationRunner $coreMigrationRunner,
        private readonly PlatformReleasePublisher $platformReleasePublisher,
    ) {
    }

    public function id(): string
    {
        return 'system_admin';
    }

    public function version(): string
    {
        return '2.0.2';
    }

    public function dependencies(): array
    {
        return ['core_auth'];
    }

    public function isProtected(): bool
    {
        return true;
    }

    public function requiredPermissions(): array
    {
        return ['admin.access', 'settings.manage', 'logs.view'];
    }

    public function registerAdminMenu(AdminMenuRegistry $menu): void
    {
        $menu->add('Przestrzeń robocza', 'Dashboard', '/admin', 'DB', 'admin.access', 10);
        $menu->add('System', 'Moduły', '/admin/modules', 'MD', 'modules.view', 50);
        $menu->add('System', 'Aktualizacje', '/admin/system-updates', 'UP', 'settings.manage', 52);
        $menu->add('System', 'Ustawienia', '/admin/settings', 'ST', 'settings.manage', 55);
        $menu->add('System', 'Dziennik zdarzeń', '/admin/logs', 'LG', 'logs.view', 60);
    }

    public function registerRoutes(Router $router): void
    {
        $router->get('/api/platform-releases/catalog', fn () => $this->servePlatformReleaseCatalog());
        $router->get(
            '/api/platform-releases/{filename}',
            fn (Request $request) => $this->servePlatformReleaseArchive($request)
        );
        $router->get('/admin', fn (Request $request) => $this->guard($request, 'admin.access', fn () => $this->renderDashboard()));
        $router->get('/admin/modules', fn (Request $request) => $this->guard(
            $request,
            'modules.view',
            fn () => $this->renderModules()
        ));
        $router->get('/admin/modules/history', fn (Request $request) => $this->guard(
            $request,
            'modules.view',
            fn () => $this->renderModuleHistory($request)
        ));
        $router->post('/admin/modules/install', fn (Request $request) => $this->guard(
            $request,
            'modules.install',
            fn () => $this->installModule($request)
        ));
        $router->post('/admin/modules/migrate', fn (Request $request) => $this->guard(
            $request,
            'modules.install',
            fn () => $this->migrateModule($request)
        ));
        $router->post('/admin/modules/update', fn (Request $request) => $this->guard(
            $request,
            'modules.install',
            fn () => $this->updateModule($request)
        ));
        $router->post('/admin/modules/toggle', fn (Request $request) => $this->guard(
            $request,
            'modules.toggle',
            fn () => $this->toggleModule($request)
        ));
        $router->post('/admin/modules/uninstall', fn (Request $request) => $this->guard(
            $request,
            'modules.remove',
            fn () => $this->uninstallModule($request)
        ));
        $router->post('/admin/modules/import', fn (Request $request) => $this->guard(
            $request,
            'modules.install',
            fn () => $this->importModuleArchive($request)
        ));
        $router->post('/admin/modules/approve', fn (Request $request) => $this->guard(
            $request,
            'modules.install',
            fn () => $this->approveModuleArchive($request)
        ));
        $router->post('/admin/modules/quarantine/delete', fn (Request $request) => $this->guard(
            $request,
            'modules.install',
            fn () => $this->deleteQuarantineImport($request)
        ));
        $router->post('/admin/modules/quarantine/cleanup', fn (Request $request) => $this->guard(
            $request,
            'modules.install',
            fn () => $this->cleanupQuarantine($request)
        ));
        $router->post('/admin/modules/export', fn (Request $request) => $this->guard(
            $request,
            'modules.install',
            fn () => $this->exportModuleArchive($request)
        ));
        $router->get('/admin/system-updates', fn (Request $request) => $this->guard(
            $request,
            'settings.manage',
            fn () => $this->renderPlatformUpdates()
        ));
        $router->post('/admin/system-updates/apply', fn (Request $request) => $this->guard(
            $request,
            'settings.manage',
            fn () => $this->applyPlatformUpdate($request)
        ));
        $router->post('/admin/system-updates/publish', fn (Request $request) => $this->guard(
            $request,
            'settings.manage',
            fn () => $this->publishPlatformRelease($request)
        ));
        $router->get('/admin/settings', fn (Request $request) => $this->guard(
            $request,
            'settings.manage',
            fn () => $this->renderSettings()
        ));
        $router->post('/admin/settings', fn (Request $request) => $this->guard(
            $request,
            'settings.manage',
            fn () => $this->saveSettings($request)
        ));
        $router->post('/admin/cache/clear', fn (Request $request) => $this->guard(
            $request,
            'settings.manage',
            fn () => $this->clearTemplateCache($request)
        ));
        $router->get('/admin/logs', fn (Request $request) => $this->guard(
            $request,
            'logs.view',
            fn () => $this->renderLogs($request)
        ));
        $router->get('/admin/logs/export', fn (Request $request) => $this->guard(
            $request,
            'logs.view',
            fn () => $this->exportLogs($request)
        ));
        $router->post('/admin/logs/archive', fn (Request $request) => $this->guard(
            $request,
            'logs.view',
            fn () => $this->archiveLogs($request)
        ));
    }

    private function renderDashboard(): void
    {
        $permissions = $this->auth->user()?->permissions ?? [];
        $visibleMenu = $this->menu->visibleFor($permissions);
        $dashboardSettings = $this->settings?->dashboardWidgetSettings() ?? [];
        $moduleEntries = $this->moduleManager?->modules() ?? [];
        $activeModules = count(array_filter(
            $moduleEntries,
            static fn (array $entry): bool => $entry['state']?->isActive() === true
        ));
        $invalidModules = count(array_filter(
            $moduleEntries,
            static fn (array $entry): bool => $entry['error'] !== null
        ));
        $pendingMigrations = array_sum(array_map(
            static fn (array $entry): int => count($entry['pending']),
            $moduleEntries
        ));
        $installedExtensions = count(array_filter(
            $moduleEntries,
            static fn (array $entry): bool => $entry['manifest']?->type === 'extension'
                && $entry['state']?->isInstalled() === true
        ));
        $disabledModules = count(array_filter(
            $moduleEntries,
            static fn (array $entry): bool => $entry['state']?->status === 'disabled'
        ));
        $today = date('Y-m-d');
        $eventsToday = 0;
        $failedEventsToday = 0;
        $recentEvents = [];
        if ($this->logs !== null) {
            try {
                $eventsToday = $this->logs->count(['date_from' => $today, 'date_to' => $today]);
                $failedEventsToday = $this->logs->count([
                    'result' => 'failed',
                    'date_from' => $today,
                    'date_to' => $today,
                ]);
                $recentEvents = $this->logs->page(1, 10);
            } catch (\Throwable) {
                $eventsToday = 0;
                $failedEventsToday = 0;
                $recentEvents = [];
            }
        }

        $this->startPage(
            'Dashboard',
            '/admin',
            'Centrum informacji o modułach, migracjach i aktywności administracyjnej.'
        );
        try {
            $currentVersion = (string) ($this->config['app']['version'] ?? '0.0.0');
            $latestRelease = $this->platformReleases->latestFor($currentVersion);
            if ($latestRelease !== null) {
                $this->theme->render_alert(
                    'Dostępna jest aktualizacja miniPORTAL ' . $latestRelease['version']
                    . '. Otwórz sekcję Aktualizacje, aby poznać listę zmian.',
                    'warning'
                );
                $this->theme->render_button(
                    'Sprawdź aktualizację',
                    'index.php?route=/admin/system-updates',
                    'primary'
                );
            }
        } catch (\Throwable $exception) {
            $this->theme->render_alert('Nie można odczytać katalogu wydań: ' . $exception->getMessage(), 'warning');
        }

        $this->theme->start_admin_metrics();
        $this->theme->render_admin_metric(
            'Aktywne moduły',
            (string) $activeModules,
            'MOD',
            count($moduleEntries) . ' wykrytych, ' . $invalidModules . ' z błędem'
        );
        $this->theme->render_admin_metric('Oczekujące migracje', (string) $pendingMigrations, 'SQL', 'Kontrola SHA-256');
        $this->theme->render_admin_metric('Rozszerzenia', (string) $installedExtensions, 'EXT', $disabledModules . ' wyłączonych');
        $this->theme->render_admin_metric('Zdarzenia dzisiaj', (string) $eventsToday, 'LOG', $failedEventsToday . ' nieudanych');
        foreach ($this->dashboard->metrics($permissions, $dashboardSettings) as $metric) {
            $this->theme->render_admin_metric($metric['label'], $metric['value'], $metric['symbol'], $metric['detail']);
        }
        $this->theme->end_admin_metrics();

        $signals = [
            [
                'Moduły z błędem',
                (string) $invalidModules,
                $invalidModules > 0 ? 'Sprawdź manager modułów' : 'Brak błędów pakietów',
            ],
            [
                'Oczekujące migracje',
                (string) $pendingMigrations,
                $pendingMigrations > 0 ? 'Wykonaj migracje przed dalszym wdrożeniem' : 'Schematy są aktualne',
            ],
            [
                'Wyłączone moduły',
                (string) $disabledModules,
                $disabledModules > 0 ? 'Zweryfikuj, czy to świadoma decyzja' : 'Brak wyłączonych modułów',
            ],
            [
                'Nieudane zdarzenia dzisiaj',
                (string) $failedEventsToday,
                $failedEventsToday > 0 ? 'Przejrzyj dziennik zdarzeń' : 'Brak nieudanych operacji dzisiaj',
            ],
            [
                'Widoczne pozycje menu',
                (string) count($visibleMenu),
                'Wynik bieżących uprawnień użytkownika',
            ],
        ];

        $this->theme->start_admin_panel_grid('dashboard');
        $this->theme->start_admin_panel('Sygnały operacyjne', 'Decyzje');
        $this->theme->render_admin_table(['Obszar', 'Wartość', 'Wniosek'], $signals);
        $this->theme->end_admin_panel();

        $this->theme->start_admin_panel('Ostatnia aktywność', count($recentEvents) . ' zdarzeń');
        if ($recentEvents === []) {
            $this->theme->render_alert('Brak zdarzeń do pokazania albo audit log jest niedostępny.', 'info');
        } else {
            $this->theme->render_admin_table(
                ['Czas', 'Użytkownik', 'Zdarzenie', 'Wynik', 'Źródło'],
                array_map(
                    static fn (array $event): array => [
                        (string) ($event['created_at'] ?? ''),
                        (string) ($event['display_name'] ?? 'System'),
                        (string) ($event['event_type'] ?? ''),
                        (string) ($event['result'] ?? ''),
                        (string) ($event['provider'] ?? ''),
                    ],
                    $recentEvents
                )
            );
        }
        $this->theme->end_admin_panel();
        $this->theme->end_admin_panel_grid();

        $panels = $this->dashboard->panels($permissions, $dashboardSettings);
        if ($panels !== []) {
            $this->theme->start_admin_panel_grid('dashboard');
            foreach ($panels as $panel) {
                $this->theme->start_admin_panel($panel['label'], $panel['meta']);
                if ($panel['rows'] === []) {
                    $this->theme->render_alert($panel['empty'], 'info');
                } else {
                    $this->theme->render_admin_table($panel['headers'], $panel['rows']);
                }
                $this->theme->end_admin_panel();
            }
            $this->theme->end_admin_panel_grid();
        }

        $this->endPage();
    }

    private function renderPlatformUpdates(string $message = '', string $variant = 'info'): void
    {
        $currentVersion = (string) ($this->config['app']['version'] ?? '0.0.0');
        $this->startPage(
            'Aktualizacje miniPORTAL',
            '/admin/system-updates',
            'Kontrolowane wydania Core, modułów chronionych i runtime bez naruszania treści.'
        );
        if ($message !== '') {
            $this->theme->render_alert($message, $variant);
        }
        try {
            $releases = $this->platformReleases->all();
            $latest = $this->platformReleases->latestFor($currentVersion);
            $this->theme->start_admin_panel('Stan platformy', 'Wersja ' . $currentVersion);
            if ($releases === []) {
                $this->theme->render_alert(
                    $this->platformReleases->usesRemoteCatalog()
                        ? 'Skonfigurowany centralny katalog wydań nie zawiera żadnych wersji.'
                        : 'Ta instalacja nie ma skonfigurowanego centralnego kanału wydań, a lokalny katalog jest pusty.',
                    'warning'
                );
            } elseif ($latest === null) {
                $this->theme->render_alert('Ta instalacja korzysta z najnowszego dostępnego wydania.', 'success');
            } else {
                $this->theme->render_alert(
                    'Dostępna aktualizacja ' . $currentVersion . ' → ' . $latest['version'] . '.',
                    'warning'
                );
                $this->theme->render_admin_table(
                    ['Lista zmian'],
                    array_map(static fn (string $item): array => [$item], $latest['changelog'])
                );
                $this->theme->render_form(
                    'index.php?route=/admin/system-updates/apply',
                    [[
                        'name' => 'version',
                        'label' => 'Wersja docelowa',
                        'type' => 'hidden',
                        'value' => $latest['version'],
                    ]],
                    'Pobierz i zaktualizuj do ' . $latest['version'],
                    $this->security->csrfToken()
                );
            }
            $this->theme->end_admin_panel();

            $this->theme->start_admin_panel('Historia dostępnych wydań', count($releases) . ' wersji');
            $this->theme->render_admin_table(
                ['Wersja', 'Data wydania', 'Wymagana wersja bazowa', 'Stan'],
                array_map(
                    static fn (array $release): array => [
                        $release['version'],
                        $release['released_at'],
                        $release['minimum_version'],
                        version_compare($release['version'], $currentVersion, '>')
                            ? 'Dostępna'
                            : ($release['version'] === $currentVersion ? 'Zainstalowana' : 'Starsza'),
                    ],
                    $releases
                )
            );
            $this->theme->end_admin_panel();

            if ($this->isOwner() && $this->platformReleasePublisher->available()) {
                $this->theme->start_admin_panel(
                    'Opublikuj własne wydanie',
                    'Instalacja macierzysta / Owner'
                );
                $this->theme->render_alert(
                    'Wpisz numer nowego wydania albo pozostaw ' . $currentVersion
                    . ', aby przebudować bieżącą wersję. Generator zaktualizuje releases/catalog.json.',
                    'info'
                );
                $this->theme->render_form(
                    'index.php?route=/admin/system-updates/publish',
                    [
                        [
                            'name' => 'version',
                            'label' => 'Publikowana wersja',
                            'value' => $currentVersion,
                            'placeholder' => '0.3.0',
                            'help' => 'Możesz przebudować bieżącą wersję albo wpisać wyższą wersję SemVer.',
                        ],
                        [
                            'name' => 'minimum_version',
                            'label' => 'Najstarsza wersja obsługiwana przez aktualizację',
                            'value' => $currentVersion,
                            'placeholder' => '0.2.0',
                            'help' => 'Instalacje starsze od tej wersji będą wymagały wydania pośredniego.',
                        ],
                        [
                            'name' => 'changelog',
                            'label' => 'Lista zmian',
                            'type' => 'textarea',
                            'rows' => 8,
                            'placeholder' => "Dodano nową funkcję.\nNaprawiono błąd aktualizacji.\nPoprawiono bezpieczeństwo.",
                            'help' => 'Jedna zmiana w każdym wierszu; maksymalnie 50 pozycji.',
                        ],
                    ],
                    'Zbuduj wskazany release',
                    $this->security->csrfToken()
                );
                $this->theme->end_admin_panel();
            }
        } catch (\Throwable $exception) {
            $this->theme->render_alert($exception->getMessage(), 'danger');
        }
        $this->endPage();
    }

    private function servePlatformReleaseCatalog(): void
    {
        try {
            $payload = json_encode(
                ['releases' => $this->platformReleases->all()],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            ) . PHP_EOL;
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=UTF-8');
                header('Content-Length: ' . strlen($payload));
                header('Cache-Control: public, max-age=300');
                header('X-Content-Type-Options: nosniff');
            }
            echo $payload;
        } catch (\Throwable) {
            http_response_code(503);
            echo '{"releases":[]}';
        }
    }

    private function servePlatformReleaseArchive(Request $request): void
    {
        $filename = $request->routeString('filename');
        $release = $this->platformReleases->findByFilename($filename);
        if ($release === null) {
            http_response_code(404);
            return;
        }
        try {
            $path = $this->platformReleases->archivePath($release);
            if (!is_file($path)) {
                throw new \RuntimeException('Archiwum wydania jest niedostępne.');
            }
            if (!headers_sent()) {
                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="' . $release['filename'] . '"');
                header('Content-Length: ' . (string) filesize($path));
                header('Cache-Control: public, max-age=86400, immutable');
                header('X-Content-Type-Options: nosniff');
                header('X-Release-SHA256: ' . $release['checksum']);
            }
            readfile($path);
        } catch (\Throwable) {
            http_response_code(503);
        }
    }

    private function applyPlatformUpdate(Request $request): void
    {
        $actor = $this->auth->user();
        $version = $request->postString('version');
        if (!$this->security->validateCsrfToken($request->postString('_token'))) {
            $this->audit->record($request, 'platform_update', 'invalid_csrf', $version, $actor?->id);
            http_response_code(403);
            $this->renderPlatformUpdates('Nieprawidłowy lub wygasły token CSRF.', 'danger');
            return;
        }
        try {
            $release = $this->platformReleases->find($version);
            if ($release === null) {
                throw new \RuntimeException('Nie znaleziono wskazanego wydania.');
            }
            if ($this->moduleManager === null || $this->coreMigrationRunner === null) {
                throw new \RuntimeException('Aktualizacja wymaga aktywnego połączenia z bazą danych.');
            }
            $currentVersion = (string) ($this->config['app']['version'] ?? '0.0.0');
            $result = $this->platformUpdater->apply(
                $release,
                $this->platformReleases->archivePath($release),
                $currentVersion,
                function (): void {
                    $this->coreMigrationRunner?->run();
                    foreach ($this->moduleManager?->modules() ?? [] as $entry) {
                        if (
                            $entry['manifest'] !== null
                            && $entry['state']?->isInstalled() === true
                            && $entry['update_available'] === true
                        ) {
                            $this->moduleManager?->update($entry['manifest']->id);
                        }
                    }
                }
            );
            $this->audit->record(
                $request,
                'platform_update',
                'success',
                $currentVersion . ' -> ' . $result['version'] . ' / files:' . $result['files'],
                $actor?->id
            );
            $this->startPage(
                'Aktualizacja zakończona',
                '/admin/system-updates',
                'Nowy runtime miniPORTAL został wdrożony.'
            );
            $this->theme->render_alert(
                'miniPORTAL zaktualizowano do ' . $result['version']
                . '. Zmieniono ' . $result['files'] . ' plików; treści i dane lokalne pozostały bez zmian.',
                'success'
            );
            $this->theme->render_button(
                'Uruchom odświeżony panel',
                'index.php?route=/admin/system-updates',
                'primary'
            );
            $this->endPage();
        } catch (\Throwable $exception) {
            $this->audit->record($request, 'platform_update', 'failed', $version, $actor?->id);
            $this->renderPlatformUpdates($exception->getMessage(), 'danger');
        }
    }

    private function publishPlatformRelease(Request $request): void
    {
        $actor = $this->auth->user();
        $version = $request->postString('version');
        if (!$this->security->validateCsrfToken($request->postString('_token'))) {
            $this->audit->record($request, 'platform_release_publish', 'invalid_csrf', $version, $actor?->id);
            http_response_code(403);
            $this->renderPlatformUpdates('Nieprawidłowy lub wygasły token CSRF.', 'danger');
            return;
        }
        if (!$this->isOwner()) {
            $this->audit->record($request, 'platform_release_publish', 'forbidden', $version, $actor?->id);
            http_response_code(403);
            $this->renderPlatformUpdates('Publikowanie wydań wymaga roli Owner.', 'danger');
            return;
        }

        try {
            $minimumVersion = trim($request->postString('minimum_version'));
            $changelog = preg_split('/\R/', $request->postString('changelog')) ?: [];
            $output = $this->platformReleasePublisher->publish(
                $version,
                $minimumVersion,
                array_values(array_map('trim', $changelog))
            );
            $this->audit->record(
                $request,
                'platform_release_publish',
                'success',
                $version . ' / entries:' . count(array_filter($changelog, static fn (string $item): bool => trim($item) !== '')),
                $actor?->id
            );
            $this->renderPlatformUpdates(
                'Wydanie ' . $version . ' zostało zbudowane. ' . $output
                . ' Odśwież panel, aby zobaczyć nową wersję runtime.',
                'success'
            );
        } catch (\Throwable $exception) {
            $this->audit->record($request, 'platform_release_publish', 'failed', $version, $actor?->id);
            $this->renderPlatformUpdates($exception->getMessage(), 'danger');
        }
    }

    private function isOwner(): bool
    {
        return in_array('*', $this->auth->user()?->permissions ?? [], true);
    }

    private function renderModules(string $message = '', string $variant = 'info'): void
    {
        $this->startPage(
            'Moduły',
            '/admin/modules',
            'Instalacja, migracje i aktywacja modułów na podstawie zweryfikowanych manifestów.'
        );
        if ($message !== '') {
            $this->theme->render_alert($message, $variant);
        }
        if ($this->moduleManager === null) {
            $this->theme->render_alert('Manager modułów wymaga aktywnego połączenia z bazą danych.', 'danger');
            $this->endPage();
            return;
        }
        $publisherMode = $this->moduleManager->signsExportsAutomatically();
        if ($publisherMode) {
            $this->theme->render_alert(
                'Tryb wydawniczy jest aktywny. Eksportuj ZIP utworzy podpisaną kopię pakietu.',
                'success'
            );
        }

        $user = $this->auth->user();
        $allows = static fn (string $permission): bool => $user !== null
            && (in_array('*', $user->permissions, true) || in_array($permission, $user->permissions, true));
        $rows = [];
        foreach ($this->moduleManager->modules() as $entry) {
            $manifest = $entry['manifest'];
            $state = $entry['state'];
            $pending = $entry['pending'];
            $loadable = $entry['loadable'];
            $updateAvailable = $entry['update_available'];
            $actions = [];

            if ($manifest === null || $state === null || $entry['error'] !== null) {
                $rows[] = [
                    'cells' => [
                        $manifest !== null
                            ? $manifest->name . ' (' . $manifest->id . ')'
                            : $entry['directory'],
                        $manifest?->version ?? 'Nieznana',
                        'Błąd pakietu',
                        $manifest?->protected === true ? 'Chroniony' : 'Niedostępne',
                        $manifest !== null ? $this->packageTrustLabel($manifest) : 'Nieznane',
                        'Operacje zablokowane',
                        $entry['error'] ?? 'Nie można odczytać stanu modułu.',
                    ],
                    'actions' => [],
                ];
                continue;
            }

            if (
                $loadable
                && !$state->isInstalled()
                && $allows('modules.install')
                && ($manifest->installFile !== null || $state->canRestorePreservedData())
            ) {
                $actions[] = [
                    'label' => $state->canRestorePreservedData() ? 'Przywróć z danymi' : 'Zainstaluj',
                    'action' => 'index.php?route=/admin/modules/install',
                    'fields' => ['module_id' => $manifest->id],
                    'variant' => 'primary',
                    'confirm' => $state->canRestorePreservedData()
                        ? 'Przywrócić moduł z zachowanymi wcześniej danymi?'
                        : 'Zainstalować i aktywować ten moduł? Po instalacji aplikacja będzie mogła wykonywać kod jego fabryki.',
                ];
            }
            if ($loadable && $state->isInstalled() && $updateAvailable && $allows('modules.install')) {
                $actions[] = [
                    'label' => 'Aktualizuj do ' . $manifest->version,
                    'action' => 'index.php?route=/admin/modules/update',
                    'fields' => ['module_id' => $manifest->id],
                    'variant' => 'primary',
                    'confirm' => 'Uruchomić kontrolowaną aktualizację modułu? Przed wykonaniem SQL zostaną sprawdzone wszystkie migracje.',
                ];
            }
            if (
                $loadable
                && $state->isInstalled()
                && !$updateAvailable
                && $pending !== []
                && $allows('modules.install')
            ) {
                $actions[] = [
                    'label' => 'Migracje (' . count($pending) . ')',
                    'action' => 'index.php?route=/admin/modules/migrate',
                    'fields' => ['module_id' => $manifest->id],
                    'variant' => 'outline-primary',
                    'confirm' => 'Wykonać oczekujące migracje bez zmiany wersji modułu?',
                ];
            }
            if ($loadable && $state->isInstalled() && !$manifest->protected && $allows('modules.toggle')) {
                $actions[] = [
                    'label' => $state->isActive() ? 'Wyłącz' : 'Włącz',
                    'action' => 'index.php?route=/admin/modules/toggle',
                    'fields' => [
                        'module_id' => $manifest->id,
                        'active' => $state->isActive() ? '0' : '1',
                    ],
                    'variant' => $state->isActive() ? 'outline-danger' : 'outline-primary',
                ];
            }
            if ($state->isInstalled() || $state->canRestorePreservedData()) {
                $actions[] = [
                    'label' => 'Historia migracji',
                    'href' => 'index.php?route=/admin/modules/history&module_id=' . rawurlencode($manifest->id),
                    'variant' => 'outline-light',
                ];
            }
            if (
                $state->isInstalled()
                && $publisherMode
                && $allows('modules.install')
            ) {
                $actions[] = [
                    'label' => 'Eksportuj ZIP',
                    'action' => 'index.php?route=/admin/modules/export',
                    'fields' => ['module_id' => $manifest->id],
                    'variant' => 'outline-light',
                ];
            }
            if (
                $loadable
                && $state->isInstalled()
                && !$state->isActive()
                && !$manifest->protected
                && $allows('modules.remove')
            ) {
                $actions[] = [
                    'label' => 'Odinstaluj, zachowaj dane',
                    'action' => 'index.php?route=/admin/modules/uninstall',
                    'fields' => ['module_id' => $manifest->id, 'preserve_data' => '1'],
                    'variant' => 'outline-warning',
                    'confirm' => 'Odinstalować moduł, zachowując jego tabele i historię migracji?',
                ];
                if ($manifest->uninstallFile !== null) {
                    $actions[] = [
                        'label' => 'Odinstaluj i usuń dane',
                        'action' => 'index.php?route=/admin/modules/uninstall',
                        'fields' => ['module_id' => $manifest->id, 'preserve_data' => '0'],
                        'variant' => 'outline-danger',
                        'confirm' => 'Trwale usunąć moduł i wszystkie jego dane? Tej operacji nie można cofnąć.',
                    ];
                }
            }

            $rows[] = [
                'cells' => [
                    $manifest->name . ' (' . $manifest->id . ')',
                    $state->version . ' / ' . $manifest->version,
                    $this->moduleStatusLabel($state->status),
                    $manifest->protected ? 'Chroniony' : 'Rozszerzenie',
                    $this->packageTrustLabel($manifest),
                    $loadable ? 'Fabryka gotowa' : 'Brak zaufanej fabryki',
                    $state->dataPreserved
                        ? 'Dane zachowane'
                        : ($pending === [] ? 'Brak' : implode(', ', $pending)),
                ],
                'actions' => $actions,
            ];
        }

        $this->theme->start_admin_panel('Rejestr modułów', count($rows) . ' manifesty');
        $this->theme->render_admin_action_table(
            ['Moduł', 'Wersja zapisana / kodu', 'Stan', 'Ochrona', 'Pochodzenie / podpis', 'Uruchamianie', 'Oczekujące migracje'],
            $rows,
            $this->security->csrfToken()
        );
        $this->theme->end_admin_panel();

        $this->theme->start_admin_panel('Import archiwum do kwarantanny', 'Bez instalacji i bez wykonania kodu');
        $modulesPath = dirname(__DIR__, 2) . '/modules';
        if (!is_dir($modulesPath) || !is_writable($modulesPath)) {
            $this->theme->render_alert(
                "PHP nie może zapisywać w katalogu aktywnych modułów.\n"
                . "Na Debianie/Ubuntu wykonaj w katalogu miniPORTAL:\n"
                . "sudo chgrp www-data modules\n"
                . "sudo chmod 2775 modules\n"
                . "Następnie sprawdź: sudo -u www-data test -w modules && echo \"modules/ jest zapisywalny\"",
                'warning'
            );
        }
        $this->theme->render_form(
            'index.php?route=/admin/modules/import',
            [[
                'name' => 'archive',
                'label' => 'Archiwum modułu',
                'type' => 'file',
                'accept' => '.tar,.tar.gz,.tgz,.zip',
                'help' => 'Pakiet zostanie rozpakowany wyłącznie do cache/module-quarantine i sprawdzony manifestem.',
            ]],
            'Importuj do kwarantanny',
            $this->security->csrfToken()
        );
        $imports = array_slice($this->moduleArchiveImporter->imports(), 0, 10);
        if ($imports !== []) {
            $this->theme->render_admin_action_table(
                ['Katalog', 'Pakiet', 'Manifest', 'Import'],
                array_map(
                    function (array $import) use ($allows): array {
                        $manifest = $import['manifest'];
                        $approvable = $manifest !== null
                            && (
                                in_array($manifest->signatureStatus, ['verified', 'verified_retired'], true)
                                || ($manifest->protected && $manifest->originType === 'bundled')
                            )
                            && $allows('modules.install');

                        $actions = [];
                        if ($approvable) {
                            $actions[] = [
                                'label' => 'Zatwierdź pakiet',
                                'action' => 'index.php?route=/admin/modules/approve',
                                'fields' => ['import_directory' => $import['directory']],
                                'variant' => 'primary',
                                'confirm' => $manifest->protected
                                    ? 'Zaktualizować chroniony moduł? Kod zostanie podmieniony atomowo, migracje wykonane od razu, a błąd przywróci poprzednią wersję.'
                                    : 'Zatwierdzić pakiet? Nowy moduł zostanie przeniesiony do modules/, a istniejący zaktualizowany atomowo wraz z migracjami.',
                            ];
                        }
                        if ($allows('modules.install')) {
                            $actions[] = [
                                'label' => 'Usuń z kwarantanny',
                                'action' => 'index.php?route=/admin/modules/quarantine/delete',
                                'fields' => ['import_directory' => $import['directory']],
                                'variant' => 'outline-danger',
                                'confirm' => 'Trwale usunąć ten import z kwarantanny?',
                            ];
                        }

                        return [
                            'cells' => [
                                $import['directory'],
                                $import['package'],
                                $manifest !== null
                                    ? $manifest->name . ' (' . $manifest->id . ', ' . $this->packageTrustLabel($manifest) . ')'
                                    : 'Błąd: ' . (string) $import['error'],
                                $import['imported_at'],
                            ],
                            'actions' => $actions,
                        ];
                    },
                    $imports
                ),
                $this->security->csrfToken()
            );
            if ($allows('modules.install')) {
                $retentionDays = (int) ($this->config['modules']['quarantine_retention_days'] ?? 7);
                $this->theme->render_form(
                    'index.php?route=/admin/modules/quarantine/cleanup',
                    [[
                        'name' => 'retention_days',
                        'label' => 'Usuń importy starsze niż liczba dni',
                        'type' => 'number',
                        'value' => (string) $retentionDays,
                        'min' => '1',
                        'max' => '365',
                    ]],
                    'Wyczyść starą kwarantannę',
                    $this->security->csrfToken()
                );
            }
        }
        $this->theme->end_admin_panel();
        $this->endPage();
    }

    private function renderModuleHistory(Request $request): void
    {
        $moduleId = $request->queryString('module_id');
        if (
            $this->moduleManager === null
            || preg_match('/^[a-z][a-z0-9_]{1,63}$/', $moduleId) !== 1
        ) {
            http_response_code(404);
            $this->theme->render_admin_access_state(
                404,
                'Nie znaleziono modułu',
                'Nie można odczytać historii migracji wskazanego modułu.',
                'index.php?route=/admin/modules',
                'Wróć do modułów'
            );
            return;
        }

        try {
            $history = $this->moduleManager->migrationHistory($moduleId);
        } catch (\Throwable $exception) {
            http_response_code(404);
            $this->theme->render_admin_access_state(
                404,
                'Historia niedostępna',
                $exception->getMessage(),
                'index.php?route=/admin/modules',
                'Wróć do modułów'
            );
            return;
        }

        $manifest = $history['manifest'];
        $migrations = $history['migrations'];
        $this->startPage(
            'Historia migracji',
            '/admin/modules',
            "{$manifest->name} ({$manifest->id}) - zapisane wykonania i kontrola integralności plików."
        );
        $this->theme->start_admin_panel('Wykonane migracje', count($migrations) . ' wpisów');
        if ($migrations === []) {
            $this->theme->render_alert(
                'Moduł nie ma zapisanych migracji. Pełny schemat mógł zostać wykonany bezpośrednio z install.sql.',
                'info'
            );
        } else {
            $this->theme->render_admin_table(
                ['Migracja', 'Wykonano', 'Zapisany SHA-256', 'Aktualny SHA-256', 'Stan'],
                array_map(
                    static fn (array $migration): array => [
                        $migration['migration'],
                        $migration['executed_at'],
                        $migration['checksum'],
                        $migration['current_checksum'] ?? 'Brak pliku',
                        $migration['status'],
                    ],
                    $migrations
                )
            );
        }
        $this->theme->end_admin_panel();
        $this->theme->render_button('Wróć do managera', 'index.php?route=/admin/modules', 'outline-light');
        $this->endPage();
    }

    private function installModule(Request $request): void
    {
        $this->moduleOperation(
            $request,
            'module_install',
            fn (string $moduleId) => $this->moduleManager?->install($moduleId),
            'Moduł został zainstalowany i aktywowany.'
        );
    }

    private function migrateModule(Request $request): void
    {
        $this->moduleOperation(
            $request,
            'module_migrate',
            function (string $moduleId): void {
                $executed = $this->moduleManager?->migrate($moduleId) ?? [];
                if ($executed === []) {
                    throw new \RuntimeException('Brak oczekujących migracji.');
                }
            },
            'Migracje modułu zostały wykonane.'
        );
    }

    private function updateModule(Request $request): void
    {
        $this->moduleOperation(
            $request,
            'module_update',
            fn (string $moduleId) => $this->moduleManager?->update($moduleId),
            'Moduł został zaktualizowany do wersji zadeklarowanej w manifeście.'
        );
    }

    private function toggleModule(Request $request): void
    {
        $active = $request->postBool('active');
        $this->moduleOperation(
            $request,
            'module_toggle',
            fn (string $moduleId) => $this->moduleManager?->toggle($moduleId, $active),
            $active ? 'Moduł został aktywowany.' : 'Moduł został wyłączony.'
        );
    }

    private function uninstallModule(Request $request): void
    {
        $preserveData = $request->postBool('preserve_data');
        $this->moduleOperation(
            $request,
            'module_uninstall',
            fn (string $moduleId) => $this->moduleManager?->uninstall($moduleId, $preserveData),
            $preserveData
                ? 'Moduł został odinstalowany, a jego dane zachowano.'
                : 'Moduł został odinstalowany, a jego dane usunięto.'
        );
    }

    private function importModuleArchive(Request $request): void
    {
        $actor = $this->auth->user();
        if (!$this->security->validateCsrfToken($request->postString('_token'))) {
            $this->audit->record($request, 'module_archive_import', 'invalid_csrf', null, $actor?->id);
            http_response_code(403);
            $this->renderModules('Nieprawidłowy lub wygasły token CSRF.', 'danger');
            return;
        }

        try {
            $file = $request->file('archive');
            if ($file === null) {
                throw new \RuntimeException('Wybierz archiwum modułu do importu.');
            }
            $result = $this->moduleArchiveImporter->importUploaded($file);
            $manifest = $result['manifest'];
            $detail = $manifest !== null
                ? $manifest->id . ' / ' . $manifest->signatureStatus
                : 'invalid_package';
            $this->audit->record($request, 'module_archive_import', $manifest !== null ? 'quarantined' : 'invalid_manifest', $detail, $actor?->id);
            $message = $manifest !== null
                ? 'Pakiet zaimportowano do kwarantanny: ' . $manifest->name . ' (' . $manifest->id . ').'
                : 'Pakiet trafił do kwarantanny, ale manifest wymaga poprawy: ' . (string) $result['error'];
            $this->renderModules($message, $manifest !== null ? 'success' : 'warning');
        } catch (\Throwable $exception) {
            $this->audit->record($request, 'module_archive_import', 'failed', null, $actor?->id);
            $this->renderModules($exception->getMessage(), 'danger');
        }
    }

    private function approveModuleArchive(Request $request): void
    {
        $actor = $this->auth->user();
        $importDirectory = $request->postString('import_directory');
        if (!$this->security->validateCsrfToken($request->postString('_token'))) {
            $this->audit->record($request, 'module_archive_approve', 'invalid_csrf', $importDirectory, $actor?->id);
            http_response_code(403);
            $this->renderModules('Nieprawidłowy lub wygasły token CSRF.', 'danger');
            return;
        }

        try {
            $result = $this->moduleArchiveImporter->approve(
                $importDirectory,
                dirname(__DIR__, 2) . '/modules',
                function ($manifest): void {
                    if ($this->moduleManager === null) {
                        throw new \RuntimeException('Manager modułów jest niedostępny.');
                    }
                    $this->moduleManager->update($manifest->id);
                }
            );
            $manifest = $result['manifest'];
            $this->audit->record(
                $request,
                'module_archive_approve',
                'success',
                $manifest->id . ' / ' . $manifest->signatureStatus,
                $actor?->id
            );
            $this->renderModules(
                $result['operation'] === 'updated'
                    ? 'Pakiet ' . $manifest->name . ' zaktualizował kod i migracje modułu do wersji ' . $manifest->version . '.'
                    : 'Pakiet ' . $manifest->name . ' zatwierdzono i przeniesiono do modules/. Instalacja pozostaje osobną operacją.',
                'success'
            );
        } catch (\Throwable $exception) {
            $this->audit->record($request, 'module_archive_approve', 'failed', $importDirectory, $actor?->id);
            $message = $exception->getMessage();
            if (str_contains($message, 'katalog modułów nie jest zapisywalny')) {
                $message .= "\nNaprawa na Debianie/Ubuntu:\n"
                    . "sudo chgrp www-data modules\n"
                    . "sudo chmod 2775 modules\n"
                    . "sudo -u www-data test -w modules && echo \"modules/ jest zapisywalny\"";
            }
            $this->renderModules($message, 'danger');
        }
    }

    private function deleteQuarantineImport(Request $request): void
    {
        $actor = $this->auth->user();
        $importDirectory = $request->postString('import_directory');
        if (!$this->security->validateCsrfToken($request->postString('_token'))) {
            $this->audit->record($request, 'module_quarantine_delete', 'invalid_csrf', $importDirectory, $actor?->id);
            http_response_code(403);
            $this->renderModules('Nieprawidłowy lub wygasły token CSRF.', 'danger');
            return;
        }
        try {
            $this->moduleArchiveImporter->remove($importDirectory);
            $this->audit->record($request, 'module_quarantine_delete', 'success', $importDirectory, $actor?->id);
            $this->renderModules('Import został usunięty z kwarantanny.', 'success');
        } catch (\Throwable $exception) {
            $this->audit->record($request, 'module_quarantine_delete', 'failed', $importDirectory, $actor?->id);
            $this->renderModules($exception->getMessage(), 'danger');
        }
    }

    private function cleanupQuarantine(Request $request): void
    {
        $actor = $this->auth->user();
        $days = max(1, min(365, $request->postInt('retention_days', 7) ?? 7));
        if (!$this->security->validateCsrfToken($request->postString('_token'))) {
            $this->audit->record($request, 'module_quarantine_cleanup', 'invalid_csrf', (string) $days, $actor?->id);
            http_response_code(403);
            $this->renderModules('Nieprawidłowy lub wygasły token CSRF.', 'danger');
            return;
        }
        try {
            $removed = $this->moduleArchiveImporter->removeOlderThan($days);
            $detail = 'days:' . $days . ' / removed:' . count($removed);
            $this->audit->record($request, 'module_quarantine_cleanup', 'success', $detail, $actor?->id);
            $this->renderModules(
                $removed === []
                    ? 'Brak importów starszych niż ' . $days . ' dni.'
                    : 'Usunięto z kwarantanny ' . count($removed) . ' starych importów.',
                'success'
            );
        } catch (\Throwable $exception) {
            $this->audit->record($request, 'module_quarantine_cleanup', 'failed', (string) $days, $actor?->id);
            $this->renderModules($exception->getMessage(), 'danger');
        }
    }

    private function exportModuleArchive(Request $request): void
    {
        $actor = $this->auth->user();
        $moduleId = $request->postString('module_id');
        if (!$this->security->validateCsrfToken($request->postString('_token'))) {
            $this->audit->record($request, 'module_export', 'invalid_csrf', $moduleId, $actor?->id);
            http_response_code(403);
            $this->renderModules('Nieprawidłowy lub wygasły token CSRF.', 'danger');
            return;
        }
        if (preg_match('/^[a-z][a-z0-9_]{1,63}$/', $moduleId) !== 1 || $this->moduleManager === null) {
            $this->audit->record($request, 'module_export', 'invalid_module', $moduleId, $actor?->id);
            $this->renderModules('Nieprawidłowy moduł albo niedostępny manager.', 'danger');
            return;
        }

        try {
            if (!$this->moduleManager->signsExportsAutomatically()) {
                throw new \RuntimeException(
                    'Eksport modułów jest wyłączony na tej instalacji produkcyjnej.'
                );
            }
            $export = $this->moduleManager->exportPackage($moduleId);
            $this->audit->record($request, 'module_export', 'success', $moduleId, $actor?->id);
            if (!headers_sent()) {
                header('Content-Type: ' . $export['mime']);
                header('Content-Disposition: attachment; filename="' . str_replace('"', '', $export['filename']) . '"');
                header('Content-Length: ' . (string) filesize($export['path']));
                header('X-Content-Type-Options: nosniff');
            }
            readfile($export['path']);
            @unlink($export['path']);
            exit;
        } catch (\Throwable $exception) {
            $this->audit->record($request, 'module_export', 'failed', $moduleId, $actor?->id);
            $this->renderModules($exception->getMessage(), 'danger');
        }
    }

    private function moduleOperation(
        Request $request,
        string $event,
        callable $operation,
        string $successMessage,
    ): void {
        $moduleId = $request->postString('module_id');
        if (!$this->security->validateCsrfToken($request->postString('_token'))) {
            $this->audit->record($request, $event, 'invalid_csrf', $moduleId, $this->auth->user()?->id);
            http_response_code(403);
            $this->renderModules('Nieprawidłowy lub wygasły token CSRF.', 'danger');
            return;
        }
        if (preg_match('/^[a-z][a-z0-9_]{1,63}$/', $moduleId) !== 1 || $this->moduleManager === null) {
            $this->audit->record($request, $event, 'invalid_module', $moduleId, $this->auth->user()?->id);
            $this->renderModules('Nieprawidłowy moduł albo niedostępny manager.', 'danger');
            return;
        }

        try {
            $operation($moduleId);
            $this->audit->record($request, $event, 'success', $moduleId, $this->auth->user()?->id);
            $this->renderModules($successMessage, 'success');
        } catch (\Throwable $exception) {
            $this->audit->record($request, $event, 'failed', $moduleId, $this->auth->user()?->id);
            $this->renderModules($exception->getMessage(), 'danger');
        }
    }

    private function moduleStatusLabel(string $status): string
    {
        return match ($status) {
            'active' => 'Aktywny',
            'disabled' => 'Wyłączony',
            'uninstalled' => 'Odinstalowany / dane zachowane',
            default => 'Wykryty / niezainstalowany',
        };
    }

    private function packageTrustLabel(\SyntaxDevTeam\Cms\Core\ModuleManifest $manifest): string
    {
        $origin = $manifest->originType === 'unspecified'
            ? 'Pochodzenie nieokreślone'
            : $manifest->originType . ($manifest->originUrl !== '' ? ': ' . $manifest->originUrl : '');
        $signature = match ($manifest->signatureStatus) {
            'verified' => 'podpis zweryfikowany (' . $manifest->signatureKeyId . ')',
            'verified_retired' => 'podpis poprawny, klucz wycofany po rotacji (' . $manifest->signatureKeyId . ')',
            'untrusted' => 'klucz niezaufany (' . $manifest->signatureKeyId . ')',
            'revoked' => 'klucz unieważniony (' . $manifest->signatureKeyId . ')',
            'outside_validity' => 'podpis poza okresem ważności klucza (' . $manifest->signatureKeyId . ')',
            default => 'brak podpisu',
        };

        return $origin . ' / ' . $signature;
    }

    private function renderSettings(string $message = '', string $variant = 'info'): void
    {
        $this->startPage(
            'Ustawienia systemowe',
            '/admin/settings',
            'Bezpieczne ustawienia prezentacji i zredagowana diagnostyka konfiguracji.'
        );
        if ($message !== '') {
            $this->theme->render_alert($message, $variant);
        }
        if ($this->settings === null) {
            $this->theme->render_alert('Ustawienia systemowe wymagają aktywnego połączenia z bazą danych.', 'danger');
            $this->endPage();
            return;
        }

        $app = is_array($this->config['app'] ?? null) ? $this->config['app'] : [];
        $values = $this->settings->themeSettings([
            'theme' => (string) ($app['theme'] ?? 'default'),
            'public_url' => (string) ($app['public_url'] ?? 'https://syntaxdevteam.pl'),
            'public_name' => (string) ($app['public_name'] ?? 'SyntaxDevTeam'),
            'public_default_title' => (string) (
                $app['public_default_title']
                ?? 'SyntaxDevTeam - software dla serwerów, społeczności i urządzeń'
            ),
            'public_eyebrow' => (string) ($app['public_eyebrow'] ?? 'Software dla społeczności'),
            'public_meta_description' => (string) (
                $app['public_meta_description']
                ?? 'SyntaxDevTeam tworzy pluginy Minecraft, boty Discord, aplikacje Android i narzędzia backendowe.'
            ),
            'public_meta_keywords' => (string) (
                $app['public_meta_keywords']
                ?? 'SyntaxDevTeam, miniPORTAL, pluginy Minecraft, boty Discord, aplikacje Android'
            ),
            'public_meta_author' => (string) ($app['public_meta_author'] ?? 'SyntaxDevTeam'),
            'public_meta_robots' => (string) (
                $app['public_meta_robots'] ?? 'index, follow, max-image-preview:large'
            ),
            'public_locale' => (string) ($app['public_locale'] ?? 'pl_PL'),
            'public_social_image_url' => (string) ($app['public_social_image_url'] ?? ''),
            'public_social_image_alt' => (string) ($app['public_social_image_alt'] ?? 'Logo SyntaxDevTeam'),
            'public_twitter_site' => (string) ($app['public_twitter_site'] ?? ''),
            'public_theme_color' => (string) ($app['public_theme_color'] ?? '#080c12'),
            'public_google_site_verification' => (string) ($app['public_google_site_verification'] ?? ''),
            'public_bing_site_verification' => (string) ($app['public_bing_site_verification'] ?? ''),
            'public_footer_text' => (string) ($app['public_footer_text'] ?? 'Projektowane modułowo. Rozwijane świadomie.'),
            'public_favicon_path' => (string) ($app['public_favicon_path'] ?? ''),
            'public_favicon_version' => (string) ($app['public_favicon_version'] ?? ''),
        ]);
        $cache = $this->templateCache->stats();
        $this->theme->start_admin_panel_grid('settings');
        $this->theme->start_admin_panel_column();
        $this->theme->start_admin_panel('Branding', 'Nazwa i wygląd');
        $this->theme->render_form(
            'index.php?route=/admin/settings',
            [
                [
                    'name' => 'settings_scope',
                    'label' => 'Zakres ustawień',
                    'type' => 'hidden',
                    'value' => 'branding',
                ],
                [
                    'name' => 'public_name',
                    'label' => 'Publiczna nazwa marki',
                    'value' => $values['public_name'],
                    'required' => true,
                    'maxlength' => 80,
                    'autocomplete' => 'organization',
                ],
                [
                    'name' => 'public_eyebrow',
                    'label' => 'Domyślny nadtytuł',
                    'value' => $values['public_eyebrow'],
                    'required' => true,
                    'maxlength' => 160,
                ],
                [
                    'name' => 'public_theme_color',
                    'label' => 'Kolor przeglądarki i urządzenia',
                    'type' => 'color',
                    'value' => $values['public_theme_color'],
                    'required' => true,
                ],
                [
                    'name' => 'public_footer_text',
                    'label' => 'Tekst stopki',
                    'value' => $values['public_footer_text'],
                    'required' => true,
                    'maxlength' => 160,
                ],
            ],
            'Zapisz branding',
            $this->security->csrfToken()
        );
        $this->theme->render_alert(
            'Generator przygotuje favicony 16-256 px, Apple Touch Icon 180 px, ikony aplikacji 192/512 px, plik ICO i manifest. Najlepszy efekt daje kwadratowy PNG z przezroczystym tłem.',
            'info'
        );
        $this->theme->render_form(
            'index.php?route=/admin/settings',
            [
                ['name' => 'settings_scope', 'label' => 'Zakres ustawień', 'type' => 'hidden', 'value' => 'favicon'],
                [
                    'name' => 'favicon',
                    'label' => 'Ikona strony w wysokiej rozdzielczości',
                    'type' => 'file',
                    'accept' => '.png,image/png',
                    'required' => true,
                    'help' => 'PNG od 512 x 512 do 4096 x 4096 px, maksymalnie 8 MiB.',
                ],
            ],
            $values['public_favicon_path'] !== '' ? 'Wygeneruj favicony ponownie' : 'Wygeneruj favicony',
            $this->security->csrfToken()
        );
        $this->theme->end_admin_panel();

        $this->theme->start_admin_panel('Szablon', 'Motyw publiczny');
        $this->theme->render_form(
            'index.php?route=/admin/settings',
            [
                [
                    'name' => 'settings_scope',
                    'label' => 'Zakres ustawień',
                    'type' => 'hidden',
                    'value' => 'theme',
                ],
                [
                    'name' => 'theme',
                    'label' => 'Aktywny motyw',
                    'type' => 'select',
                    'value' => $values['theme'],
                    'options' => $this->availableThemes,
                    'help' => 'Zmiana zacznie obowiązywać od następnego żądania.',
                ],
            ],
            'Zapisz szablon',
            $this->security->csrfToken()
        );
        $this->theme->end_admin_panel();

        $this->theme->start_admin_panel('Cache szablonów', $cache['enabled'] ? 'Aktywny' : 'Wyłączony');
        $this->theme->render_admin_fact_grid([
            ['label' => 'Ważne wpisy', 'value' => (string) $cache['entries'], 'detail' => 'Anonimowe odpowiedzi'],
            ['label' => 'Wygasłe', 'value' => (string) $cache['expired'], 'detail' => 'Zostaną nadpisane'],
            ['label' => 'Rozmiar', 'value' => $this->formatBytes((int) $cache['bytes']), 'detail' => 'Pliki HTML'],
            ['label' => 'TTL', 'value' => (string) $cache['ttl'] . ' s', 'detail' => $cache['writable'] ? 'Katalog zapisywalny' : 'Brak prawa zapisu'],
        ]);
        $this->theme->render_alert(
            'Cache obejmuje anonimowe wejścia na stronę główną oraz obsługiwane podstrony. Zalogowany administrator zawsze otrzymuje świeży widok, dlatego jego wejścia nie zwiększają licznika.',
            'info'
        );
        $this->theme->render_admin_table(['Katalog cache'], [[$cache['directory']]]);
        $this->theme->render_form(
            'index.php?route=/admin/cache/clear',
            [],
            'Wyczyść cache szablonów',
            $this->security->csrfToken()
        );
        $this->theme->end_admin_panel();

        $dashboardDefinitions = $this->dashboard->definitions($this->auth->user()?->permissions ?? []);
        if ($dashboardDefinitions !== []) {
            $dashboardSettings = $this->settings->dashboardWidgetSettings();
            $dashboardFields = [[
                'name' => 'settings_scope',
                'label' => 'Zakres ustawień',
                'type' => 'hidden',
                'value' => 'dashboard',
            ]];
            foreach ($dashboardDefinitions as $definition) {
                $dashboardFields[] = [
                    'name' => 'dashboard_widget_' . $this->publicNavigationFieldKey($definition['id']),
                    'label' => $definition['label'],
                    'type' => 'checkbox',
                    'checked' => $dashboardSettings[$definition['id']] ?? $definition['default_enabled'],
                    'help' => $definition['description'],
                ];
            }
            $this->theme->start_admin_panel('Widżety dashboardu', count($dashboardDefinitions) . ' dostępnych');
            $this->theme->render_form(
                'index.php?route=/admin/settings',
                $dashboardFields,
                'Zapisz widżety',
                $this->security->csrfToken()
            );
            $this->theme->end_admin_panel();
        }
        $this->theme->end_admin_panel_column();

        $this->theme->start_admin_panel_column();

        $this->theme->start_admin_panel('SEO i udostępnianie', 'Indeksowanie i social media');
        $this->theme->render_form(
            'index.php?route=/admin/settings',
            [
                ['name' => 'settings_scope', 'label' => 'Zakres ustawień', 'type' => 'hidden', 'value' => 'seo'],
                [
                    'name' => 'public_url',
                    'label' => 'Bazowy adres kanoniczny',
                    'type' => 'url',
                    'value' => $values['public_url'],
                    'required' => true,
                    'maxlength' => 255,
                    'autocomplete' => 'url',
                    'placeholder' => 'https://syntaxdevteam.pl',
                ],
                [
                    'name' => 'public_default_title',
                    'label' => 'Domyślny tytuł strony głównej',
                    'value' => $values['public_default_title'],
                    'required' => true,
                    'maxlength' => 120,
                ],
                [
                    'name' => 'public_meta_description',
                    'label' => 'Domyślny opis wyników wyszukiwania',
                    'type' => 'textarea',
                    'rows' => 3,
                    'value' => $values['public_meta_description'],
                    'required' => true,
                    'maxlength' => 320,
                ],
                [
                    'name' => 'public_meta_author',
                    'label' => 'Autor / wydawca',
                    'value' => $values['public_meta_author'],
                    'maxlength' => 80,
                    'autocomplete' => 'organization',
                ],
                [
                    'name' => 'public_locale',
                    'label' => 'Język i region',
                    'value' => $values['public_locale'],
                    'required' => true,
                    'maxlength' => 5,
                    'placeholder' => 'pl_PL',
                    'help' => 'Format język_KRAJ, np. pl_PL.',
                ],
                [
                    'name' => 'public_meta_robots',
                    'label' => 'Domyślna polityka indeksowania',
                    'type' => 'select',
                    'value' => $values['public_meta_robots'],
                    'options' => [
                        'index, follow, max-image-preview:large' => 'Indeksuj i zezwalaj na duże podglądy obrazów',
                        'index, follow' => 'Indeksuj standardowo',
                        'noindex, nofollow' => 'Nie indeksuj witryny',
                    ],
                ],
                [
                    'name' => 'public_social_image_url',
                    'label' => 'Obraz Open Graph / social media',
                    'value' => $values['public_social_image_url'],
                    'maxlength' => 500,
                    'placeholder' => '/assets/social-cover.jpg lub https://...',
                    'help' => 'Puste pole użyje logo SyntaxDevTeam. Zalecany obraz ma proporcje 1,91:1.',
                ],
                [
                    'name' => 'public_social_image_alt',
                    'label' => 'Opis obrazu społecznościowego',
                    'value' => $values['public_social_image_alt'],
                    'maxlength' => 200,
                ],
                [
                    'name' => 'public_twitter_site',
                    'label' => 'Nazwa konta X/Twitter',
                    'value' => $values['public_twitter_site'],
                    'maxlength' => 15,
                    'placeholder' => 'SyntaxDevTeam',
                    'help' => 'Bez znaku @.',
                ],
                [
                    'name' => 'public_meta_keywords',
                    'label' => 'Słowa kluczowe meta (zgodność wsteczna)',
                    'value' => $values['public_meta_keywords'],
                    'maxlength' => 255,
                    'help' => 'Google ich nie używa; pole pozostaje dla innych integracji i silników.',
                ],
                [
                    'name' => 'public_google_site_verification',
                    'label' => 'Token Google Search Console',
                    'value' => $values['public_google_site_verification'],
                    'maxlength' => 255,
                ],
                [
                    'name' => 'public_bing_site_verification',
                    'label' => 'Token Bing Webmaster Tools',
                    'value' => $values['public_bing_site_verification'],
                    'maxlength' => 255,
                ],
            ],
            'Zapisz SEO i udostępnianie',
            $this->security->csrfToken()
        );
        $this->theme->end_admin_panel();
        $this->theme->end_admin_panel_column();
        $this->theme->end_admin_panel_grid();

        $this->theme->start_admin_panel_grid('balanced');
        $publicNavigationItems = $this->publicNavigation->items($this->settings->publicNavigationSettings());
        if ($publicNavigationItems !== []) {
            $fields = [
                ['name' => 'settings_scope', 'label' => 'Zakres ustawień', 'type' => 'hidden', 'value' => 'navigation'],
            ];
            foreach ($publicNavigationItems as $item) {
                $key = $this->publicNavigationFieldKey($item['id']);
                $fields[] = [
                    'name' => 'public_nav_' . $key . '_label',
                    'label' => 'Etykieta: ' . $item['default_label'],
                    'value' => $item['label'],
                    'help' => $item['href'],
                ];
                $fields[] = [
                    'name' => 'public_nav_' . $key . '_main',
                    'label' => 'Pokazuj w menu głównym',
                    'type' => 'checkbox',
                    'checked' => $item['show_main'],
                ];
                $fields[] = [
                    'name' => 'public_nav_' . $key . '_footer',
                    'label' => 'Pokazuj w stopce',
                    'type' => 'checkbox',
                    'checked' => $item['show_footer'],
                ];
                $fields[] = [
                    'name' => 'public_nav_' . $key . '_order',
                    'label' => 'Kolejność: ' . $item['default_label'],
                    'type' => 'number',
                    'value' => (string) $item['order'],
                    'help' => 'Niższa liczba przesuwa link wcześniej w menu i stopce.',
                ];
            }
            $this->theme->start_admin_panel('Publiczna nawigacja modułów', count($publicNavigationItems) . ' linków');
            $this->theme->render_form(
                'index.php?route=/admin/settings',
                $fields,
                'Zapisz nawigację',
                $this->security->csrfToken()
            );
            $this->theme->end_admin_panel();
        }

        $this->theme->end_admin_panel_grid();

        $this->theme->start_admin_panel_grid('system');
        $this->theme->start_admin_panel('Konfiguracja chroniona', 'Tylko podgląd zredagowany');
        $this->theme->render_alert(
            'Sekrety pozostają w pliku środowiskowym poza katalogiem publicznym. Panel pokazuje wyłącznie stan ich konfiguracji.',
            'warning'
        );
        $this->theme->render_admin_table(['Obszar', 'Parametr', 'Stan / wartość'], $this->configurationRows());
        $this->theme->end_admin_panel();

        $this->theme->start_admin_panel('Diagnostyka Core', count($this->diagnostics) . ' kontroli');
        $this->theme->render_admin_table(['Element', 'Implementacja', 'Stan'], $this->diagnostics);
        $this->theme->end_admin_panel();
        $this->theme->end_admin_panel_grid();

        $publisherRows = [];
        foreach ($this->trustedModulePublishers as $keyId => $publisher) {
            $publicKey = (string) ($publisher['public_key'] ?? '');
            $publisherRows[] = [
                (string) $keyId,
                (string) ($publisher['name'] ?? 'Nieznany wydawca'),
                (string) ($publisher['status'] ?? 'active'),
                (string) ($publisher['valid_from'] ?? 'Bez ograniczenia'),
                (string) ($publisher['valid_until'] ?? 'Bezterminowo'),
                (string) ($publisher['replacement_key_id'] ?? '—'),
                $publicKey !== '' ? substr(hash('sha256', $publicKey), 0, 16) : 'Brak',
            ];
        }
        $this->theme->start_admin_panel('Zaufani wydawcy modułów', count($publisherRows) . ' klucze');
        $this->theme->render_admin_table(
            ['Key ID', 'Wydawca', 'Stan', 'Ważny od', 'Ważny do', 'Następca', 'Fingerprint'],
            $publisherRows
        );
        $this->theme->end_admin_panel();
        $this->endPage();
    }

    private function saveSettings(Request $request): void
    {
        $actor = $this->auth->user();
        if ($actor === null || $this->settings === null) {
            return;
        }
        if (!$this->security->validateCsrfToken($request->postString('_token'))) {
            $this->audit->record($request, 'system_settings_update', 'invalid_csrf', null, $actor->id);
            http_response_code(403);
            $this->renderSettings('Nieprawidłowy lub wygasły token CSRF.', 'danger');
            return;
        }

        try {
            $scope = $request->postString('settings_scope');
            if ($scope === 'theme') {
                $this->settings->saveThemeChoice($request->postString('theme'), $this->availableThemes, $actor->id);
            } elseif ($scope === 'branding') {
                $this->settings->saveBrandingSettings(
                    [
                        'public_name' => $request->postString('public_name'),
                        'public_eyebrow' => $request->postString('public_eyebrow'),
                        'public_theme_color' => $request->postString('public_theme_color'),
                        'public_footer_text' => $request->postString('public_footer_text'),
                    ],
                    $actor->id
                );
            } elseif ($scope === 'favicon') {
                $file = $request->file('favicon');
                if ($file === null) {
                    throw new \RuntimeException('Wybierz plik PNG z ikoną strony.');
                }
                $app = is_array($this->config['app'] ?? null) ? $this->config['app'] : [];
                $values = $this->settings->themeSettings([
                    'public_name' => (string) ($app['public_name'] ?? 'miniPORTAL'),
                    'public_theme_color' => (string) ($app['public_theme_color'] ?? '#080c12'),
                ]);
                $this->brandIconGenerator->generate(
                    $file,
                    $values['public_name'],
                    $values['public_theme_color']
                );
                $this->settings->saveFaviconSettings('/uploads/branding', (string) time(), $actor->id);
            } elseif ($scope === 'seo') {
                $this->settings->saveSeoSettings(
                    [
                        'public_url' => $request->postString('public_url'),
                        'public_default_title' => $request->postString('public_default_title'),
                        'public_meta_description' => $request->postString('public_meta_description'),
                        'public_meta_author' => $request->postString('public_meta_author'),
                        'public_locale' => $request->postString('public_locale'),
                        'public_meta_robots' => $request->postString('public_meta_robots'),
                        'public_social_image_url' => $request->postString('public_social_image_url'),
                        'public_social_image_alt' => $request->postString('public_social_image_alt'),
                        'public_twitter_site' => $request->postString('public_twitter_site'),
                        'public_meta_keywords' => $request->postString('public_meta_keywords'),
                        'public_google_site_verification' => $request->postString('public_google_site_verification'),
                        'public_bing_site_verification' => $request->postString('public_bing_site_verification'),
                    ],
                    $actor->id
                );
            } elseif ($scope === 'navigation') {
                $publicNavigationSettings = [];
                $publicNavigationLinks = [];
                foreach ($this->publicNavigation->items() as $item) {
                    $key = $this->publicNavigationFieldKey($item['id']);
                    $publicNavigationLinks[$item['id']] = $item['default_label'];
                    $publicNavigationSettings[$item['id']] = [
                        'label' => $request->postString('public_nav_' . $key . '_label', $item['default_label']),
                        'main' => $request->postBool('public_nav_' . $key . '_main'),
                        'footer' => $request->postBool('public_nav_' . $key . '_footer'),
                        'order' => max(0, $request->postInt('public_nav_' . $key . '_order', $item['order']) ?? $item['order']),
                    ];
                }
                $this->settings->savePublicNavigationSettings(
                    $publicNavigationSettings,
                    $publicNavigationLinks,
                    $actor->id
                );
            } elseif ($scope === 'dashboard') {
                $dashboardSettings = [];
                foreach ($this->dashboard->definitions($actor->permissions) as $definition) {
                    $dashboardSettings[$definition['id']] = $request->postBool(
                        'dashboard_widget_' . $this->publicNavigationFieldKey($definition['id'])
                    );
                }
                $this->settings->saveDashboardWidgetSettings($dashboardSettings, $actor->id);
            } else {
                throw new \RuntimeException('Nieznany zakres ustawień.');
            }
            if (in_array($scope, ['theme', 'branding', 'seo', 'navigation', 'favicon'], true)) {
                $this->templateCache->invalidateTags(['theme', 'homepage']);
            }
            $this->audit->record($request, 'system_settings_update', 'success', null, $actor->id);
            header('Location: index.php?route=/admin/settings', true, 303);
        } catch (\Throwable $exception) {
            $this->audit->record($request, 'system_settings_update', 'failed', null, $actor->id);
            $this->renderSettings($exception->getMessage(), 'danger');
        }
    }

    private function publicNavigationFieldKey(string $id): string
    {
        return preg_replace('/[^a-zA-Z0-9_]/', '_', $id) ?? $id;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 1, ',', ' ') . ' KiB';
        }

        return number_format($bytes / 1024 / 1024, 1, ',', ' ') . ' MiB';
    }

    private function clearTemplateCache(Request $request): void
    {
        $actor = $this->auth->user();
        if ($actor === null) {
            return;
        }
        if (!$this->security->validateCsrfToken($request->postString('_token'))) {
            $this->audit->record($request, 'template_cache_clear', 'invalid_csrf', null, $actor->id);
            http_response_code(403);
            $this->renderSettings('Nieprawidłowy lub wygasły token CSRF.', 'danger');
            return;
        }

        $removed = $this->templateCache->clear();
        $this->audit->record($request, 'template_cache_clear', 'success', (string) $removed, $actor->id);
        $this->renderSettings("Usunięto {$removed} plików cache.", 'success');
    }

    private function renderLogs(Request $request): void
    {
        $this->startPage(
            'Dziennik zdarzeń',
            '/admin/logs',
            'Zdarzenia logowania, ACL, operacje administracyjne i działania modułów.'
        );
        if ($this->logs === null) {
            $this->theme->render_alert('Dziennik wymaga aktywnego połączenia z bazą danych.', 'danger');
            $this->endPage();
            return;
        }

        $page = max(1, $request->queryInt('page', 1) ?? 1);
        $perPage = 50;
        $options = $this->logs->filterOptions();
        $filters = $this->logFilters($request, $options);
        $total = $this->logs->count($filters);
        $hiddenRoutineAccess = $this->logs->hiddenRoutineAccessCount();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $events = $this->logs->page($page, $perPage, $filters);
        $rows = array_map(
            static fn (array $event): array => [
                (string) $event['created_at'],
                (string) ($event['display_name'] ?? 'System / gość'),
                (string) $event['event_type'],
                (string) $event['result'],
                (string) ($event['provider'] ?? '—'),
                $event['ip_hash'] !== null ? substr((string) $event['ip_hash'], 0, 12) . '…' : 'Brak',
            ],
            $events
        );

        $eventOptions = ['' => 'Wszystkie zdarzenia'];
        foreach ($options['event_types'] as $eventType) {
            $eventOptions[$eventType] = $eventType;
        }
        $resultOptions = ['' => 'Wszystkie wyniki'];
        foreach ($options['results'] as $result) {
            $resultOptions[$result] = $result;
        }
        $this->theme->start_admin_panel('Filtry', 'GET - bez zmiany danych');
        $this->theme->render_form(
            'index.php',
            [
                ['name' => 'route', 'label' => 'Trasa', 'type' => 'hidden', 'value' => '/admin/logs'],
                [
                    'name' => 'event_type',
                    'label' => 'Typ zdarzenia',
                    'type' => 'select',
                    'value' => $filters['event_type'],
                    'options' => $eventOptions,
                ],
                [
                    'name' => 'result',
                    'label' => 'Wynik',
                    'type' => 'select',
                    'value' => $filters['result'],
                    'options' => $resultOptions,
                ],
                ['name' => 'date_from', 'label' => 'Data od', 'type' => 'date', 'value' => $filters['date_from']],
                ['name' => 'date_to', 'label' => 'Data do', 'type' => 'date', 'value' => $filters['date_to']],
            ],
            'Filtruj',
            '',
            'get'
        );
        $this->theme->render_button('Wyczyść filtry', 'index.php?route=/admin/logs', 'outline-light');
        $this->theme->end_admin_panel();

        $query = http_build_query(array_filter($filters, static fn (string $value): bool => $value !== ''));
        $pageBase = 'index.php?route=/admin/logs' . ($query !== '' ? '&' . $query : '');
        $exportUrl = 'index.php?route=/admin/logs/export' . ($query !== '' ? '&' . $query : '');
        $this->theme->start_admin_panel('Audit log', "{$total} zdarzeń, strona {$page}/{$pages}");
        $this->theme->render_alert(
            "Rutynowe poprawne otwarcia panelu nie są już rejestrowane. Ukryto {$hiddenRoutineAccess} historycznych wpisów admin_access/allowed.",
            'info'
        );
        $this->theme->render_button('Eksportuj bieżący filtr do CSV', $exportUrl, 'outline-light');
        $authConfig = is_array($this->config['auth'] ?? null) ? $this->config['auth'] : [];
        $retentionDays = (int) ($authConfig['audit_retention_days'] ?? 180);
        $archiveLimit = (int) ($authConfig['audit_archive_limit'] ?? 5000);
        $this->theme->render_form(
            'index.php?route=/admin/logs/archive',
            [
                ['name' => 'retention_days', 'label' => 'Retencja dni', 'type' => 'number', 'value' => (string) $retentionDays],
                ['name' => 'limit', 'label' => 'Limit archiwizacji', 'type' => 'number', 'value' => (string) $archiveLimit],
            ],
            'Archiwizuj starsze wpisy',
            $this->security->csrfToken()
        );
        $this->theme->render_admin_table(
            ['Data', 'Użytkownik', 'Zdarzenie', 'Wynik', 'Kontekst', 'Skrót IP'],
            $rows
        );
        if ($page > 1) {
            $this->theme->render_button('Nowsze zdarzenia', $pageBase . '&page=' . ($page - 1), 'outline-light');
        }
        if ($page < $pages) {
            $this->theme->render_button('Starsze zdarzenia', $pageBase . '&page=' . ($page + 1), 'outline-light');
        }
        $this->theme->end_admin_panel();
        $this->endPage();
    }

    private function exportLogs(Request $request): void
    {
        $actor = $this->auth->user();
        if ($actor === null || $this->logs === null) {
            http_response_code(503);
            return;
        }

        try {
            $filters = $this->logFilters($request, $this->logs->filterOptions());
            $events = $this->logs->export($filters);
            $this->audit->record($request, 'audit_export', 'success', 'csv', $actor->id);

            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="miniportal-audit-' . gmdate('Ymd-His') . '.csv"');
            header('Cache-Control: no-store, private');
            header('X-Content-Type-Options: nosniff');

            $stream = fopen('php://output', 'wb');
            (new AuditCsvExporter())->write($stream, $events);
            fclose($stream);
        } catch (\Throwable) {
            $this->audit->record($request, 'audit_export', 'failed', 'csv', $actor->id);
            http_response_code(500);
            echo 'Nie można przygotować eksportu dziennika.';
        }
    }

    private function archiveLogs(Request $request): void
    {
        $actor = $this->auth->user();
        if ($actor === null || $this->logs === null) {
            http_response_code(503);
            return;
        }
        if (!$this->security->validateCsrfToken($request->postString('_token'))) {
            $this->audit->record($request, 'audit_archive', 'invalid_csrf', null, $actor->id);
            http_response_code(403);
            $this->renderLogs($request);
            return;
        }

        try {
            $days = $request->postInt('retention_days', (int) ($this->config['auth']['audit_retention_days'] ?? 180)) ?? 180;
            $limit = $request->postInt('limit', (int) ($this->config['auth']['audit_archive_limit'] ?? 5000)) ?? 5000;
            $result = $this->logs->archiveOlderThan($days, $limit);
            $this->audit->record($request, 'audit_archive', 'success', (string) $result['archived'], $actor->id);
            $this->startPage('Dziennik zdarzeń', '/admin/logs', 'Retencja i archiwizacja dziennika.');
            $this->theme->render_alert(
                'Zarchiwizowano ' . $result['archived'] . ' wpisów starszych niż ' . $result['cutoff'] . '.',
                'success'
            );
            $this->theme->render_button('Wróć do dziennika', 'index.php?route=/admin/logs', 'outline-light');
            $this->endPage();
        } catch (\Throwable $exception) {
            $this->audit->record($request, 'audit_archive', 'failed', null, $actor->id);
            $this->startPage('Dziennik zdarzeń', '/admin/logs', 'Retencja i archiwizacja dziennika.');
            $this->theme->render_alert($exception->getMessage(), 'danger');
            $this->theme->render_button('Wróć do dziennika', 'index.php?route=/admin/logs', 'outline-light');
            $this->endPage();
        }
    }

    /**
     * @return list<list<string>>
     */
    private function configurationRows(): array
    {
        $meta = is_array($this->config['meta'] ?? null) ? $this->config['meta'] : [];
        $app = is_array($this->config['app'] ?? null) ? $this->config['app'] : [];
        $database = is_array($this->config['database'] ?? null) ? $this->config['database'] : [];
        $session = is_array($this->config['session'] ?? null) ? $this->config['session'] : [];
        $auth = is_array($this->config['auth'] ?? null) ? $this->config['auth'] : [];
        $providers = is_array($auth['providers'] ?? null) ? $auth['providers'] : [];
        $rows = [
            ['Środowisko', 'Plik konfiguracji', (string) ($meta['environment_file'] ?? 'Nieznany')],
            ['Środowisko', 'Plik czytelny', ($meta['environment_readable'] ?? false) ? 'Tak' : 'Nie'],
            ['Aplikacja', 'Wersja', (string) ($app['version'] ?? 'Nieznana')],
            ['Aplikacja', 'PHP', PHP_VERSION],
            ['Aplikacja', 'Tryb debug', ($app['debug'] ?? false) ? 'Włączony' : 'Wyłączony'],
            ['Aplikacja', 'Strefa czasowa', (string) ($app['timezone'] ?? 'Nieznana')],
            ['Sesja', 'Nazwa', (string) ($session['name'] ?? 'Nieznana')],
            ['Sesja', 'SameSite', (string) ($session['same_site'] ?? 'Nieznane')],
            ['Baza danych', 'Połączenie', ($database['enabled'] ?? false) ? 'Włączone' : 'Wyłączone'],
            ['Baza danych', 'Sterownik', (string) ($database['database_type'] ?? 'Nieznany')],
            ['Baza danych', 'Serwer', (string) ($database['server'] ?? 'Nieznany')],
            ['Baza danych', 'Nazwa bazy', (string) ($database['database_name'] ?? 'Nieustawiona')],
            ['Baza danych', 'Użytkownik', $this->mask((string) ($database['username'] ?? ''))],
            ['Baza danych', 'Hasło', $this->configured($database['password'] ?? '')],
            ['Baza danych', 'Kodowanie', (string) ($database['charset'] ?? 'Nieznane')],
            ['Uwierzytelnianie', 'Magazyn', (string) ($auth['storage'] ?? 'Nieznany')],
            ['Uwierzytelnianie', 'Klucz HMAC audytu', $this->configured($auth['audit_hash_key'] ?? '')],
        ];
        foreach (['github' => 'GitHub', 'discord' => 'Discord', 'google' => 'Google'] as $key => $label) {
            $provider = is_array($providers[$key] ?? null) ? $providers[$key] : [];
            $rows[] = ['OAuth', "{$label} Client ID", $this->configured($provider['client_id'] ?? '')];
            $rows[] = ['OAuth', "{$label} Client Secret", $this->configured($provider['client_secret'] ?? '')];
            $rows[] = ['OAuth', "{$label} callback", (string) ($provider['callback_url'] ?? 'Nieustawiony')];
        }

        return $rows;
    }

    private function configured(mixed $value): string
    {
        return is_string($value) && trim($value) !== '' ? 'Ustawiono' : 'Brak';
    }

    /**
     * @param list<string> $allowed
     */
    private function allowedFilter(string $value, array $allowed): string
    {
        return in_array($value, $allowed, true) ? $value : '';
    }

    private function dateFilter(string $value): string
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value ? $value : '';
    }

    /**
     * @param array{event_types: list<string>, results: list<string>} $options
     * @return array{event_type: string, result: string, date_from: string, date_to: string}
     */
    private function logFilters(Request $request, array $options): array
    {
        return [
            'event_type' => $this->allowedFilter($request->queryString('event_type'), $options['event_types']),
            'result' => $this->allowedFilter($request->queryString('result'), $options['results']),
            'date_from' => $this->dateFilter($request->queryString('date_from')),
            'date_to' => $this->dateFilter($request->queryString('date_to')),
        ];
    }

    private function mask(string $value): string
    {
        return $value === '' ? 'Brak' : substr($value, 0, 1) . str_repeat('•', min(8, max(3, strlen($value) - 1)));
    }

    private function safeFilename(string $table): string
    {
        $filename = preg_replace('/[^a-z0-9_.-]+/i', '-', $table) ?? 'table';
        $filename = trim($filename, '-.');

        return $filename !== '' ? $filename : 'table';
    }

    private function startPage(string $title, string $activePath, string $lead): void
    {
        $user = $this->auth->user();

        if ($user === null) {
            return;
        }

        $this->theme->start_admin_page(
            $title,
            $this->menu->visibleFor($user->permissions),
            $activePath,
            [
                'name' => $user->displayName,
                'role' => ucfirst($user->primaryRole()),
                'initials' => $user->initials(),
                'avatar_url' => $user->avatarUrl ?? '',
                'logout_action' => 'index.php?route=/admin/logout',
                'logout_token' => $this->security->csrfToken(),
            ]
        );
        $this->theme->start_admin_content(
            $title,
            $lead,
            [
                ['label' => 'Panel', 'href' => 'index.php?route=/admin'],
                ['label' => $title, 'href' => ''],
            ]
        );
    }

    private function endPage(): void
    {
        $this->theme->end_admin_content();
        $this->theme->end_admin_page();
    }

    private function guard(Request $request, string $permission, callable $handler): void
    {
        $decision = $this->access->check($permission);
        $userId = $this->auth->user()?->id;

        if ($decision === AdminAccessGate::UNAUTHENTICATED) {
            $this->audit->record($request, 'admin_access', 'unauthenticated', null);
            http_response_code(401);
            $this->theme->render_admin_access_state(
                401,
                'Wymagane logowanie',
                'Ta trasa panelu jest dostępna wyłącznie dla zalogowanych użytkowników.',
                'index.php?route=/admin/login',
                'Przejdź do logowania'
            );
            return;
        }

        if ($decision === AdminAccessGate::FORBIDDEN) {
            $user = $this->auth->user();
            if ($user !== null && !$user->isActive()) {
                $this->audit->record($request, 'admin_access', 'pending', null, $userId);
                http_response_code(303);
                header('Location: index.php?route=/admin/account-pending', true, 303);
                return;
            }
            $this->audit->record($request, 'admin_access', 'forbidden', null, $userId);
            http_response_code(403);
            $this->theme->render_admin_access_state(
                403,
                'Brak uprawnienia',
                "Twoje konto nie posiada uprawnienia {$permission}.",
                'index.php?route=/admin',
                'Wróć do dashboardu'
            );
            return;
        }

        $handler();
    }
}
