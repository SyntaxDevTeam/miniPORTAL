<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\Wikipedia;

use SyntaxDevTeam\Cms\Core\AdminMenuRegistry;
use SyntaxDevTeam\Cms\Core\ContentRenderer;
use SyntaxDevTeam\Cms\Core\ModuleInterface;
use SyntaxDevTeam\Cms\Core\Request;
use SyntaxDevTeam\Cms\Core\Router;
use SyntaxDevTeam\Cms\Core\Security;
use SyntaxDevTeam\Cms\Core\TemplateCacheInterface;
use SyntaxDevTeam\Cms\Core\ThemeInterface;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AdminAccessGate;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuditLogService;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuthService;
use SyntaxDevTeam\Cms\Modules\CoreAuth\User;

final class WikipediaModule implements ModuleInterface
{
    public function __construct(
        private readonly ThemeInterface $theme,
        private readonly AdminMenuRegistry $menu,
        private readonly WikiRepository $wiki,
        private readonly AuthService $auth,
        private readonly AdminAccessGate $access,
        private readonly Security $security,
        private readonly AuditLogService $audit,
        private readonly TemplateCacheInterface $templateCache,
    ) {
    }

    public function id(): string
    {
        return 'wikipedia';
    }

    public function version(): string
    {
        return '1.0.2';
    }

    public function dependencies(): array
    {
        return ['core_auth'];
    }

    public function isProtected(): bool
    {
        return false;
    }

    public function requiredPermissions(): array
    {
        return ['wikipedia.view'];
    }

    public function registerAdminMenu(AdminMenuRegistry $menu): void
    {
        $menu->add('Treść', 'Dokumentacja', '/admin/wikipedia', 'WK', 'wikipedia.view', 35);
    }

    public function registerRoutes(Router $router): void
    {
        $router->get('/wiki', fn () => $this->renderPublicIndex());
        $router->get('/wiki/project', fn (Request $request) => $this->renderPublicProject($request));
        $router->get('/wiki/page', fn (Request $request) => $this->renderPublicPage($request));
        $router->get('/admin/wikipedia', fn (Request $request) => $this->guard(
            $request,
            'wikipedia.view',
            fn () => $this->renderDashboard()
        ));
        $router->get('/admin/wikipedia/projects/create', fn (Request $request) => $this->guard(
            $request,
            'wikipedia.create',
            fn () => $this->renderProjectForm()
        ));
        $router->post('/admin/wikipedia/projects/create', fn (Request $request) => $this->guard(
            $request,
            'wikipedia.create',
            fn () => $this->createProject($request)
        ));
        $router->get('/admin/wikipedia/projects/edit', fn (Request $request) => $this->guard(
            $request,
            'wikipedia.edit',
            fn () => $this->renderProjectEdit($request)
        ));
        $router->post('/admin/wikipedia/projects/edit', fn (Request $request) => $this->guard(
            $request,
            'wikipedia.edit',
            fn () => $this->updateProject($request)
        ));
        $router->post('/admin/wikipedia/projects/publish', fn (Request $request) => $this->guard(
            $request,
            'wikipedia.publish',
            fn () => $this->changeProjectPublication($request)
        ));
        $router->post('/admin/wikipedia/projects/delete', fn (Request $request) => $this->guard(
            $request,
            'wikipedia.delete',
            fn () => $this->deleteProject($request)
        ));
        $router->get('/admin/wikipedia/pages/create', fn (Request $request) => $this->guard(
            $request,
            'wikipedia.create',
            fn () => $this->renderPageForm(null, $request->queryInt('project_id'))
        ));
        $router->post('/admin/wikipedia/pages/create', fn (Request $request) => $this->guard(
            $request,
            'wikipedia.create',
            fn () => $this->createPage($request)
        ));
        $router->get('/admin/wikipedia/pages/edit', fn (Request $request) => $this->guard(
            $request,
            'wikipedia.edit',
            fn () => $this->renderPageEdit($request)
        ));
        $router->post('/admin/wikipedia/pages/edit', fn (Request $request) => $this->guard(
            $request,
            'wikipedia.edit',
            fn () => $this->updatePage($request)
        ));
        $router->post('/admin/wikipedia/pages/publish', fn (Request $request) => $this->guard(
            $request,
            'wikipedia.publish',
            fn () => $this->changePagePublication($request)
        ));
        $router->post('/admin/wikipedia/pages/delete', fn (Request $request) => $this->guard(
            $request,
            'wikipedia.delete',
            fn () => $this->deletePage($request)
        ));
    }

