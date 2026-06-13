<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\CorePages;

use SyntaxDevTeam\Cms\Core\AdminMenuRegistry;
use SyntaxDevTeam\Cms\Core\ModuleInterface;
use SyntaxDevTeam\Cms\Core\Request;
use SyntaxDevTeam\Cms\Core\Router;
use SyntaxDevTeam\Cms\Core\Security;
use SyntaxDevTeam\Cms\Core\ThemeInterface;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AdminAccessGate;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuditLogService;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuthService;
use SyntaxDevTeam\Cms\Modules\CoreAuth\User;

final class CorePagesModule implements ModuleInterface
{
    public function __construct(
        private readonly ThemeInterface $theme,
        private readonly AdminMenuRegistry $menu,
        private readonly PageRepository $pages,
        private readonly AuthService $auth,
        private readonly AdminAccessGate $access,
        private readonly Security $security,
        private readonly AuditLogService $audit,
    ) {
    }

    public function id(): string
    {
        return 'core_pages';
    }

    public function requiredPermissions(): array
    {
        return ['pages.view'];
    }

    public function registerAdminMenu(AdminMenuRegistry $menu): void
    {
        $menu->add('Treść', 'Strony', '/admin/pages', 'PG', 'pages.view', 20);
    }

    public function registerRoutes(Router $router): void
    {
        $router->get('/page', fn (Request $request) => $this->renderPublicPage($request));
        $router->get('/admin/pages', fn (Request $request) => $this->guard(
            $request,
            'pages.view',
            fn () => $this->renderList()
        ));
        $router->get('/admin/pages/create', fn (Request $request) => $this->guard(
            $request,
            'pages.create',
            fn () => $this->renderForm()
        ));
        $router->post('/admin/pages/create', fn (Request $request) => $this->guard(
            $request,
            'pages.create',
            fn () => $this->create($request)
        ));
        $router->get('/admin/pages/edit', fn (Request $request) => $this->guard(
            $request,
            'pages.edit',
            fn () => $this->renderEdit($request)
        ));
        $router->post('/admin/pages/edit', fn (Request $request) => $this->guard(
            $request,
            'pages.edit',
            fn () => $this->update($request)
        ));
        $router->post('/admin/pages/publish', fn (Request $request) => $this->guard(
            $request,
            'pages.publish',
            fn () => $this->changePublication($request)
        ));
        $router->post('/admin/pages/delete', fn (Request $request) => $this->guard(
            $request,
            'pages.delete',
            fn () => $this->delete($request)
        ));
    }

    private function renderPublicPage(Request $request): void
    {
        $slug = $this->normalizeSlug($request->queryString('slug'));
        $page = $slug !== '' ? $this->pages->findPublishedBySlug($slug) : null;

        if ($page === null) {
            http_response_code(404);
            $this->theme->render_page_not_found(
                'Nie znaleziono strony',
                'Ta strona nie istnieje albo nie została jeszcze opublikowana.'
            );
            return;
        }

        $this->theme->render_public_page(
            $page->title,
            $page->content,
            $page->publishedAt ?? $page->updatedAt
        );
    }

