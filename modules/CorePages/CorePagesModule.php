<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\CorePages;

use SyntaxDevTeam\Cms\Core\AdminMenuRegistry;
use SyntaxDevTeam\Cms\Core\ModuleInterface;
use SyntaxDevTeam\Cms\Core\Request;
use SyntaxDevTeam\Cms\Core\RichTextSanitizer;
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
        private readonly HomepageSectionRepository $homepageSections,
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
        $menu->add('Treść', 'Strona główna', '/admin/homepage', 'HG', 'pages.view', 15);
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
        $router->get('/admin/homepage', fn (Request $request) => $this->guard(
            $request,
            'pages.view',
            fn () => $this->renderHomepageList()
        ));
        $router->get('/admin/homepage/create', fn (Request $request) => $this->guard(
            $request,
            'pages.create',
            fn () => $this->renderHomepageForm()
        ));
        $router->post('/admin/homepage/create', fn (Request $request) => $this->guard(
            $request,
            'pages.create',
            fn () => $this->createHomepageSection($request)
        ));
        $router->get('/admin/homepage/edit', fn (Request $request) => $this->guard(
            $request,
            'pages.edit',
            fn () => $this->renderHomepageEdit($request)
        ));
        $router->post('/admin/homepage/edit', fn (Request $request) => $this->guard(
            $request,
            'pages.edit',
            fn () => $this->updateHomepageSection($request)
        ));
        $router->post('/admin/homepage/move', fn (Request $request) => $this->guard(
            $request,
            'pages.edit',
            fn () => $this->moveHomepageSection($request)
        ));
        $router->post('/admin/homepage/visibility', fn (Request $request) => $this->guard(
            $request,
            'pages.publish',
            fn () => $this->toggleHomepageSection($request)
        ));
        $router->post('/admin/homepage/delete', fn (Request $request) => $this->guard(
            $request,
            'pages.delete',
            fn () => $this->deleteHomepageSection($request)
        ));
    }

    private function renderHomepageList(string $message = '', string $variant = 'info'): void
    {
        $user = $this->auth->user();

        if ($user === null) {
            return;
        }

        $sections = $this->homepageSections->all();
        $allows = static fn (string $permission): bool => in_array('*', $user->permissions, true)
            || in_array($permission, $user->permissions, true);

        $this->theme->start_admin_page(
            'Strona główna',
            $this->menu->visibleFor($user->permissions),
            '/admin/homepage',
            $this->adminUser($user)
        );
        $this->theme->start_admin_content(
            'Strona główna',
            'Zarządzaj sekcjami, formatowaniem, układem, widocznością i kolejnością.',
            [
                ['label' => 'Panel', 'href' => 'index.php?route=/admin'],
                ['label' => 'Strona główna', 'href' => ''],
            ],
            $allows('pages.create')
                ? ['label' => 'Dodaj sekcję', 'href' => 'index.php?route=/admin/homepage/create']
                : null
        );

        if ($message !== '') {
            $this->theme->render_alert($message, $variant);
        }

        $this->theme->start_admin_panel('Sekcje', count($sections) . ' elementów');
        if ($sections === []) {
            $this->theme->render_alert('Brak sekcji. Dodaj pierwszą sekcję strony głównej.', 'info');
        } else {
            $this->theme->render_admin_action_table(
                ['Kolejność', 'Sekcja', 'Typ i układ', 'Status'],
                array_map(
                    static fn (HomepageSection $section): array => [
                        'cells' => [
                            $section->sortOrder,
                            $section->title . ' (#' . $section->sectionKey . ')',
                            $section->sectionType . ' / ' . $section->layout,
                            $section->isVisible ? 'Widoczna' : 'Ukryta',
                        ],
                        'actions' => array_values(array_filter([
                            $allows('pages.edit') ? [
                                'label' => 'W górę',
                                'action' => 'index.php?route=/admin/homepage/move',
                                'fields' => ['id' => $section->id, 'direction' => 'up'],
                                'variant' => 'outline-light',
                            ] : null,
                            $allows('pages.edit') ? [
                                'label' => 'W dół',
                                'action' => 'index.php?route=/admin/homepage/move',
                                'fields' => ['id' => $section->id, 'direction' => 'down'],
                                'variant' => 'outline-light',
                            ] : null,
                            $allows('pages.edit') ? [
                                'label' => 'Edytuj',
                                'href' => 'index.php?route=/admin/homepage/edit&id=' . $section->id,
                                'variant' => 'outline-primary',
                            ] : null,
                            $allows('pages.publish') ? [
                                'label' => $section->isVisible ? 'Ukryj' : 'Pokaż',
                                'action' => 'index.php?route=/admin/homepage/visibility',
                                'fields' => ['id' => $section->id],
                                'variant' => 'outline-warning',
                            ] : null,
                            $allows('pages.delete') ? [
                                'label' => 'Usuń',
                                'action' => 'index.php?route=/admin/homepage/delete',
                                'fields' => ['id' => $section->id],
                                'variant' => 'outline-danger',
                            ] : null,
                        ])),
                    ],
                    $sections
                ),
                $this->security->csrfToken()
            );
        }
        $this->theme->end_admin_panel();
        $this->theme->end_admin_content();
        $this->theme->end_admin_page();
    }

    private function renderHomepageEdit(Request $request): void
    {
        $section = $this->homepageSections->find($request->queryInt('id') ?? 0);

        if ($section === null) {
            http_response_code(404);
            $this->renderHomepageList('Nie znaleziono sekcji.', 'danger');
            return;
        }

        $this->renderHomepageForm($section);
    }

    /**
     * @param array<string, string|bool> $values
     */
    private function renderHomepageForm(
        ?HomepageSection $section = null,
        string $message = '',
        string $variant = 'info',
        array $values = [],
    ): void {
        $user = $this->auth->user();

        if ($user === null) {
            return;
        }

        $editing = $section !== null;
        $value = static function (string $key, string $fallback = '') use ($values, $section): string {
            if (array_key_exists($key, $values)) {
                return (string) $values[$key];
            }

            return match ($key) {
                'section_key' => $section?->sectionKey ?? $fallback,
                'section_type' => $section?->sectionType ?? $fallback,
                'eyebrow' => $section?->eyebrow ?? $fallback,
                'title' => $section?->title ?? $fallback,
                'content_html' => $section?->contentHtml ?? $fallback,
                'layout' => $section?->layout ?? $fallback,
                'button_label' => $section?->buttonLabel ?? $fallback,
                'button_url' => $section?->buttonUrl ?? $fallback,
                default => $fallback,
            };
        };
        $visible = array_key_exists('is_visible', $values)
            ? (bool) $values['is_visible']
            : ($section?->isVisible ?? true);
        $title = $editing ? 'Edytuj sekcję' : 'Dodaj sekcję';

        $this->theme->start_admin_page(
            $title,
            $this->menu->visibleFor($user->permissions),
            '/admin/homepage',
            $this->adminUser($user)
        );
        $this->theme->start_admin_content(
            $title,
            'Treść jest formatowana w kontrolowanym edytorze i renderowana przez aktywny motyw.',
            [
                ['label' => 'Panel', 'href' => 'index.php?route=/admin'],
                ['label' => 'Strona główna', 'href' => 'index.php?route=/admin/homepage'],
                ['label' => $editing ? 'Edycja' : 'Nowa sekcja', 'href' => ''],
            ]
        );

        if ($message !== '') {
            $this->theme->render_alert($message, $variant);
        }

        $this->theme->start_admin_panel('Ustawienia sekcji', $editing ? 'ID ' . $section->id : 'Nowy element');
        $this->theme->render_form(
            $editing
                ? 'index.php?route=/admin/homepage/edit'
                : 'index.php?route=/admin/homepage/create',
            [
                ...($editing ? [[
                    'name' => 'id',
                    'label' => 'ID',
                    'type' => 'hidden',
                    'value' => (string) $section->id,
                ]] : []),
                [
                    'name' => 'title',
                    'label' => 'Nagłówek',
                    'value' => $value('title'),
                    'help' => 'Główny nagłówek sekcji.',
                ],
                [
                    'name' => 'section_key',
                    'label' => 'Kotwica URL',
                    'value' => $value('section_key'),
                    'help' => 'Np. projects utworzy adres #projects. Puste pole wygeneruje wartość z nagłówka.',
                ],
                [
                    'name' => 'eyebrow',
                    'label' => 'Nadtytuł',
                    'value' => $value('eyebrow'),
                ],
                [
                    'name' => 'section_type',
                    'label' => 'Typ sekcji',
                    'type' => 'select',
                    'value' => $value('section_type', 'content'),
                    'options' => [
                        'hero' => 'Hero / otwarcie strony',
                        'content' => 'Sekcja treści',
                        'cta' => 'Wezwanie do działania',
                    ],
                ],
                [
                    'name' => 'layout',
                    'label' => 'Układ',
                    'type' => 'select',
                    'value' => $value('layout', 'full'),
                    'options' => [
                        'full' => 'Pełna szerokość',
                        'split' => 'Nagłówek i treść obok siebie',
                        'columns' => 'Treść w kolumnach',
                        'accent' => 'Panel wyróżniony',
                    ],
                ],
                [
                    'name' => 'content_html',
                    'label' => 'Treść',
                    'type' => 'richtext',
                    'value' => $value('content_html'),
                    'help' => 'Dozwolone są akapity, nagłówki H2/H3, pogrubienie, kursywa, cytaty i listy.',
                ],
                ['name' => 'button_label', 'label' => 'Etykieta przycisku', 'value' => $value('button_label')],
                [
                    'name' => 'button_url',
                    'label' => 'Adres przycisku',
                    'value' => $value('button_url'),
                    'help' => 'Dozwolone: https://, http://, mailto:, kotwica # lub index.php.',
                ],
                [
                    'name' => 'is_visible',
                    'label' => 'Sekcja widoczna publicznie',
                    'type' => 'checkbox',
                    'checked' => $visible,
                ],
            ],
            $editing ? 'Zapisz sekcję' : 'Dodaj sekcję',
            $this->security->csrfToken()
        );
        $this->theme->end_admin_panel();
        $this->theme->end_admin_content();
        $this->theme->end_admin_page();
    }

    private function createHomepageSection(Request $request): void
    {
        if (!$this->validCsrf($request, 'homepage_section_create')) {
            return;
        }

        [$data, $error] = $this->validatedHomepageInput($request);
        if ($error !== '') {
            $this->renderHomepageForm(null, $error, 'danger', $data);
            return;
        }

        $data['author_id'] = $this->auth->user()?->id ?? 0;
        $id = $this->homepageSections->create($data);
        $this->audit->record($request, 'homepage_section_create', 'success', null, $this->auth->user()?->id);
        header('Location: index.php?route=/admin/homepage/edit&id=' . $id, true, 303);
    }

    private function updateHomepageSection(Request $request): void
    {
        if (!$this->validCsrf($request, 'homepage_section_update')) {
            return;
        }

        $id = $request->postInt('id') ?? 0;
        $section = $this->homepageSections->find($id);
        if ($section === null) {
            $this->renderHomepageList('Nie znaleziono sekcji.', 'danger');
            return;
        }

        [$data, $error] = $this->validatedHomepageInput($request, $id);
        if ($error !== '') {
            $this->renderHomepageForm($section, $error, 'danger', $data);
            return;
        }

        $this->homepageSections->update($id, $data);
        $this->audit->record($request, 'homepage_section_update', 'success', null, $this->auth->user()?->id);
        header('Location: index.php?route=/admin/homepage/edit&id=' . $id, true, 303);
    }

    private function moveHomepageSection(Request $request): void
    {
        if (!$this->validCsrf($request, 'homepage_section_move')) {
            return;
        }

        $moved = $this->homepageSections->move(
            $request->postInt('id') ?? 0,
            $request->postString('direction')
        );
        $this->audit->record($request, 'homepage_section_move', $moved ? 'success' : 'unchanged', null, $this->auth->user()?->id);
        $this->renderHomepageList($moved ? 'Kolejność została zmieniona.' : 'Sekcja jest już na skraju listy.', $moved ? 'success' : 'info');
    }

    private function toggleHomepageSection(Request $request): void
    {
        if (!$this->validCsrf($request, 'homepage_section_visibility')) {
            return;
        }

        $changed = $this->homepageSections->toggleVisibility($request->postInt('id') ?? 0);
        $this->audit->record($request, 'homepage_section_visibility', $changed ? 'success' : 'not_found', null, $this->auth->user()?->id);
        $this->renderHomepageList($changed ? 'Widoczność została zmieniona.' : 'Nie znaleziono sekcji.', $changed ? 'success' : 'warning');
    }

    private function deleteHomepageSection(Request $request): void
    {
        if (!$this->validCsrf($request, 'homepage_section_delete')) {
            return;
        }

        $deleted = $this->homepageSections->delete($request->postInt('id') ?? 0);
        $this->audit->record($request, 'homepage_section_delete', $deleted ? 'success' : 'not_found', null, $this->auth->user()?->id);
        $this->renderHomepageList($deleted ? 'Sekcja została usunięta.' : 'Nie znaleziono sekcji.', $deleted ? 'success' : 'warning');
    }

    /**
     * @return array{0: array<string, string|int>, 1: string}
     */
    private function validatedHomepageInput(Request $request, ?int $exceptId = null): array
    {
        $title = $request->postString('title');
        $sectionKey = $this->normalizeSlug($request->postString('section_key') ?: $title);
        $sectionType = $request->postString('section_type');
        $layout = $request->postString('layout');
        $buttonUrl = $request->postString('button_url');
        $data = [
            'section_key' => $sectionKey,
            'section_type' => $sectionType,
            'eyebrow' => substr($request->postString('eyebrow'), 0, 160),
            'title' => $title,
            'content_html' => (new RichTextSanitizer())->sanitize($request->postString('content_html')),
            'layout' => $layout,
            'button_label' => substr($request->postString('button_label'), 0, 120),
            'button_url' => substr($buttonUrl, 0, 500),
            'is_visible' => $request->postBool('is_visible') ? 1 : 0,
        ];

        if ($title === '' || strlen($title) > 220) {
            return [$data, 'Nagłówek jest wymagany i może mieć maksymalnie 220 znaków.'];
        }
        if ($sectionKey === '' || strlen($sectionKey) > 64) {
            return [$data, 'Kotwica URL jest nieprawidłowa lub zbyt długa.'];
        }
        if ($this->homepageSections->keyExists($sectionKey, $exceptId)) {
            return [$data, 'Kotwica URL jest już używana przez inną sekcję.'];
        }
        if (!in_array($sectionType, ['hero', 'content', 'cta'], true)) {
            return [$data, 'Wybrano nieprawidłowy typ sekcji.'];
        }
        if (!in_array($layout, ['full', 'split', 'columns', 'accent'], true)) {
            return [$data, 'Wybrano nieprawidłowy układ sekcji.'];
        }
        if ($buttonUrl !== '' && preg_match('~^(?:https?://|mailto:|#|index\.php(?:\?|$))~i', $buttonUrl) !== 1) {
            return [$data, 'Adres przycisku używa niedozwolonego schematu.'];
        }

        return [$data, ''];
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
        $homepageOperation = str_starts_with($event, 'homepage_');
        http_response_code(403);
        $this->theme->render_admin_access_state(
            403,
            'Nieprawidłowy token CSRF',
            $homepageOperation
                ? 'Operacja na sekcji strony głównej została odrzucona.'
                : 'Operacja na stronie została odrzucona.',
            $homepageOperation
                ? 'index.php?route=/admin/homepage'
                : 'index.php?route=/admin/pages',
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
