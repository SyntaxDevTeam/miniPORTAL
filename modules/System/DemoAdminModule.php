<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\System;

use SyntaxDevTeam\Cms\Core\AdminMenuRegistry;
use SyntaxDevTeam\Cms\Core\ModuleInterface;
use SyntaxDevTeam\Cms\Core\Router;
use SyntaxDevTeam\Cms\Core\ThemeInterface;

final class DemoAdminModule implements ModuleInterface
{
    /**
     * @param list<string> $permissions
     */
    public function __construct(
        private readonly ThemeInterface $theme,
        private readonly AdminMenuRegistry $menu,
        private readonly array $permissions,
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
        $menu->add('Przestrzeń robocza', 'Dashboard', '/admin-demo', 'DB', 'admin.access', 10);
        $menu->add('Treść', 'Strony', '/admin-demo/pages', 'PG', 'pages.view', 20);
        $menu->add('Treść', 'Artykuły', '/admin-demo/articles', 'AR', 'articles.view', 30);
        $menu->add('System', 'Użytkownicy', '/admin-demo/users', 'US', 'users.view', 40);
        $menu->add('System', 'Moduły', '/admin-demo/modules', 'MD', 'modules.view', 50);
    }

    public function registerRoutes(Router $router): void
    {
        $router->get('/admin-demo', fn () => $this->renderDashboard());
        $router->get('/admin-demo/pages', fn () => $this->renderSection(
            'Strony',
            '/admin-demo/pages',
            'pages.view',
            'Ta trasa została zarejestrowana przez moduł. Pełny CRUD powstanie w module core_pages.'
        ));
        $router->get('/admin-demo/articles', fn () => $this->renderSection(
            'Artykuły',
            '/admin-demo/articles',
            'articles.view',
            'Ta trasa demonstruje niezależną pozycję menu i osobne uprawnienie modułu.'
        ));
    }

    private function renderDashboard(): void
    {
        $visibleMenu = $this->menu->visibleFor($this->permissions);

        $this->startPage(
            'Dashboard',
            '/admin-demo',
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
        $this->theme->start_admin_page(
            $title,
            $this->menu->visibleFor($this->permissions),
            $activePath,
            ['name' => 'WieszczY', 'role' => 'Administrator', 'initials' => 'WY']
        );
        $this->theme->start_admin_content(
            $title,
            $lead,
            [
                ['label' => 'Panel', 'href' => 'index.php?route=/admin-demo'],
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
}
