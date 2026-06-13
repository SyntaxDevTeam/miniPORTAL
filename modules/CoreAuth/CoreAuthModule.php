<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\CoreAuth;

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
        private readonly bool $demoEnabled = false,
    ) {
    }

    public function id(): string
    {
        return 'core_auth';
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
        $router->get('/admin/login', fn () => $this->renderLogin());
        $router->post('/admin/login', fn (Request $request) => $this->login($request));
        $router->post('/admin/logout', fn (Request $request) => $this->logout($request));
    }

    private function renderLogin(string $message = '', string $variant = 'info'): void
    {
        if ($this->auth->user() !== null) {
            header('Location: index.php?route=/admin', true, 303);
            return;
        }

        $this->theme->render_admin_login(
            'index.php?route=/admin/login',
            $this->demoEnabled ? [
                ['provider' => 'demo', 'subject' => 'administrator', 'label' => 'Administrator demo', 'description' => 'Pełne uprawnienia panelu'],
                ['provider' => 'demo', 'subject' => 'editor', 'label' => 'Redaktor demo', 'description' => 'Treść bez zarządzania użytkownikami'],
            ] : [],
            $this->security->csrfToken(),
            $message !== '' ? $message : ($this->demoEnabled
                ? ''
                : 'Logowanie demonstracyjne jest wyłączone. Adaptery OAuth nie są jeszcze skonfigurowane.'),
            $message !== '' ? $variant : 'warning'
        );
    }

    private function login(Request $request): void
    {
        if (!$this->security->validateCsrfToken($request->postString('_token'))) {
            http_response_code(403);
            $this->renderLogin('Token CSRF jest nieprawidłowy lub wygasł.', 'danger');
            return;
        }

        if (!$this->demoEnabled || $request->postString('provider') !== 'demo') {
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
            http_response_code(401);
            $this->renderLogin('Nie znaleziono aktywnego konta dla wybranej tożsamości.', 'danger');
            return;
        }

        header('Location: index.php?route=/admin', true, 303);
    }

    private function logout(Request $request): void
    {
        if (!$this->security->validateCsrfToken($request->postString('_token'))) {
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

        $this->auth->logout();
        header('Location: index.php?route=/admin/login', true, 303);
    }
}
