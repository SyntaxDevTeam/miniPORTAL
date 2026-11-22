<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\Cooperation;

use InvalidArgumentException;
use SyntaxDevTeam\Cms\Core\{AdminMenuRegistry, AdminSearchProviderInterface, AdminSearchRegistry, HookProviderInterface, HookRegistry, ModuleInterface, PublicNavigationProviderInterface, PublicNavigationRegistry, Request, Router, Security, TemplateCacheInterface, ThemeInterface};
use SyntaxDevTeam\Cms\Modules\CoreAuth\{AdminAccessGate, AuditLogService, AuthService};

final class CooperationModule implements ModuleInterface, HookProviderInterface, AdminSearchProviderInterface, PublicNavigationProviderInterface
{
    private const PLACEMENTS = [
        'homepage_start' => 'Na początku strony',
        'after_hero' => 'Bezpośrednio po sekcji Hero',
        'before_contact' => 'Przed sekcją kontaktu',
        'before_footer' => 'Na końcu strony, przed stopką',
    ];

    public function __construct(
        private readonly ThemeInterface $theme,
        private readonly AdminMenuRegistry $menu,
        private readonly PartnerRepository $partners,
        private readonly AuthService $auth,
        private readonly AdminAccessGate $access,
        private readonly Security $security,
        private readonly AuditLogService $audit,
        private readonly TemplateCacheInterface $cache,
    ) {}

    public function id(): string { return 'cooperation'; }
    public function version(): string { return '1.2.0'; }
    public function dependencies(): array { return ['core_auth', 'core_pages']; }
    public function isProtected(): bool { return false; }
    public function requiredPermissions(): array { return ['cooperation.manage']; }

    public function registerAdminMenu(AdminMenuRegistry $menu): void
    {
        $menu->add('Treść', 'Współpraca', '/admin/cooperation', 'WP', 'cooperation.manage', 43);
    }

    public function registerPublicNavigation(PublicNavigationRegistry $navigation): void
    {
        // Homepage strip anchor; visibility and location are configured in /admin/settings.
        $navigation->add('cooperation.partners', 'Our partners', '/#cooperation', 'none', 70);
    }

    public function registerAdminSearch(AdminSearchRegistry $search): void
    {
        $search->add('cooperation.manage', 'Współpraca', 'Partnerzy i pasek współpracy na stronie głównej.',
            'index.php?route=/admin/cooperation', ['partner', 'współpraca', 'sponsor', 'pasek'], 'cooperation.manage', 'Treść', 43);
    }

    public function registerHooks(HookRegistry $hooks): void
    {
        // After widget placement: the strip does not take over the before_footer slot.
        $hooks->addFilter('homepage.sections', function (array $sections): array {
            $settings = $this->partners->settings();
            if (!(bool) $settings['is_enabled']) { return $sections; }
            $partners = $this->partners->all(true);
            if ($partners === []) {
                return $sections;
            }
            $section = [
                'key' => 'cooperation', 'type' => 'content', 'layout' => 'link_strip',
                'navigation_visible' => false, // Delegated to PublicNavigationRegistry, not automatic section links.
                'eyebrow' => 'Partnerships', 'title' => 'Our partners',
                'content_html' => '', 'content_format' => 'html', 'button_label' => '', 'button_url' => '',
                'items' => array_map(static fn (array $partner): array => [
                    'title' => $partner['name'], 'description' => $partner['description'], 'href' => $partner['website_url'],
                ], $partners),
            ];
            if ($settings['placement'] === 'homepage_start') {
                array_unshift($sections, $section);
                return $sections;
            }
            foreach ($sections as $index => $existing) {
                if ($settings['placement'] === 'after_hero' && ($existing['type'] ?? '') === 'hero') {
                    array_splice($sections, $index + 1, 0, [$section]);
                    return $sections;
                }
                if ($settings['placement'] === 'before_contact' && (($existing['key'] ?? '') === 'contact' || ($existing['layout'] ?? '') === 'contact')) {
                    array_splice($sections, $index, 0, [$section]);
                    return $sections;
                }
            }
            $sections[] = $section;
            return $sections;
        }, 200);
    }

    public function registerRoutes(Router $router): void
    {
        $router->get('/admin/cooperation', fn (Request $r) => $this->guard($r, fn () => $this->renderList()));
        $router->get('/admin/cooperation/create', fn (Request $r) => $this->guard($r, fn () => $this->renderForm()));
        $router->get('/admin/cooperation/edit', fn (Request $r) => $this->guard($r, function () use ($r): void {
            $partner = $this->partners->find($r->queryInt('id', 0) ?? 0);
            if ($partner === null) { http_response_code(404); $this->renderList('Nie znaleziono partnera.', 'danger'); return; }
            $this->renderForm($partner);
        }));
        $router->post('/admin/cooperation/settings', fn (Request $r) => $this->guard($r, fn () => $this->saveSettings($r)));
        foreach (['create', 'edit', 'toggle', 'delete'] as $action) {
            $router->post('/admin/cooperation/' . $action, fn (Request $r) => $this->guard($r, fn () => $this->mutate($r, $action)));
        }
    }

