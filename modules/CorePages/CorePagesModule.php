<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\CorePages;

use SyntaxDevTeam\Cms\Core\AdminMenuRegistry;
use SyntaxDevTeam\Cms\Core\ModuleInterface;
use SyntaxDevTeam\Cms\Core\Request;
use SyntaxDevTeam\Cms\Core\ContentRenderer;
use SyntaxDevTeam\Cms\Core\Router;
use SyntaxDevTeam\Cms\Core\Security;
use SyntaxDevTeam\Cms\Core\ThemeInterface;
use SyntaxDevTeam\Cms\Core\TemplateCacheInterface;
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
        private readonly HomepageSectionItemRepository $homepageItems,
        private readonly AuthService $auth,
        private readonly AdminAccessGate $access,
        private readonly Security $security,
        private readonly AuditLogService $audit,
        private readonly TemplateCacheInterface $templateCache,
    ) {
    }

    public function id(): string
    {
        return 'core_pages';
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
        $router->get('/pages', fn () => $this->renderPublicPages());
        foreach ($this->pages->published() as $page) {
            $router->get('/p/' . $page->slug, fn () => $this->renderPage($page));
        }
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
        $router->get('/admin/homepage/preview', fn (Request $request) => $this->guard(
            $request,
            'pages.view',
            fn () => $this->renderHomepagePreview()
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
        $router->get('/admin/homepage/items', fn (Request $request) => $this->guard(
            $request,
            'pages.view',
            fn () => $this->renderHomepageItems($request->queryInt('section_id') ?? 0)
        ));
        $router->get('/admin/homepage/items/create', fn (Request $request) => $this->guard(
            $request,
            'pages.create',
            fn () => $this->renderHomepageItemForm($request->queryInt('section_id') ?? 0)
        ));
        $router->post('/admin/homepage/items/create', fn (Request $request) => $this->guard(
            $request,
            'pages.create',
            fn () => $this->createHomepageItem($request)
        ));
        $router->get('/admin/homepage/items/edit', fn (Request $request) => $this->guard(
            $request,
            'pages.edit',
            fn () => $this->renderHomepageItemEdit($request)
        ));
        $router->post('/admin/homepage/items/edit', fn (Request $request) => $this->guard(
            $request,
            'pages.edit',
            fn () => $this->updateHomepageItem($request)
        ));
        $router->post('/admin/homepage/items/move', fn (Request $request) => $this->guard(
            $request,
            'pages.edit',
            fn () => $this->moveHomepageItem($request)
        ));
        $router->post('/admin/homepage/items/visibility', fn (Request $request) => $this->guard(
            $request,
            'pages.publish',
            fn () => $this->toggleHomepageItem($request)
        ));
        $router->post('/admin/homepage/items/delete', fn (Request $request) => $this->guard(
            $request,
            'pages.delete',
            fn () => $this->deleteHomepageItem($request)
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
                            $allows('pages.view') && in_array($section->layout, ['columns', 'contact'], true) ? [
                                'label' => 'Elementy',
                                'href' => 'index.php?route=/admin/homepage/items&section_id=' . $section->id,
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
                'content_format' => $section?->contentFormat ?? 'html',
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
            ],
            $editing ? [
                'label' => 'Podgląd roboczy',
                'href' => 'index.php?route=/admin/homepage/preview',
            ] : null
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
                    'name' => '_autosave_key',
                    'label' => 'Autozapis',
                    'type' => 'hidden',
                    'value' => 'homepage-section-' . ($section?->id ?? 'new'),
                ],
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
                        'contact' => 'Kontakt / kanały i osoby',
                    ],
                ],
                [
                    'name' => 'content_html',
                    'label' => 'Treść',
                    'type' => 'richtext',
                    'value' => $value('content_html'),
                    'format_name' => 'content_format',
                    'format_value' => $value('content_format', 'html'),
                    'help' => 'Przełączaj między edytorem wizualnym i Markdown w stylu GitHub.',
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
        header(
            'Location: index.php?route=/admin/homepage/edit&id=' . $id
            . '&autosave_clear=homepage-section-new',
            true,
            303
        );
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
        header(
            'Location: index.php?route=/admin/homepage/edit&id=' . $id
            . '&autosave_clear=homepage-section-' . $id,
            true,
            303
        );
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
        $contentFormat = (new ContentRenderer())->normalizeFormat($request->postString('content_format'));
        $data = [
            'section_key' => $sectionKey,
            'section_type' => $sectionType,
            'eyebrow' => substr($request->postString('eyebrow'), 0, 160),
            'title' => $title,
            'content_html' => (new ContentRenderer())->prepareForStorage(
                $request->postString('content_html'),
                $contentFormat
            ),
            'content_format' => $contentFormat,
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
        if (!in_array($layout, ['full', 'split', 'columns', 'accent', 'contact'], true)) {
            return [$data, 'Wybrano nieprawidłowy układ sekcji.'];
        }
        if ($buttonUrl !== '' && preg_match('~^(?:https?://|mailto:|#|index\.php(?:\?|$))~i', $buttonUrl) !== 1) {
            return [$data, 'Adres przycisku używa niedozwolonego schematu.'];
        }

        return [$data, ''];
    }

    private function renderHomepagePreview(): void
    {
        $sections = array_map(
            fn (HomepageSection $section): array => $section->toThemeData(
                $this->homepageItems->forSection($section->id)
            ),
            $this->homepageSections->all()
        );
        $pages = array_map(
            static fn (Page $page): array => [
                'title' => $page->title,
                'slug' => $page->slug,
                'summary' => $page->summary,
                'type' => $page->pageType,
                'navigation_area' => $page->navigationArea,
                'navigation_label' => $page->navigationLabel,
            ],
            $this->pages->published()
        );

        header('Cache-Control: no-store, private');
        $this->theme->render_homepage($sections, $pages, true);
    }

    private function renderHomepageItems(
        int $sectionId,
        string $message = '',
        string $variant = 'info',
    ): void {
        $section = $this->homepageSections->find($sectionId);
        $user = $this->auth->user();

        if ($section === null || $user === null) {
            http_response_code(404);
            $this->renderHomepageList('Nie znaleziono sekcji dla elementów.', 'danger');
            return;
        }

        $items = $this->homepageItems->forSection($sectionId);
        $allows = static fn (string $permission): bool => in_array('*', $user->permissions, true)
            || in_array($permission, $user->permissions, true);

        $this->theme->start_admin_page(
            'Elementy sekcji',
            $this->menu->visibleFor($user->permissions),
            '/admin/homepage',
            $this->adminUser($user)
        );
        $this->theme->start_admin_content(
            'Elementy: ' . $section->title,
            'Elementy są renderowane jako karty, kanały komunikacji albo osoby, zależnie od układu sekcji.',
            [
                ['label' => 'Panel', 'href' => 'index.php?route=/admin'],
                ['label' => 'Strona główna', 'href' => 'index.php?route=/admin/homepage'],
                ['label' => 'Elementy', 'href' => ''],
            ],
            $allows('pages.create') ? [
                'label' => 'Dodaj element',
                'href' => 'index.php?route=/admin/homepage/items/create&section_id=' . $sectionId,
            ] : null
        );
        if ($message !== '') {
            $this->theme->render_alert($message, $variant);
        }
        $this->theme->start_admin_panel('Elementy sekcji', count($items) . ' elementów');
        if ($items === []) {
            $this->theme->render_alert('Brak elementów. Sekcja pokaże zwykłą treść WYSIWYG.', 'info');
        } else {
            $this->theme->render_admin_action_table(
                ['Kolejność', 'Element', 'Typ i wygląd', 'Status'],
                array_map(
                    static fn (HomepageSectionItem $item): array => [
                        'cells' => [
                            $item->sortOrder,
                            $item->title . ($item->label !== '' ? ' (' . $item->label . ')' : ''),
                            $item->itemKind . ' / ' . $item->iconKey . ' / ' . $item->variant,
                            $item->isVisible ? 'Widoczny' : 'Ukryty',
                        ],
                        'actions' => array_values(array_filter([
                            $allows('pages.edit') ? [
                                'label' => 'W górę',
                                'action' => 'index.php?route=/admin/homepage/items/move',
                                'fields' => ['id' => $item->id, 'direction' => 'up'],
                                'variant' => 'outline-light',
                            ] : null,
                            $allows('pages.edit') ? [
                                'label' => 'W dół',
                                'action' => 'index.php?route=/admin/homepage/items/move',
                                'fields' => ['id' => $item->id, 'direction' => 'down'],
                                'variant' => 'outline-light',
                            ] : null,
                            $allows('pages.edit') ? [
                                'label' => 'Edytuj',
                                'href' => 'index.php?route=/admin/homepage/items/edit&id=' . $item->id,
                                'variant' => 'outline-primary',
                            ] : null,
                            $allows('pages.publish') ? [
                                'label' => $item->isVisible ? 'Ukryj' : 'Pokaż',
                                'action' => 'index.php?route=/admin/homepage/items/visibility',
                                'fields' => ['id' => $item->id],
                                'variant' => 'outline-warning',
                            ] : null,
                            $allows('pages.delete') ? [
                                'label' => 'Usuń',
                                'action' => 'index.php?route=/admin/homepage/items/delete',
                                'fields' => ['id' => $item->id],
                                'variant' => 'outline-danger',
                            ] : null,
                        ])),
                    ],
                    $items
                ),
                $this->security->csrfToken()
            );
        }
        $this->theme->end_admin_panel();
        $this->theme->end_admin_content();
        $this->theme->end_admin_page();
    }

    private function renderHomepageItemEdit(Request $request): void
    {
        $item = $this->homepageItems->find($request->queryInt('id') ?? 0);
        if ($item === null) {
            $this->renderHomepageList('Nie znaleziono elementu.', 'danger');
            return;
        }

        $this->renderHomepageItemForm($item->sectionId, $item);
    }

    /**
     * @param array<string, string|bool> $values
     */
    private function renderHomepageItemForm(
        int $sectionId,
        ?HomepageSectionItem $item = null,
        string $message = '',
        string $variant = 'info',
        array $values = [],
    ): void {
        $section = $this->homepageSections->find($sectionId);
        $user = $this->auth->user();
        if ($section === null || $user === null) {
            $this->renderHomepageList('Nie znaleziono sekcji.', 'danger');
            return;
        }

        $editing = $item !== null;
        $value = static function (string $key, string $fallback = '') use ($values, $item): string {
            if (array_key_exists($key, $values)) {
                return (string) $values[$key];
            }
            return match ($key) {
                'label' => $item?->label ?? $fallback,
                'title' => $item?->title ?? $fallback,
                'content' => $item?->content ?? $fallback,
                'content_format' => $item?->contentFormat ?? 'html',
                'item_kind' => $item?->itemKind ?? $fallback,
                'icon_key' => $item?->iconKey ?? $fallback,
                'button_label' => $item?->buttonLabel ?? $fallback,
                'button_url' => $item?->buttonUrl ?? $fallback,
                'page_id' => $item?->pageId !== null ? (string) $item->pageId : $fallback,
                'variant' => $item?->variant ?? $fallback,
                'width' => $item?->width ?? $fallback,
                default => $fallback,
            };
        };
        $visible = array_key_exists('is_visible', $values)
            ? (bool) $values['is_visible']
            : ($item?->isVisible ?? true);
        $title = $editing ? 'Edytuj element' : 'Dodaj element';

        $this->theme->start_admin_page(
            $title,
            $this->menu->visibleFor($user->permissions),
            '/admin/homepage',
            $this->adminUser($user)
        );
        $this->theme->start_admin_content(
            $title,
            'Karta należy do sekcji „' . $section->title . '”.',
            [
                ['label' => 'Strona główna', 'href' => 'index.php?route=/admin/homepage'],
                [
                    'label' => 'Elementy',
                    'href' => 'index.php?route=/admin/homepage/items&section_id=' . $sectionId,
                ],
                ['label' => $editing ? 'Edycja' : 'Nowy', 'href' => ''],
            ]
        );
        if ($message !== '') {
            $this->theme->render_alert($message, $variant);
        }
        $this->theme->start_admin_panel('Treść i wygląd karty');
        $this->theme->render_form(
            $editing
                ? 'index.php?route=/admin/homepage/items/edit'
                : 'index.php?route=/admin/homepage/items/create',
            [
                ['name' => 'section_id', 'label' => 'Sekcja', 'type' => 'hidden', 'value' => (string) $sectionId],
                ...($editing ? [[
                    'name' => 'id',
                    'label' => 'ID',
                    'type' => 'hidden',
                    'value' => (string) $item->id,
                ]] : []),
                [
                    'name' => '_autosave_key',
                    'label' => 'Autozapis',
                    'type' => 'hidden',
                    'value' => 'homepage-item-' . ($item?->id ?? 'new') . '-' . $sectionId,
                ],
                ['name' => 'label', 'label' => 'Etykieta', 'value' => $value('label'), 'help' => 'Np. SERWERY albo PROJECT / 001.'],
                ['name' => 'title', 'label' => 'Tytuł', 'value' => $value('title')],
                [
                    'name' => 'content',
                    'label' => 'Opis',
                    'type' => 'richtext',
                    'value' => $value('content'),
                    'format_name' => 'content_format',
                    'format_value' => $value('content_format', 'html'),
                    'help' => 'Opis może używać Markdown. Obraz: ![opis](https://adres-obrazka), bez osadzania data:.',
                ],
                [
                    'name' => 'item_kind',
                    'label' => 'Typ elementu',
                    'type' => 'select',
                    'value' => $value('item_kind', 'card'),
                    'options' => [
                        'card' => 'Standardowa karta',
                        'channel' => 'Kanał komunikacji',
                        'person' => 'Osoba / członek zespołu',
                    ],
                ],
                [
                    'name' => 'icon_key',
                    'label' => 'Ikona',
                    'type' => 'select',
                    'value' => $value('icon_key'),
                    'options' => [
                        '' => 'Automatyczna',
                        'discord' => 'Discord',
                        'github' => 'GitHub',
                        'hangar' => 'Hangar',
                        'mail' => 'E-mail',
                        'person' => 'Osoba',
                        'web' => 'WWW',
                    ],
                    'help' => 'Motyw wybiera bezpieczną ikonę z kontrolowanego zestawu.',
                ],
                [
                    'name' => 'page_id',
                    'label' => 'Powiązana podstrona',
                    'type' => 'select',
                    'value' => $value('page_id'),
                    'options' => ['' => 'Brak powiązania'] + array_column(
                        array_map(
                            static fn (Page $page): array => [
                                'id' => (string) $page->id,
                                'label' => $page->title . ' (' . $page->slug . ')',
                            ],
                            $this->pages->all()
                        ),
                        'label',
                        'id'
                    ),
                    'help' => 'Powiązana opublikowana strona ma pierwszeństwo przed ręcznym adresem przycisku.',
                ],
                ['name' => 'button_label', 'label' => 'Etykieta przycisku', 'value' => $value('button_label')],
                ['name' => 'button_url', 'label' => 'Adres przycisku', 'value' => $value('button_url')],
                [
                    'name' => 'variant',
                    'label' => 'Wariant wizualny',
                    'type' => 'select',
                    'value' => $value('variant', 'primary'),
                    'options' => [
                        'primary' => 'Primary / turkusowy',
                        'violet' => 'Violet / fioletowy',
                        'neutral' => 'Neutral / stonowany',
                    ],
                ],
                [
                    'name' => 'width',
                    'label' => 'Szerokość',
                    'type' => 'select',
                    'value' => $value('width', 'standard'),
                    'options' => [
                        'standard' => 'Standardowa',
                        'wide' => 'Szeroka',
                    ],
                ],
                ['name' => 'is_visible', 'label' => 'Element widoczny', 'type' => 'checkbox', 'checked' => $visible],
            ],
            $editing ? 'Zapisz element' : 'Dodaj element',
            $this->security->csrfToken()
        );
        $this->theme->end_admin_panel();
        $this->theme->end_admin_content();
        $this->theme->end_admin_page();
    }

    private function createHomepageItem(Request $request): void
    {
        if (!$this->validCsrf($request, 'homepage_item_create')) {
            return;
        }
        $sectionId = $request->postInt('section_id') ?? 0;
        [$data, $error] = $this->validatedHomepageItemInput($request);
        if ($this->homepageSections->find($sectionId) === null) {
            $error = 'Nie znaleziono sekcji.';
        }
        if ($error !== '') {
            $this->renderHomepageItemForm($sectionId, null, $error, 'danger', $data);
            return;
        }
        $this->homepageItems->create($sectionId, $data);
        $this->audit->record($request, 'homepage_item_create', 'success', null, $this->auth->user()?->id);
        header(
            'Location: index.php?route=/admin/homepage/items&section_id=' . $sectionId
            . '&autosave_clear=homepage-item-new-' . $sectionId,
            true,
            303
        );
    }

    private function updateHomepageItem(Request $request): void
    {
        if (!$this->validCsrf($request, 'homepage_item_update')) {
            return;
        }
        $item = $this->homepageItems->find($request->postInt('id') ?? 0);
        if ($item === null) {
            $this->renderHomepageList('Nie znaleziono elementu.', 'danger');
            return;
        }
        [$data, $error] = $this->validatedHomepageItemInput($request);
        if ($error !== '') {
            $this->renderHomepageItemForm($item->sectionId, $item, $error, 'danger', $data);
            return;
        }
        $this->homepageItems->update($item->id, $data);
        $this->audit->record($request, 'homepage_item_update', 'success', null, $this->auth->user()?->id);
        header(
            'Location: index.php?route=/admin/homepage/items&section_id=' . $item->sectionId
            . '&autosave_clear=homepage-item-' . $item->id . '-' . $item->sectionId,
            true,
            303
        );
    }

    private function moveHomepageItem(Request $request): void
    {
        if (!$this->validCsrf($request, 'homepage_item_move')) {
            return;
        }
        $item = $this->homepageItems->find($request->postInt('id') ?? 0);
        $moved = $item !== null && $this->homepageItems->move($item->id, $request->postString('direction'));
        $this->audit->record($request, 'homepage_item_move', $moved ? 'success' : 'unchanged', null, $this->auth->user()?->id);
        $this->renderHomepageItems(
            $item?->sectionId ?? 0,
            $moved ? 'Kolejność elementów została zmieniona.' : 'Element jest już na skraju listy.',
            $moved ? 'success' : 'info'
        );
    }

    private function toggleHomepageItem(Request $request): void
    {
        if (!$this->validCsrf($request, 'homepage_item_visibility')) {
            return;
        }
        $item = $this->homepageItems->find($request->postInt('id') ?? 0);
        $changed = $item !== null && $this->homepageItems->toggleVisibility($item->id);
        $this->audit->record($request, 'homepage_item_visibility', $changed ? 'success' : 'not_found', null, $this->auth->user()?->id);
        $this->renderHomepageItems(
            $item?->sectionId ?? 0,
            $changed ? 'Widoczność elementu została zmieniona.' : 'Nie znaleziono elementu.',
            $changed ? 'success' : 'warning'
        );
    }

    private function deleteHomepageItem(Request $request): void
    {
        if (!$this->validCsrf($request, 'homepage_item_delete')) {
            return;
        }
        $item = $this->homepageItems->find($request->postInt('id') ?? 0);
        $deleted = $item !== null && $this->homepageItems->delete($item->id);
        $this->audit->record($request, 'homepage_item_delete', $deleted ? 'success' : 'not_found', null, $this->auth->user()?->id);
        $this->renderHomepageItems(
            $item?->sectionId ?? 0,
            $deleted ? 'Element został usunięty.' : 'Nie znaleziono elementu.',
            $deleted ? 'success' : 'warning'
        );
    }

    /**
     * @return array{0: array<string, string|int>, 1: string}
     */
    private function validatedHomepageItemInput(Request $request): array
    {
        $title = $request->postString('title');
        $variant = $request->postString('variant');
        $width = $request->postString('width');
        $itemKind = $request->postString('item_kind', 'card');
        $iconKey = $request->postString('icon_key');
        $buttonUrl = $request->postString('button_url');
        $pageId = $request->postInt('page_id');
        $contentFormat = (new ContentRenderer())->normalizeFormat($request->postString('content_format'));
        $rawContent = $request->postString('content');
        $data = [
            'page_id' => $pageId,
            'label' => substr($request->postString('label'), 0, 120),
            'title' => $title,
            'content' => (new ContentRenderer())->prepareForStorage($rawContent, $contentFormat),
            'content_format' => $contentFormat,
            'item_kind' => $itemKind,
            'icon_key' => $iconKey,
            'button_label' => substr($request->postString('button_label'), 0, 120),
            'button_url' => substr($buttonUrl, 0, 500),
            'variant' => $variant,
            'width' => $width,
            'is_visible' => $request->postBool('is_visible') ? 1 : 0,
        ];

        if ($title === '' || strlen($title) > 180) {
            return [$data, 'Tytuł jest wymagany i może mieć maksymalnie 180 znaków.'];
        }
        if (strlen($data['content']) > 4000) {
            return [$data, 'Opis może mieć maksymalnie 4000 znaków. Obrazy dodawaj przez adres HTTPS zamiast kodu base64.'];
        }
        if (
            $contentFormat === ContentRenderer::MARKDOWN
            && preg_match('~!\[[^\]]*]\(\s*data:~i', $rawContent) === 1
        ) {
            return [$data, 'Obrazy data: nie są obsługiwane. Użyj składni ![opis](https://adres-obrazka).'];
        }
        if (!in_array($variant, ['primary', 'violet', 'neutral'], true)) {
            return [$data, 'Wybrano nieprawidłowy wariant wizualny.'];
        }
        if (!in_array($width, ['standard', 'wide'], true)) {
            return [$data, 'Wybrano nieprawidłową szerokość elementu.'];
        }
        if (!in_array($itemKind, ['card', 'channel', 'person'], true)) {
            return [$data, 'Wybrano nieprawidłowy typ elementu.'];
        }
        if (!in_array($iconKey, ['', 'discord', 'github', 'hangar', 'mail', 'person', 'web'], true)) {
            return [$data, 'Wybrano nieprawidłową ikonę elementu.'];
        }
        if ($buttonUrl !== '' && preg_match('~^(?:https?://|mailto:|#|index\.php(?:\?|$))~i', $buttonUrl) !== 1) {
            return [$data, 'Adres przycisku używa niedozwolonego schematu.'];
        }
        if ($pageId !== null && $this->pages->find($pageId) === null) {
            return [$data, 'Wybrana podstrona nie istnieje.'];
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

        echo $this->cachedPublic(
            'page:' . $page->slug,
            fn (): string => $this->capture(fn () => $this->renderPage($page)),
            ['pages', 'page:' . $page->slug, 'theme']
        );
    }

    private function renderPage(Page $page): void
    {
        $this->theme->render_public_page(
            $page->title,
            $page->content,
            $page->publishedAt ?? $page->updatedAt,
            $page->metaDescription !== '' ? $page->metaDescription : $page->summary,
            $page->pageType,
            $page->contentFormat,
            $page->eyebrow
        );
    }

    private function renderPublicPages(): void
    {
        echo $this->cachedPublic(
            'pages:index',
            function (): string {
                $pages = $this->pages->published();
                return $this->capture(function () use ($pages): void {
        $this->theme->start_page(
            'Podstrony - SyntaxDevTeam',
            'Projekty, informacje i dokumenty serwisu SyntaxDevTeam.'
        );
        $this->theme->start_header(
            'Podstrony',
            'Opisy projektów, dodatkowe informacje oraz dokumenty prawne.',
            'SyntaxDevTeam / Podstrony'
        );
        $this->theme->end_header();
        $this->theme->start_section();

        if ($pages === []) {
            $this->theme->render_alert('Nie opublikowano jeszcze żadnych podstron.', 'info');
        } else {
            $this->theme->start_grid();
            foreach ($pages as $page) {
                $this->theme->start_column('md-6');
                $this->theme->start_card($page->title, $this->pageTypeLabel($page->pageType));
                $this->theme->render_text($page->summary !== '' ? $page->summary : 'Otwórz stronę, aby przeczytać pełną treść.');
                $this->theme->render_button(
                    'Otwórz',
                    '/p/' . rawurlencode($page->slug),
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
            ['pages', 'pages:index', 'theme']
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
                ['Tytuł', 'Typ', 'Nawigacja', 'Status', 'Aktualizacja'],
                array_map(
                    fn (Page $page): array => [
                        'cells' => [
                            $page->title,
                            $this->pageTypeLabel($page->pageType),
                            $page->navigationArea === 'none'
                                ? 'Brak'
                                : $page->navigationArea . ' / ' . ($page->navigationLabel ?: $page->title),
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
            'Treść jest formatowana w kontrolowanym edytorze WYSIWYG i sanitizowana po stronie serwera.',
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
                [
                    'name' => '_autosave_key',
                    'label' => 'Autozapis',
                    'type' => 'hidden',
                    'value' => 'core-page-' . ($page?->id ?? 'new'),
                ],
                ['name' => 'title', 'label' => 'Tytuł', 'value' => $page?->title ?? ''],
                ['name' => 'slug', 'label' => 'Slug (opcjonalnie)', 'value' => $page?->slug ?? ''],
                [
                    'name' => 'eyebrow',
                    'label' => 'Nadtytuł',
                    'value' => $page?->eyebrow ?? '',
                    'help' => 'Krótka etykieta nad tytułem, np. PROJEKT / CLEANERX. Puste pole użyje wartości domyślnej.',
                ],
                [
                    'name' => 'page_type',
                    'label' => 'Typ podstrony',
                    'type' => 'select',
                    'value' => $page?->pageType ?? 'standard',
                    'options' => [
                        'standard' => 'Informacyjna',
                        'project' => 'Opis projektu',
                        'legal' => 'Dokument prawny / RODO',
                    ],
                ],
                [
                    'name' => 'summary',
                    'label' => 'Krótki opis',
                    'type' => 'textarea',
                    'rows' => 3,
                    'value' => $page?->summary ?? '',
                    'help' => 'Widoczny na listach i kartach podstron.',
                ],
                [
                    'name' => 'meta_description',
                    'label' => 'Opis SEO',
                    'value' => $page?->metaDescription ?? '',
                    'help' => 'Maksymalnie 255 znaków; jeśli pusty, używany jest krótki opis.',
                ],
                [
                    'name' => 'navigation_area',
                    'label' => 'Miejsce w nawigacji',
                    'type' => 'select',
                    'value' => $page?->navigationArea ?? 'none',
                    'options' => [
                        'none' => 'Nie pokazuj automatycznie',
                        'main' => 'Główne menu',
                        'footer' => 'Stopka',
                    ],
                ],
                [
                    'name' => 'navigation_label',
                    'label' => 'Etykieta w nawigacji',
                    'value' => $page?->navigationLabel ?? '',
                    'help' => 'Jeśli pusta, zostanie użyty tytuł strony.',
                ],
                [
                    'name' => 'sort_order',
                    'label' => 'Kolejność',
                    'type' => 'number',
                    'value' => (string) ($page?->sortOrder ?? 100),
                ],
                [
                    'name' => 'content',
                    'label' => 'Treść',
                    'type' => 'richtext',
                    'value' => $page?->content ?? '',
                    'format_name' => 'content_format',
                    'format_value' => $page?->contentFormat ?? 'html',
                    'help' => 'Przełączaj między edytorem wizualnym i Markdown w stylu GitHub.',
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

        [$data, $error] = $this->validatedInput($request);

        if ($error !== '') {
            $this->renderForm(null, $error, 'danger');
            return;
        }

        $user = $this->auth->user();
        $id = $this->pages->create($data, $user?->id ?? 0);
        $this->invalidatePageCache($data['slug']);
        $this->audit->record($request, 'page_create', 'success', null, $user?->id);
        header(
            'Location: index.php?route=/admin/pages/edit&id=' . $id
            . '&autosave_clear=core-page-new',
            true,
            303
        );
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

        [$data, $error] = $this->validatedInput($request, $id);

        if ($error !== '') {
            $this->renderForm($page, $error, 'danger');
            return;
        }

        $this->pages->update($id, $data);
        $this->invalidatePageCache($page->slug, (string) $data['slug']);
        $userId = $this->auth->user()?->id;
        $this->audit->record($request, 'page_update', 'success', null, $userId);
        header(
            'Location: index.php?route=/admin/pages/edit&id=' . $id
            . '&autosave_clear=core-page-' . $id,
            true,
            303
        );
    }

    private function changePublication(Request $request): void
    {
        if (!$this->validCsrf($request, 'page_publish')) {
            return;
        }

        $id = $request->postInt('id') ?? 0;
        $publish = $request->postString('action') === 'publish';
        $changed = $publish ? $this->pages->publish($id) : $this->pages->unpublish($id);
        $page = $this->pages->find($id);
        $this->invalidatePageCache($page?->slug);
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

        $id = $request->postInt('id') ?? 0;
        $page = $this->pages->find($id);
        $deleted = $this->pages->delete($id);
        $this->invalidatePageCache($page?->slug);
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
     * @return array{0: array<string, string|int>, 1: string}
     */
    private function validatedInput(Request $request, ?int $exceptId = null): array
    {
        $title = $request->postString('title');
        $slug = $this->normalizeSlug($request->postString('slug') ?: $title);
        $contentFormat = (new ContentRenderer())->normalizeFormat($request->postString('content_format'));
        $content = (new ContentRenderer())->prepareForStorage(
            $request->postString('content'),
            $contentFormat
        );
        $pageType = $request->postString('page_type');
        $navigationArea = $request->postString('navigation_area');
        $sortOrder = $request->postInt('sort_order', 100) ?? 100;
        $data = [
            'title' => $title,
            'slug' => $slug,
            'eyebrow' => substr($request->postString('eyebrow'), 0, 160),
            'summary' => substr($request->postString('summary'), 0, 320),
            'meta_description' => substr($request->postString('meta_description'), 0, 255),
            'content' => $content,
            'content_format' => $contentFormat,
            'page_type' => $pageType,
            'navigation_area' => $navigationArea,
            'navigation_label' => substr($request->postString('navigation_label'), 0, 80),
            'sort_order' => max(0, min(65535, $sortOrder)),
        ];

        if ($title === '' || strlen($title) > 180) {
            return [$data, 'Tytuł jest wymagany i może mieć maksymalnie 180 znaków.'];
        }

        if ($slug === '' || strlen($slug) > 191) {
            return [$data, 'Slug jest nieprawidłowy lub zbyt długi.'];
        }

        if ($this->pages->slugExists($slug, $exceptId)) {
            return [$data, 'Slug jest już używany przez inną stronę.'];
        }
        if (!in_array($pageType, ['standard', 'project', 'legal'], true)) {
            return [$data, 'Wybrano nieprawidłowy typ podstrony.'];
        }
        if (!in_array($navigationArea, ['none', 'main', 'footer'], true)) {
            return [$data, 'Wybrano nieprawidłowe miejsce w nawigacji.'];
        }

        return [$data, ''];
    }

    private function pageTypeLabel(string $type): string
    {
        return match ($type) {
            'project' => 'Projekt',
            'legal' => 'Dokument prawny',
            default => 'Informacje',
        };
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

    private function invalidatePageCache(?string ...$slugs): void
    {
        $tags = ['homepage', 'pages', 'pages:index'];
        foreach ($slugs as $slug) {
            if ($slug !== null && $slug !== '') {
                $tags[] = 'page:' . $slug;
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
        if ($request->method() === 'POST') {
            $this->templateCache->invalidateTags(['homepage', 'pages']);
        }
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
