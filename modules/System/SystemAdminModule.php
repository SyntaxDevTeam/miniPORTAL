<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\System;

use SyntaxDevTeam\Cms\Core\AdminMenuRegistry;
use SyntaxDevTeam\Cms\Core\ModuleInterface;
use SyntaxDevTeam\Cms\Core\ModuleManagerService;
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
        private readonly ?SystemSettingsRepository $settings,
        private readonly ?SystemLogRepository $logs,
        private readonly array $config,
        private readonly array $diagnostics,
        private readonly array $availableThemes,
        private readonly TemplateCacheInterface $templateCache,
        private readonly array $trustedModulePublishers,
    ) {
    }

    public function id(): string
    {
        return 'system_admin';
    }

    public function version(): string
    {
        return '1.3.0';
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
        $menu->add('System', 'Ustawienia', '/admin/settings', 'ST', 'settings.manage', 55);
        $menu->add('System', 'Dziennik zdarzeń', '/admin/logs', 'LG', 'logs.view', 60);
        $menu->add('System', 'Wzorce UI', '/admin/design-system', 'UI', 'admin.access', 70);
    }

    public function registerRoutes(Router $router): void
    {
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
        $router->get('/admin/design-system', fn (Request $request) => $this->guard(
            $request,
            'admin.access',
            fn () => $this->renderResources()
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
    }

    private function renderDashboard(): void
    {
        $visibleMenu = $this->menu->visibleFor($this->auth->user()?->permissions ?? []);
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

        $this->startPage(
            'Dashboard',
            '/admin',
            'Stan uruchomionych modułów, migracji i menu wynikający z bieżącej konfiguracji.'
        );

        $this->theme->start_admin_metrics();
        $this->theme->render_admin_metric('Widoczne pozycje menu', (string) count($visibleMenu), 'ACL', 'Zależne od uprawnień');
        $this->theme->render_admin_metric(
            'Aktywne moduły',
            (string) $activeModules,
            'MOD',
            count($moduleEntries) . ' wykrytych, ' . $invalidModules . ' z błędem'
        );
        $this->theme->render_admin_metric('Oczekujące migracje', (string) $pendingMigrations, 'SQL', 'Kontrola SHA-256');
        $this->theme->render_admin_metric('Warstwa HTML', 'Theme', 'UI', 'Moduł nie zna znaczników');
        $this->theme->end_admin_metrics();

        $this->theme->start_admin_panel('Stan architektury', 'Krok 6');
        $this->theme->render_admin_table(
            ['Odpowiedzialność', 'Właściciel'],
            [
                ['Rejestracja trasy', 'ModuleInterface + Router'],
                ['Pozycje menu', 'AdminMenuRegistry'],
                ['Filtrowanie menu', 'Lista uprawnień użytkownika'],
                ['Układ i HTML', 'ThemeInterface'],
                ['Stan modułów', 'modules_config'],
                ['Historia migracji', 'module_migrations'],
            ]
        );
        $this->theme->end_admin_panel();

        $this->endPage();
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

    private function renderResources(): void
    {
        $user = $this->auth->user();

        if ($user === null) {
            return;
        }

        $this->theme->render_admin_resources(
            [
                [
                    'label' => 'Strona główna - prototyp',
                    'href' => 'templates/default/homepage.html',
                    'description' => 'Pierwotne źródło wyglądu dynamicznej strony głównej.',
                ],
                [
                    'label' => 'Stylebook publiczny',
                    'href' => 'templates/default/stylebook.html',
                    'description' => 'Karty, formularze, tabele, alerty i typografia motywu.',
                ],
                [
                    'label' => 'Stylebook panelu',
                    'href' => 'templates/default/admin-stylebook.html',
                    'description' => 'Dashboard, sidebar, formularze i stany panelu administracyjnego.',
                ],
                [
                    'label' => 'Test Security / Request',
                    'href' => 'index.php?route=/security-demo',
                    'description' => 'Dynamiczny test CSRF, normalizacji wejścia i warstwy Theme.',
                ],
            ],
            $this->menu->visibleFor($user->permissions),
            [
                'name' => $user->displayName,
                'role' => ucfirst($user->primaryRole()),
                'initials' => $user->initials(),
                'logout_action' => 'index.php?route=/admin/logout',
                'logout_token' => $this->security->csrfToken(),
            ]
        );
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
            'public_name' => (string) ($app['public_name'] ?? 'SyntaxDevTeam'),
            'public_eyebrow' => (string) ($app['public_eyebrow'] ?? 'Software dla społeczności'),
        ]);
        $this->theme->start_admin_panel('Szablon i branding', 'Bezpieczne do edycji');
        $this->theme->render_form(
            'index.php?route=/admin/settings',
            [
                [
                    'name' => 'theme',
                    'label' => 'Aktywny motyw',
                    'type' => 'select',
                    'value' => $values['theme'],
                    'options' => $this->availableThemes,
                    'help' => 'Zmiana zacznie obowiązywać od następnego żądania.',
                ],
                [
                    'name' => 'public_name',
                    'label' => 'Publiczna nazwa marki',
                    'value' => $values['public_name'],
                ],
                [
                    'name' => 'public_eyebrow',
                    'label' => 'Domyślny nadtytuł',
                    'value' => $values['public_eyebrow'],
                ],
            ],
            'Zapisz ustawienia',
            $this->security->csrfToken()
        );
        $this->theme->end_admin_panel();

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

        $cache = $this->templateCache->stats();
        $this->theme->start_admin_panel('Cache szablonów', $cache['enabled'] ? 'Aktywny' : 'Wyłączony');
        $this->theme->render_admin_table(
            ['Parametr', 'Wartość'],
            [
                ['Liczba wpisów', (string) $cache['entries']],
                ['Rozmiar', (string) $cache['bytes'] . ' B'],
                ['Katalog', $cache['directory']],
            ]
        );
        $this->theme->render_form(
            'index.php?route=/admin/cache/clear',
            [],
            'Wyczyść cache szablonów',
            $this->security->csrfToken()
        );
        $this->theme->end_admin_panel();

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
            $this->settings->saveThemeSettings(
                [
                    'theme' => $request->postString('theme'),
                    'public_name' => $request->postString('public_name'),
                    'public_eyebrow' => $request->postString('public_eyebrow'),
                ],
                $this->availableThemes,
                $actor->id
            );
            $this->templateCache->invalidateTags(['theme']);
            $this->audit->record($request, 'system_settings_update', 'success', null, $actor->id);
            header('Location: index.php?route=/admin/settings', true, 303);
        } catch (\Throwable $exception) {
            $this->audit->record($request, 'system_settings_update', 'failed', null, $actor->id);
            $this->renderSettings($exception->getMessage(), 'danger');
        }
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
            ],
            ['label' => 'Admin stylebook', 'href' => 'templates/default/admin-stylebook.html']
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