    private function renderPublicIndex(): void
    {
        echo $this->cachedPublic('wiki:index', function (): string {
            $projects = $this->wiki->publishedProjects();

            return $this->capture(function () use ($projects): void {
                $this->theme->start_page('Dokumentacja projektów - SyntaxDevTeam', 'Baza wiedzy projektów SyntaxDevTeam.');
                $this->theme->start_header('Dokumentacja projektów', 'Wiedza techniczna, procedury i opisy projektów.', 'SyntaxDevTeam / Wiki');
                $this->theme->end_header();
                $this->theme->start_section();
                if ($projects === []) {
                    $this->theme->render_alert('Nie opublikowano jeszcze żadnej dokumentacji projektowej.', 'info');
                } else {
                    $this->theme->start_grid();
                    foreach ($projects as $project) {
                        $this->theme->start_column('md-6');
                        $this->theme->start_card($project->name, 'Projekt');
                        $this->theme->render_text($project->summary);
                        $this->theme->render_button(
                            'Otwórz dokumentację',
                            'index.php?route=/wiki/project&slug=' . rawurlencode($project->slug),
                            'outline-light'
                        );
                        $this->theme->end_card();
                        $this->theme->end_column();
                    }
                    $this->theme->end_grid();
                }
                $this->theme->end_section();
                $this->theme->end_page();
            });
        }, ['wikipedia', 'wiki:index', 'theme']);
    }

    private function renderPublicProject(Request $request): void
    {
        $slug = $this->normalizeSlug($request->queryString('slug'));
        $project = $slug !== '' ? $this->wiki->findPublishedProjectBySlug($slug) : null;
        if ($project === null) {
            http_response_code(404);
            $this->theme->render_page_not_found('Nie znaleziono dokumentacji', 'Ten projekt nie istnieje albo nie został opublikowany.');
            return;
        }

        echo $this->cachedPublic('wiki:project:' . $project->slug, function () use ($project): string {
            $pages = $this->wiki->publishedPages($project->id);

            return $this->capture(function () use ($project, $pages): void {
                $this->theme->start_page($project->name . ' - dokumentacja', $project->summary);
                $this->theme->start_header($project->name, $project->summary, 'Wiki / Projekt');
                $this->theme->end_header();
                $this->theme->start_section();
                if ($pages === []) {
                    $this->theme->render_alert('Projekt nie ma jeszcze opublikowanych stron dokumentacji.', 'info');
                } else {
                    $this->theme->start_grid();
                    foreach ($pages as $page) {
                        $this->theme->start_column('md-6');
                        $this->theme->start_card($page->title, 'Strona dokumentacji');
                        $this->theme->render_text($page->summary);
                        $this->theme->render_button(
                            'Czytaj',
                            'index.php?route=/wiki/page&project=' . rawurlencode($page->projectSlug)
                                . '&slug=' . rawurlencode($page->slug),
                            'outline-light'
                        );
                        $this->theme->end_card();
                        $this->theme->end_column();
                    }
                    $this->theme->end_grid();
                }
                $this->theme->render_button('Wróć do dokumentacji', 'index.php?route=/wiki', 'outline-light');
                $this->theme->end_section();
                $this->theme->end_page();
            });
        }, ['wikipedia', 'wiki:index', 'wiki:project:' . $project->slug, 'theme']);
    }

    private function renderPublicPage(Request $request): void
    {
        $projectSlug = $this->normalizeSlug($request->queryString('project'));
        $pageSlug = $this->normalizeSlug($request->queryString('slug'));
        $page = $projectSlug !== '' && $pageSlug !== ''
            ? $this->wiki->findPublishedPage($projectSlug, $pageSlug)
            : null;
        if ($page === null) {
            http_response_code(404);
            $this->theme->render_page_not_found('Nie znaleziono strony dokumentacji', 'Ta strona nie istnieje albo nie została opublikowana.');
            return;
        }

        echo $this->cachedPublic('wiki:page:' . $page->projectSlug . ':' . $page->slug, function () use ($page): string {
            $navigation = $this->wikiPageNavigation($page);

            return $this->capture(function () use ($page, $navigation): void {
            $this->theme->start_page($page->title . ' - ' . $page->projectName, $page->summary);
            $this->theme->start_header(
                $page->title,
                $page->summary,
                'Wiki / ' . $page->projectName
            );
            $this->theme->end_header();
            $this->theme->start_section();
            $this->theme->start_card('', 'Dokumentacja');
            $this->theme->render_rich_content($page->content, $page->contentFormat);
            $this->theme->render_content_navigation($navigation);
            $this->theme->end_card();
            $this->theme->end_section();
            $this->theme->end_page();
            });
        }, ['wikipedia', 'wiki:project:' . $page->projectSlug, 'wiki:page:' . $page->projectSlug . ':' . $page->slug, 'theme']);
    }

