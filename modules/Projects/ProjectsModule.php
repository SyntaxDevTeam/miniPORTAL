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
use SyntaxDevTeam\Cms\Core\SeoIndex;
use SyntaxDevTeam\Cms\Core\SeoProviderInterface;
use SyntaxDevTeam\Cms\Core\ThemeInterface;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuditLogService;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuthService;
use SyntaxDevTeam\Cms\Modules\CoreAuth\User;
use SyntaxDevTeam\Cms\Modules\MediaLibrary\MediaAssetRepository;

final class ProjectsModule implements \SyntaxDevTeam\Cms\Core\HookProviderInterface, ModuleInterface, PublicNavigationProviderInterface, AdminSearchProviderInterface, DashboardProviderInterface, SeoProviderInterface
{
    private const STATUSES = [
        'planned' => 'Planowany',
        'development' => 'W trakcie tworzenia',
        'released' => 'Publicznie wydany',
        'commissioned' => 'Zrealizowany na zamówienie',
        'paused' => 'Wstrzymany',
    ];

    private const PUBLIC_STATUSES = [
        'planned' => 'Planned',
        'development' => 'In development',
        'released' => 'Publicly released',
        'commissioned' => 'Completed to order',
        'paused' => 'Paused',
    ];

    public function __construct(
        private readonly ThemeInterface $theme,
        private readonly AdminMenuRegistry $menu,
        private readonly ProjectRepository $projects,
        private readonly AuthService $auth,
        private readonly Security $security,
        private readonly AuditLogService $audit,
        private readonly ?MediaAssetRepository $mediaAssets = null,
        private readonly ?ProjectLanguageSourceStore $languageSources = null,
        private readonly ?\SyntaxDevTeam\Cms\Core\HookRegistry $hooks = null,
    ) {
    }

    public function id(): string { return 'projects'; }
    public function version(): string { return '1.6.0'; }
    public function dependencies(): array { return ['core_auth', 'core_pages', 'wikipedia']; }
    public function isProtected(): bool { return false; }
    public function requiredPermissions(): array { return ['projects.view', 'projects.manage']; }

