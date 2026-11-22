<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\CoreAuth;

use Throwable;
use SyntaxDevTeam\Cms\Core\AdminMenuRegistry;
use SyntaxDevTeam\Cms\Core\DashboardProviderInterface;
use SyntaxDevTeam\Cms\Core\DashboardRegistry;
use SyntaxDevTeam\Cms\Core\HookProviderInterface;
use SyntaxDevTeam\Cms\Core\HookRegistry;
use SyntaxDevTeam\Cms\Core\ModuleInterface;
use SyntaxDevTeam\Cms\Core\Request;
use SyntaxDevTeam\Cms\Core\Router;
use SyntaxDevTeam\Cms\Core\Security;
use SyntaxDevTeam\Cms\Core\ThemeInterface;

final class CoreAuthModule implements ModuleInterface, DashboardProviderInterface, HookProviderInterface
{
    private ?HookRegistry $hooks = null;

    public function registerHooks(HookRegistry $hooks): void
    {
        $this->hooks = $hooks;
    }

    public function __construct(
        private readonly ThemeInterface $theme,
        private readonly Security $security,
        private readonly AuthService $auth,
        private readonly IdentityProviderRegistry $providers,
        private readonly OAuthStateStore $oauthStates,
        private readonly OAuthAttemptLimiter $oauthLimiter,
        private readonly AuditLogService $audit,
        private readonly AdminMenuRegistry $menu,
        private readonly AdminAccessGate $access,
        private readonly ?UserAdministrationRepository $userAdministration,
        private readonly RegistrationConfig $registration,
        private readonly bool $demoEnabled = false,
    ) {
    }

    public function id(): string
    {
        return 'core_auth';
    }

    public function version(): string
    {
        return '2.2.1';
    }

    public function dependencies(): array
    {
        return [];
    }

    public function isProtected(): bool
    {
        return true;
    }

    public function requiredPermissions(): array
    {
        return ['users.view', 'users.manage', 'roles.view', 'roles.manage', 'logs.view', 'database.view'];
    }

    public function registerAdminMenu(AdminMenuRegistry $menu): void
    {
        $menu->add('Core', 'Użytkownicy', '/admin/users', 'US', 'users.view', 40);
        $menu->add('Core', 'Role i uprawnienia', '/admin/roles', 'RL', 'roles.view', 45);
    }

    public function registerDashboard(DashboardRegistry $dashboard): void
    {
        if ($this->userAdministration === null) {
            return;
        }
        foreach (['accounts' => ['auth.accounts', 'Konta na stronie', 'Aktywne konta WWW; bez oczekujących i zablokowanych', 'WWW'],
                  'staff' => ['auth.site_staff', 'Administracja strony', 'Aktywne konta z rolą Owner lub Administrator', 'ADM']] as $key => [$id, $label, $detail, $symbol]) {
            $dashboard->addMetric($id, $label, $detail, $symbol,
                fn (): array => ['value' => $this->userAdministration->dashboardCounts()[$key], 'detail' => $detail],
                'users.view', 70, false);
        }
        $dashboard->addMetric(
            'auth.online_users',
            'Użytkownicy online',
            'Konta z aktywną sesją w ciągu ostatnich 5 minut.',
            'USR',
            fn (): array => [
                'value' => $this->userAdministration->onlineCount(),
                'detail' => 'Aktywność w ciągu ostatnich 5 minut',
            ],
            'users.view',
            80
        );
        $dashboard->addPanel(
            'auth.online_user_list',
            'Aktualnie zalogowani użytkownicy',
            'Lista kont z aktywną sesją w ciągu ostatnich 5 minut.',
            function (): array {
                $records = $this->userAdministration->online();
                return [
                    'meta' => count($records) . ' online',
                    'headers' => ['Użytkownik', 'Role', 'Ostatnia aktywność'],
                    'rows' => array_map(static fn (UserAdminRecord $record): array => [
                        $record->displayName,
                        $record->roles !== [] ? implode(', ', $record->roles) : 'Brak',
                        $record->lastActivityAt ?? 'Brak',
                    ], $records),
                    'empty' => 'Brak aktywnych użytkowników.',
                ];
            },
            'users.view',
            81
        );
    }

    public function registerRoutes(Router $router): void
    {
        $router->get('/admin/login', fn (Request $request) => $this->renderLogin($request));
        $router->get('/account', fn () => $this->redirectToAccountHome());
        $router->get('/admin/account-pending', fn () => $this->renderPendingAccount());
        $router->get('/account/registration', fn () => $this->renderRegistration());
        $router->post('/account/registration', fn (Request $request) => $this->acceptRegistration($request));
        $router->post('/admin/login', fn (Request $request) => $this->login($request));
        $router->post('/admin/logout', fn (Request $request) => $this->logout($request));
        $router->get('/admin/users', fn (Request $request) => $this->guard(
            $request,
            'users.view',
            fn () => $this->renderUsers(request: $request)
        ));
        $router->get('/admin/users/view', fn (Request $request) => $this->guard(
            $request, 'users.view', fn () => $this->renderUserDetails($request)
        ));
        $router->post('/admin/users/revoke-sessions', fn (Request $request) => $this->guard(
            $request, 'users.manage', fn () => $this->revokeUserSessions($request)
        ));
        $router->get('/admin/users/edit', fn (Request $request) => $this->guard(
            $request,
            'users.manage',
            fn () => $this->renderUserEdit($request)
        ));
        $router->post('/admin/users/edit', fn (Request $request) => $this->guard(
            $request,
            'users.manage',
            fn () => $this->updateUser($request)
        ));
        $router->post('/admin/users/create', fn (Request $request) => $this->guard(
            $request,
            'users.manage',
            fn () => $this->createUser($request)
        ));
        $router->post('/admin/users/accept', fn (Request $request) => $this->guard(
            $request,
            'users.manage',
            fn () => $this->acceptUser($request)
        ));
        $router->post('/admin/users/delete', fn (Request $request) => $this->guard(
            $request,
            'users.manage',
            fn () => $this->deleteUser($request)
        ));
        $router->get('/admin/roles', fn (Request $request) => $this->guard(
            $request,
            'roles.view',
            fn () => $this->renderRoles()
        ));
        $router->get('/admin/roles/edit', fn (Request $request) => $this->guard(
            $request,
            'roles.manage',
            fn () => $this->renderRoleEdit($request)
        ));
        $router->post('/admin/roles/edit', fn (Request $request) => $this->guard(
            $request,
            'roles.manage',
            fn () => $this->saveRole($request)
        ));
        $router->post('/admin/roles/delete', fn (Request $request) => $this->guard(
            $request,
            'roles.manage',
            fn () => $this->deleteRole($request)
        ));
        $router->post('/admin/roles/preview', fn (Request $request) => $this->guard(
            $request,
            'roles.manage',
            fn () => $this->startRolePreview($request)
        ));
        $router->post('/admin/roles/preview/stop', fn (Request $request) => $this->stopRolePreview($request));
        $router->get('/admin/identities', fn (Request $request) => $this->renderIdentitiesNotice($request));
        $router->get('/admin/profile/identities', fn (Request $request) => $this->renderIdentitiesNotice($request));
        $router->post('/admin/identity/unlink', fn (Request $request) => $this->unlinkIdentity($request));

        foreach ($this->providers->all() as $provider) {
            $name = $provider->name();
            $router->get(
                "/admin/auth/{$name}",
                fn (Request $request) => $this->startProviderLogin($request, $name)
            );
            $router->post(
                "/admin/auth/{$name}",
                fn (Request $request) => $this->startProviderLogin($request, $name)
            );
            $router->get(
                "/admin/auth/{$name}/callback",
                fn (Request $request) => $this->completeProviderLogin($request, $name)
            );
            $router->get(
                "/admin/identity/{$name}/link",
                fn (Request $request) => $this->startProviderLogin($request, $name, 'link')
            );
        }
    }

    private function renderLogin(?Request $request = null, string $message = '', string $variant = 'info'): void
    {
        if ($request !== null) {
            $this->rememberLoginRequestTarget($request);
        }
        $user = $this->auth->user();
        if ($user !== null) {
            $redirect = $this->redirectAfterAuthentication($user);
            header('Location: ' . $redirect, true, 303);
            return;
        }

        $identities = array_map(
            static fn (IdentityProviderInterface $provider): array => [
                'provider' => $provider->name(),
                'subject' => '',
                'label' => 'Zaloguj przez ' . $provider->label(),
                'description' => 'Bezpieczne logowanie przez zewnętrznego dostawcę',
                'href' => 'index.php?route=/admin/auth/' . rawurlencode($provider->name()),
            ],
            $this->providers->configured()
        );

        if ($this->demoEnabled) {
            $identities = [
                ...$identities,
                ['provider' => 'demo', 'subject' => 'administrator', 'label' => 'Administrator demo', 'description' => 'Pełne uprawnienia panelu'],
                ['provider' => 'demo', 'subject' => 'editor', 'label' => 'Redaktor demo', 'description' => 'Treść bez zarządzania użytkownikami'],
            ];
        }

        $this->theme->render_admin_login(
            'index.php?route=/admin/login',
            $identities,
            $this->security->csrfToken(),
            $message !== '' ? $message : ($identities !== []
                ? ''
                : 'Żaden dostawca logowania nie jest jeszcze skonfigurowany.'),
            $message !== '' ? $variant : 'warning'
        );
    }