    private function renderDashboard(string $message = '', string $variant = 'info'): void
    {
        $user = $this->auth->user();
        if ($user === null) {
            return;
        }
        $projects = $this->wiki->projects();
        $pages = $this->wiki->pages();
        $allows = $this->allows($user);
        $this->startAdminPage(
            $user,
            'Dokumentacja',
            'Twórz projektową bazę wiedzy z projektami i stronami dokumentacji.',
            $allows('wikipedia.create') ? ['label' => 'Dodaj projekt', 'href' => 'index.php?route=/admin/wikipedia/projects/create'] : null
        );
        if ($message !== '') {
            $this->theme->render_alert($message, $variant);
        }
        $this->theme->render_button('Zobacz publiczną dokumentację', 'index.php?route=/wiki', 'outline-light');

        $this->theme->start_admin_panel('Projekty dokumentacji', count($projects) . ' rekordów');
        $this->theme->render_admin_action_table(
            ['Projekt', 'Slug', 'Status', 'Kolejność'],
            array_map(fn (WikiProject $project): array => [
                'cells' => [
                    $project->name,
                    $project->slug,
                    $project->status === 'published' ? 'Opublikowany' : 'Szkic',
                    (string) $project->sortOrder,
                ],
                'actions' => array_values(array_filter([
                    $allows('wikipedia.create') ? [
                        'label' => 'Dodaj stronę',
                        'href' => 'index.php?route=/admin/wikipedia/pages/create&project_id=' . $project->id,
                        'variant' => 'outline-primary',
                    ] : null,
                    $allows('wikipedia.edit') ? [
                        'label' => 'Edytuj',
                        'href' => 'index.php?route=/admin/wikipedia/projects/edit&id=' . $project->id,
                        'variant' => 'outline-light',
                    ] : null,
                    $allows('wikipedia.publish') ? [
                        'label' => $project->status === 'published' ? 'Cofnij' : 'Publikuj',
                        'action' => 'index.php?route=/admin/wikipedia/projects/publish',
                        'fields' => ['id' => $project->id, 'action' => $project->status === 'published' ? 'draft' : 'publish'],
                        'variant' => 'outline-primary',
                    ] : null,
                    $allows('wikipedia.delete') ? [
                        'label' => 'Usuń',
                        'action' => 'index.php?route=/admin/wikipedia/projects/delete',
                        'fields' => ['id' => $project->id],
                        'variant' => 'outline-danger',
                        'confirm' => 'Usunąć projekt i wszystkie jego strony dokumentacji?',
                    ] : null,
                ])),
            ], $projects),
            $this->security->csrfToken()
        );
        $this->theme->end_admin_panel();

        $this->theme->start_admin_panel('Strony dokumentacji', count($pages) . ' rekordów');
        $this->theme->render_admin_action_table(
            ['Tytuł', 'Projekt', 'Slug', 'Status', 'Aktualizacja'],
            array_map(fn (WikiPage $page): array => [
                'cells' => [
                    $page->title,
                    $page->projectName,
                    $page->slug,
                    $page->status === 'published' ? 'Opublikowana' : 'Szkic',
                    $page->updatedAt,
                ],
                'actions' => array_values(array_filter([
                    $allows('wikipedia.edit') ? [
                        'label' => 'Edytuj',
                        'href' => 'index.php?route=/admin/wikipedia/pages/edit&id=' . $page->id,
                        'variant' => 'outline-light',
                    ] : null,
                    $allows('wikipedia.publish') ? [
                        'label' => $page->status === 'published' ? 'Cofnij' : 'Publikuj',
                        'action' => 'index.php?route=/admin/wikipedia/pages/publish',
                        'fields' => ['id' => $page->id, 'action' => $page->status === 'published' ? 'draft' : 'publish'],
                        'variant' => 'outline-primary',
                    ] : null,
                    $allows('wikipedia.delete') ? [
                        'label' => 'Usuń',
                        'action' => 'index.php?route=/admin/wikipedia/pages/delete',
                        'fields' => ['id' => $page->id],
                        'variant' => 'outline-danger',
                    ] : null,
                ])),
            ], $pages),
            $this->security->csrfToken()
        );
        $this->theme->end_admin_panel();
        $this->endAdminPage();
    }

