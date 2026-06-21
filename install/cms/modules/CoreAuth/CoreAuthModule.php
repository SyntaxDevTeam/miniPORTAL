<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\CoreAuth;

use Throwable;
use SyntaxDevTeam\Cms\Core\AdminMenuRegistry;
use SyntaxDevTeam\Cms\Core\ModuleInterface;
use SyntaxDevTeam\Cms\Core\Request;
use SyntaxDevTeam\Cms\Core\Router;
use SyntaxDevTeam\Cms\Core\Security;
use SyntaxDevTeam\Cms\Core\ThemeInterface;

final class CoreAuthModule implements ModuleInterface
{
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
        private readonly bool $demoEnabled = false,
    ) {
    }

    public function id(): string
    {
        return 'core_auth';
    }

    public function version(): string
    {
        return '1.5.0';
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
        $menu->add('System', 'Użytkownicy', '/admin/users', 'US', 'users.view', 40);
        $menu->add('System', 'Role i uprawnienia', '/admin/roles', 'RL', 'roles.view', 45);
    }

    public function registerRoutes(Router $router): void
    {
        $router->get('/admin/login', fn () => $this->renderLogin());
        $router->post('/admin/login', fn (Request $request) => $this->login($request));
        $router->post('/admin/logout', fn (Request $request) => $this->logout($request));
        $router->get('/admin/users', fn (Request $request) => $this->guard(
            $request,
            'users.view',
            fn () => $this->renderUsers()
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
        $router->get('/admin/identities', fn (Request $request) => $this->renderIdentitiesNotice($request));
        $router->get('/admin/profile/identities', fn (Request $request) => $this->renderIdentitiesNotice($request));
        $router->post('/admin/identity/unlink', fn (Request $request) => $this->unlinkIdentity($request));

        foreach ($this->providers->all() as $provider) {
            $name = $provider->name();
            $router->get(
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

    private function renderLogin(string $message = '', string $variant = 'info'): void
    {
        if ($this->auth->user() !== null) {
            header('Location: index.php?route=/admin', true, 303);
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

    private function renderUsers(string $message = '', string $variant = 'info'): void
    {
        $user = $this->auth->user();
        if ($user === null) {
            return;
        }

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
        if ($canManage) {
            $this->theme->start_admin_panel('Dodaj użytkownika', 'Konto lokalne do późniejszego połączenia z OAuth');
            $this->theme->render_form(
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
                            '' => 'Bez tożsamości - konto administracyjne',
                            'github' => 'GitHub',
                            'discord' => 'Discord',
                            'google' => 'Google',
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
                        'type' => 'multiselect',
                        'values' => ['user'],
                        'options' => $assignableRoles,
                        'help' => 'Możesz zaznaczyć kilka pozycji klawiszem Ctrl lub Cmd.',
                    ],
                ],
                'Dodaj użytkownika',
                $this->security->csrfToken()
            );
            $this->theme->end_admin_panel();
        }
        $records = $this->userAdministration->all();
        $this->theme->start_admin_panel('Konta użytkowników', count($records) . ' rekordów');
        $this->theme->render_admin_action_table(
            ['Użytkownik', 'Status', 'Role', 'Tożsamości', 'Ostatnie logowanie'],
            array_map(
                fn (UserAdminRecord $record): array => [
                    'cells' => [
                        $record->displayName . ($record->email !== null ? ' (' . $record->email . ')' : ''),
                        match ($record->status) {
                            'active' => 'Aktywny',
                            'blocked' => 'Zablokowany',
                            default => 'Oczekujący',
                        },
                        $record->roles !== [] ? implode(', ', $record->roles) : 'Brak',
                        $record->providers !== [] ? implode(', ', $record->providers) : 'Brak',
                        $record->lastLoginAt ?? 'Nigdy',
                    ],
                    'actions' => $canManage
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
                    ])) : [],
                ],
                $records
            ),
            $this->security->csrfToken()
        );
        $this->theme->end_admin_panel();
        $this->endAdminPage();
    }

    private function renderUserEdit(
        Request $request,
        string $message = '',
        string $variant = 'info',
    ): void {
        $id = $request->queryInt('id') ?? $request->postInt('id') ?? 0;
        $record = null;
        foreach ($this->userAdministration?->all() ?? [] as $candidate) {
            if ($candidate->id === $id) {
                $record = $candidate;
                break;
            }
        }
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
                    'type' => 'multiselect',
                    'values' => $record->roles,
                    'options' => $this->assignableRoles($actor),
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
                    'actions' => $canManage && !$role->system ? array_values(array_filter([
                        [
                            'label' => 'Edytuj',
                            'href' => 'index.php?route=/admin/roles/edit&name=' . rawurlencode($role->name),
                            'variant' => 'outline-light',
                        ],
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
        foreach ($this->userAdministration?->all() ?? [] as $record) {
            if ($record->id === $id) {
                return $record;
            }
        }

        return null;
    }

    private function startProviderLogin(Request $request, string $name, string $purpose = 'login'): void
    {
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

        $oauth = $this->oauthStates->issue($name, $purpose, $user?->id);
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
            $this->renderLogin('Odpowiedź dostawcy ma nieprawidłowy lub wygasły parametr state.', 'danger');
            return;
        }

        if ($request->queryString('error') !== '') {
            $this->audit->record($request, 'oauth_callback', 'provider_denied', $name, $context->userId);
            http_response_code(401);
            $this->renderLogin('Logowanie zostało anulowane albo odrzucone przez dostawcę.', 'warning');
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

            $user = $this->auth->loginIdentity($identity);
        } catch (Throwable $exception) {
            error_log(sprintf(
                'miniPORTAL OAuth callback failed: provider=%s exception=%s',
                $name,
                $exception::class
            ));
            $this->audit->record($request, 'oauth_callback', 'provider_error', $name, $context->userId);
            http_response_code(502);
            $this->renderLogin('Nie udało się potwierdzić tożsamości u dostawcy.', 'danger');
            return;
        }

        if ($user === null) {
            $this->audit->record($request, 'login', 'pending_or_inactive', $name);
            http_response_code(403);
            $this->renderLogin(
                'Tożsamość została potwierdzona. Konto oczekuje na akceptację administratora albo jest zablokowane.',
                'warning'
            );
            return;
        }

        $this->audit->record($request, 'login', $user->isActive() ? 'success' : 'pending_public_session', $name, $user->id);
        $redirect = $this->consumeAfterLoginRedirect();
        header('Location: ' . $redirect, true, 303);
    }

    private function login(Request $request): void
    {
        if (!$this->security->validateCsrfToken($request->postString('_token'))) {
            $this->audit->record($request, 'login_demo', 'invalid_csrf', 'demo');
            http_response_code(403);
            $this->renderLogin('Token CSRF jest nieprawidłowy lub wygasł.', 'danger');
            return;
        }

        if (!$this->demoEnabled || $request->postString('provider') !== 'demo') {
            $this->audit->record($request, 'login_demo', 'disabled', 'demo');
            http_response_code(401);
            $this->renderLogin('Logowanie demonstracyjne jest wyłączone.', 'danger');
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
            $this->renderLogin('Nie znaleziono aktywnego konta dla wybranej tożsamości.', 'danger');
            return;
        }

        $this->audit->record($request, 'login_demo', $user->isActive() ? 'success' : 'pending_public_session', 'demo', $user->id);
        header('Location: ' . $this->consumeAfterLoginRedirect(), true, 303);
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

    private function consumeAfterLoginRedirect(): string
    {
        $redirect = $_SESSION['_miniportal_after_login'] ?? '';
        unset($_SESSION['_miniportal_after_login']);
        if (!is_string($redirect) || $redirect === '') {
            return 'index.php?route=/admin';
        }
        if (!str_starts_with($redirect, 'index.php?route=/') && !str_starts_with($redirect, '/')) {
            return 'index.php?route=/admin';
        }

        return $redirect;
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
            $this->renderLogin('Sesja łączenia tożsamości wygasła lub została zmieniona.', 'danger');
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

        if ($user === null) {
            http_response_code(401);
            $this->theme->render_admin_access_state(
                401,
                'Wymagane logowanie',
                'Zarządzanie tożsamościami wymaga aktywnej sesji.',
                'index.php?route=/admin/login',
                'Przejdź do logowania'
            );
            return;
        }

        $linkedProviders = array_map(
            static fn (ExternalIdentity $identity): string => $identity->provider,
            $user->identities
        );
        $providers = array_map(
            static fn (IdentityProviderInterface $provider): array => [
                'name' => $provider->name(),
                'label' => $provider->label(),
                'configured' => $provider->isConfigured(),
                'linked' => in_array($provider->name(), $linkedProviders, true),
            ],
            $this->providers->all()
        );

        $this->theme->render_admin_identities(
            ['name' => $user->displayName, 'role' => ucfirst($user->primaryRole())],
            $providers,
            'index.php?route=/admin/identity/unlink',
            $this->security->csrfToken(),
            $message,
            $variant
        );
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
            $status === 401 ? 'index.php?route=/admin/login' : 'index.php?route=/admin',
            $status === 401 ? 'Przejdź do logowania' : 'Wróć do dashboardu'
        );
    }
}