    private function renderPendingAccount(): void
    {
        $user = $this->auth->user();
        if ($user === null) {
            header('Location: index.php?route=/admin/login', true, 303);
            return;
        }
        if ($user->isActive()) {
            header('Location: index.php?route=/admin', true, 303);
            return;
        }
        if ($this->registration->isSelfService()) {
            header('Location: index.php?route=/account/registration', true, 303);
            return;
        }

        http_response_code(200);
        $this->theme->render_admin_access_state(
            202,
            'Konto oczekuje na akceptację',
            'Konto zostało utworzone i logowanie przebiegło poprawnie. '
                . 'Do czasu aktywacji przez administratora możesz korzystać wyłącznie '
                . 'z publicznych funkcji dostępnych dla kont oczekujących.',
            '/',
            'Przejdź do strony głównej'
        );
    }

    private function redirectToAccountHome(): void
    {
        $user = $this->auth->user();
        if ($user === null) {
            header('Location: index.php?route=/admin/login&next=%2Faccount', true, 303);
            return;
        }
        if (!$user->isActive()) {
            header('Location: index.php?route=/admin/account-pending', true, 303);
            return;
        }

        header('Location: ' . $this->accountHomePath($user), true, 303);
    }

    private function accountHomePath(User $user): string
    {
        $target = $this->menu->firstVisiblePath($user->permissions, '/');
        return $target === '/account' ? '/' : $target;
    }

    private function renderRegistration(string $message = '', string $variant = 'info'): void
    {
        $user = $this->auth->actualUser();
        if ($user === null) {
            header('Location: index.php?route=/admin/login', true, 303);
            return;
        }
        if ($user->isActive()) {
            header('Location: ' . $this->consumeRegistrationRedirect($user), true, 303);
            return;
        }
        if (!$this->registration->isSelfService()) {
            header('Location: index.php?route=/admin/account-pending', true, 303);
            return;
        }

        $this->theme->start_page(
            'Dokończ tworzenie konta',
            'Zaakceptuj aktualny regulamin, aby automatycznie aktywować zwykłe konto.',
            false
        );
        $this->theme->start_header(
            'Jedna zgoda i konto jest gotowe',
            'Tożsamość została potwierdzona. Nie musisz czekać na administratora.',
            'miniPORTAL / Rejestracja'
        );
        $this->theme->end_header();
        $this->theme->start_section();
        if ($message !== '') {
            $this->theme->render_alert($message, $variant);
        }
        $this->theme->start_card('Warunki korzystania z konta', 'Wersja ' . $this->registration->termsVersion);
        $this->theme->render_text(
            'Konto otrzyma podstawową rolę użytkownika. Uprawnienia administracyjne nadal może nadać wyłącznie uprawniona osoba.'
        );
        $this->theme->render_link_list([
            ['label' => 'Regulamin usługi', 'href' => $this->registration->termsUrl, 'meta' => 'przeczytaj przed akceptacją'],
            ['label' => 'Polityka prywatności', 'href' => $this->registration->privacyUrl, 'meta' => 'informacje o przetwarzaniu danych'],
        ]);
        $this->theme->render_form(
            'index.php?route=/account/registration',
            [[
                'name' => 'accept_terms',
                'label' => 'Akceptuję regulamin usługi i potwierdzam zapoznanie się z polityką prywatności.',
                'type' => 'checkbox',
                'required' => true,
            ]],
            'Utwórz konto i kontynuuj',
            $this->security->csrfToken()
        );
        $this->theme->end_card();
        $this->theme->end_section();
        $this->theme->end_page();
    }

    private function acceptRegistration(Request $request): void
    {
        $user = $this->auth->actualUser();
        if ($user === null) {
            header('Location: index.php?route=/admin/login', true, 303);
            return;
        }
        if (!$this->registration->isSelfService()) {
            header('Location: index.php?route=/admin/account-pending', true, 303);
            return;
        }
        if (!$this->security->validateCsrfToken($request->postString('_token'))) {
            $this->audit->record($request, 'terms_acceptance', 'invalid_csrf', null, $user->id);
            http_response_code(403);
            $this->renderRegistration('Formularz wygasł. Odśwież stronę i spróbuj ponownie.', 'danger');
            return;
        }
        if (!$request->postBool('accept_terms')) {
            $this->audit->record($request, 'terms_acceptance', 'not_accepted', null, $user->id);
            http_response_code(422);
            $this->renderRegistration('Aktywacja konta wymaga jawnej akceptacji regulaminu.', 'warning');
            return;
        }

        try {
            $activated = $this->auth->activateWithTermsAcceptance(
                $this->registration->termsVersion,
                $this->registration->termsUrl,
                $this->registration->privacyUrl,
                $this->registration->ipHash($request->clientIp()),
                $request->userAgent() !== '' ? $request->userAgent() : null,
            );
        } catch (Throwable $exception) {
            error_log(sprintf('miniPORTAL account activation failed: user_id=%d exception=%s', $user->id, $exception::class));
            $activated = null;
        }
        if ($activated === null || !$activated->isActive()) {
            $this->audit->record($request, 'terms_acceptance', 'activation_failed', null, $user->id);
            http_response_code(503);
            $this->renderRegistration('Nie udało się aktywować konta. Spróbuj ponownie za chwilę.', 'danger');
            return;
        }

        $this->audit->record($request, 'terms_acceptance', 'accepted', null, $user->id);
        header('Location: ' . $this->consumeRegistrationRedirect($activated), true, 303);
    }

