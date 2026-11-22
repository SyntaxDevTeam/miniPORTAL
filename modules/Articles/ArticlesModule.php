<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\Articles;

use DateTimeImmutable;
use DomainException;
use PDOException;
use SyntaxDevTeam\Cms\Core\AdminMenuRegistry;
use SyntaxDevTeam\Cms\Core\ArticleThemeInterface;
use SyntaxDevTeam\Cms\Core\ContentRenderer;
use SyntaxDevTeam\Cms\Core\HookProviderInterface;
use SyntaxDevTeam\Cms\Core\HookRegistry;
use SyntaxDevTeam\Cms\Core\ModuleInterface;
use SyntaxDevTeam\Cms\Core\PublicNavigationProviderInterface;
use SyntaxDevTeam\Cms\Core\PublicNavigationRegistry;
use SyntaxDevTeam\Cms\Core\Request;
use SyntaxDevTeam\Cms\Core\Router;
use SyntaxDevTeam\Cms\Core\Security;
use SyntaxDevTeam\Cms\Core\SeoIndex;
use SyntaxDevTeam\Cms\Core\SeoProviderInterface;
use SyntaxDevTeam\Cms\Core\ThemeInterface;
use SyntaxDevTeam\Cms\Core\TemplateCacheInterface;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AdminAccessGate;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuditLogService;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuthService;
use SyntaxDevTeam\Cms\Modules\CoreAuth\User;
use SyntaxDevTeam\Cms\Modules\MediaLibrary\MediaAsset;
use SyntaxDevTeam\Cms\Modules\MediaLibrary\MediaAssetRepository;

final class ArticlesModule implements ModuleInterface, PublicNavigationProviderInterface, SeoProviderInterface, HookProviderInterface
{
    private const LANGUAGES = ['pl' => 'Polski', 'en' => 'English', 'und' => 'Unspecified'];
    private const STATUSES = [
        'draft' => 'Szkic',
        'published' => 'Opublikowany',
        'hidden' => 'Ukryty',
        'archived' => 'Zarchiwizowany',
    ];
    private const BULK_ACTIONS = [
        'category' => ['Zmień kategorię', 'articles.edit'],
        'language' => ['Zmień język', 'articles.edit'],
        'publish' => ['Opublikuj', 'articles.publish'],
        'hide' => ['Ukryj', 'articles.publish'],
        'archive' => ['Archiwizuj', 'articles.publish'],
        'restore' => ['Przywróć do szkiców', 'articles.publish'],
        'delete' => ['Usuń trwale', 'articles.delete'],
    ];

    public function __construct(
        private readonly ThemeInterface $theme,
        private readonly AdminMenuRegistry $menu,
        private readonly ArticleRepository $articles,
        private readonly AuthService $auth,
        private readonly AdminAccessGate $access,
        private readonly Security $security,
        private readonly AuditLogService $audit,
        private readonly TemplateCacheInterface $templateCache,
        private readonly ?MediaAssetRepository $mediaAssets = null,
        private readonly ?HookRegistry $hooks = null,
        private readonly string $publicUrl = '',
    ) {
    }

    public function id(): string
    {
        return 'articles';
    }

