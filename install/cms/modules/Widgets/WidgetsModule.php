<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\Widgets;

use SyntaxDevTeam\Cms\Core\AdminMenuRegistry;
use SyntaxDevTeam\Cms\Core\AdminSearchProviderInterface;
use SyntaxDevTeam\Cms\Core\AdminSearchRegistry;
use SyntaxDevTeam\Cms\Core\ContentRenderer;
use SyntaxDevTeam\Cms\Core\DashboardProviderInterface;
use SyntaxDevTeam\Cms\Core\DashboardRegistry;
use SyntaxDevTeam\Cms\Core\HookProviderInterface;
use SyntaxDevTeam\Cms\Core\HookRegistry;
use SyntaxDevTeam\Cms\Core\ModuleInterface;
use SyntaxDevTeam\Cms\Core\Request;
use SyntaxDevTeam\Cms\Core\Router;
use SyntaxDevTeam\Cms\Core\Security;
use SyntaxDevTeam\Cms\Core\TemplateCacheInterface;
use SyntaxDevTeam\Cms\Core\ThemeInterface;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuditLogService;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuthService;
use SyntaxDevTeam\Cms\Modules\CoreAuth\User;

final class WidgetsModule implements ModuleInterface, HookProviderInterface, AdminSearchProviderInterface, DashboardProviderInterface
{
    private const TYPES = [
        'terminal' => 'Interaktywny terminal',
        'card' => 'Karta informacyjna',
    ];

    private const PLACEMENTS = [
        'homepage_start' => 'Początek strony głównej',
        'hero_aside' => 'Bok sekcji Hero',
        'after_hero' => 'Bezpośrednio po Hero',
        'before_section' => 'Przed wskazaną sekcją',
        'after_section' => 'Po wskazanej sekcji',
        'before_footer' => 'Przed stopką strony głównej',
    ];

    /** @param array<string, string> $availableThemes */
    public function __construct(
        private readonly ThemeInterface $theme,
        private readonly AdminMenuRegistry $menu,
        private readonly WidgetRepository $widgets,
        private readonly AuthService $auth,
        private readonly Security $security,
        private readonly AuditLogService $audit,
        private readonly TemplateCacheInterface $cache,
        private readonly array $availableThemes,
        private readonly WidgetLayout $layout = new WidgetLayout(),
    ) {
    }

    public function id(): string
    {
        return 'widgets';
    }

    public function version(): string
    {
        return '1.1.0';
    }

    public function dependencies(): array
    {
        return ['core_auth', 'core_pages'];
    }

    public function isProtected(): bool
    {
        return false;
    }

    public function requiredPermissions(): array
    {
        return ['widgets.manage'];
    }

    public function registerAdminMenu(AdminMenuRegistry $menu): void
    {
        $menu->add('Treść', 'Widgety', '/admin/widgets', 'WG', 'widgets.manage', 42);
    }

    public function registerAdminSearch(AdminSearchRegistry $search): void
    {
        $search->add(
            'widgets.create',
            'Dodaj widget',
            'Dodaj terminal albo kartę do wybranego slotu strony głównej.',
            'index.php?route=/admin/widgets/create',
            ['widget', 'terminal', 'hero', 'karta', 'motyw', 'sekcja'],
            'widgets.manage',
            'Treść',
            42,
        );
    }

    public function registerDashboard(DashboardRegistry $dashboard): void
    {
        $dashboard->addMetric(
            'widgets.visible',
            'Aktywne widgety',
            'Widgety udostępniane publicznym motywom.',
            'WG',
            function (): array {
                $stats = $this->widgets->stats();
                return ['value' => $stats['visible'], 'detail' => $stats['all'] . ' wszystkich widgetów'];
            },
            'widgets.manage',
            125,
        );
    }