    private function renderUsers(string $message = '', string $variant = 'info', ?Request $request = null): void
    {
        $user = $this->auth->user();
        if ($user === null) {
            return;
        }

        $this->theme->require_asset('admin_catalog');
        $this->startAdminPage(
            'Użytkownicy',
            '/admin/users',
            'Konta lokalne, role i statusy niezależne od dostawców logowania.'
        );
        if ($message !== '') {
            $this->theme->render_alert($message, $variant);
        }
        if ($this->userAdministration === null) {
            $this->theme->render_alert('Zarządzanie użytkownikami wymaga bazy danych.', 'danger');
            $this->endAdminPage();
            return;
        }

        $canManage = in_array('*', $user->permissions, true)
            || in_array('users.manage', $user->permissions, true);
        $canManageOwner = $this->isOwner($user);
        $canManagePrivileged = $canManageOwner || in_array('administrator', $user->roles, true);
        $assignableRoles = $this->assignableRoles($user);
        $roleLabels = $this->userAdministration->roles();
        $query = new UserDirectoryQuery($request ?? Request::fromArrays([], [], []));
        $counts = $this->userAdministration->directoryCounts();
        $this->theme->start_admin_metrics();
        foreach (['total' => 'Wszystkie konta', 'active' => 'Aktywne', 'pending' => 'Do akceptacji', 'blocked' => 'Zablokowane'] as $key => $label) {
            $this->theme->render_admin_metric($label, (string) $counts[$key], 'US');
        }
        $this->theme->end_admin_metrics();
        $this->theme->start_admin_panel('Katalog użytkowników', 'Wyszukuj po nazwie, e-mailu lub dokładnym ID konta.');
        $this->theme->start_admin_catalog_toolbar();
        $this->theme->render_form('index.php', [
            ['name' => 'route', 'type' => 'hidden', 'value' => '/admin/users'],
            ['name' => 'q', 'id' => 'users-search', 'label' => 'Szukaj użytkownika', 'value' => $query->search, 'placeholder' => 'Nazwa, e-mail lub ID konta'],
            ...array_map(static fn (string $name): array => ['name' => $name, 'type' => 'hidden', 'value' => (string) $query->{$name}], ['status', 'role', 'provider', 'presence', 'sort']),
            ['name' => 'per_page', 'type' => 'hidden', 'value' => (string) $query->perPage],
        ], 'Szukaj', '', 'get');
        $this->theme->render_admin_filter_modal('users', 'Filtry użytkowników', 'index.php', [
            ['name' => 'route', 'type' => 'hidden', 'value' => '/admin/users'],
            ['name' => 'q', 'label' => 'Nazwa, e-mail lub ID', 'value' => $query->search],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'value' => $query->status,
                'options' => ['' => 'Wszystkie statusy', 'active' => 'Aktywne', 'pending' => 'Oczekujące', 'blocked' => 'Zablokowane']],
            ['name' => 'role', 'label' => 'Rola lokalna', 'type' => 'select', 'value' => $query->role,
                'options' => ['' => 'Wszystkie role'] + $this->userAdministration->roles()],
            ['name' => 'provider', 'label' => 'Dostawca logowania', 'type' => 'select', 'value' => $query->provider,
                'options' => ['' => 'Wszyscy dostawcy', 'github' => 'GitHub', 'discord' => 'Discord', 'google' => 'Google', 'microsoft' => 'Microsoft', 'none' => 'Bez tożsamości OAuth']],
            ['name' => 'presence', 'label' => 'Obecność', 'type' => 'select', 'value' => $query->presence,
                'options' => ['' => 'Wszyscy', 'online' => 'Online', 'offline' => 'Offline']],
            ['name' => 'sort', 'label' => 'Sortowanie', 'type' => 'select', 'value' => $query->sort,
                'options' => ['newest' => 'Najnowsze konta', 'oldest' => 'Najstarsze konta', 'name' => 'Nazwa A–Z', 'login' => 'Ostatnio zalogowani']],
            ['name' => 'per_page', 'label' => 'Na stronie', 'type' => 'select', 'value' => (string) $query->perPage,
                'options' => ['25' => '25', '50' => '50', '100' => '100']],
        ], 'index.php?route=/admin/users');
        $this->theme->render_admin_panel_actions([
            ['label' => 'Wszystkie konta', 'href' => 'index.php?route=/admin/users'],
            ['label' => 'Do akceptacji (' . $counts['pending'] . ')', 'href' => 'index.php?route=/admin/users&status=pending'],
            ['label' => 'Online', 'href' => 'index.php?route=/admin/users&presence=online'],
        ]);
        if ($canManage) {
            $this->theme->render_admin_form_modal(
                'users-create', 'Dodaj użytkownika',
                'index.php?route=/admin/users/create',
                [
                    ['name' => 'display_name', 'label' => 'Nazwa wyświetlana'],
                    ['name' => 'email', 'label' => 'E-mail kontaktowy', 'type' => 'email'],
                    [
                        'name' => 'provider',
                        'label' => 'Dostawca tożsamości',
                        'type' => 'select',
                        'value' => '',
                        'options' => [
                            '' => 'Bez tożsamości OAuth — konto bez dostępu do logowania',
                            'github' => 'GitHub',
                            'discord' => 'Discord',
                            'google' => 'Google',
                            'microsoft' => 'Microsoft',
                        ],
                    ],
                    [
                        'name' => 'provider_subject',
                        'label' => 'Niezmienny ID użytkownika u dostawcy',
                        'help' => 'Nie używaj e-maila ani loginu. Pole wymagane tylko przy wyborze dostawcy.',
                    ],
                    [
                        'name' => 'status',
                        'label' => 'Status początkowy',
                        'type' => 'select',
                        'value' => 'pending',
                        'options' => ['pending' => 'Oczekujący', 'active' => 'Aktywny'],
                    ],
                    [
                        'name' => 'roles',
                        'label' => 'Role',
                        'type' => 'checkbox_groups',
                        'values' => ['user'],
                        'groups' => ['Role lokalne' => $assignableRoles],
                        'help' => 'Zaznacz co najmniej jedną rolę. Uprawnienia wybranych ról sumują się.',
                    ],
                ],
                'Dodaj użytkownika',
                $this->security->csrfToken(),
                'Dodaj użytkownika'
            );
        }
        $this->theme->end_admin_catalog_toolbar();
        $total = $this->userAdministration->countDirectory($query);
        $pages = max(1, (int) ceil($total / $query->perPage));
        if ($query->page > $pages) {
            $this->theme->render_alert('Wybrana strona nie istnieje. Wróć do pierwszej strony wyników.', 'info');
            $this->theme->render_button('Pierwsza strona', $query->url());
        }
        $records = $this->userAdministration->all($query);
        $this->theme->render_text('Wyniki: ' . $total . ' • Strona ' . $query->page . ' z ' . $pages
            . ($query->search !== '' ? ' • Szukasz: ' . $query->search : '')
            . ($query->status !== '' ? ' • Status: ' . $query->status : '')
            . ($query->role !== '' ? ' • Rola: ' . $query->role : '')
            . ($query->provider !== '' ? ' • Dostawca: ' . $query->provider : '')
            . ($query->presence !== '' ? ' • Obecność: ' . $query->presence : ''));
        if ($total === 0) $this->theme->render_alert('Brak kont spełniających wybrane kryteria. Zmień lub wyczyść filtry.', 'info');
        $this->theme->render_admin_action_table(
            ['Użytkownik', 'Status konta', 'Obecność', 'Role lokalne', 'Logowanie OAuth', 'Ostatnie logowanie'],
            array_map(
                fn (UserAdminRecord $record): array => [
                    'cells' => [
                        '#' . $record->id . ' ' . $record->displayName . ($record->email !== null ? ' (' . $record->email . ')' : ''),
                        match ($record->status) {
                            'active' => 'Aktywny',
                            'blocked' => 'Zablokowany',
                            default => 'Oczekujący',
                        },
                        $record->online ? 'Online' : 'Offline',
                        implode(', ', array_map(fn (string $role): string => $roleLabels[$role] ?? $role, $record->roles)) ?: 'Brak',
                        $record->providers !== [] ? implode(', ', $record->providers) : 'Brak',
                        $record->lastLoginAt ?? 'Nigdy',
                    ],
                    'actions' => array_merge([['label' => 'Szczegóły', 'href' => 'index.php?route=/admin/users/view&id=' . $record->id]], $canManage
                        && ($canManageOwner || !in_array('owner', $record->roles, true))
                        && ($canManagePrivileged || array_intersect(['administrator', 'maintainer'], $record->roles) === [])
                        ? array_values(array_filter([
                        [
                            'label' => 'Edytuj',
                            'href' => 'index.php?route=/admin/users/edit&id=' . $record->id,
                            'variant' => 'outline-light',
                        ],
                        $record->status === 'pending' ? [
                            'label' => 'Zaakceptuj',
                            'action' => 'index.php?route=/admin/users/accept',
                            'fields' => ['id' => $record->id],
                            'variant' => 'primary',
                            'confirm' => 'Aktywować konto oczekującego użytkownika?',
                        ] : null,
                    ])) : []),
                ],
                $records
            ),
            $this->security->csrfToken()
        );
        $pagination = [];
        if ($query->page > 1) $pagination[] = ['label' => 'Poprzednia', 'href' => $query->url($query->page - 1)];
        if ($query->page < $pages) $pagination[] = ['label' => 'Następna', 'href' => $query->url($query->page + 1)];
        $this->theme->render_admin_panel_actions($pagination);
        $this->theme->end_admin_panel();
        $this->endAdminPage();
    }

    private function renderUserDetails(Request $request, string $message = '', string $variant = 'info'): void
    {
        $record = $this->userAdministration?->find($request->queryInt('id') ?? $request->postInt('id') ?? 0);
        if ($record === null) {
            http_response_code(404);
            $this->theme->render_admin_access_state(404, 'Nie znaleziono użytkownika', 'Wybrane konto nie istnieje.', 'index.php?route=/admin/users', 'Wróć do użytkowników');
            return;
        }
        $actor = $this->auth->user();
        if ($actor === null) return;
        $this->startAdminPage($record->displayName, '/admin/users', 'Karta użytkownika • ID ' . $record->id);
        if ($message !== '') $this->theme->render_alert($message, $variant);
        $canManage = $this->mayManageAccount($actor, $record);
        $actions = [['label' => 'Wróć do katalogu', 'href' => 'index.php?route=/admin/users']];
        if ($canManage) $actions[] = ['label' => 'Edytuj role i status', 'href' => 'index.php?route=/admin/users/edit&id=' . $record->id, 'variant' => 'primary'];
        $this->theme->render_admin_panel_actions($actions);
        $this->theme->start_admin_panel('Konto miniPORTAL', 'Dane konta i aktywność');
        $roles = $this->userAdministration->roles();
        $roleLabels = static fn (array $names): string => implode(', ', array_map(static fn (string $name): string => $roles[$name] ?? $name, $names)) ?: 'Brak';
        $derived = $record->status === 'active'
            ? $this->userAdministration->supplementalRoles($record->id, $this->hooks)
            : ['roles' => [], 'permissions' => []];
        $this->theme->render_admin_fact_grid([
            ['label' => 'Nazwa wyświetlana', 'value' => $record->displayName],
            ['label' => 'E-mail kontaktowy', 'value' => $record->email ?? 'Nie podano'],
            ['label' => 'Status', 'value' => match ($record->status) {'active' => 'Aktywny', 'blocked' => 'Zablokowany', default => 'Oczekujący'}],
            ['label' => 'Obecność', 'value' => $record->online ? 'Online' : 'Offline', 'detail' => 'Online oznacza aktywność w ostatnich 5 minutach.'],
            ['label' => 'Utworzono', 'value' => $record->createdAt],
            ['label' => 'Ostatnie logowanie', 'value' => $record->lastLoginAt ?? 'Nigdy'],
            ['label' => 'Ostatnia aktywność (UTC)', 'value' => $record->lastActivityAt ?? 'Brak'],
            ['label' => 'Role lokalne', 'value' => $roleLabels($record->roles), 'detail' => 'Przypisane ręcznie w miniPORTAL.'],
            ['label' => 'Role z integracji', 'value' => $roleLabels($derived['roles']), 'detail' => 'Obowiązują zgodnie z bieżącym potwierdzeniem i mapowaniem modułu źródłowego.'],
        ]);
        $this->theme->end_admin_panel();
        $this->theme->start_admin_panel('Tożsamości logowania', 'Dostawcy logowania przypisani do konta');
        $identities = $this->userAdministration->accountIdentities($record->id);
        if ($identities === []) $this->theme->render_alert('Konto nie ma połączonej tożsamości OAuth. Sam e-mail kontaktowy nie umożliwia logowania.', 'info');
        $this->theme->render_admin_table(['Dostawca', 'Login', 'Weryfikacja e-maila', 'Połączono', 'Ostatnio użyto'], array_map(
            static fn (array $identity): array => [$identity['provider'], $identity['provider_login'] ?? 'Brak', (bool) $identity['email_verified'] ? 'Potwierdzony' : 'Niepotwierdzony', $identity['linked_at'], $identity['last_used_at'] ?? 'Nigdy'], $identities
        ));
        $this->theme->render_text('Łączenie tożsamości OAuth wymaga zalogowania u dostawcy przez właściciela konta. Zgodny e-mail lub nick nie łączy kont automatycznie.');
        $this->theme->end_admin_panel();
        $this->theme->start_admin_panel('Powiązania z modułami', 'Profile i potwierdzone tożsamości zewnętrzne');
        $relations = $this->hooks?->applyFilters('core_auth.account_relations', [], $record->id, $actor) ?? [];
        $rows = [];
        foreach (is_array($relations) ? array_slice($relations, 0, 100) : [] as $relation) {
            if (!is_array($relation) || !is_string($relation['label'] ?? null) || !is_string($relation['value'] ?? null)) continue;
            $href = $relation['href'] ?? '';
            // Extensions may link only to local routes, never external or script URLs.
            $validHref = is_string($href) && preg_match('~^/(?!/)[a-zA-Z0-9/_?=&%:.+-]*$~D', $href) === 1;
            $rows[] = ['cells' => [$relation['label'], $relation['value'], (string) ($relation['detail'] ?? '')],
                'actions' => $validHref ? [['label' => 'Otwórz', 'href' => $href]] : []];
        }
        if ($rows === []) $this->theme->render_alert('Brak powiązań udostępnionych przez aktywne moduły dla Twoich uprawnień.', 'info');
        else $this->theme->render_admin_action_table(['Moduł', 'Tożsamość', 'Stan powiązania'], $rows, $this->security->csrfToken());
        $this->theme->end_admin_panel();
        $this->theme->start_admin_panel('Bezpieczeństwo sesji', 'Rejestr aktywności z ostatnich 30 dni • maksymalnie 100 ostatnich wpisów');
        $sessions = $this->userAdministration->accountSessions($record->id);
        $this->theme->render_text('Rejestr obecności nie jest listą urządzeń. Nie przechowuje adresów IP ani danych przeglądarek. Czasy podano w UTC.');
        $this->theme->render_admin_table(['Utworzono', 'Ostatnia aktywność', 'Obecność'], array_map(
            static fn (array $session): array => [$session['created_at'], $session['last_seen_at'], $record->status !== 'blocked' && strtotime($session['last_seen_at'] . ' UTC') >= time() - 300 ? 'Online' : 'Nieaktywna'], $sessions
        ));
        if ($canManage && $this->userAdministration->sessionRevocationAvailable($record->id)) {
            $this->theme->render_admin_action_table(['Operacja', 'Skutek'], [['cells' => ['Unieważnij wszystkie sesje', 'Wszystkie urządzenia będą wymagały ponownego logowania przy następnym żądaniu.'], 'actions' => [[
                'label' => 'Wyloguj wszystkie urządzenia', 'action' => 'index.php?route=/admin/users/revoke-sessions', 'fields' => ['id' => $record->id], 'variant' => 'danger',
                'confirm' => 'Unieważnić wszystkie sesje użytkownika „' . $record->displayName . '”? Wymagane będzie ponowne logowanie, także na bieżącym urządzeniu tego konta.',
            ]]]], $this->security->csrfToken());
        }
        $this->theme->end_admin_panel();
        if ($canManage && $record->id !== $actor->id) {
            $this->theme->start_admin_panel('Operacje na koncie', 'Trwałe usunięcie');
            $this->theme->render_text('Usunięcie konta jest nieodwracalne. Powiązane treści lub zamówienia mogą wymagać wcześniejszego przeniesienia danych albo procedury anonimizacji.');
            $this->theme->render_admin_action_table(['Konto'], [['cells' => [$record->displayName], 'actions' => [[
                'label' => 'Usuń konto', 'action' => 'index.php?route=/admin/users/delete', 'fields' => ['id' => $record->id], 'variant' => 'danger',
                'confirm' => 'Trwale usunąć konto użytkownika „' . $record->displayName . '”? Tej operacji nie można cofnąć.',
            ]]]], $this->security->csrfToken());
            $this->theme->end_admin_panel();
        }
        $this->endAdminPage();
    }

    private function mayManageAccount(User $actor, UserAdminRecord $record): bool
    {
        return (in_array('*', $actor->permissions, true) || in_array('users.manage', $actor->permissions, true))
            && ($this->isOwner($actor) || !in_array('owner', $record->roles, true))
            && ($this->isOwner($actor) || in_array('administrator', $actor->roles, true)
                || array_intersect(['administrator', 'maintainer'], $record->roles) === []);
    }

    private function revokeUserSessions(Request $request): void
    {
        $actor = $this->auth->user();
        $id = $request->postInt('id') ?? 0;
        if ($actor === null || $this->userAdministration === null) return;
        if (!$this->security->validateCsrfToken($request->postString('_token'))) {
            $this->audit->record($request, 'user_sessions_revoke', 'invalid_csrf', null, $actor->id);
            http_response_code(403);
            $this->renderUserDetails($request, 'Nieprawidłowy lub wygasły token CSRF.', 'danger');
            return;
        }
        try {
            $record = $this->userAdministration->find($id);
            if ($record === null || !$this->mayManageAccount($actor, $record)) {
                throw new \RuntimeException('Brak uprawnień do zarządzania tym kontem.');
            }
            $this->userAdministration->revokeSessions($id, $actor->id,
                fn () => $this->audit->record($request, 'user_sessions_revoke', 'success', 'user:' . $id, $actor->id));
        } catch (\Throwable) {
            $this->audit->record($request, 'user_sessions_revoke', 'failed', null, $actor->id);
            http_response_code(409);
            $this->renderUserDetails($request, 'Nie udało się unieważnić sesji. Sprawdź uprawnienia i aktualizację modułu.', 'danger');
            return;
        }
        if ($id === $actor->id) {
            $this->auth->logout();
            header('Location: index.php?route=/admin/login', true, 303);
            return;
        }
        $this->renderUserDetails($request, 'Wszystkie dotychczasowe sesje zostały unieważnione.', 'success');
    }

    private function renderUserEdit(
        Request $request,
        string $message = '',
        string $variant = 'info',
    ): void {
        $id = $request->queryInt('id') ?? $request->postInt('id') ?? 0;
        $record = $this->userAdministration?->find($id);
        if ($record === null || $this->userAdministration === null) {
            http_response_code(404);
            $this->theme->render_admin_access_state(
                404,
                'Nie znaleziono użytkownika',
                'Wybrane konto nie istnieje.',
                'index.php?route=/admin/users',
                'Wróć do użytkowników'
            );
            return;
        }
        $actor = $this->auth->user();
        if (in_array('owner', $record->roles, true) && !$this->isOwner($actor)) {
            http_response_code(403);
            $this->theme->render_admin_access_state(
                403,
                'Konto Ownera jest chronione',
                'Tylko Owner może zarządzać innym kontem Ownera.',
                'index.php?route=/admin/users',
                'Wróć do użytkowników'
            );
            return;
        }
        if (array_intersect(['administrator', 'maintainer'], $record->roles) !== []
            && ($actor === null || (!$this->isOwner($actor) && !in_array('administrator', $actor->roles, true)))) {
            http_response_code(403);
            $this->theme->render_admin_access_state(
                403,
                'Konto uprzywilejowane jest chronione',
                'Tylko Owner lub Administrator może zarządzać tym kontem.',
                'index.php?route=/admin/users',
                'Wróć do użytkowników'
            );
            return;
        }

        $this->startAdminPage(
            'Edytuj użytkownika',
            '/admin/users',
            'Status i rola są lokalne; dostawca OAuth nie nadaje uprawnień.'
        );
        if ($message !== '') {
            $this->theme->render_alert($message, $variant);
        }
        $this->theme->render_admin_panel_actions([
            ['label' => 'Szczegóły konta', 'href' => 'index.php?route=/admin/users/view&id=' . $record->id],
            ['label' => 'Wróć do katalogu', 'href' => 'index.php?route=/admin/users'],
        ]);
        $this->theme->start_admin_panel($record->displayName, 'ID ' . $record->id);
        $this->theme->render_form(
            'index.php?route=/admin/users/edit',
            [
                ['name' => 'id', 'label' => 'ID', 'type' => 'hidden', 'value' => (string) $record->id],
                [
                    'name' => 'status',
                    'label' => 'Status konta',
                    'type' => 'select',
                    'value' => $record->status,
                    'options' => [
                        'active' => 'Aktywny',
                        'blocked' => 'Zablokowany',
                        'pending' => 'Oczekujący',
                    ],
                ],
                [
                    'name' => 'roles',
                    'label' => 'Role',
                    'type' => 'checkbox_groups',
                    'values' => $record->roles,
                    'groups' => ['Role lokalne' => $this->assignableRoles($actor)],
                    'help' => 'Użytkownik może mieć wiele ról; uprawnienia są ich sumą.',
                ],
            ],
            'Zapisz użytkownika',
            $this->security->csrfToken()
        );
        $this->theme->end_admin_panel();
        $this->endAdminPage();
    }

    private function updateUser(Request $request): void
    {
        $actor = $this->auth->user();
        if ($actor === null || $this->userAdministration === null) {
            return;
        }
        if (!$this->security->validateCsrfToken($request->postString('_token'))) {
            $this->audit->record($request, 'user_update', 'invalid_csrf', null, $actor->id);
            http_response_code(403);
            $this->renderUserEdit($request, 'Nieprawidłowy lub wygasły token CSRF.', 'danger');
            return;
        }

        $userId = $request->postInt('id') ?? 0;
        try {
            $this->userAdministration->updateAccount(
                $userId,
                $request->postString('status'),
                $request->postStringList('roles'),
                $actor->id
            );
        } catch (\Throwable $exception) {
            $this->audit->record($request, 'user_update', 'failed', null, $actor->id);
            $this->renderUserEdit($request, $exception->getMessage(), 'danger');
            return;
        }

        $this->audit->record($request, 'user_update', 'success', null, $actor->id);
        header('Location: index.php?route=/admin/users', true, 303);
    }

    private function createUser(Request $request): void
    {
        $actor = $this->auth->user();
        if ($actor === null || $this->userAdministration === null) {
            return;
        }
        if (!$this->security->validateCsrfToken($request->postString('_token'))) {
            $this->audit->record($request, 'user_create', 'invalid_csrf', null, $actor->id);
            $this->renderUsers('Nieprawidłowy lub wygasły token CSRF.', 'danger');
            return;
        }

        try {
            $userId = $this->userAdministration->createAccount(
                $request->postString('display_name'),
                $request->postString('email') ?: null,
                $request->postString('status'),
                $request->postStringList('roles'),
                $request->postString('provider'),
                $request->postString('provider_subject'),
                $actor->id
            );
            $this->audit->record($request, 'user_create', 'success', null, $actor->id);
            $this->renderUsers("Użytkownik ID {$userId} został dodany.", 'success');
        } catch (\Throwable $exception) {
            $this->audit->record($request, 'user_create', 'failed', null, $actor->id);
            $this->renderUsers($exception->getMessage(), 'danger');
        }
    }

    private function acceptUser(Request $request): void
    {
        $actor = $this->auth->user();
        if ($actor === null || $this->userAdministration === null) {
            return;
        }
        if (!$this->security->validateCsrfToken($request->postString('_token'))) {
            $this->audit->record($request, 'user_accept', 'invalid_csrf', null, $actor->id);
            $this->renderUsers('Nieprawidłowy lub wygasły token CSRF.', 'danger');
            return;
        }

        $userId = $request->postInt('id') ?? 0;
        $record = $this->findUserRecord($userId);
        if ($record === null || $record->status !== 'pending') {
            $this->renderUsers('Konto nie istnieje albo nie oczekuje na akceptację.', 'warning');
            return;
        }
        try {
            $this->userAdministration->updateAccount($userId, 'active', $record->roles, $actor->id);
            $this->audit->record($request, 'user_accept', 'success', null, $actor->id);
            $this->renderUsers('Konto zostało zaakceptowane i aktywowane.', 'success');
        } catch (\Throwable $exception) {
            $this->audit->record($request, 'user_accept', 'failed', null, $actor->id);
            $this->renderUsers($exception->getMessage(), 'danger');
        }
    }

    private function deleteUser(Request $request): void
    {
        $actor = $this->auth->user();
        if ($actor === null || $this->userAdministration === null) {
            return;
        }
        if (!$this->security->validateCsrfToken($request->postString('_token'))) {
            $this->audit->record($request, 'user_delete', 'invalid_csrf', null, $actor->id);
            http_response_code(403);
            $this->renderUsers('Nieprawidłowy lub wygasły token CSRF.', 'danger');
            return;
        }

        $userId = $request->postInt('id') ?? 0;
        try {
            $this->userAdministration->deleteAccount($userId, $actor->id);
            $this->audit->record($request, 'user_delete', 'success', 'target:' . $userId, $actor->id);
            $this->renderUsers('Konto użytkownika zostało trwale usunięte.', 'success');
        } catch (\Throwable $exception) {
            $this->audit->record($request, 'user_delete', 'failed', 'target:' . $userId, $actor->id);
            $this->renderUsers($exception->getMessage(), 'danger');
        }
    }

    private function renderRoles(string $message = '', string $variant = 'info'): void
    {
        $user = $this->auth->user();
        if ($user === null || $this->userAdministration === null) {
            return;
        }
        $this->startAdminPage(
            'Role i uprawnienia',
            '/admin/roles',
            'Lokalne role łączą uprawnienia niezależnie od dostawcy logowania.'
        );
        if ($message !== '') {
            $this->theme->render_alert($message, $variant);
        }
        $canManage = in_array('*', $user->permissions, true)
            || in_array('roles.manage', $user->permissions, true);
        $roles = $this->userAdministration->roleRecords();
        $canPreviewRole = static fn (RoleAdminRecord $role): bool => in_array('*', $user->permissions, true)
            || array_diff($role->permissions, $user->permissions) === [];
        $this->theme->start_admin_panel('Definicje ról', count($roles) . ' ról');
        $this->theme->render_admin_action_table(
            ['Rola', 'Typ', 'Użytkownicy', 'Uprawnienia'],
            array_map(
                static fn (RoleAdminRecord $role): array => [
                    'cells' => [
                        $role->label . ' (' . $role->name . ')',
                        $role->system ? 'Systemowa' : 'Niestandardowa',
                        $role->usersCount,
                        $role->permissions !== [] ? implode(', ', $role->permissions) : 'Brak',
                    ],
                    'actions' => $canManage ? array_values(array_filter([
                        $canPreviewRole($role) ? [
                            'label' => 'Pokaż jako…',
                            'action' => 'index.php?route=/admin/roles/preview',
                            'fields' => ['role' => $role->name],
                            'variant' => 'outline-info',
                        ] : null,
                        !$role->system ? [
                            'label' => 'Edytuj',
                            'href' => 'index.php?route=/admin/roles/edit&name=' . rawurlencode($role->name),
                            'variant' => 'outline-light',
                        ] : null,
                        !$role->system && $role->usersCount === 0 ? [
                            'label' => 'Usuń',
                            'action' => 'index.php?route=/admin/roles/delete',
                            'fields' => ['name' => $role->name],
                            'variant' => 'outline-danger',
                            'confirm' => 'Usunąć tę rolę?',
                        ] : null,
                    ])) : [],
                ],
                $roles
            ),
            $this->security->csrfToken()
        );
        $this->theme->end_admin_panel();
        if ($canManage) {
            $this->theme->render_button(
                'Dodaj rolę',
                'index.php?route=/admin/roles/edit',
                'primary'
            );
        }
        $this->endAdminPage();
    }

    private function startRolePreview(Request $request): void
    {
        $actor = $this->auth->actualUser();
        if ($actor === null || $this->userAdministration === null) {
            return;
        }
        if (!$this->security->validateCsrfToken($request->postString('_token'))) {
            $this->audit->record($request, 'role_preview_start', 'invalid_csrf', null, $actor->id);
            $this->renderRoles('Nieprawidłowy lub wygasły token CSRF.', 'danger');
            return;
        }

        $role = $this->userAdministration->findRole($request->postString('role'));
        if ($role === null || !$this->auth->startRolePreview($role->name, $role->label, $role->permissions)) {
            $this->audit->record($request, 'role_preview_start', 'forbidden', null, $actor->id);
            $this->renderRoles('Nie można uruchomić podglądu tej roli.', 'danger');
            return;
        }

        $this->audit->record($request, 'role_preview_start', 'success', 'role=' . $role->name, $actor->id);
        header('Location: index.php?route=/admin', true, 303);
    }

    private function stopRolePreview(Request $request): void
    {
        $actor = $this->auth->actualUser();
        if ($actor === null) {
            http_response_code(401);
            $this->theme->render_admin_access_state(
                401,
                'Wymagane logowanie',
                'Zakończenie podglądu roli wymaga aktywnej sesji.',
                'index.php?route=/admin/login',
                'Przejdź do logowania'
            );
            return;
        }
        if (!$this->actualUserAllows($actor, 'roles.manage')) {
            http_response_code(403);
            $this->theme->render_admin_access_state(
                403,
                'Brak uprawnienia',
                'Twoje konto nie może zarządzać podglądem ról.',
                'index.php?route=/admin',
                'Wróć do dashboardu'
            );
            return;
        }
        if (!$this->security->validateCsrfToken($request->postString('_token'))) {
            $this->audit->record($request, 'role_preview_stop', 'invalid_csrf', null, $actor->id);
            http_response_code(403);
            $this->theme->render_admin_access_state(
                403,
                'Nieprawidłowy token CSRF',
                'Nie udało się bezpiecznie zakończyć podglądu roli.',
                'index.php?route=/admin',
                'Wróć do dashboardu'
            );
            return;
        }

        $preview = $this->auth->rolePreview();
        $this->auth->stopRolePreview();
        $this->audit->record(
            $request,
            'role_preview_stop',
            'success',
            $preview !== null ? 'role=' . $preview['role'] : null,
            $actor->id
        );
        header('Location: index.php?route=/admin/roles', true, 303);
    }

    private function renderRoleEdit(
        Request $request,
        string $message = '',
        string $variant = 'info',
    ): void {
        if ($this->userAdministration === null) {
            return;
        }
        $name = $request->queryString('name') ?: $request->postString('original_name');
        $role = $name !== '' ? $this->userAdministration->findRole($name) : null;
        if ($name !== '' && $role === null) {
            http_response_code(404);
            $this->theme->render_admin_access_state(
                404,
                'Nie znaleziono roli',
                'Wybrana rola nie istnieje.',
                'index.php?route=/admin/roles',
                'Wróć do ról'
            );
            return;
        }
        if ($role?->system === true) {
            http_response_code(403);
            $this->theme->render_admin_access_state(
                403,
                'Rola systemowa jest chroniona',
                'Preset tej roli jest utrzymywany przez migracje Core i nie podlega ręcznej edycji.',
                'index.php?route=/admin/roles',
                'Wróć do ról'
            );
            return;
        }
        $this->startAdminPage(
            $role === null ? 'Dodaj rolę' : 'Edytuj rolę',
            '/admin/roles',
            'Uprawnienia zaznaczone dla roli są sumowane z pozostałymi rolami użytkownika.'
        );
        if ($message !== '') {
            $this->theme->render_alert($message, $variant);
        }
        $this->theme->start_admin_panel($role?->label ?? 'Nowa rola', $role?->system ? 'Rola systemowa' : 'Rola niestandardowa');
        $this->theme->render_form(
            'index.php?route=/admin/roles/edit',
            [
                ['name' => 'original_name', 'label' => 'Poprzednia nazwa', 'type' => 'hidden', 'value' => $role?->name ?? ''],
                [
                    'name' => 'name',
                    'label' => 'Identyfikator',
                    'value' => $role?->name ?? '',
                    'help' => $role?->system ? 'Identyfikator roli systemowej jest niezmienny.' : 'Małe litery, cyfry i podkreślenia.',
                ],
                ['name' => 'label', 'label' => 'Etykieta', 'value' => $role?->label ?? ''],
                [
                    'name' => 'permissions',
                    'label' => 'Uprawnienia',
                    'type' => 'checkbox_groups',
                    'values' => $role?->permissions ?? [],
                    'groups' => $this->permissionGroups(),
                    'help' => 'Uprawnienia są pogrupowane według obszaru systemu. Możesz zaznaczać je pojedynczo albo całymi grupami.',
                ],
            ],
            $role === null ? 'Utwórz rolę' : 'Zapisz rolę',
            $this->security->csrfToken()
        );
        $this->theme->end_admin_panel();
        $this->endAdminPage();
    }

    private function saveRole(Request $request): void
    {
        $actor = $this->auth->user();
        if ($actor === null || $this->userAdministration === null) {
            return;
        }
        if (!$this->security->validateCsrfToken($request->postString('_token'))) {
            $this->audit->record($request, 'role_save', 'invalid_csrf', null, $actor->id);
            $this->renderRoleEdit($request, 'Nieprawidłowy lub wygasły token CSRF.', 'danger');
            return;
        }
        try {
            $name = $this->userAdministration->saveRole(
                $request->postString('original_name'),
                $request->postString('name'),
                $request->postString('label'),
                $request->postStringList('permissions')
            );
            $this->audit->record($request, 'role_save', 'success', null, $actor->id);
            header('Location: index.php?route=/admin/roles/edit&name=' . rawurlencode($name), true, 303);
        } catch (\Throwable $exception) {
            $this->audit->record($request, 'role_save', 'failed', null, $actor->id);
            $this->renderRoleEdit($request, $exception->getMessage(), 'danger');
        }
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function permissionGroups(): array
    {
        if ($this->userAdministration === null) {
            return [];
        }

        $labels = [
            'admin' => 'Panel administracyjny',
            'pages' => 'Strony i strona główna',
            'articles' => 'Artykuły',
            'users' => 'Użytkownicy',
            'roles' => 'Role i uprawnienia',
            'modules' => 'Moduły',
            'settings' => 'Ustawienia',
        ];
        $groups = [];
        foreach ($this->userAdministration->permissions() as $name => $label) {
            if ($name === '*') {
                continue;
            }
            $namespace = explode('.', $name, 2)[0];
            $groupLabel = $labels[$namespace] ?? ucfirst(str_replace('_', ' ', $namespace));
            $groups[$groupLabel][$name] = $label;
        }

        return $groups;
    }

    private function isOwner(?User $user): bool
    {
        return $user !== null && (in_array('owner', $user->roles, true) || in_array('*', $user->permissions, true));
    }

    private function actualUserAllows(User $user, string $permission): bool
    {
        return $user->isActive()
            && (in_array('*', $user->permissions, true) || in_array($permission, $user->permissions, true));
    }

    /** @return array<string, string> */
    private function assignableRoles(?User $actor): array
    {
        $roles = $this->userAdministration?->roles() ?? [];
        if (!$this->isOwner($actor)) {
            unset($roles['owner']);
        }
        if ($actor === null || (!in_array('administrator', $actor->roles, true) && !$this->isOwner($actor))) {
            unset($roles['administrator'], $roles['maintainer']);
        }
        return $roles;
    }

    private function deleteRole(Request $request): void
    {
        $actor = $this->auth->user();
        if ($actor === null || $this->userAdministration === null) {
            return;
        }
        if (!$this->security->validateCsrfToken($request->postString('_token'))) {
            $this->audit->record($request, 'role_delete', 'invalid_csrf', null, $actor->id);
            $this->renderRoles('Nieprawidłowy lub wygasły token CSRF.', 'danger');
            return;
        }
        try {
            $this->userAdministration->deleteRole($request->postString('name'));
            $this->audit->record($request, 'role_delete', 'success', null, $actor->id);
            $this->renderRoles('Rola została usunięta.', 'success');
        } catch (\Throwable $exception) {
            $this->audit->record($request, 'role_delete', 'failed', null, $actor->id);
            $this->renderRoles($exception->getMessage(), 'danger');
        }
    }

    private function findUserRecord(int $id): ?UserAdminRecord
    {
        return $this->userAdministration?->find($id);
    }

    private function startProviderLogin(Request $request, string $name, string $purpose = 'login'): void
    {
        $this->rememberLoginRequestTarget($request);
        $provider = $this->providers->get($name);
        $user = $this->auth->user();

        if ($purpose === 'link' && $user === null) {
            http_response_code(401);
            $this->theme->render_admin_access_state(
                401,
                'Wymagane logowanie',
                'Połączenie dodatkowej tożsamości wymaga aktywnej sesji.',
                'index.php?route=/admin/login',
                'Przejdź do logowania'
            );
            return;
        }

        if (!$this->oauthLimiter->allowStart($name)) {
            $this->rejectRateLimit($request, $name, $user?->id);
            return;
        }

        if ($provider === null || !$provider->isConfigured()) {
            $this->audit->record($request, 'oauth_start', 'provider_unavailable', $name, $user?->id);
            http_response_code(503);
            $this->theme->render_admin_access_state(
                503,
                'Dostawca niedostępny',
                'Wybrany dostawca logowania nie został skonfigurowany.',
                'index.php?route=/admin/login',
                'Wróć do logowania'
            );
            return;
        }

        $remember = false;
        if ($purpose === 'login' && $request->method() === 'POST') {
            if (!$this->security->validateCsrfToken($request->postString('_token'))) {
                $this->audit->record($request, 'oauth_start', 'invalid_csrf', $name, $user?->id);
                http_response_code(403);
                $this->renderLogin(null, 'Token CSRF jest nieprawidłowy lub wygasł.', 'danger');
                return;
            }
            $remember = $request->postBool('remember');
        }
        $oauth = $this->oauthStates->issue($name, $purpose, $user?->id, $remember);
        $this->audit->record($request, 'oauth_start', $purpose, $name, $user?->id);
        header(
            'Location: ' . $provider->authorizationUrl($oauth['state'], $oauth['challenge'], $oauth['nonce']),
            true,
            302
        );
    }

    private function completeProviderLogin(Request $request, string $name): void
    {
        if (!$this->oauthLimiter->allowCallback($name)) {
            $this->rejectRateLimit($request, $name, $this->auth->user()?->id);
            return;
        }

        $provider = $this->providers->get($name);
        $state = $request->queryString('state');
        $context = $this->oauthStates->consume($name, $state);

        if ($provider === null || !$provider->isConfigured() || $context === null) {
            $this->audit->record($request, 'oauth_callback', 'invalid_state', $name);
            http_response_code(403);
            $this->renderLogin(null, 'Odpowiedź dostawcy ma nieprawidłowy lub wygasły parametr state.', 'danger');
            return;
        }

        if ($request->queryString('error') !== '') {
            $this->audit->record($request, 'oauth_callback', 'provider_denied', $name, $context->userId);
            http_response_code(401);
            $this->renderLogin(null, 'Logowanie zostało anulowane albo odrzucone przez dostawcę.', 'warning');
            return;
        }

        try {
            $identity = $provider->resolveIdentity(
                $request->queryString('code'),
                $context->verifier,
                $context->nonce
            );

            if ($context->purpose === 'link') {
                $this->completeIdentityLink($request, $identity, $context);
                return;
            }

            $user = $this->auth->loginIdentity($identity, $context->remember);
        } catch (Throwable $exception) {
            error_log(sprintf(
                'miniPORTAL OAuth callback failed: provider=%s exception=%s',
                $name,
                $exception::class
            ));
            $this->audit->record($request, 'oauth_callback', 'provider_error', $name, $context->userId);
            http_response_code(502);
            $this->renderLogin(null, 'Nie udało się potwierdzić tożsamości u dostawcy.', 'danger');
            return;
        }

        if ($user === null) {
            $this->audit->record($request, 'login', 'pending_or_inactive', $name);
            http_response_code(403);
            $this->renderLogin(
                null,
                'Tożsamość została potwierdzona. Konto oczekuje na akceptację administratora albo jest zablokowane.',
                'warning'
            );
            return;
        }

        $this->audit->record($request, 'login', $user->isActive() ? 'success' : 'pending_public_session', $name, $user->id);
        header('Location: ' . $this->redirectAfterAuthentication($user), true, 303);
    }

    private function login(Request $request): void
    {
        if (!$this->security->validateCsrfToken($request->postString('_token'))) {
            $this->audit->record($request, 'login_demo', 'invalid_csrf', 'demo');
            http_response_code(403);
            $this->renderLogin(null, 'Token CSRF jest nieprawidłowy lub wygasł.', 'danger');
            return;
        }

        if (!$this->demoEnabled || $request->postString('provider') !== 'demo') {
            $this->audit->record($request, 'login_demo', 'disabled', 'demo');
            http_response_code(401);
            $this->renderLogin(null, 'Logowanie demonstracyjne jest wyłączone.', 'danger');
            return;
        }

        $identity = new ExternalIdentity(
            $request->postString('provider'),
            $request->postString('subject'),
            $request->postString('subject')
        );
        $user = $this->auth->loginIdentity($identity);

        if ($user === null) {
            $this->audit->record($request, 'login_demo', 'identity_unlinked', 'demo');
            http_response_code(401);
            $this->renderLogin(null, 'Nie znaleziono aktywnego konta dla wybranej tożsamości.', 'danger');
            return;
        }

        $this->audit->record($request, 'login_demo', $user->isActive() ? 'success' : 'pending_public_session', 'demo', $user->id);
        header('Location: ' . $this->redirectAfterAuthentication($user), true, 303);
    }

    private function logout(Request $request): void
    {
        if (!$this->security->validateCsrfToken($request->postString('_token'))) {
            $this->audit->record($request, 'logout', 'invalid_csrf', null, $this->auth->user()?->id);
            http_response_code(403);
            $this->theme->render_admin_access_state(
                403,
                'Nieprawidłowy token CSRF',
                'Wylogowanie zostało odrzucone.',
                'index.php?route=/admin',
                'Wróć do panelu'
            );
            return;
        }

        $userId = $this->auth->user()?->id;
        $this->auth->logout();
        $this->audit->record($request, 'logout', 'success', null, $userId);
        header('Location: index.php?route=/admin/login', true, 303);
    }

    private function consumeAfterLoginRedirect(User $user): string
    {
        AuthPolicyRuntime::applyLoginRedirectPolicy();
        $redirect = $_SESSION['_miniportal_after_login'] ?? '';
        unset($_SESSION['_miniportal_after_login']);
        if (!is_string($redirect) || trim($redirect) === '') {
            return $this->menu->firstVisiblePath($user->permissions);
        }

        return $this->normalizeAfterLoginRedirect($redirect);
    }

    private function consumeRegistrationRedirect(User $user): string
    {
        AuthPolicyRuntime::applyLoginRedirectPolicy();
        $redirect = $_SESSION['_miniportal_after_login'] ?? '';
        unset($_SESSION['_miniportal_after_login']);
        if (!is_string($redirect) || trim($redirect) === '') {
            return $this->menu->firstVisiblePath($user->permissions);
        }

        return $this->normalizeAfterLoginRedirect($redirect);
    }

    private function redirectAfterAuthentication(User $user): string
    {
        if ($user->isActive()) {
            return $this->consumeAfterLoginRedirect($user);
        }
        if ($this->registration->isSelfService()) {
            return 'index.php?route=/account/registration';
        }

        $redirect = $this->consumeAfterLoginRedirect($user);
        return $this->isAdminRedirect($redirect)
            ? 'index.php?route=/admin/account-pending'
            : $redirect;
    }

    private function rememberLoginRequestTarget(Request $request): void
    {
        $explicit = $request->queryString('next');
        if ($explicit !== '') {
            $this->rememberAfterLoginRedirect($explicit);
            return;
        }
        $referer = parse_url($request->header('Referer'));
        if (!is_array($referer)
            || $request->host() === ''
            || strtolower((string) ($referer['host'] ?? '')) !== $request->host()
            || ($referer['scheme'] ?? '') !== ($request->isSecure() ? 'https' : 'http')
            || isset($referer['user']) || isset($referer['pass'])
        ) {
            return;
        }
        $path = (string) ($referer['path'] ?? '/');
        if ($path === '/index.php') {
            parse_str((string) ($referer['query'] ?? ''), $query);
            $route = $query['route'] ?? '/';
            if (!is_string($route) || AuthPolicyRuntime::normalizeLocalRedirect($route) === null) {
                return;
            }
        }
        $this->rememberAfterLoginRedirect(
            $path . (isset($referer['query']) ? '?' . $referer['query'] : '')
        );
    }

    private function rememberAfterLoginRedirect(string $redirect): void
    {
        $redirect = AuthPolicyRuntime::normalizeLocalRedirect($redirect);
        if ($redirect !== null) {
            $_SESSION['_miniportal_after_login'] = $redirect;
        }
    }

    private function normalizeAfterLoginRedirect(string $redirect): string
    {
        return AuthPolicyRuntime::normalizeLocalRedirect($redirect) ?? 'index.php?route=/admin';
    }

    private function isAdminRedirect(string $redirect): bool
    {
        if (str_starts_with($redirect, 'index.php?route=')) {
            $redirect = substr($redirect, strlen('index.php?route='));
        }

        return $redirect === '/admin'
            || str_starts_with($redirect, '/admin?')
            || str_starts_with($redirect, '/admin/');
    }

    private function completeIdentityLink(
        Request $request,
        ExternalIdentity $identity,
        OAuthStateContext $context,
    ): void {
        $user = $this->auth->user();

        if ($user === null || $context->userId !== $user->id) {
            $this->audit->record($request, 'identity_link', 'session_mismatch', $identity->provider);
            http_response_code(403);
            $this->renderLogin(null, 'Sesja łączenia tożsamości wygasła lub została zmieniona.', 'danger');
            return;
        }

        if (!$this->auth->linkIdentity($identity)) {
            $this->audit->record($request, 'identity_link', 'already_linked', $identity->provider, $user->id);
            $this->redirectToIdentities('already-linked');
            return;
        }

        $this->audit->record($request, 'identity_link', 'success', $identity->provider, $user->id);
        $this->redirectToIdentities('linked');
    }

    private function unlinkIdentity(Request $request): void
    {
        $user = $this->auth->user();

        if ($user === null) {
            http_response_code(401);
            $this->theme->render_admin_access_state(
                401,
                'Wymagane logowanie',
                'Odłączenie tożsamości wymaga aktywnej sesji.',
                'index.php?route=/admin/login',
                'Przejdź do logowania'
            );
            return;
        }

        if (!$this->security->validateCsrfToken($request->postString('_token'))) {
            $this->audit->record($request, 'identity_unlink', 'invalid_csrf', null, $user->id);
            http_response_code(403);
            $this->renderIdentities('Token CSRF jest nieprawidłowy lub wygasł.', 'danger');
            return;
        }

        $provider = $request->postString('provider');
        $identity = null;
        foreach ($user->identities as $candidate) {
            if ($candidate->provider === $provider) {
                $identity = $candidate;
                break;
            }
        }

        if ($identity === null || !$this->auth->unlinkIdentity($identity->provider, $identity->subject)) {
            $this->audit->record($request, 'identity_unlink', 'rejected', $provider, $user->id);
            $this->renderIdentities('Nie można odłączyć ostatniej albo nieistniejącej tożsamości.', 'warning');
            return;
        }

        $this->audit->record($request, 'identity_unlink', 'success', $provider, $user->id);
        $this->renderIdentities('Tożsamość została odłączona.', 'success');
    }

    private function renderIdentities(string $message = '', string $variant = 'info'): void
    {
        $user = $this->auth->user();
        if ($user === null || !$user->isActive()) {
            http_response_code($user === null ? 401 : 403);
            $this->theme->render_admin_access_state($user === null ? 401 : 403, 'Wymagane aktywne konto', 'Zarządzanie połączeniami wymaga zalogowania na aktywne konto.', 'index.php?route=/admin/login', 'Przejdź do logowania');
            return;
        }
        if (!headers_sent()) {
            header('Cache-Control: private, no-store');
            header('Cloudflare-CDN-Cache-Control: no-store');
            header('X-Robots-Tag: noindex, nofollow');
        }
        $this->startAdminPage('Połączone konta', '/admin/profile', 'Zarządzaj sposobami logowania i połączeniami z kontami w grze.');
        $this->theme->render_admin_panel_actions([['label' => 'Wróć do profilu', 'href' => 'index.php?route=/admin/profile']]);
        $preview = $this->auth->rolePreview() !== null;
        $notice = $preview ? null : LinkedAccountNoticeStore::consume($user->id);
        if ($notice !== null) $this->theme->render_alert($notice['message'], $notice['variant']);
        if ($message !== '') $this->theme->render_alert($message, $variant);
        $linked = array_map(static fn (ExternalIdentity $identity): string => $identity->provider, $user->identities);
        $this->theme->start_admin_panel('Logowanie do miniPORTAL', 'Dostawcy OAuth');
        $rows = [];
        foreach ($this->providers->all() as $provider) {
            $isLinked = in_array($provider->name(), $linked, true);
            $actions = [];
            if (!$preview && $isLinked && count($user->identities) > 1) {
                $actions[] = ['label' => 'Odłącz', 'action' => 'index.php?route=/admin/identity/unlink',
                    'fields' => ['provider' => $provider->name()], 'variant' => 'outline-danger',
                    'confirm' => 'Odłączyć tego dostawcę logowania od swojego konta?'];
            } elseif (!$preview && !$isLinked && $provider->isConfigured()) {
                $actions[] = ['label' => 'Połącz', 'href' => 'index.php?route=/admin/identity/' . rawurlencode($provider->name()) . '/link', 'variant' => 'primary'];
            }
            $rows[] = ['cells' => [$provider->label(), $isLinked ? 'Połączono' : ($provider->isConfigured() ? 'Dostępny' : 'Nieskonfigurowany'),
                $isLinked && count($user->identities) === 1 ? 'Jedyna tożsamość logowania — zachowaj dostęp do konta' : ''], 'actions' => $actions];
        }
        $this->theme->render_admin_action_table(['Dostawca', 'Status', 'Informacje'], $rows, $this->security->csrfToken());
        $this->theme->end_admin_panel();
        // Providers receive only the signed-in account. An ID in the URL never selects another user.
        $sections = $preview ? [] : ($this->hooks?->applyFilters('core_auth.linked_account_sections', [], $user) ?? []);
        foreach (is_array($sections) ? array_values(array_slice($sections, 0, 32)) : [] as $sectionIndex => $section) {
            if (!$section instanceof LinkedAccountSection) continue;
            $this->theme->start_admin_panel($section->title, $section->source);
            $this->theme->render_text($section->description);
            if ($section->facts !== []) $this->theme->render_admin_fact_grid($section->facts);
            $links = array_values(array_filter($section->links,
                static fn (array $link): bool => LinkedAccountSection::localRoute((string) ($link['href'] ?? ''))));
            if ($links !== []) $this->theme->render_admin_panel_actions($links);
            if (!$preview) {
                foreach ($section->forms as $form) {
                    if (LinkedAccountSection::localRoute((string) $form['action'])) {
                        $this->theme->render_form($form['action'], $form['fields'], $form['label'], $this->security->csrfToken());
                    }
                }
                $commands = array_values(array_filter($section->commands,
                    static fn (array $command): bool => LinkedAccountSection::localRoute((string) ($command['action'] ?? ''))));
                foreach ($commands as $commandIndex => $command) {
                    $fields = [];
                    foreach ($command['fields'] ?? [] as $name => $value) $fields[] = ['name' => $name, 'type' => 'hidden', 'value' => (string) $value];
                    $this->theme->render_admin_form_modal('linked-account-' . $sectionIndex . '-' . $commandIndex,
                        (string) ($command['confirm'] ?? $command['label']), $command['action'], $fields,
                        $command['label'], $this->security->csrfToken(), $command['label'], $command['variant'] ?? 'outline-danger');
                }
            }
            $this->theme->end_admin_panel();
        }
        $this->endAdminPage();
    }

    private function renderIdentitiesNotice(Request $request): void
    {
        $notice = $request->queryString('notice');

        if ($notice === 'linked') {
            $this->renderIdentities('Nowa tożsamość została połączona z kontem.', 'success');
            return;
        }

        if ($notice === 'already-linked') {
            $this->renderIdentities('Ta tożsamość jest już połączona z kontem.', 'warning');
            return;
        }

        $this->renderIdentities();
    }

    private function redirectToIdentities(string $notice): void
    {
        header(
            'Location: index.php?route=/admin/identities&notice=' . rawurlencode($notice),
            true,
            303
        );
    }

    private function rejectRateLimit(Request $request, string $provider, ?int $userId): void
    {
        $this->audit->record($request, 'oauth_rate_limit', 'rejected', $provider, $userId);
        header('Retry-After: ' . $this->oauthLimiter->retryAfter());
        http_response_code(429);
        $this->theme->render_admin_access_state(
            429,
            'Zbyt wiele prób',
            'Limit prób uwierzytelniania został przekroczony. Spróbuj ponownie później.',
            'index.php?route=/admin/login',
            'Wróć do logowania'
        );
    }

    private function startAdminPage(string $title, string $activePath, string $lead): void
    {
        $user = $this->auth->user();
        if ($user === null) {
            return;
        }

        $this->theme->start_admin_page(
            $title,
            $this->menu->visibleFor($user->permissions),
            $activePath,
            [
                'name' => $user->displayName,
                'role' => ucfirst($user->primaryRole()),
                'initials' => $user->initials(),
                'avatar_url' => $user->avatarUrl ?? '',
                'logout_action' => 'index.php?route=/admin/logout',
                'logout_token' => $this->security->csrfToken(),
            ]
        );
        $this->theme->start_admin_content(
            $title,
            $lead,
            [
                ['label' => 'Panel', 'href' => 'index.php?route=/admin'],
                ['label' => $title, 'href' => ''],
            ]
        );
    }

    private function endAdminPage(): void
    {
        $this->theme->end_admin_content();
        $this->theme->end_admin_page();
    }

    private function guard(Request $request, string $permission, callable $handler): void
    {
        if (!headers_sent()) {
            header('Cache-Control: private, no-store');
            header('Cloudflare-CDN-Cache-Control: no-store');
            header('X-Robots-Tag: noindex, nofollow');
        }
        if ($this->auth->rolePreviewBlocks($request)) {
            http_response_code(403);
            $this->audit->record($request, 'role_preview_write', 'forbidden', null, $this->auth->actualUser()?->id);
            $this->theme->render_admin_access_state(403, 'Podgląd tylko do odczytu', 'Zakończ podgląd roli przed zmianą konta.', 'index.php?route=/admin/users', 'Wróć do użytkowników');
            return;
        }
        $decision = $this->access->check($permission);
        if ($decision === AdminAccessGate::ALLOWED) {
            $handler();
            return;
        }

        $status = $decision === AdminAccessGate::UNAUTHENTICATED ? 401 : 403;
        $this->audit->record(
            $request,
            'users_access',
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
            $status === 401 ? 'index.php?route=/admin/login&next=' . rawurlencode($request->path()) : 'index.php?route=/admin',
            $status === 401 ? 'Przejdź do logowania' : 'Wróć do dashboardu'
        );
    }
}
