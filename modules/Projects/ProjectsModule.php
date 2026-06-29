<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\Projects;

use SyntaxDevTeam\Cms\Core\AdminMenuRegistry;
use SyntaxDevTeam\Cms\Core\AdminSearchProviderInterface;
use SyntaxDevTeam\Cms\Core\AdminSearchRegistry;
use SyntaxDevTeam\Cms\Core\DashboardProviderInterface;
use SyntaxDevTeam\Cms\Core\DashboardRegistry;
use SyntaxDevTeam\Cms\Core\ModuleInterface;
use SyntaxDevTeam\Cms\Core\PublicNavigationProviderInterface;
use SyntaxDevTeam\Cms\Core\PublicNavigationRegistry;
use SyntaxDevTeam\Cms\Core\Request;
use SyntaxDevTeam\Cms\Core\Router;
use SyntaxDevTeam\Cms\Core\Security;
use SyntaxDevTeam\Cms\Core\ThemeInterface;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuditLogService;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuthService;
use SyntaxDevTeam\Cms\Modules\CoreAuth\User;

final class ProjectsModule implements ModuleInterface, PublicNavigationProviderInterface, AdminSearchProviderInterface, DashboardProviderInterface
{
    private const STATUSES = [
        'planned' => 'Planowany',
        'development' => 'W trakcie tworzenia',
        'released' => 'Publicznie wydany',
        'paused' => 'Wstrzymany',
    ];

    private const PUBLIC_STATUSES = [
        'planned' => 'Planned',
        'development' => 'In development',
        'released' => 'Publicly released',
        'paused' => 'Paused',
    ];

    public function __construct(
        private readonly ThemeInterface $theme,
        private readonly AdminMenuRegistry $menu,
        private readonly ProjectRepository $projects,
        private readonly AuthService $auth,
        private readonly Security $security,
        private readonly AuditLogService $audit,
    ) {
    }

    public function id(): string { return 'projects'; }
    public function version(): string { return '1.2.0'; }
    public function dependencies(): array { return ['core_auth', 'core_pages', 'wikipedia']; }
    public function isProtected(): bool { return false; }
    public function requiredPermissions(): array { return ['projects.view', 'projects.manage']; }

    public function registerAdminMenu(AdminMenuRegistry $menu): void
    {
        $menu->add('Treść', 'Projekty', '/admin/projects', 'PR', 'projects.view', 37);
    }

    public function registerPublicNavigation(PublicNavigationRegistry $navigation): void
    {
        $navigation->add('projects.index', 'Projects', '/projects', 'main', 55);
    }

    public function registerAdminSearch(AdminSearchRegistry $search): void
    {
        $search->add('projects.create', 'Dodaj projekt', 'Utwórz pozycję publicznego katalogu projektów.', 'index.php?route=/admin/projects/create', ['projekt', 'nowy', 'katalog'], 'projects.manage', 'Treść', 37);
    }

    public function registerDashboard(DashboardRegistry $dashboard): void
    {
        $dashboard->addMetric('projects.catalog', 'Projekty', 'Stan katalogu projektów.', 'PR', function (): array {
            $all = $this->projects->all();
            $published = count(array_filter($all, static fn (Project $project): bool => $project->published));
            return ['value' => $published, 'detail' => count($all) . ' wszystkich projektów'];
        }, 'projects.view', 110);
    }

    public function registerRoutes(Router $router): void
    {
        $router->get('/projects', fn () => $this->renderPublicList());
        $router->get('/projects/project', fn (Request $request) => $this->renderPublicDetail($request->queryString('slug')));
        $router->get('/projects/{slug}', fn (Request $request) => $this->renderPublicDetail($request->routeString('slug')));
        $router->get('/admin/projects', fn (Request $request) => $this->guard($request, 'projects.view', fn () => $this->renderAdminList()));
        $router->get('/admin/projects/create', fn (Request $request) => $this->guard($request, 'projects.manage', fn () => $this->renderForm()));
        $router->post('/admin/projects/create', fn (Request $request) => $this->guard($request, 'projects.manage', fn () => $this->save($request)));
        $router->get('/admin/projects/edit', fn (Request $request) => $this->guard($request, 'projects.manage', fn () => $this->renderEdit($request)));
        $router->post('/admin/projects/edit', fn (Request $request) => $this->guard($request, 'projects.manage', fn () => $this->save($request, $this->projects->find($request->postInt('id', 0) ?? 0))));
        $router->post('/admin/projects/delete', fn (Request $request) => $this->guard($request, 'projects.manage', fn () => $this->delete($request)));
    }