    public function registerHooks(HookRegistry $hooks): void
    {
        $hooks->addFilter('homepage.sections', function (array $sections, array $context = []): array {
            $widgets = $this->widgets->visibleForTheme((string) ($context['theme'] ?? 'default'));

            return $this->layout->attach($sections, $widgets);
        }, 100);
    }

    public function registerRoutes(Router $router): void
    {
        $router->get('/admin/widgets', fn (Request $request) => $this->guard($request, fn () => $this->renderList()));
        $router->get('/admin/widgets/create', fn (Request $request) => $this->guard($request, fn () => $this->renderForm()));
        $router->post('/admin/widgets/create', fn (Request $request) => $this->guard($request, fn () => $this->save($request)));
        $router->get('/admin/widgets/edit', fn (Request $request) => $this->guard(
            $request,
            fn () => $this->renderEdit($request)
        ));
        $router->post('/admin/widgets/edit', fn (Request $request) => $this->guard(
            $request,
            fn () => $this->update($request)
        ));
        $router->post('/admin/widgets/delete', fn (Request $request) => $this->guard(
            $request,
            fn () => $this->delete($request)
        ));
    }

    private function renderList(string $message = '', string $variant = 'info'): void
    {
        $this->startAdminPage('Widgety', 'Małe elementy publiczne osadzane w nazwanych slotach aktywnego motywu.', [[
            'label' => 'Dodaj widget',
            'href' => 'index.php?route=/admin/widgets/create',
            'variant' => 'primary',
        ], [
            'label' => 'Strona główna',
            'href' => '/',
            'variant' => 'outline-light',
        ]]);
        if ($message !== '') {
            $this->theme->render_alert($message, $variant);
        }
        $this->theme->render_alert(
            'W slocie „Bok sekcji Hero” wyświetlany jest pierwszy pasujący widget. Wpis przypisany do konkretnego motywu ma pierwszeństwo przed wspólnym.',
            'info'
        );
        $items = $this->widgets->all();
        $this->theme->start_admin_panel('Zarejestrowane widgety', count($items) . ' pozycji');
        if ($items === []) {
            $this->theme->render_alert('Nie dodano jeszcze żadnego widgetu.', 'info');
        } else {
            $this->theme->render_admin_action_table(
                ['Kolejność', 'Nazwa', 'Typ', 'Położenie', 'Motyw', 'Stan'],
                array_map(fn (Widget $widget): array => [
                    'cells' => [
                        $widget->sortOrder,
                        $widget->name,
                        self::TYPES[$widget->type] ?? $widget->type,
                        self::PLACEMENTS[$widget->placement] ?? $widget->placement,
                        $widget->themeName === '*' ? 'Wszystkie' : ($this->availableThemes[$widget->themeName] ?? $widget->themeName),
                        $widget->visible ? 'Widoczny' : 'Ukryty',
                    ],
                    'actions' => [[
                        'label' => 'Edytuj',
                        'href' => 'index.php?route=/admin/widgets/edit&id=' . $widget->id,
                        'variant' => 'primary',
                    ], [
                        'label' => 'Usuń',
                        'action' => 'index.php?route=/admin/widgets/delete',
                        'variant' => 'danger',
                        'fields' => ['id' => $widget->id],
                        'confirm' => 'Usunąć widget „' . $widget->name . '”?',
                    ]],
                ], $items),
                $this->security->csrfToken()
            );
        }
        $this->theme->end_admin_panel();
        $this->endAdminPage();
    }