    public function version(): string
    {
        return '1.7.3';
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
        $router->get('/articles/code-runner', fn (Request $request) => $this->renderCodeRunner($request));
        $router->get('/articles', fn (Request $request) => $this->renderPublicList($request));
        $router->get('/article', fn (Request $request) => $this->renderPublicArticle($request));
        $router->post('/article/{slug}/statistics', fn (Request $request) => $this->trackBrowserEvent($request));
        $router->get('/article/{slug}/pdf', fn (Request $request) => $this->downloadPdf($request));
        $router->get('/article/{slug}', fn (Request $request) => $this->renderPublicArticleSlug($request->routeString('slug')));
        $router->get('/admin/articles', fn (Request $request) => $this->guard(
            $request,
            'articles.view',
            fn () => $this->renderList(request: $request)
        ));
        $router->post('/admin/articles/bulk', fn (Request $request) => $this->guard(
            $request,
            'articles.view',
            fn () => $this->bulk($request)
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

    public function registerHooks(HookRegistry $hooks): void
    {
        $hooks->addAction('articles.public.attachment_downloaded', fn (int $article) => $this->track('downloads', $article, $this->auth->actualUser()));
    }

    private function trackBrowserEvent(Request $request): void
    {
        header('Cache-Control: private, no-store');
        $event = $request->header('X-Article-Event');
        $column = match ($event) {
            'view' => 'views',
            'share' => 'shares',
            default => null,
        };
        $slug = $request->routeString('slug');
        if ($column === null || !preg_match('/\A[a-z0-9-]{1,191}\z/', $slug)) {
            http_response_code(400);
            return;
        }

        // The custom header keeps cross-origin forms from submitting events.
        $this->track($column, $slug, $this->auth->actualUser());
        http_response_code(204);
    }

    private function track(string $column, string|int $article, ?User $user): void
    {
        if ($user !== null && in_array('owner', $user->roles, true)) {
            return;
        }

        try {
            $this->articles->incrementStatistic($column, $article);
        } catch (\PDOException $exception) {
            error_log('miniPORTAL article statistics unavailable: ' . $exception->getCode());
        }
    }

    public function registerSeo(SeoIndex $seo): void
    {
        $articles = $this->articles->seoEntries();
        $seo->add(
            '/articles',
            'Articles',
            'Browse published articles, tutorials and project updates.',
            $articles[0]['updated_at'] ?? null,
            0.7,
            'weekly',
            'CollectionPage'
        );
        foreach ($articles as $article) {
            $seo->add(
                $this->articleHref($article['slug']),
                $article['seo_title'] !== '' ? $article['seo_title'] : $article['title'],
                $article['seo_description'] !== '' ? $article['seo_description'] : $article['summary'],
                $article['updated_at'],
                0.7,
                'monthly',
                'Article'
            );
        }
    }

    private function renderPublicList(Request $request): void
    {
        $query = mb_substr(mb_scrub($request->queryString('q'), 'UTF-8'), 0, 120, 'UTF-8');
        $category = substr($this->normalizeSlug($request->queryString('category')), 0, 191);
        $language = strtolower($request->queryString('lang'));
        $language = isset(self::LANGUAGES[$language]) ? $language : '';
        $page = max(1, $request->queryInt('page', 1) ?? 1);
        $filtered = $query !== '' || $category !== '' || $language !== '';

        $render = fn (): string => $this->capture(
            fn () => $this->renderCatalog($query, $category, $language, $page, $filtered)
        );
        echo !$filtered && $page === 1
            ? $this->cachedPublic('articles:catalog:v3', $render, ['articles', 'articles:index', 'theme'])
            : $render();
    }

    private function renderCatalog(string $query, string $category, string $language, int $page, bool $filtered): void
    {
        $result = $this->articles->searchPublished($query, $category, $language, $page);
        $page = $result['page'];
        $filters = array_filter(['q' => $query, 'category' => $category, 'lang' => $language], static fn (string $value): bool => $value !== '');
        $categories = [];
        foreach ($this->articles->categories(public: true) as $entry) {
            $categories[$entry->id] = $entry;
        }
        $categoryHref = fn (string $slug): string => $this->catalogHref(array_replace($filters, ['category' => $slug]));
        $tree = [];
        $categoryLabel = '';
        foreach ($categories as $entry) {
            if ($entry->parentId === null) {
                $tree[$entry->id] = [
                    'slug' => $entry->slug,
                    'name' => $entry->name,
                    'href' => $categoryHref($entry->slug),
                    'children' => [],
                ];
            }
        }
        foreach ($categories as $entry) {
            $parent = $categories[$entry->parentId] ?? null;
            if ($parent !== null) {
                $tree[$parent->id]['children'][] = [
                    'slug' => $entry->slug,
                    'name' => $entry->name,
                    'href' => $categoryHref($entry->slug),
                ];
            }
            if ($entry->slug === $category) {
                $categoryLabel = $parent === null ? $entry->name : $parent->name . ' / ' . $entry->name;
            }
        }

        $without = fn (string $key): string => $this->catalogHref(array_diff_key($filters, [$key => true]));
        $activeFilters = [];
        $filterLabels = [
            'q' => $query,
            'category' => $categoryLabel !== '' ? $categoryLabel : $category,
            'lang' => self::LANGUAGES[$language] ?? '',
        ];
        foreach (array_keys($filters) as $key) {
            $activeFilters[] = ['label' => $filterLabels[$key], 'href' => $without($key)];
        }

        $languageLinks = ['' => $without('lang')];
        foreach (array_keys(self::LANGUAGES) as $code) {
            $languageLinks[$code] = $this->catalogHref(array_replace($filters, ['lang' => $code]));
        }

        $items = [];
        foreach ($result['items'] as $row) {
            $entry = $categories[(int) $row['category_id']] ?? null;
            $parent = $categories[$entry?->parentId] ?? null;
            $links = [];
            foreach (array_filter([$parent, $entry]) as $itemCategory) {
                $links[] = [
                    'name' => $itemCategory->name,
                    'href' => $categoryHref($itemCategory->slug),
                ];
            }
            $items[] = [
                'title' => $row['title'],
                'href' => $this->articleHref($row['slug']),
                'categories' => $links,
                'language' => $row['language'],
                'date' => substr($row['published_at'] ?? '', 0, 10),
                'summary' => $row['summary'],
            ];
        }

        $canonical = $this->catalogHref([], $page);
        $title = $page > 1 ? 'Articles - Page ' . $page : 'Articles';
        $description = 'Browse published articles, tutorials and project updates.';
        $catalogTheme = $this->theme instanceof ArticleThemeInterface ? $this->theme : null;
        $previous = $page > 1 ? $this->catalogHref($filters, $page - 1) : null;
        $next = $page < $result['pages'] ? $this->catalogHref($filters, $page + 1) : null;
        if ($catalogTheme !== null) {
            $catalogTheme->start_article_page($title, $description, ['path' => $canonical, 'language' => 'en'], !$filtered);
            $first = ($page - 1) * ArticleRepository::PAGE_SIZE + 1;
            $catalogTheme->render_article_catalog([
                'query' => $query,
                'category' => $category,
                'language' => $language,
                'categories' => $tree,
                'all_categories' => $without('category'),
                'languages' => self::LANGUAGES,
                'language_links' => $languageLinks,
                'filtered' => $filtered,
                'active_filters' => $activeFilters,
                'results_title' => match (true) {
                    $query !== '' => 'Search results',
                    $categoryLabel !== '' => $categoryLabel,
                    default => 'All articles',
                },
                'items' => $items,
                'page' => $page,
                'pages' => $result['pages'],
                'total' => $result['total'],
                'first' => $first,
                'last' => $first + count($items) - 1,
                'previous' => $previous,
                'next' => $next,
            ]);
        } else {
            $this->theme->start_page($title, $description, !$filtered);
            $this->theme->start_header('Articles', $description);
            $this->theme->end_header();
            $this->theme->start_section();
            foreach ($items as $item) {
                $this->theme->render_button($item['title'], $item['href'], 'outline-light');
            }
            if ($previous !== null) {
                $this->theme->render_button('Previous', $previous, 'outline-light');
            }
            if ($next !== null) {
                $this->theme->render_button('Next', $next, 'outline-light');
            }
            $this->theme->end_section();
        }
        $this->theme->end_page();
    }

    private function catalogHref(array $filters, int $page = 1): string
    {
        if ($page > 1) {
            $filters['page'] = $page;
        }

        return '/articles' . ($filters === [] ? '' : '?' . http_build_query($filters, '', '&', PHP_QUERY_RFC3986));
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
            'article:seo-v1:' . $article->slug,
            fn (): string => $this->capture(fn () => $this->renderArticle($article)),
            ['articles', 'article:' . $article->slug, 'article-category:' . $article->categoryName, 'theme']
        );
    }

    private function renderArticle(Article $article): void
    {
        $path = $this->articleHref($article->slug);
        $published = (new DateTimeImmutable($article->publishedAt ?? $article->createdAt))->format(DATE_ATOM);
        $modified = (new DateTimeImmutable($article->updatedAt))->format(DATE_ATOM);
        $seoTitle = $article->seoTitle !== '' ? $article->seoTitle : $article->title;
        $description = $article->seoDescription !== '' ? $article->seoDescription : $article->summary;
        if ($this->theme instanceof ArticleThemeInterface) {
            $this->theme->start_article_page($seoTitle, $description, [
                'path' => $path,
                'language' => $article->language,
                'published' => $published,
                'modified' => $modified,
            ]);
        } else {
            $this->theme->start_page($seoTitle, $description);
        }

        $lead = $article->categoryName . ' | Published: ' . substr($published, 0, 10);
        $eyebrow = 'Article / ' . $article->categoryName;
        if ($this->theme instanceof \SyntaxDevTeam\Cms\Core\DocumentHeaderThemeInterface) {
            $this->theme->render_document_header([
                'title' => $article->title,
                'lead' => $lead,
                'eyebrow' => $eyebrow,
                'path' => $path,
                'download_url' => $path . '/pdf',
                'track_statistics' => true,
            ]);
        } else {
            $this->theme->start_header($article->title, $lead, $eyebrow);
            $this->theme->render_button('Pobierz jako PDF', $path . '/pdf');
            $this->theme->end_header();
        }
        $this->theme->start_section();
        $this->theme->start_card('', 'Article');
        if ($this->theme instanceof \SyntaxDevTeam\Cms\Core\CodeExamplesThemeInterface) {
            $this->theme->render_code_examples($article->content, $article->contentFormat);
        } else {
            $this->theme->render_rich_content($article->content, $article->contentFormat);
        }
        $this->hooks?->doAction('articles.public.attachments', $article->id);
        $this->theme->render_button('Back to articles', '/articles', 'outline-light');
        $this->theme->end_card();

        $canonical = rtrim($this->publicUrl, '/') . $path;
        $this->theme->render_structured_data([
            '@type' => 'Article',
            'headline' => $article->title,
            'description' => $description,
            'articleSection' => $article->categoryName,
            'datePublished' => $published,
            'dateModified' => $modified,
            'url' => $canonical,
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $canonical],
            ...($article->language !== 'und' ? ['inLanguage' => $article->language] : []),
        ]);
        $this->theme->end_section();
        $this->theme->end_page();
    }

    private function renderCodeRunner(Request $request): void
    {
        if (!$this->theme instanceof \SyntaxDevTeam\Cms\Core\CodeExamplesThemeInterface) {
            http_response_code(404);
            return;
        }
        $this->security->closeSession();
        $origin = $this->publicUrl !== '' ? rtrim($this->publicUrl, '/')
            : ($request->isSecure() ? 'https://' : 'http://') . $request->host();
        $runtimeBase = $origin . '/templates/shared/assets/vendor/pyodide/0.27.7/';
        $nonce = bin2hex(random_bytes(16));
        $this->security->sendCodeRunnerHeaders($runtimeBase, $nonce);
        $this->theme->render_code_runner($runtimeBase, $nonce);
    }

    private function downloadPdf(Request $request): void
    {
        header('Cache-Control: private, no-store');
        $article=$this->articles->findPublishedBySlug($request->routeString('slug'));
        if(!$article){http_response_code(404);return;}
        $limit=(new \SyntaxDevTeam\Cms\Core\RateLimiter(dirname(__DIR__,2).'/cache/rate-limits'))->hit('article-pdf',$request->clientIp(),30,60);
        if($limit['limited']){http_response_code(429);header('Retry-After: '.$limit['retry_after']);return;}
        $user = $this->auth->actualUser();
        if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
        try{$bytes=(new ArticlePdf(dirname(__DIR__,2).'/cache/article-pdf',$this->publicUrl))->render($article);}
        catch(\RuntimeException){http_response_code(503);header('Retry-After: 5');header('Content-Type: text/plain; charset=utf-8');echo 'Nie udało się przygotować PDF. Spróbuj ponownie za chwilę.';return;}
        if ($request->method() === 'GET') {
            $this->track('downloads', $article->id, $user);
        }
        header('Content-Type: application/pdf');header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: attachment; filename="'.$article->slug.'.pdf"');
        header('Content-Length: '.strlen($bytes));echo $bytes;
    }

    private function articleHref(string $slug): string
    {
        return '/article/' . rawurlencode($slug);
    }

    private function renderList(string $message = '', string $variant = 'info', ?Request $request = null): void
    {
        $user = $this->auth->user();

        if ($user === null) {
            return;
        }

        $filters = $this->adminListFilters($request);
        ['articles' => $articles, 'page' => $page, 'pages' => $pages, 'total' => $total] = $this->articles->adminPage(
            $filters['status'] ?? '',
            $filters['category'] ?? 0,
            $filters['page'] ?? 1,
        );
        $categories = $this->categoryLabels($this->articles->categories());
        $ids = array_map(static fn (Article $article): int => $article->id, $articles);
        try {
            $statistics = $ids === [] ? [] : $this->articles->statistics($ids);
        } catch (\PDOException $exception) {
            error_log('miniPORTAL article statistics unavailable: ' . $exception->getCode());
            $statistics = null;
        }
        $count = static fn (Article $article, string $column): string => $statistics !== null && ($article->status === 'published' || isset($statistics[$article->id]))
            ? ($statistics[$article->id][$column] ?? '0')
            : '—';
        $allows = static fn (string $permission): bool => in_array('*', $user->permissions, true)
            || in_array($permission, $user->permissions, true);

        $actions = ['' => 'Wybierz operację'];
        foreach (self::BULK_ACTIONS as $operation => [$label, $permission]) {
            if ($allows($permission)) {
                $actions[$operation] = $label;
            }
        }

        $this->startAdminPage(
            $user,
            'Artykuły',
            'Zarządzaj publikacjami, szkicami i archiwum.',
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

        $this->theme->start_admin_panel('Lista artykułów', "{$total} wyników · strona {$page}/{$pages}");
        $this->theme->render_admin_filter_modal('articles', 'Filtruj artykuły', 'index.php', [
            ['type' => 'hidden', 'name' => 'route', 'value' => '/admin/articles'],
            [
                'name' => 'status', 'label' => 'Status', 'type' => 'select',
                'value' => $filters['status'] ?? '', 'options' => ['' => 'Wszystkie statusy'] + self::STATUSES,
            ],
            [
                'name' => 'category', 'label' => 'Kategoria', 'type' => 'select',
                'value' => (string) ($filters['category'] ?? ''), 'options' => ['' => 'Wszystkie kategorie'] + $categories,
            ],
        ], $this->adminListHref());
        if ($articles === []) {
            $this->theme->render_alert('Brak artykułów spełniających wybrane kryteria.', 'info');
        } else {
            $this->theme->render_admin_action_table(
                ['Tytuł', 'Kategoria', 'Slug', 'Status', 'Aktualizacja', 'Odsłony', 'Udostępnienia', 'Pobrania'],
                array_map(
                    static fn (Article $article): array => [
                        'selection_value' => $article->id,
                        'cells' => [
                            $article->title,
                            $categories[$article->categoryId] ?? $article->categoryName,
                            $article->slug,
                            self::STATUSES[$article->status] ?? 'Nieustawiony',
                            $article->updatedAt,
                            $count($article, 'views'),
                            $count($article, 'shares'),
                            $count($article, 'downloads'),
                        ],
                        'actions' => array_values(array_filter([
                            $allows('articles.edit') ? [
                                'label' => 'Edytuj',
                                'href' => 'index.php?route=/admin/articles/edit&id=' . $article->id,
                                'variant' => 'outline-light',
                            ] : null,
                            $allows('articles.publish') ? [
                                'label' => match ($article->status) {
                                    'published' => 'Ukryj',
                                    'archived' => 'Przywróć szkic',
                                    default => 'Publikuj',
                                },
                                'action' => 'index.php?route=/admin/articles/publish',
                                'fields' => [
                                    'id' => $article->id,
                                    'action' => match ($article->status) {
                                        'published' => 'hide',
                                        'archived' => 'restore',
                                        default => 'publish',
                                    },
                                ],
                                'variant' => 'outline-primary',
                            ] : null,
                            $allows('articles.delete') ? [
                                'label' => 'Usuń',
                                'action' => 'index.php?route=/admin/articles/delete',
                                'fields' => ['id' => $article->id],
                                'variant' => 'outline-danger',
                                'confirm' => 'Czy trwale usunąć ten artykuł?',
                            ] : null,
                        ])),
                    ],
                    $articles
                ),
                $this->security->csrfToken(),
                count($actions) > 1 ? [
                    'action' => $this->adminListHref($filters, '/admin/articles/bulk'),
                    'label' => 'Sprawdź wybór',
                    'choice' => ['name' => 'operation', 'label' => 'Operacja zbiorcza', 'options' => $actions],
                ] : null
            );
            if ($page > 1) {
                $this->theme->render_button('Poprzednia strona', $this->adminListHref(array_replace($filters, ['page' => $page - 1])), 'outline-light');
            }
            if ($page < $pages) {
                $this->theme->render_button('Następna strona', $this->adminListHref(array_replace($filters, ['page' => $page + 1])), 'outline-light');
            }
        }
        $this->theme->end_admin_panel();
        $this->endAdminPage();
    }

    private function adminListFilters(?Request $request): array
    {
        $status = $request?->queryString('status') ?? '';
        return array_filter([
            'status' => isset(self::STATUSES[$status]) ? $status : '',
            'category' => max(0, $request?->queryInt('category') ?? 0),
            'page' => max(0, $request?->queryInt('page') ?? 0),
        ], static fn (int|string $value): bool => $value !== '' && $value !== 0);
    }

    private function adminListHref(array $filters = [], string $path = '/admin/articles'): string
    {
        return 'index.php?route=' . $path . ($filters === [] ? '' : '&' . http_build_query($filters));
    }

    private function bulk(Request $request): void
    {
        $operation = $request->postString('operation');
        $action = self::BULK_ACTIONS[$operation] ?? null;
        if ($action === null) {
            http_response_code(422);
            $this->renderList('Wybierz operację zbiorczą.', 'warning', $request);
            return;
        }

        $this->guard($request, $action[1], fn () => $this->handleBulk($request, $operation, $action[0]));
    }

    private function handleBulk(Request $request, string $operation, string $label): void
    {
        $event = 'article_bulk_' . $operation;
        if (!$this->validCsrf($request, $event)) {
            return;
        }

        $ids = $this->bulkIds($request);
        if ($ids === []) {
            http_response_code(422);
            $this->renderList('Zaznacz poprawne artykuły z jednej strony listy.', 'warning', $request);
            return;
        }
        $selection = $this->articles->bulkSelection($ids);
        if (count($selection) !== count($ids)) {
            http_response_code(409);
            $this->renderList('Część zaznaczonych artykułów została usunięta. Zaznacz artykuły ponownie.', 'warning', $request);
            return;
        }
        if (!$request->postBool('confirmed')) {
            $this->renderBulkConfirmation($request, $operation, $label, $selection);
            return;
        }

        $target = match ($operation) {
            'category' => $request->postInt('target'),
            'language' => $request->postString('target'),
            default => null,
        };
        $error = match ($operation) {
            'category' => $this->articles->categoryExists($target ?? 0) ? '' : 'Wybierz istniejącą kategorię.',
            'language' => isset(self::LANGUAGES[$target]) ? '' : 'Wybierz dostępny język.',
            default => '',
        };
        if ($error !== '') {
            http_response_code(422);
            $this->renderBulkConfirmation($request, $operation, $label, $selection, $error);
            return;
        }

        try {
            $changed = $this->articles->applyBulk($operation, $ids, $target);
        } catch (DomainException $exception) {
            $this->renderPublicationSchemaError($request, $event, $exception);
            return;
        } catch (PDOException $exception) {
            if ((string) $exception->getCode() !== '23000') {
                throw $exception;
            }
            $this->audit->record($request, $event, 'conflict', null, $this->auth->user()?->id);
            http_response_code(409);
            $this->renderList('Dane powiązane z artykułami uległy zmianie. Sprawdź zaznaczenie i spróbuj ponownie.', 'warning', $request);
            return;
        }

        if ($changed > 0) {
            $this->invalidateArticleCache();
        }
        $skipped = count($ids) - $changed;
        $this->audit->record(
            $request,
            $event,
            $changed === 0 ? 'no_changes' : ($skipped === 0 ? 'success' : 'partial'),
            'selected=' . count($ids) . ';changed=' . $changed,
            $this->auth->user()?->id
        );
        $message = ($operation === 'delete' ? 'Liczba usuniętych artykułów' : 'Liczba zmienionych artykułów') . ': ' . $changed . '.';
        if ($skipped > 0) {
            $message .= ' Bez zmian: ' . $skipped . '.';
        }
        $this->renderList($message, $changed > 0 ? 'success' : 'info', $request);
    }

    /** @return list<int> */
    private function bulkIds(Request $request): array
    {
        $selection = $request->postArray('ids');
        if (count($selection) > ArticleRepository::ADMIN_PAGE_SIZE) {
            return [];
        }
        $ids = [];
        foreach ($selection as $value) {
            $id = is_string($value) || is_int($value)
                ? filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
                : false;
            if ($id === false) {
                return [];
            }
            $ids[$id] = $id;
        }

        return array_values($ids);
    }

    private function renderBulkConfirmation(Request $request, string $operation, string $label, array $selection, string $error = ''): void
    {
        $user = $this->auth->user();
        if ($user === null) {
            return;
        }

        $filters = $this->adminListFilters($request);
        $this->startAdminPage($user, $label, 'Sprawdź artykuły wybrane do operacji zbiorczej.');
        $this->theme->start_admin_panel('Potwierdzenie operacji', 'Wybrane artykuły: ' . count($selection));
        if ($error !== '') {
            $this->theme->render_alert($error, 'danger');
        }
        if ($operation === 'delete') {
            $this->theme->render_alert('Artykuły zostaną trwale usunięte wraz z ich statystykami.', 'danger');
        }
        $this->theme->render_admin_table(
            ['Tytuł', 'Status'],
            array_map(static fn (array $row): array => [$row['title'], self::STATUSES[$row['status']] ?? 'Nieustawiony'], $selection)
        );
        $fields = [
            ['type' => 'hidden', 'name' => 'operation', 'value' => $operation],
            ['type' => 'hidden', 'name' => 'confirmed', 'value' => '1'],
            ...array_map(static fn (array $row): array => ['type' => 'hidden', 'name' => 'ids[]', 'value' => (string) $row['id']], $selection),
        ];
        if (in_array($operation, ['category', 'language'], true)) {
            $options = $operation === 'category'
                ? $this->categoryLabels($this->articles->categories())
                : array_replace(self::LANGUAGES, ['und' => 'Nieustawiony']);
            $fields[] = [
                'name' => 'target', 'label' => $operation === 'category' ? 'Kategoria docelowa' : 'Język',
                'type' => 'select', 'value' => '', 'required' => true, 'options' => ['' => 'Wybierz'] + $options,
            ];
        }
        $this->theme->render_form(
            $this->adminListHref($filters, '/admin/articles/bulk'),
            $fields,
            $operation === 'delete' ? 'Usuń trwale zaznaczone artykuły' : 'Zatwierdź operację',
            $this->security->csrfToken()
        );
        $this->theme->render_button('Anuluj', $this->adminListHref($filters), 'outline-light');
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
        $labels = $this->categoryLabels($categories);
        $parents = ['' => 'Brak (kategoria główna)'];
        foreach ($categories as $category) {
            if ($category->parentId === null) {
                $parents[$category->id] = $category->name;
            }
        }
        $this->startAdminPage(
            $user,
            'Kategorie artykułów',
            'Wybierz kategorię nadrzędną, aby dodać podkategorię.'
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
                ['name' => 'parent_id', 'label' => 'Kategoria nadrzędna', 'type' => 'select', 'value' => '', 'options' => $parents],
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
                    'cells' => [$labels[$category->id], $category->slug],
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

    /** @param list<Category> $categories */
    private function categoryLabels(array $categories): array
    {
        $names = [];
        foreach ($categories as $category) {
            $names[$category->id] = $category->name;
        }

        $labels = [];
        foreach ($categories as $category) {
            $parent = $names[$category->parentId] ?? null;
            $labels[$category->id] = $parent === null ? $category->name : $parent . ' / ' . $category->name;
        }
        asort($labels, SORT_NATURAL | SORT_FLAG_CASE);

        return $labels;
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

    private function renderForm(
        ?Article $article = null,
        string $message = '',
        string $variant = 'info',
        ?Request $submitted = null,
    ): void
    {
        $user = $this->auth->user();

        if ($user === null) {
            return;
        }

        $editing = $article !== null;
        $title = $editing ? 'Edytuj artykuł' : 'Dodaj artykuł';
        $this->startAdminPage($user, $title, 'Treść, kategoria, język i metadane publikacji.');

        if ($message !== '') {
            $this->theme->render_alert($message, $variant);
        }

        $categories = $this->categoryLabels($this->articles->categories());
        $value = static fn (string $name, string $default): string => $submitted?->postString($name, $default) ?? $default;

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
                    'value' => $value('category_id', (string) ($article?->categoryId ?? array_key_first($categories) ?? '')),
                    'options' => $categories,
                ],
                [
                    'name' => 'language',
                    'label' => 'Język artykułu',
                    'type' => 'select',
                    'value' => $value('language', $article?->language ?? 'und'),
                    'options' => array_replace(self::LANGUAGES, ['und' => 'Nieustawiony']),
                ],
                ['name' => 'title', 'label' => 'Tytuł', 'value' => $value('title', $article?->title ?? '')],
                ['name' => 'slug', 'label' => 'Slug (opcjonalnie)', 'value' => $value('slug', $article?->slug ?? '')],
                [
                    'name' => 'summary',
                    'label' => 'Zajawka',
                    'type' => 'textarea',
                    'rows' => 3,
                    'value' => $value('summary', $article?->summary ?? ''),
                ],
                [
                    'name' => 'seo_title',
                    'label' => 'Tytuł SEO',
                    'value' => $value('seo_title', $article?->seoTitle ?? ''),
                    'maxlength' => 180,
                    'help' => 'Jeżeli pole jest puste, użyjemy tytułu artykułu.',
                ],
                [
                    'name' => 'seo_description',
                    'label' => 'Opis SEO',
                    'type' => 'textarea',
                    'rows' => 2,
                    'value' => $value('seo_description', $article?->seoDescription ?? ''),
                    'maxlength' => 320,
                    'help' => 'Jeżeli pole jest puste, użyjemy zajawki.',
                ],
                [
                    'name' => 'content_media_path',
                    'label' => 'Grafika z biblioteki',
                    'type' => 'select',
                    'value' => '',
                    'options' => $this->mediaOptions(),
                    'help' => 'Opcjonalnie dopisze wybraną grafikę do treści przy zapisie.',
                ],
                [
                    'name' => 'content',
                    'label' => 'Treść',
                    'type' => 'richtext',
                    'value' => $value('content', $article?->content ?? ''),
                    'format_name' => 'content_format',
                    'format_value' => $value('content_format', $article?->contentFormat ?? 'html'),
                    'code_examples' => true,
                    'help' => 'Przykład Python dodaje edytowalny blok z przyciskiem Uruchom. W Markdown użyj bloku python-run. Kod działa lokalnie w przeglądarce; zmiany czytelnika nie zmieniają artykułu.',
                ],
            ],
            $editing ? 'Zapisz zmiany' : 'Utwórz szkic',
            $this->security->csrfToken()
        );
        $this->theme->end_admin_panel();
        if($editing)$this->hooks?->doAction('articles.admin.attachments',$article->id);
        $this->endAdminPage();
    }

    private function create(Request $request): void
    {
        if (!$this->validCsrf($request, 'article_create')) {
            return;
        }

        [$categoryId, $title, $slug, $summary, $content, $contentFormat, $metadata, $error] = $this->validatedInput($request);
        if ($error !== '') {
            $this->renderForm(null, $error, 'danger', $request);
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
            $user?->id ?? 0,
            ...$metadata
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

        [$categoryId, $title, $slug, $summary, $content, $contentFormat, $metadata, $error] = $this->validatedInput($request, $article);
        if ($error !== '') {
            $this->renderForm($article, $error, 'danger', $request);
            return;
        }

        $this->articles->update($id, $categoryId, $title, $slug, $summary, $content, $contentFormat, ...$metadata);
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
        $operation = $request->postString('action');
        try {
            $changed = match ($operation) {
                'publish', 'hide', 'restore' => $id > 0 && $this->articles->applyBulk($operation, [$id]) === 1,
                'draft' => $this->articles->unpublish($id),
                default => false,
            };
        } catch (DomainException $exception) {
            $this->renderPublicationSchemaError($request, 'article_publish', $exception);
            return;
        }
        $article = $this->articles->find($id);
        $this->invalidateArticleCache($article?->slug);
        $this->audit->record(
            $request,
            'article_publish',
            $changed ? $operation : 'not_changed',
            null,
            $this->auth->user()?->id
        );
        $this->renderList(
            $changed ? 'Status publikacji został zaktualizowany.' : 'Status artykułu pozostaje bez zmian.',
            $changed ? 'success' : 'warning'
        );
    }

    private function renderPublicationSchemaError(Request $request, string $event, DomainException $exception): void
    {
        $this->audit->record($request, $event, 'schema_update_required', null, $this->auth->user()?->id);
        http_response_code(409);
        $this->renderList($exception->getMessage(), 'warning', $request);
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
        $parentId = $request->postInt('parent_id');
        if ($request->postString('parent_id') !== '' && !$this->articles->rootCategoryExists($parentId ?? 0)) {
            $this->renderCategories('Wybierz istniejącą kategorię główną.', 'danger');
            return;
        }

        if ($name === '' || strlen($name) > 120 || $slug === '' || strlen($slug) > 191) {
            $this->renderCategories('Nazwa lub slug kategorii są nieprawidłowe.', 'danger');
            return;
        }
        if ($this->articles->categorySlugExists($slug) || $this->articles->categoryNameExists($name)) {
            $this->renderCategories('Kategoria z taką nazwą albo slugiem już istnieje.', 'warning');
            return;
        }

        try {
            $this->articles->createCategory($name, $slug, $parentId);
        } catch (PDOException $error) {
            if ((string) $error->getCode() !== '23000') {
                throw $error;
            }
            $this->renderCategories('Nazwa lub slug są już używane albo kategoria nadrzędna została usunięta.', 'warning');
            return;
        }
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

        try {
            $deleted = $this->articles->deleteCategory($request->postInt('id') ?? 0);
        } catch (PDOException $error) {
            if ((string) $error->getCode() !== '23000') {
                throw $error;
            }
            $deleted = false;
        }
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
                : 'Nie można usunąć kategorii, która nie istnieje, zawiera artykuły albo ma podkategorie.',
            $deleted ? 'success' : 'warning'
        );
    }

    /**
     * @return array{int, string, string, string, string, string, array{language: string, seoTitle: string, seoDescription: string}, string}
     */
    private function validatedInput(Request $request, ?Article $article = null): array
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
        $media = $this->selectedMediaAsset($request);
        if ($media instanceof MediaAsset) {
            $content = $this->appendMediaToContent($content, $contentFormat, $media);
        }

        $language = strtolower($request->postString('language', $article?->language ?? 'und'));
        $seoTitle = $this->metadataText($request->postString('seo_title', $article?->seoTitle ?? ''));
        $seoDescription = $this->metadataText($request->postString('seo_description', $article?->seoDescription ?? ''));
        $metadata = compact('language', 'seoTitle', 'seoDescription');
        $error = match (true) {
            !$this->articles->categoryExists($categoryId) => 'Wybierz istniejącą kategorię.',
            $title === '' || mb_strlen($title, 'UTF-8') > 180 => 'Tytuł jest wymagany i może mieć maksymalnie 180 znaków.',
            $slug === '' || strlen($slug) > 191 || $this->articles->slugExists($slug, $article?->id) => 'Slug jest nieprawidłowy albo już używany.',
            $summary === '' || mb_strlen($summary, 'UTF-8') > 500 => 'Zajawka jest wymagana i może mieć maksymalnie 500 znaków.',
            $content === '' => 'Treść artykułu jest wymagana.',
            !isset(self::LANGUAGES[$language]) => 'Wybierz dostępny język artykułu.',
            mb_strlen($seoTitle, 'UTF-8') > 180 => 'Tytuł SEO może mieć maksymalnie 180 znaków.',
            mb_strlen($seoDescription, 'UTF-8') > 320 => 'Opis SEO może mieć maksymalnie 320 znaków.',
            default => '',
        };

        return [$categoryId, $title, $slug, $summary, $content, $contentFormat, $metadata, $error];
    }

    private function metadataText(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', mb_scrub($value, 'UTF-8')) ?? '');
    }

    /** @return array<string, string> */
    private function mediaOptions(): array
    {
        if (!$this->mediaAssets instanceof MediaAssetRepository) {
            return ['' => 'Biblioteka grafik niedostępna'];
        }

        return ['' => 'Nie dodawaj grafiki'] + $this->mediaAssets->options();
    }

    private function selectedMediaAsset(Request $request): ?MediaAsset
    {
        if (!$this->mediaAssets instanceof MediaAssetRepository) {
            return null;
        }

        return $this->mediaAssets->findByPublicPath($request->postString('content_media_path'));
    }

    private function appendMediaToContent(string $content, string $contentFormat, MediaAsset $asset): string
    {
        if (str_contains($content, $asset->publicPath)) {
            return $content;
        }
        $alt = $asset->altText !== '' ? $asset->altText : $asset->title;
        if ($contentFormat === ContentRenderer::MARKDOWN) {
            return rtrim($content) . "\n\n![" . str_replace([']', "\n", "\r"], ['', ' ', ' '], $alt) . '](' . $asset->publicPath . ")\n";
        }

        return rtrim($content) . "\n\n<p><img src=\"" . htmlspecialchars($asset->publicPath, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($alt, ENT_QUOTES, 'UTF-8') . "\"></p>\n";
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
        try {
            $renderer();
            return (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
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