    private function renderPublicList(): void
    {
        $projects = $this->projects->all(true);
        $this->theme->start_page('Projects - SyntaxDevTeam', 'Public and actively developed SyntaxDevTeam projects.');
        $this->theme->start_header('Projects', 'Released solutions, active development and team plans.', 'SyntaxDevTeam / Projects');
        $this->theme->end_header();
        $this->theme->start_section();
        if ($projects === []) {
            $this->theme->render_alert('The project catalog is empty for now.', 'info');
        } else {
            $columnSize = $this->publicColumnSize(count($projects));
            $this->theme->start_grid();
            foreach ($projects as $project) {
                $this->theme->start_column($columnSize);
                $this->theme->start_card($project->name, self::PUBLIC_STATUSES[$project->lifecycleStatus]);
                $this->theme->render_link_list($this->publicLinks($project));
                $this->theme->end_card();
                $this->theme->end_column();
            }
            $this->theme->end_grid();
        }
        $this->theme->end_section();
        $this->theme->end_page();
    }

    private function renderPublicDetail(string $slug): void
    {
        $project = $this->projects->findPublishedBySlug($slug);
        if (!$project instanceof Project) {
            $this->theme->render_public_error(404, 'Project not found', 'This project is not publicly available.', 'Back to projects', '/projects');
            return;
        }
        $this->theme->start_page($project->name . ' - Projects', 'Related resources for ' . $project->name . '.');
        $this->theme->start_header($project->name, self::PUBLIC_STATUSES[$project->lifecycleStatus], 'SyntaxDevTeam / Projects');
        $this->theme->end_header();
        $this->theme->start_section();
        $this->theme->start_card('Project resources', 'Status: ' . self::PUBLIC_STATUSES[$project->lifecycleStatus]);
        $this->theme->render_link_list($this->publicLinks($project));
        $this->theme->end_card();
        $this->theme->end_section();
        $this->theme->end_page();
    }

    private function renderAdminList(string $message = '', string $variant = 'info'): void
    {
        $this->startAdminPage('Projekty', 'Katalog łączy status projektu z istniejącą podstroną i dokumentacją.', [[
            'label' => 'Dodaj projekt', 'href' => 'index.php?route=/admin/projects/create', 'variant' => 'primary',
        ], ['label' => 'Publiczny katalog', 'href' => '/projects', 'variant' => 'outline-light']]);
        if ($message !== '') { $this->theme->render_alert($message, $variant); }
        $projects = $this->projects->all();
        $this->theme->start_admin_panel('Katalog projektów', count($projects) . ' pozycji');
        if ($projects === []) {
            $this->theme->render_alert('Nie dodano jeszcze projektów.', 'info');
        } else {
            $this->theme->render_admin_action_table(['Kolejność', 'Nazwa', 'Status', 'Publikacja', 'Podstrona', 'Wiki'], array_map(
                static fn (Project $project): array => [
                    'cells' => [$project->sortOrder, $project->name, self::STATUSES[$project->lifecycleStatus], $project->published ? 'Publiczny' : 'Ukryty', $project->pageTitle ?: 'Brak', $project->wikiName ?: 'Brak'],
                    'actions' => [[
                        'label' => 'Edytuj', 'href' => 'index.php?route=/admin/projects/edit&id=' . $project->id, 'variant' => 'primary',
                    ], [
                        'label' => 'Usuń', 'action' => 'index.php?route=/admin/projects/delete', 'fields' => ['id' => $project->id], 'variant' => 'danger', 'confirm' => 'Usunąć projekt z katalogu?',
                    ]],
                ], $projects
            ), $this->security->csrfToken());
        }
        $this->theme->end_admin_panel();
        $this->endAdminPage();
    }

    private function renderEdit(Request $request): void
    {
        $project = $this->projects->find($request->queryInt('id', 0) ?? 0);
        $project instanceof Project ? $this->renderForm($project) : $this->renderAdminList('Nie znaleziono projektu.', 'danger');
    }