    private function renderForm(?Widget $widget = null, string $message = '', string $variant = 'info'): void
    {
        $editing = $widget !== null;
        $this->startAdminPage(
            $editing ? 'Edytuj widget' : 'Dodaj widget',
            'Moduł przechowuje dane. O wyglądzie terminala albo karty decyduje aktywny motyw.',
            [['label' => 'Wróć do listy', 'href' => 'index.php?route=/admin/widgets', 'variant' => 'outline-light']]
        );
        if ($message !== '') {
            $this->theme->render_alert($message, $variant);
        }
        $themeOptions = ['*' => 'Wszystkie motywy'] + $this->availableThemes;
        $this->theme->start_admin_panel('Konfiguracja widgetu', $editing ? $widget->key : 'Nowy element');
        $contentField = [
            'name' => 'content',
            'label' => 'Treść lub powitanie terminala',
            'type' => ($widget?->type ?? 'card') === 'terminal' ? 'textarea' : 'richtext',
            'value' => $widget?->content ?? '',
            'rows' => 9,
            'format_name' => 'content_format',
            'format_value' => $widget?->contentFormat ?? 'html',
            'help' => 'Dla terminala wpisz kolejne linie startowe. Dla karty możesz użyć edytora wizualnego albo Markdown.',
        ];
        $this->theme->render_form(
            'index.php?route=' . ($editing ? '/admin/widgets/edit' : '/admin/widgets/create'),
            array_merge($editing ? [[
                'name' => 'id', 'label' => 'ID', 'type' => 'hidden', 'value' => (string) $widget->id,
            ]] : [], [[
                'name' => 'name', 'label' => 'Nazwa administracyjna', 'value' => $widget?->name ?? '',
            ], [
                'name' => 'widget_key', 'label' => 'Klucz widgetu', 'value' => $widget?->key ?? '',
                'help' => 'Małe litery, cyfry, myślnik lub podkreślenie. Puste pole wygeneruje klucz z nazwy.',
            ], [
                'name' => 'widget_type', 'label' => 'Typ', 'type' => 'select',
                'value' => $widget?->type ?? 'card', 'options' => self::TYPES,
            ], [
                'name' => 'placement', 'label' => 'Położenie', 'type' => 'select',
                'value' => $widget?->placement ?? 'before_footer', 'options' => self::PLACEMENTS,
            ], [
                'name' => 'target_section_key', 'label' => 'Klucz wskazanej sekcji',
                'value' => $widget?->targetSectionKey ?? '',
                'help' => 'Wymagany tylko dla położenia przed/po sekcji, np. projects albo contact.',
            ], [
                'name' => 'theme_name', 'label' => 'Motyw', 'type' => 'select',
                'value' => $widget?->themeName ?? '*', 'options' => $themeOptions,
            ], [
                'name' => 'title', 'label' => 'Tytuł lub pasek terminala', 'value' => $widget?->title ?? '',
            ], $contentField, [
                'name' => 'button_label', 'label' => 'Etykieta przycisku karty', 'value' => $widget?->buttonLabel ?? '',
            ], [
                'name' => 'button_url', 'label' => 'Adres przycisku karty', 'value' => $widget?->buttonUrl ?? '',
                'help' => 'Dozwolone: HTTPS, ścieżka lokalna /... albo kotwica #....',
            ], [
                'name' => 'sort_order', 'label' => 'Kolejność', 'type' => 'number',
                'value' => (string) ($widget?->sortOrder ?? 100),
            ], [
                'name' => 'is_visible', 'label' => 'Widoczny publicznie', 'type' => 'checkbox',
                'checked' => $widget?->visible ?? true,
            ]]),
            $editing ? 'Zapisz widget' : 'Dodaj widget',
            $this->security->csrfToken()
        );
        $this->theme->end_admin_panel();
        $this->endAdminPage();
    }

    private function renderEdit(Request $request): void
    {
        $widget = $this->widgets->find($request->queryInt('id', 0) ?? 0);
        if (!$widget instanceof Widget) {
            $this->renderList('Nie znaleziono widgetu.', 'danger');
            return;
        }
        $this->renderForm($widget);
    }

    private function update(Request $request): void
    {
        $widget = $this->widgets->find($request->postInt('id', 0) ?? 0);
        if (!$widget instanceof Widget) {
            $this->renderList('Nie znaleziono widgetu.', 'danger');
            return;
        }
        $this->save($request, $widget);
    }

