<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\Articles;

use SyntaxDevTeam\Cms\Core\AdminMenuRegistry;
use SyntaxDevTeam\Cms\Core\ContentRenderer;
use SyntaxDevTeam\Cms\Core\ModuleInterface;
use SyntaxDevTeam\Cms\Core\PublicNavigationProviderInterface;
use SyntaxDevTeam\Cms\Core\PublicNavigationRegistry;
use SyntaxDevTeam\Cms\Core\Request;
use SyntaxDevTeam\Cms\Core\Router;
use SyntaxDevTeam\Cms\Core\Security;
use SyntaxDevTeam\Cms\Core\ThemeInterface;
use SyntaxDevTeam\Cms\Core\TemplateCacheInterface;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AdminAccessGate;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuditLogService;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuthService;
use SyntaxDevTeam\Cms\Modules\CoreAuth\User;

final class ArticlesModule implements ModuleInterface, PublicNavigationProviderInterface
{
    public function __construct(
        private readonly ThemeInterface $theme,
        private readonly AdminMenuRegistry $menu,
        private readonly ArticleRepository $articles,
        private readonly AuthService $auth,
        private readonly AdminAccessGate $access,
        private readonly Security $security,
        private readonly AuditLogService $audit,
        private readonly TemplateCacheInterface $templateCache,
    ) {
    }

    public function id(): string
    {
        return 'articles';
    }

    public function version(): string
    {
        return '1.0.4';
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
        return ['articles.view'];
    }

    public function registerAdminMenu(AdminMenuRegistry $menu): void
    {
        $menu->add('Treść', 'Artykuły', '/admin/articles', 'AR', 'articles.view', 30);
    }

    public function registerPublicNavigation(PublicNavigationRegistry $navigation): void
    {
        $navigation->add('articles.index', 'Articles', '/articles', 'none', 50);
    }

    public function registerRoutes(Router $router): void
    {
        $router->get('/articles', fn (Request $request) => $this->renderPublicList($request));
        $router->get('/article', fn (Request $request) => $this->renderPublicArticle($request));
        $router->get('/article/{slug}', fn (Request $request) => $this->renderPublicArticleSlug($request->routeString('slug')));
        $router->get('/admin/articles', fn (Request $request) => $this->guard(
            $request,
            'articles.view',
            fn () => $this->renderList()
        ));
        $router->get('/admin/articles/create', fn (Request $request) => $this->guard(
            $request,
            'articles.create',
            fn () => $this->renderForm()
        ));
        $router->post('/admin/articles/create', fn (Request $request) => $this->guard(
            $request,
            'articles.create',
            fn () => $this->create($request)
        ));
        $router->get('/admin/articles/edit', fn (Request $request) => $this->guard(
            $request,
            'articles.edit',
            fn () => $this->renderEdit($request)
        ));
        $router->post('/admin/articles/edit', fn (Request $request) => $this->guard(
            $request,
            'articles.edit',
            fn () => $this->update($request)
        ));
        $router->post('/admin/articles/publish', fn (Request $request) => $this->guard(
            $request,
            'articles.publish',
            fn () => $this->changePublication($request)
        ));
        $router->post('/admin/articles/delete', fn (Request $request) => $this->guard(
            $request,
            'articles.delete',
            fn () => $this->delete($request)
        ));
        $router->get('/admin/articles/categories', fn (Request $request) => $this->guard(
            $request,
            'articles.edit',
            fn () => $this->renderCategories()
        ));
        $router->post('/admin/articles/categories/create', fn (Request $request) => $this->guard(
            $request,
            'articles.edit',
            fn () => $this->createCategory($request)
        ));
        $router->post('/admin/articles/categories/delete', fn (Request $request) => $this->guard(
            $request,
            'articles.delete',
            fn () => $this->deleteCategory($request)
        ));
    }