    private function renderForm(?Project $project = null, string $message = '', string $variant = 'info'): void
    {
        $this->startAdminPage($project === null ? 'Dodaj projekt' : 'Edytuj projekt', 'Treści pozostają w podstronie i Wiki; katalog przechowuje ich powiązania.', [[
            'label' => 'Wróć do projektów', 'href' => 'index.php?route=/admin/projects', 'variant' => 'outline-light',
        ]]);
        if ($message !== '') { $this->theme->render_alert($message, $variant); }
        $pages = ['0' => 'Brak powiązanej podstrony'] + $this->stringOptions($this->projects->pageOptions());
        $wiki = ['0' => 'Brak dokumentacji'] + $this->stringOptions($this->projects->wikiOptions());
        $fields = $project !== null ? [['name' => 'id', 'label' => 'ID', 'type' => 'hidden', 'value' => (string) $project->id]] : [];
        $fields = [...$fields,
            ['name' => 'name', 'label' => 'Nazwa projektu', 'value' => $project?->name ?? ''],
            ['name' => 'slug', 'label' => 'Slug', 'value' => $project?->slug ?? '', 'help' => 'Puste pole wygeneruje slug z nazwy.'],
            ['name' => 'lifecycle_status', 'label' => 'Stan projektu', 'type' => 'select', 'value' => $project?->lifecycleStatus ?? 'planned', 'options' => self::STATUSES],
            ['name' => 'page_id', 'label' => 'Powiązana podstrona', 'type' => 'select', 'value' => (string) ($project?->pageId ?? 0), 'options' => $pages],
            ['name' => 'wiki_project_id', 'label' => 'Powiązany projekt Wiki', 'type' => 'select', 'value' => (string) ($project?->wikiProjectId ?? 0), 'options' => $wiki],
            ['name' => 'sort_order', 'label' => 'Kolejność', 'type' => 'number', 'value' => (string) ($project?->sortOrder ?? 100)],
            ['name' => 'is_published', 'label' => 'Widoczny publicznie', 'type' => 'checkbox', 'checked' => $project?->published ?? false],
        ];
        $this->theme->start_admin_panel('Dane projektu', 'Katalog /projects');
        $this->theme->render_form('index.php?route=' . ($project === null ? '/admin/projects/create' : '/admin/projects/edit'), $fields, $project === null ? 'Dodaj projekt' : 'Zapisz projekt', $this->security->csrfToken());
        $this->theme->end_admin_panel();
        $this->endAdminPage();
    }

    private function save(Request $request, ?Project $project = null): void
    {
        $actor = $this->auth->user();
        if ($project === null && $request->postInt('id', 0)) {
            $this->renderAdminList('Nie znaleziono projektu.', 'danger'); return;
        }
        if (!$this->security->validateCsrfToken($request->postString('_token'))) {
            $this->audit->record($request, 'project_save', 'invalid_csrf', 'projects', $actor?->id);
            $this->renderForm($project, 'Token CSRF jest nieprawidłowy lub wygasł.', 'danger'); return;
        }
        $name = $this->bounded($request->postString('name'), 180);
        $status = $request->postString('lifecycle_status');
        $slug = $this->slugify($request->postString('slug') ?: $name);
        if ($name === '' || $slug === '' || !isset(self::STATUSES[$status])) {
            $this->renderForm($project, 'Uzupełnij nazwę, slug i poprawny status.', 'warning'); return;
        }
        if ($this->projects->slugExists($slug, $project?->id)) {
            $this->renderForm($project, 'Ten slug projektu jest już używany.', 'warning'); return;
        }
        $data = [
            'name' => $name, 'slug' => $slug, 'summary' => '', 'lifecycle_status' => $status,
            'page_id' => ($request->postInt('page_id', 0) ?: null),
            'wiki_project_id' => ($request->postInt('wiki_project_id', 0) ?: null),
            'sort_order' => max(0, $request->postInt('sort_order', 100) ?? 100),
            'is_published' => $request->postBool('is_published') ? 1 : 0,
        ];
        try {
            if ($project === null) {
                $id = $this->projects->create($data);
                $this->audit->record($request, 'project_create', 'success', 'project:' . $id, $actor?->id);
            } else {
                $this->projects->update($project->id, $data);
                $this->audit->record($request, 'project_update', 'success', 'project:' . $project->id, $actor?->id);
            }
            $this->renderAdminList('Projekt został zapisany.', 'success');
        } catch (\Throwable $exception) {
            $this->audit->record($request, 'project_save', 'failed', 'projects', $actor?->id);
            $this->renderForm($project, 'Nie udało się zapisać projektu: ' . $exception->getMessage(), 'danger');
        }
    }