    private function renderProjectEdit(Request $request): void
    {
        $project = $this->wiki->findProject($request->queryInt('id') ?? 0);
        if ($project === null) {
            http_response_code(404);
            $this->theme->render_admin_access_state(404, 'Nie znaleziono projektu', 'Wybrany projekt dokumentacji nie istnieje.', 'index.php?route=/admin/wikipedia', 'Wróć do dokumentacji');
            return;
        }
        $this->renderProjectForm($project);
    }

    private function renderProjectForm(?WikiProject $project = null, string $message = '', string $variant = 'info'): void
    {
        $user = $this->auth->user();
        if ($user === null) {
            return;
        }
        $editing = $project !== null;
        $this->startAdminPage(
            $user,
            $editing ? 'Edytuj projekt dokumentacji' : 'Dodaj projekt dokumentacji',
            'Projekt grupuje strony wiki dla jednego produktu lub usługi.',
            null,
            $editing ? [
                ['label' => $project->name, 'href' => ''],
            ] : []
        );
        if ($message !== '') {
            $this->theme->render_alert($message, $variant);
        }
        $this->theme->start_admin_panel('Dane projektu', $editing ? 'ID ' . $project->id : 'Nowy szkic');
        $this->theme->render_form(
            $editing ? 'index.php?route=/admin/wikipedia/projects/edit' : 'index.php?route=/admin/wikipedia/projects/create',
            [
                ...($editing ? [['name' => 'id', 'label' => 'ID', 'type' => 'hidden', 'value' => (string) $project->id]] : []),
                ['name' => 'name', 'label' => 'Nazwa projektu', 'value' => $project?->name ?? ''],
                ['name' => 'slug', 'label' => 'Slug (opcjonalnie)', 'value' => $project?->slug ?? ''],
                ['name' => 'summary', 'label' => 'Opis', 'type' => 'textarea', 'rows' => 3, 'value' => $project?->summary ?? ''],
                ['name' => 'sort_order', 'label' => 'Kolejność', 'type' => 'number', 'value' => (string) ($project?->sortOrder ?? 100)],
            ],
            $editing ? 'Zapisz projekt' : 'Utwórz projekt',
            $this->security->csrfToken()
        );
        $this->theme->end_admin_panel();
        $this->endAdminPage();
    }

    private function renderPageEdit(Request $request): void
    {
        $page = $this->wiki->findPage($request->queryInt('id') ?? 0);
        if ($page === null) {
            http_response_code(404);
            $this->theme->render_admin_access_state(404, 'Nie znaleziono strony', 'Wybrana strona dokumentacji nie istnieje.', 'index.php?route=/admin/wikipedia', 'Wróć do dokumentacji');
            return;
        }
        $this->renderPageForm($page);
    }

