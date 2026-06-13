<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\System;

use SyntaxDevTeam\Cms\Core\AdminMenuRegistry;
use SyntaxDevTeam\Cms\Core\ModuleInterface;
use SyntaxDevTeam\Cms\Core\Request;
use SyntaxDevTeam\Cms\Core\Router;
use SyntaxDevTeam\Cms\Core\Security;
use SyntaxDevTeam\Cms\Core\ThemeInterface;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AdminAccessGate;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuthService;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuditLogService;

final class DemoAdminModule implements ModuleInterface
{
    public function __construct(
        private readonly ThemeInterface $theme,
        private readonly AdminMenuRegistry $menu,
        private readonly AuthService $auth,
        private readonly AdminAccessGate $access,
        private readonly Security $security,
        private readonly AuditLogService $audit,
    ) {
    }

    public function id(): string
    {
        return 'system_demo_admin';
    }

    public function requiredPermissions(): array
    {
        return ['admin.access'];
    }

    public function registerAdminMenu(AdminMenuRegistry $menu): void
    {
        $menu->add('Przestrzeń robocza', 'Dashboard', '/admin', 'DB', 'admin.access', 10);
        $menu->add('Treść', 'Artykuły', '/admin/articles', 'AR', 'articles.view', 30);
        $menu->add('System', 'Użytkownicy', '/admin/users', 'US', 'users.view', 40);
        $menu->add('System', 'Moduły', '/admin/modules', 'MD', 'modules.view', 50);
        $menu->add('Profil', 'Połączone konta', '/admin/identities', 'ID', 'admin.access', 60);
    }

    public function registerRoutes(Router $router): void
    {
        $router->get('/admin', fn (Request $request) => $this->guard($request, 'admin.access', fn () => $this->renderDashboard()));
        $router->get('/admin/articles', fn (Request $request) => $this->guard($request, 'articles.view', fn () => $this->renderSection(
            'Artykuły',
            '/admin/articles',
            'articles.view',
            'Ta trasa demonstruje niezależną pozycję menu i osobne uprawnienie modułu.'
        )));
        $router->get('/admin/users', fn (Request $request) => $this->guard($request, 'users.view', fn () => $this->renderSection(
            'Użytkownicy',
            '/admin/users',
            'users.view',
            'Administrator może zarządzać użytkownikami, redaktor otrzyma dla tej trasy odpowiedź 403.'
        )));
        $router->get('/admin/modules', fn (Request $request) => $this->guard($request, 'modules.view', fn () => $this->renderSection(
            'Moduły',
            '/admin/modules',
            'modules.view',
            'Widok managera modułów jest dostępny wyłącznie użytkownikom z właściwym uprawnieniem.'
        )));
    }

    private function renderDashboard(): void
    {
        $visibleMenu = $this->menu->visibleFor($this->auth->user()?->permissions ?? []);

        $this->startPage(
            'Dashboard',
            '/admin',
            'Menu po lewej zostało zarejestrowane przez moduły i odfiltrowane według przykładowych uprawnień.'
        );

        $this->theme->start_admin_metrics();
        $this->theme->render_admin_metric('Widoczne pozycje menu', (string) count($visibleMenu), 'ACL', 'Zależne od uprawnień');
        $this->theme->render_admin_metric('Moduł demonstracyjny', $this->id(), 'MOD', 'Rejestruje trasę i menu');
        $this->theme->render_admin_metric('Wymagane uprawnienie', 'admin.access', 'ACL', 'Deklarowane przez moduł');
        $this->theme->render_admin_metric('Warstwa HTML', 'Theme', 'UI', 'Moduł nie zna znaczników');
        $this->theme->end_admin_metrics();

        $this->theme->start_admin_panel('Kontrakt modułu', 'Krok 5B');
        $this->theme->render_admin_table(
            ['Odpowiedzialność', 'Właściciel'],
            [
                ['Rejestracja trasy', 'ModuleInterface + Router'],
                ['Pozycje menu', 'AdminMenuRegistry'],
                ['Filtrowanie menu', 'Lista uprawnień użytkownika'],
                ['Układ i HTML', 'ThemeInterface'],
            ]
        );
        $this->theme->end_admin_panel();

        $this->endPage();
    }

    private function renderSection(string $title, string $path, string $permission, string $description): void
    {
        $this->startPage($title, $path, $description);
        $this->theme->start_admin_panel('Rejestracja modułu', $permission);
        $this->theme->render_admin_table(
            ['Element', 'Wartość'],
            [
                ['Moduł', $this->id()],
                ['Trasa', $path],
                ['Uprawnienie menu', $permission],
                ['Stan', 'Prototyp kontraktu'],
            ]
        );
        $this->theme->end_admin_panel();
        $this->endPage();
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