    private function delete(Request $request): void
    {
        $actor = $this->auth->user();
        if (!$this->security->validateCsrfToken($request->postString('_token'))) {
            $this->audit->record($request, 'project_delete', 'invalid_csrf', 'projects', $actor?->id);
            $this->renderAdminList('Token CSRF jest nieprawidłowy lub wygasł.', 'danger'); return;
        }
        $id = $request->postInt('id', 0) ?? 0;
        $ok = $this->projects->delete($id);
        $this->audit->record($request, 'project_delete', $ok ? 'success' : 'failed', 'project:' . $id, $actor?->id);
        $this->renderAdminList($ok ? 'Projekt został usunięty.' : 'Nie udało się usunąć projektu.', $ok ? 'success' : 'danger');
    }

    private function guard(Request $request, string $permission, callable $handler): void
    {
        $user = $this->auth->user();
        if (!$user instanceof User) {
            http_response_code(401);
            $this->theme->render_admin_access_state(401, 'Wymagane logowanie', 'Zarządzanie projektami wymaga aktywnej sesji.', 'index.php?route=/admin/login', 'Przejdź do logowania'); return;
        }
        if (!in_array('*', $user->permissions, true) && !in_array($permission, $user->permissions, true)) {
            $this->audit->record($request, 'admin_access', 'denied', $permission, $user->id);
            http_response_code(403);
            $this->theme->render_admin_access_state(403, 'Brak uprawnień', 'Twoje konto nie ma uprawnienia: ' . $permission, 'index.php?route=/admin', 'Wróć do panelu'); return;
        }
        $handler();
    }

    private function startAdminPage(string $title, string $lead, ?array $actions = null): void
    {
        $user = $this->auth->user();
        $this->theme->start_admin_page($title, $this->menu->visibleFor($user?->permissions ?? []), '/admin/projects', [
            'name' => $user?->displayName ?? 'Gość', 'role' => $user?->primaryRole() ?? 'Gość',
            'initials' => $user?->initials() ?? 'G', 'avatar_url' => $user?->avatarUrl ?? '',
            'logout_action' => 'index.php?route=/admin/logout', 'logout_token' => $this->security->csrfToken(),
        ]);
        $this->theme->start_admin_content($title, $lead, [['label' => 'Panel', 'href' => 'index.php?route=/admin'], ['label' => 'Projekty', 'href' => 'index.php?route=/admin/projects']], $actions);
    }

    private function endAdminPage(): void { $this->theme->end_admin_content(); $this->theme->end_admin_page(); }
    private function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        return substr(trim(preg_replace('/[^a-z0-9]+/', '-', $value) ?? '', '-'), 0, 191);
    }
    private function bounded(string $value, int $max): string { return function_exists('mb_substr') ? mb_substr(trim($value), 0, $max) : substr(trim($value), 0, $max); }

    private function publicColumnSize(int $count): string
    {
        return match ($count) {
            1 => '12',
            2, 4 => 'lg-6',
            default => 'lg-4',
        };
    }

    /** @return list<array{label: string, href: string, meta: string}> */
    private function publicLinks(Project $project): array
    {
        $links = [];
        if ($project->pageStatus === 'published') {
            $links[] = ['label' => 'Project page', 'href' => '/p/' . rawurlencode($project->pageSlug), 'meta' => $project->pageTitle];
        }
        if ($project->wikiStatus === 'published') {
            $links[] = ['label' => 'Documentation', 'href' => '/wiki/project/' . rawurlencode($project->wikiSlug), 'meta' => $project->wikiName];
        }
        $links[] = ['label' => 'Build Explorer', 'href' => '/builds/' . rawurlencode($project->slug), 'meta' => 'Versions and downloadable files'];

        return $links;
    }
    /** @param array<int, string> $options @return array<string, string> */
    private function stringOptions(array $options): array
    {
        $result = []; foreach ($options as $id => $label) { $result[(string) $id] = $label; } return $result;
    }
}