    private function renderList(string $message = '', string $variant = 'info'): void
    {
        $user = $this->auth->user();

        if ($user === null) {
            return;
        }

        $pages = $this->pages->all();
        $allows = static fn (string $permission): bool => in_array('*', $user->permissions, true)
            || in_array($permission, $user->permissions, true);

        $this->theme->start_admin_page(
            'Strony',
            $this->menu->visibleFor($user->permissions),
            '/admin/pages',
            $this->adminUser($user)
        );
        $this->theme->start_admin_content(
            'Strony',
            'Twórz, edytuj i publikuj treści przez moduł core_pages.',
            [
                ['label' => 'Panel', 'href' => 'index.php?route=/admin'],
                ['label' => 'Strony', 'href' => ''],
            ],
            $allows('pages.create')
                ? ['label' => 'Dodaj stronę', 'href' => 'index.php?route=/admin/pages/create']
                : null
        );

        if ($message !== '') {
            $this->theme->render_alert($message, $variant);
        }

        $this->theme->start_admin_panel('Lista stron', count($pages) . ' rekordów');

        if ($pages === []) {
            $this->theme->render_alert('Brak stron. Utwórz pierwszy szkic, aby rozpocząć.', 'info');
        } else {
            $this->theme->render_admin_action_table(
                ['Tytuł', 'Slug', 'Status', 'Aktualizacja'],
                array_map(
                    static fn (Page $page): array => [
                        'cells' => [
                            $page->title,
                            $page->slug,
                            $page->status === 'published' ? 'Opublikowana' : 'Szkic',
                            $page->updatedAt,
                        ],
                        'actions' => array_values(array_filter([
                            $allows('pages.edit') ? [
                                'label' => 'Edytuj',
                                'href' => 'index.php?route=/admin/pages/edit&id=' . $page->id,
                                'variant' => 'outline-light',
                            ] : null,
                            $allows('pages.publish') ? [
                                'label' => $page->status === 'published' ? 'Cofnij' : 'Publikuj',
                                'action' => 'index.php?route=/admin/pages/publish',
                                'fields' => [
                                    'id' => $page->id,
                                    'action' => $page->status === 'published' ? 'draft' : 'publish',
                                ],
                                'variant' => 'outline-primary',
                            ] : null,
                            $allows('pages.delete') ? [
                                'label' => 'Usuń',
                                'action' => 'index.php?route=/admin/pages/delete',
                                'fields' => ['id' => $page->id],
                                'variant' => 'outline-danger',
                            ] : null,
                        ])),
                    ],
                    $pages
                ),
                $this->security->csrfToken()
            );
        }

        $this->theme->end_admin_panel();
        $this->theme->end_admin_content();
        $this->theme->end_admin_page();
    }

    private function renderEdit(Request $request): void
    {
        $page = $this->pages->find($request->queryInt('id') ?? 0);

        if ($page === null) {
            http_response_code(404);
            $this->theme->render_admin_access_state(
                404,
                'Nie znaleziono strony',
                'Wybrana strona nie istnieje.',
                'index.php?route=/admin/pages',
                'Wróć do listy'
            );
            return;
        }

        $this->renderForm($page);
    }

    private function renderForm(?Page $page = null, string $message = '', string $variant = 'info'): void
    {
        $user = $this->auth->user();

        if ($user === null) {
            return;
        }

        $editing = $page !== null;
        $title = $editing ? 'Edytuj stronę' : 'Dodaj stronę';

        $this->theme->start_admin_page(
            $title,
            $this->menu->visibleFor($user->permissions),
            '/admin/pages',
            $this->adminUser($user)
        );
        $this->theme->start_admin_content(
            $title,
            'Podstawowy formularz treści bez zależności od edytora WYSIWYG.',
            [
                ['label' => 'Panel', 'href' => 'index.php?route=/admin'],
                ['label' => 'Strony', 'href' => 'index.php?route=/admin/pages'],
                ['label' => $editing ? 'Edycja' : 'Nowa', 'href' => ''],
            ]
        );

        if ($message !== '') {
            $this->theme->render_alert($message, $variant);
        }

        $this->theme->start_admin_panel('Dane strony', $editing ? 'ID ' . $page->id : 'Nowy szkic');
        $this->theme->render_form(
            $editing
                ? 'index.php?route=/admin/pages/edit'
                : 'index.php?route=/admin/pages/create',
            [
                ...($editing ? [[
                    'name' => 'id',
                    'label' => 'ID',
                    'type' => 'hidden',
                    'value' => (string) $page->id,
                ]] : []),
                ['name' => 'title', 'label' => 'Tytuł', 'value' => $page?->title ?? ''],
                ['name' => 'slug', 'label' => 'Slug (opcjonalnie)', 'value' => $page?->slug ?? ''],
                [
                    'name' => 'content',
                    'label' => 'Treść',
                    'type' => 'textarea',
                    'rows' => 12,
                    'value' => $page?->content ?? '',
                ],
            ],
            $editing ? 'Zapisz zmiany' : 'Utwórz szkic',
            $this->security->csrfToken()
        );
        $this->theme->end_admin_panel();
        $this->theme->end_admin_content();
        $this->theme->end_admin_page();
    }

    private function create(Request $request): void
    {
        if (!$this->validCsrf($request, 'page_create')) {
            return;
        }

        [$title, $slug, $content, $error] = $this->validatedInput($request);

        if ($error !== '') {
            $this->renderForm(null, $error, 'danger');
            return;
        }

        $user = $this->auth->user();
        $id = $this->pages->create($title, $slug, $content, $user?->id ?? 0);
        $this->audit->record($request, 'page_create', 'success', null, $user?->id);
        header('Location: index.php?route=/admin/pages/edit&id=' . $id, true, 303);
    }