    private function renderPageForm(?WikiPage $page = null, ?int $defaultProjectId = null, string $message = '', string $variant = 'info'): void
    {
        $user = $this->auth->user();
        if ($user === null) {
            return;
        }
        $editing = $page !== null;
        $projects = [];
        $projectRecords = [];
        foreach ($this->wiki->projects() as $project) {
            $projects[(string) $project->id] = $project->name;
            $projectRecords[$project->id] = $project;
        }
        $selectedProject = $page?->projectId ?? $defaultProjectId ?? (int) (array_key_first($projects) ?? 0);
        $selectedProjectRecord = $projectRecords[$selectedProject] ?? null;
        $this->startAdminPage(
            $user,
            $editing ? 'Edytuj stronę dokumentacji' : 'Dodaj stronę dokumentacji',
            'Strony mogą używać kontrolowanego HTML albo Markdown.',
            null,
            $this->adminPageBreadcrumbContext($selectedProjectRecord, $page)
        );
        if ($message !== '') {
            $this->theme->render_alert($message, $variant);
        }
        if ($projects === []) {
            $this->theme->render_alert('Najpierw utwórz projekt dokumentacji.', 'warning');
            $this->endAdminPage();
            return;
        }
        $this->theme->start_admin_panel('Treść strony', $editing ? 'ID ' . $page->id : 'Nowy szkic');
        $this->theme->render_form(
            $editing ? 'index.php?route=/admin/wikipedia/pages/edit' : 'index.php?route=/admin/wikipedia/pages/create',
            [
                ...($editing ? [['name' => 'id', 'label' => 'ID', 'type' => 'hidden', 'value' => (string) $page->id]] : []),
                ['name' => 'project_id', 'label' => 'Projekt', 'type' => 'select', 'value' => (string) $selectedProject, 'options' => $projects],
                ['name' => 'title', 'label' => 'Tytuł', 'value' => $page?->title ?? ''],
                ['name' => 'slug', 'label' => 'Slug (opcjonalnie)', 'value' => $page?->slug ?? ''],
                ['name' => 'summary', 'label' => 'Opis', 'type' => 'textarea', 'rows' => 3, 'value' => $page?->summary ?? ''],
                ['name' => 'sort_order', 'label' => 'Kolejność', 'type' => 'number', 'value' => (string) ($page?->sortOrder ?? 100)],
                [
                    'name' => 'content',
                    'label' => 'Treść dokumentacji',
                    'type' => 'richtext',
                    'value' => $page?->content ?? '',
                    'format_name' => 'content_format',
                    'format_value' => $page?->contentFormat ?? 'markdown',
                ],
            ],
            $editing ? 'Zapisz stronę' : 'Utwórz stronę',
            $this->security->csrfToken()
        );
        $this->theme->end_admin_panel();
        $this->endAdminPage();
    }

    private function createProject(Request $request): void
    {
        if (!$this->validCsrf($request, 'wiki_project_create')) {
            return;
        }
        [$data, $error] = $this->validatedProjectInput($request);
        if ($error !== '') {
            $this->renderProjectForm(null, $error, 'danger');
            return;
        }
        $this->wiki->createProject($data);
        $this->invalidateWikiCache($data['slug']);
        $this->audit->record($request, 'wiki_project_create', 'success', null, $this->auth->user()?->id);
        header('Location: index.php?route=/admin/wikipedia', true, 303);
    }

    private function updateProject(Request $request): void
    {
        if (!$this->validCsrf($request, 'wiki_project_update')) {
            return;
        }
        $id = $request->postInt('id') ?? 0;
        $project = $this->wiki->findProject($id);
        if ($project === null) {
            $this->renderDashboard('Nie znaleziono projektu do edycji.', 'danger');
            return;
        }
        [$data, $error] = $this->validatedProjectInput($request, $id);
        if ($error !== '') {
            $this->renderProjectForm($project, $error, 'danger');
            return;
        }
        $this->wiki->updateProject($id, $data);
        $this->invalidateWikiCache($project->slug, $data['slug']);
        $this->audit->record($request, 'wiki_project_update', 'success', null, $this->auth->user()?->id);
        header('Location: index.php?route=/admin/wikipedia', true, 303);
    }

    private function changeProjectPublication(Request $request): void
    {
        if (!$this->validCsrf($request, 'wiki_project_publish')) {
            return;
        }
        $id = $request->postInt('id') ?? 0;
        $publish = $request->postString('action') === 'publish';
        $changed = $this->wiki->setProjectPublished($id, $publish);
        $project = $this->wiki->findProject($id);
        $this->invalidateWikiCache($project?->slug);
        $this->audit->record($request, 'wiki_project_publish', $changed ? ($publish ? 'published' : 'draft') : 'not_found', null, $this->auth->user()?->id);
        $this->renderDashboard($changed ? 'Status projektu został zaktualizowany.' : 'Nie znaleziono projektu.', $changed ? 'success' : 'warning');
    }

    private function deleteProject(Request $request): void
    {
        if (!$this->validCsrf($request, 'wiki_project_delete')) {
            return;
        }
        $id = $request->postInt('id') ?? 0;
        $project = $this->wiki->findProject($id);
        $deleted = $this->wiki->deleteProject($id);
        $this->invalidateWikiCache($project?->slug);
        $this->audit->record($request, 'wiki_project_delete', $deleted ? 'success' : 'not_found', null, $this->auth->user()?->id);
        $this->renderDashboard($deleted ? 'Projekt dokumentacji został usunięty.' : 'Nie znaleziono projektu.', $deleted ? 'success' : 'warning');
    }

