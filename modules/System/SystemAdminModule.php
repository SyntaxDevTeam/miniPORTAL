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
    ) {
    }

    public function id(): string
    {
        return 'system_admin';
    }

    public function version(): string
    {
        return '1.0.0';
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
        return ['admin.access'];
    }

    public function registerAdminMenu(AdminMenuRegistry $menu): void
    {
        $menu->add('Przestrzeń robocza', 'Dashboard', '/admin', 'DB', 'admin.access', 10);
        $menu->add('System', 'Moduły', '/admin/modules', 'MD', 'modules.view', 50);
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
                    $loadable ? 'Fabryka gotowa' : 'Brak fabryki',
                    $state->dataPreserved
                        ? 'Dane zachowane'
                        : ($pending === [] ? 'Brak' : implode(', ', $pending)),
                ],
                'actions' => $actions,
            ];
        }

        $this->theme->start_admin_panel('Rejestr modułów', count($rows) . ' manifesty');
        $this->theme->render_admin_action_table(
            ['Moduł', 'Wersja zapisana / kodu', 'Stan', 'Ochrona', 'Uruchamianie', 'Oczekujące migracje'],
            $rows,
            $this->security->csrfToken()
        );
        $this->theme->end_admin_panel();
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

        $this->audit->record($request, 'admin_access', 'allowed', null, $userId);
        $handler();
    }
}