    private function update(Request $request): void
    {
        if (!$this->validCsrf($request, 'page_update')) {
            return;
        }

        $id = $request->postInt('id') ?? 0;
        $page = $this->pages->find($id);

        if ($page === null) {
            http_response_code(404);
            $this->renderList('Nie znaleziono strony do edycji.', 'danger');
            return;
        }

        [$title, $slug, $content, $error] = $this->validatedInput($request, $id);

        if ($error !== '') {
            $this->renderForm($page, $error, 'danger');
            return;
        }

        $this->pages->update($id, $title, $slug, $content);
        $userId = $this->auth->user()?->id;
        $this->audit->record($request, 'page_update', 'success', null, $userId);
        header('Location: index.php?route=/admin/pages/edit&id=' . $id, true, 303);
    }

    private function changePublication(Request $request): void
    {
        if (!$this->validCsrf($request, 'page_publish')) {
            return;
        }

        $id = $request->postInt('id') ?? 0;
        $publish = $request->postString('action') === 'publish';
        $changed = $publish ? $this->pages->publish($id) : $this->pages->unpublish($id);
        $this->audit->record(
            $request,
            'page_publish',
            $changed ? ($publish ? 'published' : 'draft') : 'not_found',
            null,
            $this->auth->user()?->id
        );
        $this->renderList(
            $changed ? 'Status publikacji został zaktualizowany.' : 'Nie znaleziono strony.',
            $changed ? 'success' : 'warning'
        );
    }

    private function delete(Request $request): void
    {
        if (!$this->validCsrf($request, 'page_delete')) {
            return;
        }

        $deleted = $this->pages->delete($request->postInt('id') ?? 0);
        $this->audit->record(
            $request,
            'page_delete',
            $deleted ? 'success' : 'not_found',
            null,
            $this->auth->user()?->id
        );
        $this->renderList(
            $deleted ? 'Strona została usunięta.' : 'Nie znaleziono strony.',
            $deleted ? 'success' : 'warning'
        );
    }

    /**
     * @return array{0: string, 1: string, 2: string, 3: string}
     */
    private function validatedInput(Request $request, ?int $exceptId = null): array
    {
        $title = $request->postString('title');
        $slug = $this->normalizeSlug($request->postString('slug') ?: $title);
        $content = $request->postString('content');

        if ($title === '' || strlen($title) > 180) {
            return [$title, $slug, $content, 'Tytuł jest wymagany i może mieć maksymalnie 180 znaków.'];
        }

        if ($slug === '' || strlen($slug) > 191) {
            return [$title, $slug, $content, 'Slug jest nieprawidłowy lub zbyt długi.'];
        }

        if ($this->pages->slugExists($slug, $exceptId)) {
            return [$title, $slug, $content, 'Slug jest już używany przez inną stronę.'];
        }

        return [$title, $slug, $content, ''];
    }

    private function normalizeSlug(string $value): string
    {
        $value = strtolower(trim($value));
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';

        return trim($value, '-');
    }

    private function validCsrf(Request $request, string $event): bool
    {
        if ($this->security->validateCsrfToken($request->postString('_token'))) {
            return true;
        }

        $this->audit->record($request, $event, 'invalid_csrf', null, $this->auth->user()?->id);
        http_response_code(403);
        $this->theme->render_admin_access_state(
            403,
            'Nieprawidłowy token CSRF',
            'Operacja na stronie została odrzucona.',
            'index.php?route=/admin/pages',
            'Wróć do listy'
        );
        return false;
    }

    private function guard(Request $request, string $permission, callable $handler): void
    {
        $decision = $this->access->check($permission);

        if ($decision !== AdminAccessGate::ALLOWED) {
            $status = $decision === AdminAccessGate::UNAUTHENTICATED ? 401 : 403;
            $this->audit->record(
                $request,
                'pages_access',
                $status === 401 ? 'unauthenticated' : 'forbidden',
                null,
                $this->auth->user()?->id
            );
            http_response_code($status);
            $this->theme->render_admin_access_state(
                $status,
                $status === 401 ? 'Wymagane logowanie' : 'Brak uprawnienia',
                $status === 401
                    ? 'Ta trasa wymaga aktywnej sesji.'
                    : "Twoje konto nie posiada uprawnienia {$permission}.",
                $status === 401 ? 'index.php?route=/admin/login' : 'index.php?route=/admin',
                $status === 401 ? 'Przejdź do logowania' : 'Wróć do dashboardu'
            );
            return;
        }

        $handler();
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