    private function createPage(Request $request): void
    {
        if (!$this->validCsrf($request, 'wiki_page_create')) {
            return;
        }
        [$projectId, $title, $slug, $summary, $content, $format, $sortOrder, $error] = $this->validatedPageInput($request);
        if ($error !== '') {
            $this->renderPageForm(null, $projectId, $error, 'danger');
            return;
        }
        $id = $this->wiki->createPage($projectId, $title, $slug, $summary, $content, $format, $sortOrder, $this->auth->user()?->id ?? 0);
        $page = $this->wiki->findPage($id);
        $this->invalidateWikiCache($page?->projectSlug, $page?->slug);
        $this->audit->record($request, 'wiki_page_create', 'success', null, $this->auth->user()?->id);
        header('Location: index.php?route=/admin/wikipedia/pages/edit&id=' . $id, true, 303);
    }

    private function updatePage(Request $request): void
    {
        if (!$this->validCsrf($request, 'wiki_page_update')) {
            return;
        }
        $id = $request->postInt('id') ?? 0;
        $page = $this->wiki->findPage($id);
        if ($page === null) {
            $this->renderDashboard('Nie znaleziono strony do edycji.', 'danger');
            return;
        }
        [$projectId, $title, $slug, $summary, $content, $format, $sortOrder, $error] = $this->validatedPageInput($request, $id);
        if ($error !== '') {
            $this->renderPageForm($page, null, $error, 'danger');
            return;
        }
        $this->wiki->updatePage($id, $projectId, $title, $slug, $summary, $content, $format, $sortOrder);
        $updated = $this->wiki->findPage($id);
        $this->invalidateWikiCache($page->projectSlug, $page->slug, $updated?->projectSlug, $updated?->slug);
        $this->audit->record($request, 'wiki_page_update', 'success', null, $this->auth->user()?->id);
        header('Location: index.php?route=/admin/wikipedia/pages/edit&id=' . $id, true, 303);
    }

    private function changePagePublication(Request $request): void
    {
        if (!$this->validCsrf($request, 'wiki_page_publish')) {
            return;
        }
        $id = $request->postInt('id') ?? 0;
        $publish = $request->postString('action') === 'publish';
        $changed = $this->wiki->setPagePublished($id, $publish);
        $page = $this->wiki->findPage($id);
        $this->invalidateWikiCache($page?->projectSlug, $page?->slug);
        $this->audit->record($request, 'wiki_page_publish', $changed ? ($publish ? 'published' : 'draft') : 'not_found', null, $this->auth->user()?->id);
        $this->renderDashboard($changed ? 'Status strony został zaktualizowany.' : 'Nie znaleziono strony.', $changed ? 'success' : 'warning');
    }

    private function deletePage(Request $request): void
    {
        if (!$this->validCsrf($request, 'wiki_page_delete')) {
            return;
        }
        $id = $request->postInt('id') ?? 0;
        $page = $this->wiki->findPage($id);
        $deleted = $this->wiki->deletePage($id);
        $this->invalidateWikiCache($page?->projectSlug, $page?->slug);
        $this->audit->record($request, 'wiki_page_delete', $deleted ? 'success' : 'not_found', null, $this->auth->user()?->id);
        $this->renderDashboard($deleted ? 'Strona dokumentacji została usunięta.' : 'Nie znaleziono strony.', $deleted ? 'success' : 'warning');
    }

    /**
     * @return array{0: array{name: string, slug: string, summary: string, sort_order: int}, 1: string}
     */
    private function validatedProjectInput(Request $request, ?int $exceptId = null): array
    {
        $name = $request->postString('name');
        $slug = $this->normalizeSlug($request->postString('slug') ?: $name);
        $summary = $request->postString('summary');
        $sortOrder = max(0, min(65535, $request->postInt('sort_order', 100) ?? 100));
        $data = ['name' => $name, 'slug' => $slug, 'summary' => $summary, 'sort_order' => $sortOrder];
        if ($name === '' || strlen($name) > 160) {
            return [$data, 'Nazwa projektu jest wymagana i może mieć maksymalnie 160 znaków.'];
        }
        if ($slug === '' || strlen($slug) > 191 || $this->wiki->projectSlugExists($slug, $exceptId)) {
            return [$data, 'Slug projektu jest nieprawidłowy albo już używany.'];
        }
        if ($summary === '' || strlen($summary) > 500) {
            return [$data, 'Opis projektu jest wymagany i może mieć maksymalnie 500 znaków.'];
        }

        return [$data, ''];
    }