    private function guard(Request $request, callable $handler): void
    {
        $status = $this->access->check('cooperation.manage');
        if ($status !== AdminAccessGate::ALLOWED) {
            http_response_code($status);
            $this->theme->render_admin_access_state($status, $status === 401 ? 'Wymagane logowanie' : 'Brak uprawnień',
                'Zarządzanie współpracą wymaga uprawnienia cooperation.manage.', 'index.php?route=/admin', 'Wróć do panelu');
            return;
        }
        $handler();
    }

    private function saveSettings(Request $request): void
    {
        if (!$this->security->validateCsrfToken($request->postString('_token'))) {
            http_response_code(403);
            $this->audit->record($request, 'cooperation_settings', 'invalid_csrf', 'cooperation', $this->auth->user()?->id);
            $this->renderList('Token CSRF wygasł lub jest nieprawidłowy.', 'danger');
            return;
        }
        $placement = $request->postString('placement');
        if (!array_key_exists($placement, self::PLACEMENTS)) {
            http_response_code(422);
            $this->renderList('Wybierz poprawną pozycję paska.', 'danger');
            return;
        }
        try {
            $this->partners->saveSettings($request->postBool('is_enabled'), $placement);
            $this->cache->invalidateTags(['homepage', 'cooperation']);
            $this->audit->record($request, 'cooperation_settings', 'success', 'cooperation', $this->auth->user()?->id);
            header('Location: index.php?route=/admin/cooperation&saved=1', true, 303);
        } catch (\Throwable $error) {
            error_log('[miniPORTAL] cooperation_settings_failed ' . $error::class);
            http_response_code(500);
            $this->renderList('Nie udało się zapisać ustawień paska.', 'danger');
        }
    }

    private function mutate(Request $request, string $action): void
    {
        if (!$this->security->validateCsrfToken($request->postString('_token'))) {
            http_response_code(403);
            $this->audit->record($request, 'cooperation_' . $action, 'invalid_csrf', 'cooperation', $this->auth->user()?->id);
            $this->renderList('Token CSRF wygasł lub jest nieprawidłowy.', 'danger');
            return;
        }
        $partner = null;
        if ($action !== 'create') {
            $partner = $this->partners->find($request->postInt('id', 0) ?? 0);
            if ($partner === null) { http_response_code(404); $this->renderList('Nie znaleziono partnera.', 'danger'); return; }
        }
        try {
            if ($action === 'delete') {
                if (!$this->partners->delete((int) $partner['id'])) { throw new \RuntimeException('Delete failed'); }
            } elseif ($action === 'toggle') {
                $this->partners->save(['is_visible' => (int) !$partner['is_visible']], (int) $partner['id']);
            } else {
                $data = PartnerInput::fromRequest($request);
                $id = $this->partners->save($data, $partner !== null ? (int) $partner['id'] : null);
            }
            $this->cache->invalidateTags(['homepage', 'cooperation']);
            $this->audit->record($request, 'cooperation_' . $action, 'success', 'partner:' . ($id ?? $partner['id']), $this->auth->user()?->id);
            header('Location: index.php?route=/admin/cooperation&saved=1', true, 303);
        } catch (InvalidArgumentException $error) {
            http_response_code(422);
            $values = ['name' => $request->postString('name'), 'website_url' => $request->postString('website_url'),
                'description' => $request->postString('description'), 'sort_order' => $request->postInt('sort_order', 100),
                'is_visible' => $request->postBool('is_visible')];
            $this->renderForm($partner, $error->getMessage(), $values);
        } catch (\Throwable $error) {
            error_log('[miniPORTAL] cooperation_write_failed ' . $error::class);
            http_response_code(500);
            $this->audit->record($request, 'cooperation_' . $action, 'failed', 'cooperation', $this->auth->user()?->id);
            $this->renderList('Nie udało się zapisać zmiany. Spróbuj ponownie.', 'danger');
        }
    }

    private function start(string $title, ?array $actions = null): void
    {
        $user = $this->auth->user();
        $this->theme->start_admin_page($title, $this->menu->visibleFor($user?->permissions ?? []), '/admin/cooperation', [
            'name' => $user?->displayName ?? '', 'role' => $user?->primaryRole() ?? '', 'initials' => $user?->initials() ?? '',
            'avatar_url' => $user?->avatarUrl ?? '', 'logout_action' => 'index.php?route=/admin/logout', 'logout_token' => $this->security->csrfToken(),
        ]);
        $this->theme->start_admin_content($title, 'Partnerzy prezentowani w niewielkim pasku na stronie głównej.',
            [['label' => 'Panel', 'href' => 'index.php?route=/admin'], ['label' => 'Współpraca', 'href' => 'index.php?route=/admin/cooperation']], $actions);
    }