    public function registerHooks(\SyntaxDevTeam\Cms\Core\HookRegistry $hooks): void
    {
        if ($this->languageSources !== null) $hooks->addFilter('projects.language_source_provider', fn ($current) => $this->languageSources);
    }
    private function canManageSources(): bool
    {
        $user=$this->auth->user();
        return $user!==null && $user->isActive() && $this->auth->rolePreview()===null && array_intersect($user->roles,['owner','administrator'])!==[];
    }

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
        $search->add('projects.categories', 'Kategorie projektów', 'Twórz i porządkuj kategorie katalogu projektów.', 'index.php?route=/admin/projects/categories', ['projekt', 'kategoria', 'katalog'], 'projects.manage', 'Treść', 38);
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
        $router->post('/admin/projects/sources', fn (Request $request) => $this->guard($request, 'projects.manage', fn () => $this->saveLanguageSources($request)));
        $router->get('/projects', fn () => $this->renderPublicList());
        $router->get('/projects/project', fn (Request $request) => $this->renderPublicDetail($request->queryString('slug')));
        $router->get('/projects/{slug}', fn (Request $request) => $this->renderPublicDetail($request->routeString('slug')));
        $router->get('/api/plugins', fn () => $this->renderPluginFeed());
        $router->get('/api/plugins/index.php', fn () => $this->renderPluginFeed());
        $router->get('/admin/projects', fn (Request $request) => $this->guard($request, 'projects.view', fn () => $this->renderAdminList()));
        $router->get('/admin/projects/create', fn (Request $request) => $this->guard($request, 'projects.manage', fn () => $this->renderForm()));
        $router->post('/admin/projects/create', fn (Request $request) => $this->guard($request, 'projects.manage', fn () => $this->save($request)));
        $router->get('/admin/projects/edit', fn (Request $request) => $this->guard($request, 'projects.manage', fn () => $this->renderEdit($request)));
        $router->post('/admin/projects/edit', fn (Request $request) => $this->guard($request, 'projects.manage', fn () => $this->save($request, $this->projects->find($request->postInt('id', 0) ?? 0))));
        $router->post('/admin/projects/delete', fn (Request $request) => $this->guard($request, 'projects.manage', fn () => $this->delete($request)));
        $router->get('/admin/projects/categories', fn (Request $request) => $this->guard($request, 'projects.manage', fn () => $this->renderCategories()));
        $router->post('/admin/projects/categories/create', fn (Request $request) => $this->guard($request, 'projects.manage', fn () => $this->saveCategory($request)));
        $router->get('/admin/projects/categories/edit', fn (Request $request) => $this->guard($request, 'projects.manage', fn () => $this->renderCategoryEdit($request)));
        $router->post('/admin/projects/categories/edit', fn (Request $request) => $this->guard($request, 'projects.manage', fn () => $this->saveCategory($request, $this->projects->findCategory($request->postInt('id', 0) ?? 0))));
        $router->post('/admin/projects/categories/delete', fn (Request $request) => $this->guard($request, 'projects.manage', fn () => $this->deleteCategory($request)));
    }

    public function registerSeo(SeoIndex $seo): void
    {
        foreach ($this->projects->all(true) as $project) {
            if (!$this->isIndexableProject($project)) {
                continue;
            }

            $seo->add(
                '/projects/' . rawurlencode($project->slug),
                $project->name . ' - SyntaxDevTeam project',
                $project->summary,
                null,
                0.8,
                'monthly',
                'SoftwareApplication'
            );
        }
    }

    private function renderPublicList(): void
    {
        $projects = $this->projects->all(true);
        $this->theme->start_page('Projects - SyntaxDevTeam', 'Public and actively developed SyntaxDevTeam projects.', false);
        $this->theme->start_header('Projects', 'Released solutions, active development and team plans.', 'SyntaxDevTeam / Projects');
        $this->theme->end_header();
        $this->theme->start_section();
        if ($projects === []) {
            $this->theme->render_alert('The project catalog is empty for now.', 'info');
        } else {
            foreach ($this->publicCategoryGroups($projects) as $group) {
                $collapsible = $this->theme instanceof \SyntaxDevTeam\Cms\Core\CollapsibleSectionThemeInterface;
                if ($collapsible) {
                    $this->theme->start_collapsible_section($group['name'], $this->publicProjectCountLabel(count($group['projects'])));
                } else {
                    $this->theme->render_section_heading($group['name'], $this->publicProjectCountLabel(count($group['projects'])));
                }
                $columnSize = $this->publicColumnSize(count($group['projects']));
                $this->theme->start_grid();
                foreach ($group['projects'] as $project) {
                    $this->theme->start_column($columnSize);
                    if ($project->iconUrl !== '') {
                        $this->theme->start_card('', self::PUBLIC_STATUSES[$project->lifecycleStatus]);
                        $this->renderProjectTitle($project);
                    } else {
                        $this->theme->start_card($project->name, self::PUBLIC_STATUSES[$project->lifecycleStatus]);
                    }
                    if (trim($project->summary) !== '') {
                        $this->theme->render_text($this->excerpt($project->summary, 220));
                    }
                    $this->theme->render_link_list($this->publicLinks($project));
                    $this->theme->end_card();
                    $this->theme->end_column();
                }
                $this->theme->end_grid();
                if ($collapsible) {
                    $this->theme->end_collapsible_section();
                }
            }
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
        $this->theme->start_page(
            $project->name . ' - Projects',
            $project->summary !== '' ? $project->summary : 'Related resources for ' . $project->name . '.',
            $this->isIndexableProject($project)
        );
        $this->theme->start_header($project->name, $this->publicProjectMeta($project), 'SyntaxDevTeam / Projects');
        $this->theme->end_header();
        $this->theme->start_section();
        if (trim($project->summary) !== '') {
            $this->theme->start_card('Project overview', $this->publicProjectMeta($project));
            $this->theme->render_text($project->summary);
            $this->theme->end_card();
        }
        if ($project->iconUrl !== '') {
            $this->theme->start_card('Project identity', 'Icon and integration');
            $this->renderProjectIcon($project);
            $this->theme->render_text('Integration UUID: ' . ($project->integrationUuid !== '' ? $project->integrationUuid : 'Not configured'));
            $this->theme->end_card();
        }
        $this->theme->start_card('Project resources', 'Status: ' . $this->publicProjectMeta($project));
        $this->theme->render_link_list($this->publicLinks($project));
        $this->theme->end_card();
        $this->theme->end_section();
        $this->theme->end_page();
    }

    private function renderAdminList(string $message = '', string $variant = 'info'): void
    {
        $this->startAdminPage('Projekty', 'Katalog łączy status projektu z istniejącą podstroną i dokumentacją.', [[
            'label' => 'Dodaj projekt', 'href' => 'index.php?route=/admin/projects/create', 'variant' => 'primary',
        ], [
            'label' => 'Kategorie', 'href' => 'index.php?route=/admin/projects/categories', 'variant' => 'outline-light',
        ], ['label' => 'Publiczny katalog', 'href' => '/projects', 'variant' => 'outline-light']]);
        if ($message !== '') { $this->theme->render_alert($message, $variant); }
        $projects = $this->projects->all();
        $this->theme->start_admin_panel('Katalog projektów', count($projects) . ' pozycji');
        if ($projects === []) {
            $this->theme->render_alert('Nie dodano jeszcze projektów.', 'info');
        } else {
            $this->theme->render_admin_action_table(['Kolejność', 'Nazwa', 'Kategoria', 'UUID', 'Prior', 'Status', 'Publikacja', 'Podstrona', 'Wiki'], array_map(
                static fn (Project $project): array => [
                    'cells' => [$project->sortOrder, $project->name, $project->categoryName ?: 'Bez kategorii', $project->integrationUuid ?: 'Brak', $project->integrationPriority, self::STATUSES[$project->lifecycleStatus], $project->published ? 'Publiczny' : 'Ukryty', $project->pageTitle ?: 'Brak', $project->wikiName ?: 'Brak'],
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
        $categories = ['0' => 'Bez kategorii'] + $this->stringOptions($this->projects->categoryOptions());
        $fields = $project !== null ? [['name' => 'id', 'label' => 'ID', 'type' => 'hidden', 'value' => (string) $project->id]] : [];
        $iconLibraryOptions = $this->mediaImageOptions();
        $fields = [...$fields,
            ['name' => 'name', 'label' => 'Nazwa projektu', 'value' => $project?->name ?? ''],
            ['name' => 'slug', 'label' => 'Slug', 'value' => $project?->slug ?? '', 'help' => 'Puste pole wygeneruje slug z nazwy.'],
            ['name' => 'integration_uuid', 'label' => 'UUID dla API pluginów', 'value' => $project?->integrationUuid ?? '', 'help' => 'Opcjonalny identyfikator eksportowany w /api/plugins.'],
            ['name' => 'integration_priority', 'label' => 'Prior / waga projektu', 'type' => 'number', 'value' => (string) ($project?->integrationPriority ?? 100), 'help' => 'Niższa wartość pojawia się wcześniej w feedzie pluginów.'],
            ['name' => 'icon_url', 'label' => 'Ikona projektu', 'value' => $project?->iconUrl ?? '', 'help' => 'Ścieżka publiczna lub URL HTTPS.'],
            ...($iconLibraryOptions !== [] ? [[
                'name' => 'icon_library_url',
                'label' => 'Wybierz ikonę z biblioteki',
                'type' => 'select',
                'value' => '',
                'options' => $iconLibraryOptions,
                'help' => 'Wybrana pozycja nadpisze ręcznie wpisaną ścieżkę ikony.',
            ]] : []),
            ['name' => 'lifecycle_status', 'label' => 'Stan projektu', 'type' => 'select', 'value' => $project?->lifecycleStatus ?? 'planned', 'options' => self::STATUSES],
            ['name' => 'category_id', 'label' => 'Kategoria', 'type' => 'select', 'value' => (string) ($project?->categoryId ?? 0), 'options' => $categories],
            ['name' => 'page_id', 'label' => 'Powiązana podstrona', 'type' => 'select', 'value' => (string) ($project?->pageId ?? 0), 'options' => $pages],
            ['name' => 'wiki_project_id', 'label' => 'Powiązany projekt Wiki', 'type' => 'select', 'value' => (string) ($project?->wikiProjectId ?? 0), 'options' => $wiki],
            ['name' => 'sort_order', 'label' => 'Kolejność', 'type' => 'number', 'value' => (string) ($project?->sortOrder ?? 100)],
            ['name' => 'is_published', 'label' => 'Widoczny publicznie', 'type' => 'checkbox', 'checked' => $project?->published ?? false],
        ];
        $this->theme->start_admin_panel('Dane projektu', 'Katalog /projects');
        $this->theme->render_form('index.php?route=' . ($project === null ? '/admin/projects/create' : '/admin/projects/edit'), $fields, $project === null ? 'Dodaj projekt' : 'Zapisz projekt', $this->security->csrfToken());
        $this->theme->end_admin_panel();
        if ($project !== null && $this->languageSources !== null && $this->canManageSources()) {
            $source = $this->languageSources->settings($project->id);
            $this->theme->start_admin_panel('Pliki językowe z GitHuba', 'Oryginały dla modułu Tłumaczenia');
            $this->theme->render_form('index.php?route=/admin/projects/sources', [
                ['name'=>'id','type'=>'hidden','label'=>'Projekt','value'=>(string)$project->id],
                ['name'=>'repository','label'=>'Repozytorium GitHub','value'=>$source['repository'],'help'=>'owner/repository lub https://github.com/owner/repository'],
                ['name'=>'ref','label'=>'Gałąź / tag','value'=>$source['ref']],
                ['name'=>'paths','label'=>'Katalogi językowe','type'=>'textarea','rows'=>5,'value'=>implode("\n",array_map(static fn(array $d):string=>$d['label'].' | '.$d['path'],$source['directories'])),'help'=>'Jeden katalog na linię, np. Paper | punisherx-paper/src/main/resources/lang. Maksymalnie 8 wariantów.'],
                ['name'=>'notes','label'=>'Informacja o źródle','value'=>$source['notes']??'','help'=>'Np. powód braku plików YAML lub wskazówka dotycząca wariantu.'],
                ['name'=>'enabled','label'=>'Udostępniaj wskazane pliki YAML jako oryginały do tłumaczenia','type'=>'checkbox','checked'=>$source['enabled']],
                ['name'=>'private','label'=>'Prywatne repozytorium — udostępniaj użytkownikom wyłącznie pliki z podanych katalogów','type'=>'checkbox','checked'=>$source['private']],
            ], 'Zapisz źródło tłumaczeń', $this->security->csrfToken());
            $this->theme->render_text($this->languageSources->hasCredential() ? 'Dostęp GitHub do prywatnych repozytoriów jest skonfigurowany na serwerze.' : 'Prywatne repozytoria wymagają PROJECT_SOURCES_GITHUB_TOKEN w chronionej konfiguracji serwera (Contents: read do wybranych repozytoriów). Panel nie przechowuje tokenów.');
            $this->theme->end_admin_panel();
        }
        $this->endAdminPage();
    }

    private function saveLanguageSources(Request $request): void
    {
        if (!$this->canManageSources() || $this->languageSources === null) { http_response_code(403); $this->renderAdminList('Źródłami zarządza Owner lub Administrator.', 'danger'); return; }
        $project = $this->projects->find($request->postInt('id',0) ?? 0);
        if (!$project || !$this->security->validateCsrfToken($request->postString('_token'))) { http_response_code(403); $this->renderAdminList('Nieprawidłowy projekt lub token CSRF.', 'danger'); return; }
        try {
            $this->languageSources->save($project->id, ['repository'=>$request->postString('repository'),'ref'=>$request->postString('ref'),'paths'=>$request->postString('paths'),'enabled'=>$request->postBool('enabled'),'private'=>$request->postBool('private'),'notes'=>$request->postString('notes')]);
            $this->hooks?->doAction('projects.language_sources_saved');
            $this->audit->record($request,'project_language_source_save','success','project:'.$project->id,$this->auth->user()?->id);
            $this->renderForm($project,'Źródło plików językowych zostało zapisane.','success');
        } catch (\InvalidArgumentException $exception) {
            $this->renderForm($project,$exception->getMessage(),'warning');
        } catch (\Throwable) {
            $this->renderForm($project,'Nie udało się zapisać źródła. Sprawdź prawa zapisu konfiguracji.','danger');
        }
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
        $integrationUuid = $this->normalizeIntegrationUuid($request->postString('integration_uuid'));
        $categoryId = $request->postInt('category_id', 0) ?? 0;
        $selectedIcon = $request->postString('icon_library_url');
        $iconUrl = $this->normalizeIconUrl($selectedIcon !== '' ? $selectedIcon : $request->postString('icon_url'));
        if ($name === '' || $slug === '' || !isset(self::STATUSES[$status])) {
            $this->renderForm($project, 'Uzupełnij nazwę, slug i poprawny status.', 'warning'); return;
        }
        if ($integrationUuid === false) {
            $this->renderForm($project, 'UUID integracji może zawierać tylko litery, cyfry, myślniki i podkreślenia, maksymalnie 64 znaki.', 'warning'); return;
        }
        if ($iconUrl === false) {
            $this->renderForm($project, 'Ikona projektu musi być ścieżką publiczną zaczynającą się od / albo adresem HTTPS.', 'warning'); return;
        }
        if ($categoryId > 0 && !($this->projects->findCategory($categoryId) instanceof ProjectCategory)) {
            $this->renderForm($project, 'Wybrana kategoria projektu nie istnieje.', 'warning'); return;
        }
        if ($this->projects->slugExists($slug, $project?->id)) {
            $this->renderForm($project, 'Ten slug projektu jest już używany.', 'warning'); return;
        }
        if ($integrationUuid !== '' && $this->projects->integrationUuidExists($integrationUuid, $project?->id)) {
            $this->renderForm($project, 'Ten UUID integracji jest już przypisany do innego projektu.', 'warning'); return;
        }
        $data = [
            'name' => $name, 'slug' => $slug, 'integration_uuid' => $integrationUuid !== '' ? $integrationUuid : null,
            'integration_priority' => max(0, $request->postInt('integration_priority', 100) ?? 100),
            'icon_url' => $iconUrl, 'summary' => '', 'lifecycle_status' => $status,
            'category_id' => $categoryId > 0 ? $categoryId : null,
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
            $this->hooks?->doAction('projects.language_sources_saved');
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

    private function renderCategories(string $message = '', string $variant = 'info'): void
    {
        $this->startAdminPage('Kategorie projektów', 'Kategorie porządkują projekty bez kopiowania treści podstron i dokumentacji.', [[
            'label' => 'Wróć do projektów', 'href' => 'index.php?route=/admin/projects', 'variant' => 'outline-light',
        ]]);
        if ($message !== '') {
            $this->theme->render_alert($message, $variant);
        }

        $categories = $this->projects->categories();
        $this->theme->start_admin_panel_grid('balanced');
        $this->theme->start_admin_panel('Kategorie', count($categories) . ' pozycji');
        if ($categories === []) {
            $this->theme->render_alert('Nie dodano jeszcze kategorii projektów.', 'info');
        } else {
            $this->theme->render_admin_action_table(
                ['Kolejność', 'Nazwa', 'Slug', 'Projekty'],
                array_map(static fn (ProjectCategory $category): array => [
                    'cells' => [$category->sortOrder, $category->name, $category->slug, $category->projectCount],
                    'actions' => [[
                        'label' => 'Edytuj',
                        'href' => 'index.php?route=/admin/projects/categories/edit&id=' . $category->id,
                        'variant' => 'primary',
                    ], [
                        'label' => 'Usuń',
                        'action' => 'index.php?route=/admin/projects/categories/delete',
                        'fields' => ['id' => $category->id],
                        'variant' => 'danger',
                        'confirm' => 'Usunąć kategorię? Przypisane projekty pozostaną bez kategorii.',
                    ]],
                ], $categories),
                $this->security->csrfToken()
            );
        }
        $this->theme->end_admin_panel();

        $this->theme->start_admin_panel('Dodaj kategorię', 'Nowa grupa projektów');
        $this->theme->render_form('index.php?route=/admin/projects/categories/create', [[
            'name' => 'name',
            'label' => 'Nazwa kategorii',
            'required' => true,
            'maxlength' => 120,
        ], [
            'name' => 'slug',
            'label' => 'Slug',
            'maxlength' => 191,
            'help' => 'Puste pole wygeneruje slug z nazwy.',
        ], [
            'name' => 'sort_order',
            'label' => 'Kolejność',
            'type' => 'number',
            'value' => '100',
            'min' => '0',
        ]], 'Dodaj kategorię', $this->security->csrfToken());
        $this->theme->end_admin_panel();
        $this->theme->end_admin_panel_grid();
        $this->endAdminPage();
    }

    private function renderCategoryEdit(Request $request, string $message = '', string $variant = 'info'): void
    {
        $category = $this->projects->findCategory($request->queryInt('id', 0) ?? 0);
        if (!$category instanceof ProjectCategory) {
            $this->renderCategories('Nie znaleziono kategorii.', 'danger');
            return;
        }

        $this->startAdminPage('Edytuj kategorię', 'Zmiana nazwy lub sluga zachowuje przypisane projekty.', [[
            'label' => 'Wróć do kategorii', 'href' => 'index.php?route=/admin/projects/categories', 'variant' => 'outline-light',
        ]]);
        if ($message !== '') {
            $this->theme->render_alert($message, $variant);
        }
        $this->theme->start_admin_panel('Dane kategorii', $category->projectCount . ' przypisanych projektów');
        $this->theme->render_form('index.php?route=/admin/projects/categories/edit', [[
            'name' => 'id', 'label' => 'ID', 'type' => 'hidden', 'value' => (string) $category->id,
        ], [
            'name' => 'name', 'label' => 'Nazwa kategorii', 'value' => $category->name,
            'required' => true, 'maxlength' => 120,
        ], [
            'name' => 'slug', 'label' => 'Slug', 'value' => $category->slug,
            'required' => true, 'maxlength' => 191,
        ], [
            'name' => 'sort_order', 'label' => 'Kolejność', 'type' => 'number',
            'value' => (string) $category->sortOrder, 'min' => '0',
        ]], 'Zapisz kategorię', $this->security->csrfToken());
        $this->theme->end_admin_panel();
        $this->endAdminPage();
    }

    private function saveCategory(Request $request, ?ProjectCategory $category = null): void
    {
        $actor = $this->auth->user();
        if ($category === null && ($request->postInt('id', 0) ?? 0) > 0) {
            $this->renderCategories('Nie znaleziono kategorii.', 'danger');
            return;
        }
        if (!$this->security->validateCsrfToken($request->postString('_token'))) {
            $this->audit->record($request, 'project_category_save', 'invalid_csrf', 'project_categories', $actor?->id);
            $this->renderCategories('Token CSRF jest nieprawidłowy lub wygasł.', 'danger');
            return;
        }

        $name = $this->bounded($request->postString('name'), 120);
        $slug = $this->slugify($request->postString('slug') ?: $name);
        if ($name === '' || $slug === '') {
            $this->renderCategories('Uzupełnij nazwę i poprawny slug kategorii.', 'warning');
            return;
        }
        if ($this->projects->categorySlugExists($slug, $category?->id)) {
            $this->renderCategories('Ten slug kategorii jest już używany.', 'warning');
            return;
        }

        $data = [
            'name' => $name,
            'slug' => $slug,
            'sort_order' => max(0, $request->postInt('sort_order', 100) ?? 100),
        ];
        try {
            if ($category === null) {
                $id = $this->projects->createCategory($data);
                $this->audit->record($request, 'project_category_create', 'success', 'project_category:' . $id, $actor?->id);
            } else {
                $this->projects->updateCategory($category->id, $data);
                $this->audit->record($request, 'project_category_update', 'success', 'project_category:' . $category->id, $actor?->id);
            }
            $this->renderCategories('Kategoria została zapisana.', 'success');
        } catch (\Throwable) {
            $this->audit->record($request, 'project_category_save', 'failed', 'project_categories', $actor?->id);
            $this->renderCategories('Nie udało się zapisać kategorii.', 'danger');
        }
    }

    private function deleteCategory(Request $request): void
    {
        $actor = $this->auth->user();
        if (!$this->security->validateCsrfToken($request->postString('_token'))) {
            $this->audit->record($request, 'project_category_delete', 'invalid_csrf', 'project_categories', $actor?->id);
            $this->renderCategories('Token CSRF jest nieprawidłowy lub wygasł.', 'danger');
            return;
        }

        $id = $request->postInt('id', 0) ?? 0;
        $category = $this->projects->findCategory($id);
        $ok = $category instanceof ProjectCategory && $this->projects->deleteCategory($id);
        $this->audit->record($request, 'project_category_delete', $ok ? 'success' : 'failed', 'project_category:' . $id, $actor?->id);
        if (!$ok) {
            $this->renderCategories('Nie udało się usunąć kategorii.', 'danger');
            return;
        }

        $suffix = $category->projectCount > 0 ? ' Przypisane projekty pozostały bez kategorii.' : '';
        $this->renderCategories('Kategoria została usunięta.' . $suffix, 'success');
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

    private function excerpt(string $value, int $max): string
    {
        $value = preg_replace('/\s+/', ' ', trim($value)) ?? trim($value);
        if (strlen($value) <= $max) {
            return $value;
        }

        return rtrim($this->bounded($value, $max - 1), " \t\n\r\0\x0B.,;:") . '.';
    }

    private function isIndexableProject(Project $project): bool
    {
        $summary = preg_replace('/\s+/', ' ', trim($project->summary)) ?? trim($project->summary);

        return strlen($summary) >= 140;
    }

    private function publicColumnSize(int $count): string
    {
        return match ($count) {
            1 => '12',
            2, 4 => 'lg-6',
            default => 'lg-4',
        };
    }

    /**
     * @param list<Project> $projects
     * @return list<array{name: string, projects: list<Project>}>
     */
    private function publicCategoryGroups(array $projects): array
    {
        $byCategory = [];
        $uncategorized = [];
        foreach ($projects as $project) {
            if ($project->categoryId === null) {
                $uncategorized[] = $project;
                continue;
            }
            $byCategory[$project->categoryId][] = $project;
        }

        $groups = [];
        foreach ($this->projects->categoryOptions() as $categoryId => $categoryName) {
            $categoryProjects = $byCategory[$categoryId] ?? [];
            if ($categoryProjects === []) {
                continue;
            }
            $groups[] = ['name' => $categoryName, 'projects' => $categoryProjects];
        }
        if ($uncategorized !== []) {
            $groups[] = ['name' => 'Other projects', 'projects' => $uncategorized];
        }

        return $groups;
    }

    private function publicProjectCountLabel(int $count): string
    {
        return $count === 1 ? '1 project' : $count . ' projects';
    }

    private function publicProjectMeta(Project $project): string
    {
        $status = self::PUBLIC_STATUSES[$project->lifecycleStatus];

        return $project->categoryName !== '' ? $status . ' · ' . $project->categoryName : $status;
    }

    private function renderProjectTitle(Project $project): void
    {
        echo '<div class="project-public-title">';
        $this->renderProjectIcon($project);
        echo '<h2 class="h4">' . $this->escape($project->name) . '</h2>';
        echo '</div>';
    }

    private function renderProjectIcon(Project $project): void
    {
        echo '<figure class="project-public-icon">';
        echo '<img src="' . $this->escape($project->iconUrl) . '" alt="' . $this->escape($project->name) . ' icon" loading="lazy" decoding="async">';
        echo '</figure>';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    private function renderPluginFeed(): void
    {
        $payload = [];
        foreach ($this->projects->all(true) as $project) {
            if ($project->integrationUuid === '') {
                continue;
            }
            $payload[] = [
                'name' => $project->name,
                'uuid' => $project->integrationUuid,
                'prior' => $project->integrationPriority,
            ];
        }
        usort($payload, static fn (array $a, array $b): int => $a['prior'] === $b['prior']
            ? strcmp((string) $a['name'], (string) $b['name'])
            : ((int) $a['prior'] <=> (int) $b['prior']));

        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: public, max-age=120');
        }
        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
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
        $links[] = ['label' => 'Build Explorer', 'href' => '/builds/' . rawurlencode($project->slug) . '/explore', 'meta' => 'Versions and downloadable files'];

        return $links;
    }
    /** @param array<int, string> $options @return array<string, string> */
    private function stringOptions(array $options): array
    {
        $result = []; foreach ($options as $id => $label) { $result[(string) $id] = $label; } return $result;
    }

    private function normalizeIntegrationUuid(string $value): string|false
    {
        $value = $this->bounded($value, 64);
        if ($value === '') {
            return '';
        }

        return preg_match('/^[A-Za-z0-9_-]{1,64}$/', $value) === 1 ? $value : false;
    }

    private function normalizeIconUrl(string $value): string|false
    {
        $value = $this->bounded($value, 500);
        if ($value === '') {
            return '';
        }
        if (str_starts_with($value, '/') && !str_starts_with($value, '//') && !str_contains($value, "\n")) {
            return $value;
        }
        if (filter_var($value, FILTER_VALIDATE_URL) !== false && str_starts_with(strtolower($value), 'https://')) {
            return $value;
        }

        return false;
    }

    /** @return array<string, string> */
    private function mediaImageOptions(): array
    {
        if (!$this->mediaAssets instanceof MediaAssetRepository) {
            return [];
        }

        $options = $this->mediaAssets->options('project_icon');
        if ($options === []) {
            return [];
        }

        return ['' => 'Nie wybieraj z biblioteki'] + $options;
    }
}