    /**
     * @return array{0: int, 1: string, 2: string, 3: string, 4: string, 5: string, 6: int, 7: string}
     */
    private function validatedPageInput(Request $request, ?int $exceptId = null): array
    {
        $projectId = $request->postInt('project_id') ?? 0;
        $title = $request->postString('title');
        $slug = $this->normalizeSlug($request->postString('slug') ?: $title);
        $summary = $request->postString('summary');
        $format = (new ContentRenderer())->normalizeFormat($request->postString('content_format'));
        $content = (new ContentRenderer())->prepareForStorage($request->postString('content'), $format);
        $sortOrder = max(0, min(65535, $request->postInt('sort_order', 100) ?? 100));
        if ($this->wiki->findProject($projectId) === null) {
            return [$projectId, $title, $slug, $summary, $content, $format, $sortOrder, 'Wybierz istniejący projekt dokumentacji.'];
        }
        if ($title === '' || strlen($title) > 180) {
            return [$projectId, $title, $slug, $summary, $content, $format, $sortOrder, 'Tytuł jest wymagany i może mieć maksymalnie 180 znaków.'];
        }
        if ($slug === '' || strlen($slug) > 191 || $this->wiki->pageSlugExists($projectId, $slug, $exceptId)) {
            return [$projectId, $title, $slug, $summary, $content, $format, $sortOrder, 'Slug strony jest nieprawidłowy albo już używany w projekcie.'];
        }
        if ($summary === '' || strlen($summary) > 500) {
            return [$projectId, $title, $slug, $summary, $content, $format, $sortOrder, 'Opis strony jest wymagany i może mieć maksymalnie 500 znaków.'];
        }
        if ($content === '') {
            return [$projectId, $title, $slug, $summary, $content, $format, $sortOrder, 'Treść dokumentacji jest wymagana.'];
        }

        return [$projectId, $title, $slug, $summary, $content, $format, $sortOrder, ''];
    }

    private function normalizeSlug(string $value): string
    {
        $value = strtolower(trim($value));
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';

        return trim($value, '-');
    }

    /**
     * @return list<array{label: string, title: string, href: string, description: string, disabled?: bool}>
     */
    private function wikiPageNavigation(WikiPage $current): array
    {
        $pages = $this->wiki->publishedPages($current->projectId);
        $previous = null;
        $next = null;
        foreach ($pages as $index => $page) {
            if ($page->id !== $current->id) {
                continue;
            }
            $previous = $pages[$index - 1] ?? null;
            $next = $pages[$index + 1] ?? null;
            break;
        }

        return [
            [
                'label' => 'Poprzednia strona...',
                'title' => $previous !== null ? '"' . $previous->title . '"' : 'Początek dokumentacji',
                'href' => $previous !== null ? $this->wikiPageHref($previous) : '',
                'direction' => 'previous',
                'description' => $previous?->summary ?? 'To pierwsza opublikowana strona projektu.',
                'disabled' => $previous === null,
            ],
            [
                'label' => 'Spis projektu',
                'title' => $current->projectName,
                'href' => 'index.php?route=/wiki/project&slug=' . rawurlencode($current->projectSlug),
                'direction' => 'index',
                'description' => 'Wróć do listy stron dokumentacji projektu.',
            ],
            [
                'label' => 'Następna strona...',
                'title' => $next !== null ? '"' . $next->title . '"' : 'Koniec dokumentacji',
                'href' => $next !== null ? $this->wikiPageHref($next) : '',
                'direction' => 'next',
                'description' => $next?->summary ?? 'To ostatnia opublikowana strona projektu.',
                'disabled' => $next === null,
            ],
        ];
    }

    private function wikiPageHref(WikiPage $page): string
    {
        return 'index.php?route=/wiki/page&project=' . rawurlencode($page->projectSlug)
            . '&slug=' . rawurlencode($page->slug);
    }

    private function validCsrf(Request $request, string $event): bool
    {
        if ($this->security->validateCsrfToken($request->postString('_token'))) {
            return true;
        }
        $this->audit->record($request, $event, 'invalid_csrf', null, $this->auth->user()?->id);
        http_response_code(403);
        $this->theme->render_admin_access_state(403, 'Nieprawidłowy token CSRF', 'Operacja na dokumentacji została odrzucona.', 'index.php?route=/admin/wikipedia', 'Wróć do dokumentacji');

        return false;
    }