    private function renderPublicList(Request $request): void
    {
        $category = $this->normalizeSlug($request->queryString('category'));
        echo $this->cachedPublic(
            'articles:index:' . ($category !== '' ? $category : 'all'),
            function () use ($category): string {
                $articles = $this->articles->published($category !== '' ? $category : null);

                return $this->capture(function () use ($articles, $category): void {
        $this->theme->start_page('Articles - SyntaxDevTeam', 'Published SyntaxDevTeam articles.');
        $this->theme->start_header(
            'Articles',
            $category !== '' ? 'Category: ' . $category : 'News, tutorials and project updates.',
            'SyntaxDevTeam / Articles'
        );
        $this->theme->end_header();
        $this->theme->start_section();

        if ($articles === []) {
            $this->theme->render_alert('There are no published articles in the selected category yet.', 'info');
        } else {
            $this->theme->start_grid();
            foreach ($articles as $article) {
                $this->theme->start_column('md-6');
                $this->theme->start_card($article->title, $article->categoryName);
                $this->theme->render_text($article->summary);
                $this->theme->render_button(
                    'Read article',
                    $this->articleHref($article->slug),
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
            },
            ['articles', 'articles:index', $category !== '' ? 'article-category:' . $category : 'article-category:all', 'theme']
        );
    }

    private function renderPublicArticle(Request $request): void
    {
        $this->renderPublicArticleSlug($this->normalizeSlug($request->queryString('slug')));
    }

    private function renderPublicArticleSlug(string $slug): void
    {
        $article = $slug !== '' ? $this->articles->findPublishedBySlug($slug) : null;

        if ($article === null) {
            http_response_code(404);
            $this->theme->render_page_not_found(
                'Article not found',
                'This article does not exist or has not been published yet.'
            );
            return;
        }

        echo $this->cachedPublic(
            'article:' . $article->slug,
            fn (): string => $this->capture(function () use ($article): void {
        $this->theme->start_page($article->title . ' - SyntaxDevTeam', $article->summary);
        $this->theme->start_header(
            $article->title,
            $article->categoryName . ' | Published: ' . ($article->publishedAt ?? $article->updatedAt),
            'Article / ' . $article->categoryName
        );
        $this->theme->end_header();
        $this->theme->start_section();
        $this->theme->start_card('', 'Article');
        $this->theme->render_rich_content($article->content, $article->contentFormat);
        $this->theme->render_button('Back to articles', '/articles', 'outline-light');
        $this->theme->end_card();
        $this->theme->end_section();
        $this->theme->end_page();
            }),
            ['articles', 'article:' . $article->slug, 'article-category:' . $article->categoryName, 'theme']
        );
    }

    private function articleHref(string $slug): string
    {
        return '/article/' . rawurlencode($slug);
    }

    private function renderList(string $message = '', string $variant = 'info'): void
    {
        $user = $this->auth->user();

        if ($user === null) {
            return;
        }

        $articles = $this->articles->all();
        $allows = static fn (string $permission): bool => in_array('*', $user->permissions, true)
            || in_array($permission, $user->permissions, true);

        $this->startAdminPage(
            $user,
            'Artykuły',
            'Twórz niezależne treści kategoryzowane i publikowane przez moduł articles.',
            $allows('articles.create')
                ? ['label' => 'Dodaj artykuł', 'href' => 'index.php?route=/admin/articles/create']
                : null
        );

        if ($message !== '') {
            $this->theme->render_alert($message, $variant);
        }

        if ($allows('articles.edit')) {
            $this->theme->render_button(
                'Zarządzaj kategoriami',
                'index.php?route=/admin/articles/categories',
                'outline-light'
            );
        }
        $this->theme->render_button(
            'Zobacz publiczne artykuły',
            'index.php?route=/articles',
            'outline-light'
        );

        $this->theme->start_admin_panel('Lista artykułów', count($articles) . ' rekordów');
        if ($articles === []) {
            $this->theme->render_alert('Brak artykułów. Utwórz pierwszy szkic, aby rozpocząć.', 'info');
        } else {
            $this->theme->render_admin_action_table(
                ['Tytuł', 'Kategoria', 'Slug', 'Status', 'Aktualizacja'],
                array_map(
                    static fn (Article $article): array => [
                        'cells' => [
                            $article->title,
                            $article->categoryName,
                            $article->slug,
                            $article->status === 'published' ? 'Opublikowany' : 'Szkic',
                            $article->updatedAt,
                        ],
                        'actions' => array_values(array_filter([
                            $allows('articles.edit') ? [
                                'label' => 'Edytuj',
                                'href' => 'index.php?route=/admin/articles/edit&id=' . $article->id,
                                'variant' => 'outline-light',
                            ] : null,
                            $allows('articles.publish') ? [
                                'label' => $article->status === 'published' ? 'Cofnij' : 'Publikuj',
                                'action' => 'index.php?route=/admin/articles/publish',
                                'fields' => [
                                    'id' => $article->id,
                                    'action' => $article->status === 'published' ? 'draft' : 'publish',
                                ],
                                'variant' => 'outline-primary',
                            ] : null,
                            $allows('articles.delete') ? [
                                'label' => 'Usuń',
                                'action' => 'index.php?route=/admin/articles/delete',
                                'fields' => ['id' => $article->id],
                                'variant' => 'outline-danger',
                            ] : null,
                        ])),
                    ],
                    $articles
                ),
                $this->security->csrfToken()
            );
        }
        $this->theme->end_admin_panel();
        $this->endAdminPage();
    }

    private function renderCategories(string $message = '', string $variant = 'info'): void
    {
        $user = $this->auth->user();

        if ($user === null) {
            return;
        }

        $categories = $this->articles->categories();
        $this->startAdminPage(
            $user,
            'Kategorie artykułów',
            'Kategorie są własnością modułu articles i nie wpływają na core_pages.'
        );

        if ($message !== '') {
            $this->theme->render_alert($message, $variant);
        }

        $this->theme->start_admin_panel('Nowa kategoria');
        $this->theme->render_form(
            'index.php?route=/admin/articles/categories/create',
            [
                ['name' => 'name', 'label' => 'Nazwa kategorii'],
                ['name' => 'slug', 'label' => 'Slug (opcjonalnie)'],
            ],
            'Dodaj kategorię',
            $this->security->csrfToken()
        );
        $this->theme->end_admin_panel();

        $this->theme->start_admin_panel('Kategorie', count($categories) . ' rekordów');
        $this->theme->render_admin_action_table(
            ['Nazwa', 'Slug'],
            array_map(
                static fn (Category $category): array => [
                    'cells' => [$category->name, $category->slug],
                    'actions' => [[
                        'label' => 'Usuń',
                        'action' => 'index.php?route=/admin/articles/categories/delete',
                        'fields' => ['id' => $category->id],
                        'variant' => 'outline-danger',
                    ]],
                ],
                $categories
            ),
            $this->security->csrfToken()
        );
        $this->theme->end_admin_panel();
        $this->endAdminPage();
    }

    private function renderEdit(Request $request): void
    {
        $article = $this->articles->find($request->queryInt('id') ?? 0);

        if ($article === null) {
            http_response_code(404);
            $this->theme->render_admin_access_state(
                404,
                'Nie znaleziono artykułu',
                'Wybrany artykuł nie istnieje.',
                'index.php?route=/admin/articles',
                'Wróć do listy'
            );
            return;
        }

        $this->renderForm($article);
    }

    private function renderForm(?Article $article = null, string $message = '', string $variant = 'info'): void
    {
        $user = $this->auth->user();

        if ($user === null) {
            return;
        }

        $editing = $article !== null;
        $title = $editing ? 'Edytuj artykuł' : 'Dodaj artykuł';
        $this->startAdminPage($user, $title, 'Formularz modułu articles korzysta z ogólnych komponentów Theme.');

        if ($message !== '') {
            $this->theme->render_alert($message, $variant);
        }

        $categories = [];
        foreach ($this->articles->categories() as $category) {
            $categories[(string) $category->id] = $category->name;
        }

        $this->theme->start_admin_panel('Dane artykułu', $editing ? 'ID ' . $article->id : 'Nowy szkic');
        $this->theme->render_form(
            $editing
                ? 'index.php?route=/admin/articles/edit'
                : 'index.php?route=/admin/articles/create',
            [
                ...($editing ? [[
                    'name' => 'id',
                    'label' => 'ID',
                    'type' => 'hidden',
                    'value' => (string) $article->id,
                ]] : []),
                [
                    'name' => '_autosave_key',
                    'label' => 'Autozapis',
                    'type' => 'hidden',
                    'value' => 'article-' . ($article?->id ?? 'new'),
                ],
                [
                    'name' => 'category_id',
                    'label' => 'Kategoria',
                    'type' => 'select',
                    'value' => (string) ($article?->categoryId ?? array_key_first($categories) ?? ''),
                    'options' => $categories,
                ],
                ['name' => 'title', 'label' => 'Tytuł', 'value' => $article?->title ?? ''],
                ['name' => 'slug', 'label' => 'Slug (opcjonalnie)', 'value' => $article?->slug ?? ''],
                [
                    'name' => 'summary',
                    'label' => 'Zajawka',
                    'type' => 'textarea',
                    'rows' => 3,
                    'value' => $article?->summary ?? '',
                ],
                [
                    'name' => 'content',
                    'label' => 'Treść',
                    'type' => 'richtext',
                    'value' => $article?->content ?? '',
                    'format_name' => 'content_format',
                    'format_value' => $article?->contentFormat ?? 'html',
                    'help' => 'Przełączaj między edytorem wizualnym i Markdown w stylu GitHub.',
                ],
            ],
            $editing ? 'Zapisz zmiany' : 'Utwórz szkic',
            $this->security->csrfToken()
        );
        $this->theme->end_admin_panel();
        $this->endAdminPage();
    }

    private function create(Request $request): void
    {
        if (!$this->validCsrf($request, 'article_create')) {
            return;
        }

        [$categoryId, $title, $slug, $summary, $content, $contentFormat, $error] = $this->validatedInput($request);
        if ($error !== '') {
            $this->renderForm(null, $error, 'danger');
            return;
        }

        $user = $this->auth->user();
        $id = $this->articles->create(
            $categoryId,
            $title,
            $slug,
            $summary,
            $content,
            $contentFormat,
            $user?->id ?? 0
        );
        $this->invalidateArticleCache($slug);
        $this->audit->record($request, 'article_create', 'success', null, $user?->id);
        header(
            'Location: index.php?route=/admin/articles/edit&id=' . $id
            . '&autosave_clear=article-new',
            true,
            303
        );
    }

    private function update(Request $request): void
    {
        if (!$this->validCsrf($request, 'article_update')) {
            return;
        }

        $id = $request->postInt('id') ?? 0;
        $article = $this->articles->find($id);
        if ($article === null) {
            http_response_code(404);
            $this->renderList('Nie znaleziono artykułu do edycji.', 'danger');
            return;
        }

        [$categoryId, $title, $slug, $summary, $content, $contentFormat, $error] = $this->validatedInput($request, $id);
        if ($error !== '') {
            $this->renderForm($article, $error, 'danger');
            return;
        }

        $this->articles->update($id, $categoryId, $title, $slug, $summary, $content, $contentFormat);
        $this->invalidateArticleCache($article->slug, $slug);
        $userId = $this->auth->user()?->id;
        $this->audit->record($request, 'article_update', 'success', null, $userId);
        header(
            'Location: index.php?route=/admin/articles/edit&id=' . $id
            . '&autosave_clear=article-' . $id,
            true,
            303
        );
    }

    private function changePublication(Request $request): void
    {
        if (!$this->validCsrf($request, 'article_publish')) {
            return;
        }

        $id = $request->postInt('id') ?? 0;
        $publish = $request->postString('action') === 'publish';
        $changed = $publish ? $this->articles->publish($id) : $this->articles->unpublish($id);
        $article = $this->articles->find($id);
        $this->invalidateArticleCache($article?->slug);
        $this->audit->record(
            $request,
            'article_publish',
            $changed ? ($publish ? 'published' : 'draft') : 'not_found',
            null,
            $this->auth->user()?->id
        );
        $this->renderList(
            $changed ? 'Status publikacji został zaktualizowany.' : 'Nie znaleziono artykułu.',
            $changed ? 'success' : 'warning'
        );
    }

    private function delete(Request $request): void
    {
        if (!$this->validCsrf($request, 'article_delete')) {
            return;
        }

        $id = $request->postInt('id') ?? 0;
        $article = $this->articles->find($id);
        $deleted = $this->articles->delete($id);
        $this->invalidateArticleCache($article?->slug);
        $this->audit->record(
            $request,
            'article_delete',
            $deleted ? 'success' : 'not_found',
            null,
            $this->auth->user()?->id
        );
        $this->renderList(
            $deleted ? 'Artykuł został usunięty.' : 'Nie znaleziono artykułu.',
            $deleted ? 'success' : 'warning'
        );
    }

    private function createCategory(Request $request): void
    {
        if (!$this->validCsrf($request, 'article_category_create')) {
            return;
        }

        $name = $request->postString('name');
        $slug = $this->normalizeSlug($request->postString('slug') ?: $name);

        if ($name === '' || strlen($name) > 120 || $slug === '' || strlen($slug) > 191) {
            $this->renderCategories('Nazwa lub slug kategorii są nieprawidłowe.', 'danger');
            return;
        }
        if ($this->articles->categorySlugExists($slug) || $this->articles->categoryNameExists($name)) {
            $this->renderCategories('Kategoria z taką nazwą albo slugiem już istnieje.', 'warning');
            return;
        }

        $this->articles->createCategory($name, $slug);
        $this->invalidateArticleCache();
        $this->audit->record(
            $request,
            'article_category_create',
            'success',
            null,
            $this->auth->user()?->id
        );
        header('Location: index.php?route=/admin/articles/categories', true, 303);
    }

    private function deleteCategory(Request $request): void
    {
        if (!$this->validCsrf($request, 'article_category_delete')) {
            return;
        }

        $deleted = $this->articles->deleteCategory($request->postInt('id') ?? 0);
        $this->invalidateArticleCache();
        $this->audit->record(
            $request,
            'article_category_delete',
            $deleted ? 'success' : 'in_use_or_missing',
            null,
            $this->auth->user()?->id
        );
        $this->renderCategories(
            $deleted
                ? 'Kategoria została usunięta.'
                : 'Nie można usunąć nieistniejącej kategorii albo kategorii używanej przez artykuły.',
            $deleted ? 'success' : 'warning'
        );
    }

    /**
     * @return array{0: int, 1: string, 2: string, 3: string, 4: string, 5: string, 6: string}
     */
    private function validatedInput(Request $request, ?int $exceptId = null): array
    {
        $categoryId = $request->postInt('category_id') ?? 0;
        $title = $request->postString('title');
        $slug = $this->normalizeSlug($request->postString('slug') ?: $title);
        $summary = $request->postString('summary');
        $contentFormat = (new ContentRenderer())->normalizeFormat($request->postString('content_format'));
        $content = (new ContentRenderer())->prepareForStorage(
            $request->postString('content'),
            $contentFormat
        );

        if (!$this->articles->categoryExists($categoryId)) {
            return [$categoryId, $title, $slug, $summary, $content, $contentFormat, 'Wybierz istniejącą kategorię.'];
        }
        if ($title === '' || strlen($title) > 180) {
            return [$categoryId, $title, $slug, $summary, $content, $contentFormat, 'Tytuł jest wymagany i może mieć maksymalnie 180 znaków.'];
        }
        if ($slug === '' || strlen($slug) > 191 || $this->articles->slugExists($slug, $exceptId)) {
            return [$categoryId, $title, $slug, $summary, $content, $contentFormat, 'Slug jest nieprawidłowy albo już używany.'];
        }
        if ($summary === '' || strlen($summary) > 500) {
            return [$categoryId, $title, $slug, $summary, $content, $contentFormat, 'Zajawka jest wymagana i może mieć maksymalnie 500 znaków.'];
        }
        if ($content === '') {
            return [$categoryId, $title, $slug, $summary, $content, $contentFormat, 'Treść artykułu jest wymagana.'];
        }

        return [$categoryId, $title, $slug, $summary, $content, $contentFormat, ''];
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
            'Operacja na artykule została odrzucona.',
            'index.php?route=/admin/articles',
            'Wróć do listy'
        );
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

    private function invalidateArticleCache(?string ...$slugs): void
    {
        $tags = ['articles', 'articles:index'];
        foreach ($slugs as $slug) {
            if ($slug !== null && $slug !== '') {
                $tags[] = 'article:' . $slug;
            }
        }
        $this->templateCache->invalidateTags($tags);
    }

    private function guard(Request $request, string $permission, callable $handler): void
    {
        $decision = $this->access->check($permission);

        if ($decision !== AdminAccessGate::ALLOWED) {
            $status = $decision === AdminAccessGate::UNAUTHENTICATED ? 401 : 403;
            $this->audit->record(
                $request,
                'articles_access',
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

    private function startAdminPage(User $user, string $title, string $lead, ?array $action = null): void
    {
        $this->theme->start_admin_page(
            $title,
            $this->menu->visibleFor($user->permissions),
            '/admin/articles',
            $this->adminUser($user)
        );
        $this->theme->start_admin_content(
            $title,
            $lead,
            [
                ['label' => 'Panel', 'href' => 'index.php?route=/admin'],
                ['label' => 'Artykuły', 'href' => $title === 'Artykuły' ? '' : 'index.php?route=/admin/articles'],
                ...($title !== 'Artykuły' ? [['label' => $title, 'href' => '']] : []),
            ],
            $action
        );
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
            'avatar_url' => $user->avatarUrl ?? '',
            'logout_action' => 'index.php?route=/admin/logout',
            'logout_token' => $this->security->csrfToken(),
        ];
    }
}