    private function save(Request $request, ?Widget $widget = null): void
    {
        $actor = $this->auth->user();
        if (!$this->security->validateCsrfToken($request->postString('_token'))) {
            $this->audit->record($request, 'widget_save', 'invalid_csrf', 'widgets', $actor?->id);
            $this->renderForm($widget, 'Token CSRF jest nieprawidłowy lub wygasł.', 'danger');
            return;
        }
        $name = $this->bounded($request->postString('name'), 160);
        $key = $this->widgetKey($request->postString('widget_key') ?: $name);
        $type = $request->postString('widget_type');
        $placement = $request->postString('placement');
        $target = $this->widgetKey($request->postString('target_section_key'));
        $themeName = $request->postString('theme_name', '*');
        $title = $this->bounded($request->postString('title'), 180);
        $rawContent = $this->bounded($request->postString('content'), 4000);
        $contentFormat = (new ContentRenderer())->normalizeFormat($request->postString('content_format', 'html'));
        $buttonLabel = $this->bounded($request->postString('button_label'), 120);
        $buttonUrl = $this->bounded($request->postString('button_url'), 500);
        $sortOrder = max(0, min(65535, $request->postInt('sort_order', 100) ?? 100));
        if ($name === '' || $key === '' || !isset(self::TYPES[$type]) || !isset(self::PLACEMENTS[$placement])) {
            $this->renderForm($widget, 'Uzupełnij nazwę, poprawny klucz, typ i położenie.', 'warning');
            return;
        }
        if (!array_key_exists($themeName, ['*' => 'Wszystkie'] + $this->availableThemes)) {
            $this->renderForm($widget, 'Wybrany motyw nie jest dostępny.', 'warning');
            return;
        }
        if (in_array($placement, ['before_section', 'after_section'], true) && $target === '') {
            $this->renderForm($widget, 'Dla położenia przed lub po sekcji podaj jej klucz.', 'warning');
            return;
        }
        if ($type === 'terminal' && $rawContent === '') {
            $this->renderForm($widget, 'Terminal wymaga tekstu powitalnego.', 'warning');
            return;
        }
        if ($buttonUrl !== '' && !$this->safeUrl($buttonUrl)) {
            $this->renderForm($widget, 'Adres przycisku musi być lokalny, kotwicą albo adresem HTTPS.', 'warning');
            return;
        }
        if ($this->widgets->keyExists($key, $themeName, $widget?->id)) {
            $this->renderForm($widget, 'Ten klucz jest już używany dla wybranego motywu.', 'warning');
            return;
        }

        $content = $type === 'card'
            ? (new ContentRenderer())->prepareForStorage($rawContent, $contentFormat)
            : $rawContent;
        if ($type === 'terminal') {
            $contentFormat = ContentRenderer::HTML;
        }

        $data = [
            'widget_key' => $key,
            'name' => $name,
            'widget_type' => $type,
            'placement' => $placement,
            'target_section_key' => in_array($placement, ['before_section', 'after_section'], true) ? $target : '',
            'theme_name' => $themeName,
            'title' => $title,
            'content' => $content,
            'content_format' => $contentFormat,
            'button_label' => $buttonLabel,
            'button_url' => $buttonUrl,
            'sort_order' => $sortOrder,
            'is_visible' => $request->postBool('is_visible') ? 1 : 0,
        ];
        try {
            $id = $widget === null ? $this->widgets->create($data) : $widget->id;
            if ($widget !== null && !$this->widgets->update($widget->id, $data)) {
                throw new \RuntimeException('Repozytorium nie zapisało widgetu.');
            }
            $this->cache->invalidateTags(['homepage', 'widgets', 'theme']);
            $this->audit->record($request, $widget === null ? 'widget_create' : 'widget_update', 'success', 'widget:' . $id, $actor?->id);
            $this->renderList($widget === null ? 'Widget został dodany.' : 'Widget został zapisany.', 'success');
        } catch (\Throwable) {
            $this->audit->record($request, 'widget_save', 'failed', 'widgets', $actor?->id);
            $this->renderForm($widget, 'Nie udało się zapisać widgetu. Sprawdź dane albo stan bazy.', 'danger');
        }
    }