    /**
     * @param callable(): string $renderer
     * @param list<string> $tags
     */
    private function cachedPublic(string $key, callable $renderer, array $tags): string
    {
        return $this->auth->user() === null
            ? $this->templateCache->remember('public', $key, $renderer, $tags)
            : $renderer();
    }

    private function capture(callable $renderer): string
    {
        ob_start();
        $renderer();

        return (string) ob_get_clean();
    }

    private function invalidateWikiCache(?string ...$parts): void
    {
        $tags = ['wikipedia', 'wiki:index'];
        $values = array_values(array_filter($parts, static fn (?string $part): bool => $part !== null && $part !== ''));
        foreach ($values as $value) {
            $tags[] = 'wiki:project:' . $value;
        }
        if (count($values) >= 2) {
            $tags[] = 'wiki:page:' . $values[0] . ':' . $values[1];
            $tags[] = 'wiki:page:' . $values[count($values) - 2] . ':' . $values[count($values) - 1];
        }
        $this->templateCache->invalidateTags($tags);
    }

    private function guard(Request $request, string $permission, callable $handler): void
    {
        $decision = $this->access->check($permission);
        if ($decision !== AdminAccessGate::ALLOWED) {
            $status = $decision === AdminAccessGate::UNAUTHENTICATED ? 401 : 403;
            $this->audit->record($request, 'wikipedia_access', $status === 401 ? 'unauthenticated' : 'forbidden', null, $this->auth->user()?->id);
            http_response_code($status);
            $this->theme->render_admin_access_state(
                $status,
                $status === 401 ? 'Wymagane logowanie' : 'Brak uprawnienia',
                $status === 401 ? 'Ta trasa wymaga aktywnej sesji.' : "Twoje konto nie posiada uprawnienia {$permission}.",
                $status === 401 ? 'index.php?route=/admin/login' : 'index.php?route=/admin',
                $status === 401 ? 'Przejdź do logowania' : 'Wróć do dashboardu'
            );
            return;
        }
        $handler();
    }

    private function allows(User $user): callable
    {
        return static fn (string $permission): bool => in_array('*', $user->permissions, true)
            || in_array($permission, $user->permissions, true);
    }

    /**
     * @param array{label: string, href: string}|null $action
     * @param list<array{label: string, href: string}> $contextBreadcrumbs
     */
    private function startAdminPage(
        User $user,
        string $title,
        string $lead,
        ?array $action = null,
        array $contextBreadcrumbs = [],
    ): void {
        $this->theme->start_admin_page(
            $title,
            $this->menu->visibleFor($user->permissions),
            '/admin/wikipedia',
            $this->adminUser($user)
        );
        $this->theme->start_admin_content(
            $title,
            $lead,
            [
                ['label' => 'Panel', 'href' => 'index.php?route=/admin'],
                ['label' => 'Dokumentacja', 'href' => $title === 'Dokumentacja' ? '' : 'index.php?route=/admin/wikipedia'],
                ...$contextBreadcrumbs,
                ...($title !== 'Dokumentacja' ? [['label' => $title, 'href' => '']] : []),
            ],
            $action
        );
    }

    /**
     * @return list<array{label: string, href: string}>
     */
    private function adminPageBreadcrumbContext(?WikiProject $project, ?WikiPage $page): array
    {
        $breadcrumbs = [];
        if ($project !== null) {
            $breadcrumbs[] = [
                'label' => $project->name,
                'href' => 'index.php?route=/admin/wikipedia/projects/edit&id=' . $project->id,
            ];
        } elseif ($page !== null) {
            $breadcrumbs[] = [
                'label' => $page->projectName,
                'href' => 'index.php?route=/admin/wikipedia',
            ];
        }
        if ($page !== null) {
            $breadcrumbs[] = [
                'label' => $page->title,
                'href' => 'index.php?route=/admin/wikipedia/pages/edit&id=' . $page->id,
            ];
        }

        return $breadcrumbs;
    }

    private function endAdminPage(): void
    {
        $this->theme->end_admin_content();
        $this->theme->end_admin_page();
    }

    private function adminUser(User $user): array
    {
        return [
            'name' => $user->displayName,
            'role' => ucfirst($user->primaryRole()),
            'initials' => $user->initials(),
            'logout_action' => 'index.php?route=/admin/logout',
            'logout_token' => $this->security->csrfToken(),
        ];
    }
}
