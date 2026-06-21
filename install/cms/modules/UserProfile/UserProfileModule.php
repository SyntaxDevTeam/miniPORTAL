<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\UserProfile;

use SyntaxDevTeam\Cms\Core\AdminMenuRegistry;
use SyntaxDevTeam\Cms\Core\ModuleInterface;
use SyntaxDevTeam\Cms\Core\Request;
use SyntaxDevTeam\Cms\Core\Router;
use SyntaxDevTeam\Cms\Core\Security;
use SyntaxDevTeam\Cms\Core\ThemeInterface;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AdminAccessGate;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuditLogService;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuthService;
use SyntaxDevTeam\Cms\Modules\CoreAuth\ExternalIdentity;

final class UserProfileModule implements ModuleInterface
{
    public function __construct(
        private readonly ThemeInterface $theme,
        private readonly AdminMenuRegistry $menu,
        private readonly AuthService $auth,
        private readonly AdminAccessGate $access,
        private readonly Security $security,
        private readonly AuditLogService $audit,
    ) {
    }

    public function id(): string
    {
        return 'user_profile';
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
        return false;
    }

    public function requiredPermissions(): array
    {
        return [];
    }

    public function registerAdminMenu(AdminMenuRegistry $menu): void
    {
    }

    public function registerRoutes(Router $router): void
    {
        $router->get('/admin/profile', fn (Request $request) => $this->guard($request, fn () => $this->renderProfile()));
        $router->get('/admin/profile/edit', fn (Request $request) => $this->guard($request, fn () => $this->renderProfileEdit()));
        $router->post('/admin/profile/edit', fn (Request $request) => $this->guard($request, fn () => $this->updateProfile($request)));
        $router->get('/admin/profile/avatar', fn (Request $request) => $this->guard($request, fn () => $this->renderAvatarSettings()));
        $router->post('/admin/profile/avatar', fn (Request $request) => $this->guard($request, fn () => $this->updateAvatar($request)));
        $router->get('/admin/profile/security', fn (Request $request) => $this->guard($request, fn () => $this->renderProfileSecurity()));
    }

    private function renderProfile(): void
    {
        $user = $this->auth->user();
        if ($user === null) {
            return;
        }

        $this->startAdminPage('Profil użytkownika', 'Podstawowe dane konta, role i połączone tożsamości logowania.');
        $providers = array_map(
            static fn (ExternalIdentity $identity): string => ucfirst($identity->provider),
            $user->identities
        );

        $this->theme->start_admin_panel_grid('compact');
        $this->theme->start_admin_panel('Dane konta', 'Widoczne w panelu administracyjnym');
        $this->theme->render_admin_table(['Pole', 'Wartość'], [
            ['Nazwa wyświetlana', $user->displayName],
            ['E-mail kontaktowy', $user->email ?? 'Brak'],
            ['Status', match ($user->status) {
                'active' => 'Aktywny',
                'blocked' => 'Zablokowany',
                default => 'Oczekujący',
            }],
            ['Role', $user->roles !== [] ? implode(', ', array_map('ucfirst', $user->roles)) : 'Brak'],
        ]);
        $this->theme->render_admin_panel_actions([
            ['label' => 'Edytuj dane', 'href' => 'index.php?route=/admin/profile/edit', 'variant' => 'primary'],
            ['label' => 'Ustawienia avatara', 'href' => 'index.php?route=/admin/profile/avatar'],
        ]);
        $this->theme->end_admin_panel();

        $this->theme->start_admin_panel('Bezpieczeństwo', 'Sesja, uprawnienia i tożsamości');
        $this->theme->render_admin_fact_grid([
            ['label' => 'Połączone konta', 'value' => (string) count($user->identities), 'detail' => 'Dostawcy OAuth'],
            ['label' => 'Dostawcy', 'value' => $providers !== [] ? implode(', ', $providers) : 'Brak', 'detail' => 'Aktywne tożsamości'],
            [
                'label' => 'Uprawnienia',
                'value' => in_array('*', $user->permissions, true) ? 'Pełny dostęp' : (string) count($user->permissions),
                'detail' => 'Wynik lokalnych ról',
            ],
        ]);
        $this->theme->render_admin_panel_actions([
            ['label' => 'Połączone konta', 'href' => 'index.php?route=/admin/identities', 'variant' => 'primary'],
            ['label' => 'Bezpieczeństwo', 'href' => 'index.php?route=/admin/profile/security'],
        ]);
        $this->theme->end_admin_panel();
        $this->theme->end_admin_panel_grid();
        $this->endAdminPage();
    }