    private function end(): void
    {
        $this->theme->end_admin_content();
        $this->theme->end_admin_page();
    }

    private function renderList(string $message = '', string $variant = 'info'): void
    {
        $this->start('Współpraca', [['label' => 'Dodaj partnera', 'href' => 'index.php?route=/admin/cooperation/create', 'variant' => 'primary']]);
        if ($message !== '') { $this->theme->render_alert($message, $variant); }
        $settings = $this->partners->settings();
        $this->theme->start_admin_panel('Ustawienia paska', 'Our partners');
        $this->theme->render_form('index.php?route=/admin/cooperation/settings', [
            ['name' => 'is_enabled', 'label' => 'Pasek włączony na stronie głównej', 'type' => 'checkbox', 'checked' => (bool) $settings['is_enabled']],
            ['name' => 'placement', 'label' => 'Pozycja paska', 'type' => 'select', 'value' => $settings['placement'], 'options' => self::PLACEMENTS],
        ], 'Zapisz ustawienia paska', $this->security->csrfToken());
        $this->theme->end_admin_panel();
        $partners = $this->partners->all();
        $this->theme->start_admin_panel('Partnerzy', count($partners) . ' wpisów');
        if ($partners === []) {
            $this->theme->render_alert('Dodaj pierwszego partnera. Pasek pojawi się po zapisaniu widocznego wpisu.', 'info');
        } else {
            $this->theme->render_admin_action_table(['Kolejność', 'Nazwa', 'Adres WWW', 'Krótki opis', 'Stan'], array_map(static fn (array $p): array => [
                'cells' => [$p['sort_order'], $p['name'], $p['website_url'], $p['description'], $p['is_visible'] ? 'Widoczny' : 'Ukryty'],
                'actions' => [
                    ['label' => 'Edytuj', 'href' => 'index.php?route=/admin/cooperation/edit&id=' . $p['id'], 'variant' => 'primary'],
                    ['label' => $p['is_visible'] ? 'Ukryj' : 'Pokaż', 'action' => 'index.php?route=/admin/cooperation/toggle', 'fields' => ['id' => $p['id']]],
                    ['label' => 'Usuń', 'action' => 'index.php?route=/admin/cooperation/delete', 'fields' => ['id' => $p['id']], 'variant' => 'danger', 'confirm' => 'Usunąć partnera „' . $p['name'] . '”?'],
                ],
            ], $partners), $this->security->csrfToken());
        }
        $this->theme->end_admin_panel();
        $this->end();
    }

    private function renderForm(?array $partner = null, string $error = '', array $values = []): void
    {
        $editing = $partner !== null;
        $values = array_replace($partner ?? ['name' => '', 'website_url' => '', 'description' => '', 'sort_order' => 100, 'is_visible' => 1], $values);
        $this->start($editing ? 'Edytuj partnera' : 'Dodaj partnera', [['label' => 'Wróć do listy', 'href' => 'index.php?route=/admin/cooperation']]);
        if ($error !== '') { $this->theme->render_alert($error, 'danger'); }
        $fields = $editing ? [['name' => 'id', 'type' => 'hidden', 'label' => 'ID', 'value' => (string) $partner['id']]] : [];
        $fields = [...$fields,
            ['name' => 'name', 'label' => 'Nazwa partnera', 'value' => $values['name'], 'required' => true, 'maxlength' => 120],
            ['name' => 'website_url', 'label' => 'Adres WWW', 'type' => 'url', 'value' => $values['website_url'], 'required' => true, 'help' => 'Pełny adres, np. https://partner.pl.'],
            ['name' => 'description', 'label' => 'Opis', 'type' => 'textarea', 'rows' => 6, 'value' => $values['description'], 'required' => true, 'help' => 'Do 5000 znaków, zwykły tekst. Na stronie głównej opis rozwija się po najechaniu lub ustawieniu fokusu.'],
            ['name' => 'sort_order', 'label' => 'Kolejność', 'type' => 'number', 'value' => (string) $values['sort_order'], 'help' => 'Niższa liczba oznacza wcześniejsze miejsce.'],
            ['name' => 'is_visible', 'label' => 'Widoczny na stronie głównej', 'type' => 'checkbox', 'checked' => (bool) $values['is_visible']],
        ];
        $this->theme->start_admin_panel('Dane partnera');
        $this->theme->render_form('index.php?route=/admin/cooperation/' . ($editing ? 'edit' : 'create'), $fields, 'Zapisz partnera', $this->security->csrfToken());
        $this->theme->end_admin_panel();
        $this->end();
    }
}