    private function delete(Request $request): void
    {
        $actor = $this->auth->user();
        if (!$this->security->validateCsrfToken($request->postString('_token'))) {
            $this->audit->record($request, 'widget_delete', 'invalid_csrf', 'widgets', $actor?->id);
            $this->renderList('Token CSRF jest nieprawidłowy lub wygasł.', 'danger');
            return;
        }
        $id = $request->postInt('id', 0) ?? 0;
        if (!$this->widgets->delete($id)) {
            $this->audit->record($request, 'widget_delete', 'failed', 'widget:' . $id, $actor?->id);
            $this->renderList('Nie udało się usunąć widgetu.', 'danger');
            return;
        }
        $this->cache->invalidateTags(['homepage', 'widgets', 'theme']);
        $this->audit->record($request, 'widget_delete', 'success', 'widget:' . $id, $actor?->id);
        $this->renderList('Widget został usunięty.', 'success');
    }

    /** @param callable(): void $handler */
    private function guard(Request $request, callable $handler): void
    {
        $user = $this->auth->user();
        if (!$user instanceof User) {
            $this->theme->render_admin_access_state(401, 'Wymagane logowanie', 'Zarządzanie widgetami wymaga aktywnej sesji.', 'index.php?route=/admin/login', 'Przejdź do logowania');
            return;
        }
        if (!$this->allowed($user)) {
            $this->audit->record($request, 'admin_access', 'denied', 'widgets.manage', $user->id);
            $this->theme->render_admin_access_state(403, 'Brak uprawnień', 'Twoje konto nie ma uprawnienia widgets.manage.', 'index.php?route=/admin', 'Wróć do panelu');
            return;
        }
        $handler();
    }

    private function allowed(User $user): bool
    {
        return in_array('*', $user->permissions, true) || in_array('widgets.manage', $user->permissions, true);
    }

    /** @param array{label:string,href:string,variant?:string}|list<array{label:string,href:string,variant?:string}>|null $actions */
    private function startAdminPage(string $title, string $lead, ?array $actions = null): void
    {
        $user = $this->auth->user();
        $this->theme->start_admin_page($title, $this->menu->visibleFor($user?->permissions ?? []), '/admin/widgets', [
            'name' => $user?->displayName ?? 'Gość',
            'role' => $user?->primaryRole() ?? 'Gość',
            'initials' => $user?->initials() ?? 'G',
            'avatar_url' => $user?->avatarUrl ?? '',
            'logout_action' => 'index.php?route=/admin/logout',
            'logout_token' => $this->security->csrfToken(),
        ]);
        $this->theme->start_admin_content(
            $title,
            $lead,
            [['label' => 'Panel', 'href' => 'index.php?route=/admin'], ['label' => 'Widgety', 'href' => 'index.php?route=/admin/widgets']],
            $actions
        );
    }

    private function endAdminPage(): void
    {
        $this->theme->end_admin_content();
        $this->theme->end_admin_page();
    }

    private function widgetKey(string $value): string
    {
        $value = strtolower(trim($value));
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        $value = trim(preg_replace('/[^a-z0-9_-]+/', '-', $value) ?? '', '-_');

        return preg_match('/^[a-z][a-z0-9_-]{1,63}$/', $value) === 1 ? $value : '';
    }

    private function safeUrl(string $value): bool
    {
        return preg_match('#^(?:/[^/]|\#[A-Za-z0-9_-])#', $value) === 1
            || (str_starts_with($value, 'https://') && filter_var($value, FILTER_VALIDATE_URL) !== false);
    }

    private function bounded(string $value, int $max): string
    {
        $value = trim($value);

        return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
    }
}