    private function renderProfileEdit(string $message = '', string $variant = 'info'): void
    {
        $user = $this->auth->user();
        if ($user === null) {
            return;
        }

        $this->startAdminPage('Edytuj dane', 'Aktualizacja podstawowych danych widocznych w panelu.');
        if ($message !== '') {
            $this->theme->render_alert($message, $variant);
        }
        $this->theme->start_admin_panel('Dane profilu', 'Nazwa i kontakt administracyjny');
        $this->theme->render_form('index.php?route=/admin/profile/edit', [
            ['name' => 'display_name', 'label' => 'Nazwa wyświetlana', 'value' => $user->displayName],
            ['name' => 'email', 'label' => 'E-mail kontaktowy', 'type' => 'email', 'value' => $user->email ?? ''],
        ], 'Zapisz dane', $this->security->csrfToken());
        $this->renderBackAction();
        $this->theme->end_admin_panel();
        $this->endAdminPage();
    }

    private function updateProfile(Request $request): void
    {
        $user = $this->auth->user();
        if ($user === null) {
            return;
        }
        if (!$this->security->validateCsrfToken($request->postString('_token'))) {
            $this->audit->record($request, 'profile_update', 'invalid_csrf', null, $user->id);
            $this->renderProfileEdit('Token CSRF jest nieprawidłowy lub wygasł.', 'danger');
            return;
        }

        $displayName = trim($request->postString('display_name'));
        $email = trim($request->postString('email'));
        if ($displayName === '' || strlen($displayName) > 120) {
            $this->renderProfileEdit('Nazwa wyświetlana jest wymagana i może mieć maksymalnie 120 znaków.', 'warning');
            return;
        }
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->renderProfileEdit('Podaj poprawny adres e-mail albo zostaw pole puste.', 'warning');
            return;
        }
        if (!$this->auth->updateProfile($displayName, $email !== '' ? $email : null)) {
            $this->audit->record($request, 'profile_update', 'failed', null, $user->id);
            $this->renderProfileEdit('Nie udało się zapisać danych profilu.', 'danger');
            return;
        }

        $this->audit->record($request, 'profile_update', 'success', null, $user->id);
        $this->renderProfileEdit('Dane profilu zostały zapisane.', 'success');
    }

    private function renderAvatarSettings(string $message = '', string $variant = 'info'): void
    {
        $user = $this->auth->user();
        if ($user === null) {
            return;
        }

        $this->startAdminPage('Ustawienia avatara', 'Avatar jest przechowywany jako bezpieczny adres HTTPS do obrazu.');
        if ($message !== '') {
            $this->theme->render_alert($message, $variant);
        }
        $this->theme->start_admin_panel('Avatar', 'Adres obrazu profilu');
        $this->theme->render_form('index.php?route=/admin/profile/avatar', [[
            'name' => 'avatar_url',
            'label' => 'Adres avatara',
            'type' => 'url',
            'value' => $user->avatarUrl ?? '',
            'help' => 'Dozwolony jest wyłącznie adres http:// albo https://. Puste pole usuwa avatar.',
        ]], 'Zapisz avatar', $this->security->csrfToken());
        $this->renderBackAction();
        $this->theme->end_admin_panel();
        $this->endAdminPage();
    }

    private function updateAvatar(Request $request): void
    {
        $user = $this->auth->user();
        if ($user === null) {
            return;
        }
        if (!$this->security->validateCsrfToken($request->postString('_token'))) {
            $this->audit->record($request, 'avatar_update', 'invalid_csrf', null, $user->id);
            $this->renderAvatarSettings('Token CSRF jest nieprawidłowy lub wygasł.', 'danger');
            return;
        }

        $avatarUrl = trim($request->postString('avatar_url'));
        if ($avatarUrl !== '' && filter_var($avatarUrl, FILTER_VALIDATE_URL) === false) {
            $this->renderAvatarSettings('Podaj poprawny adres URL albo zostaw pole puste.', 'warning');
            return;
        }
        if ($avatarUrl !== '' && preg_match('~^https?://~i', $avatarUrl) !== 1) {
            $this->renderAvatarSettings('Avatar musi używać adresu http:// albo https://.', 'warning');
            return;
        }
        if (!$this->auth->updateAvatar($avatarUrl !== '' ? $avatarUrl : null)) {
            $this->audit->record($request, 'avatar_update', 'failed', null, $user->id);
            $this->renderAvatarSettings('Nie udało się zapisać avatara.', 'danger');
            return;
        }

        $this->audit->record($request, 'avatar_update', 'success', null, $user->id);
        $this->renderAvatarSettings('Avatar został zapisany.', 'success');
    }

    private function renderProfileSecurity(): void
    {
        $user = $this->auth->user();
        if ($user === null) {
            return;
        }
        $providers = array_map(
            static fn (ExternalIdentity $identity): string => ucfirst($identity->provider),
            $user->identities
        );

        $this->startAdminPage('Bezpieczeństwo', 'Przegląd dostępu, połączonych kont i lokalnych uprawnień.');
        $this->theme->start_admin_panel('Stan bezpieczeństwa', 'Sesja, uprawnienia i tożsamości');
        $this->theme->render_admin_fact_grid([
            ['label' => 'Status konta', 'value' => ucfirst($user->status), 'detail' => 'Lokalny stan użytkownika'],
            ['label' => 'Role', 'value' => $user->roles !== [] ? implode(', ', $user->roles) : 'Brak', 'detail' => 'Role lokalne'],
            ['label' => 'Tożsamości', 'value' => $providers !== [] ? implode(', ', $providers) : 'Brak', 'detail' => 'Zewnętrzne logowanie'],
            [
                'label' => 'Uprawnienia',
                'value' => in_array('*', $user->permissions, true) ? 'Pełny dostęp' : (string) count($user->permissions),
                'detail' => 'Nadane przez role',
            ],
        ]);
        $this->theme->render_admin_panel_actions([
            ['label' => 'Zarządzaj połączonymi kontami', 'href' => 'index.php?route=/admin/identities', 'variant' => 'primary'],
            ['label' => 'Wróć do profilu', 'href' => 'index.php?route=/admin/profile'],
        ]);
        $this->theme->end_admin_panel();
        $this->endAdminPage();
    }

    private function startAdminPage(string $title, string $lead): void
    {
        $user = $this->auth->user();
        if ($user === null) {
            return;
        }
        $this->theme->start_admin_page($title, $this->menu->visibleFor($user->permissions), '/admin/profile', [
            'name' => $user->displayName,
            'role' => ucfirst($user->primaryRole()),
            'initials' => $user->initials(),
            'avatar_url' => $user->avatarUrl ?? '',
            'logout_action' => 'index.php?route=/admin/logout',
            'logout_token' => $this->security->csrfToken(),
        ]);
        $this->theme->start_admin_content($title, $lead, [
            ['label' => 'Panel', 'href' => 'index.php?route=/admin'],
            ['label' => 'Profil użytkownika', 'href' => $title === 'Profil użytkownika' ? '' : 'index.php?route=/admin/profile'],
            ...($title === 'Profil użytkownika' ? [] : [['label' => $title, 'href' => '']]),
        ]);
    }

    private function endAdminPage(): void
    {
        $this->theme->end_admin_content();
        $this->theme->end_admin_page();
    }

    private function renderBackAction(): void
    {
        $this->theme->render_admin_panel_actions([
            ['label' => 'Wróć do profilu', 'href' => 'index.php?route=/admin/profile'],
        ]);
    }

    private function guard(Request $request, callable $handler): void
    {
        $decision = $this->access->check('admin.access');
        if ($decision === AdminAccessGate::ALLOWED) {
            $handler();
            return;
        }

        $status = $decision === AdminAccessGate::UNAUTHENTICATED ? 401 : 403;
        $this->audit->record(
            $request,
            'profile_access',
            $status === 401 ? 'unauthenticated' : 'forbidden',
            null,
            $this->auth->user()?->id
        );
        http_response_code($status);
        $this->theme->render_admin_access_state(
            $status,
            $status === 401 ? 'Wymagane logowanie' : 'Brak uprawnienia',
            $status === 401 ? 'Ta trasa wymaga aktywnej sesji.' : 'Twoje konto nie posiada dostępu do panelu.',
            $status === 401 ? 'index.php?route=/admin/login' : 'index.php?route=/admin',
            $status === 401 ? 'Przejdź do logowania' : 'Wróć do dashboardu'
        );
    }
}
