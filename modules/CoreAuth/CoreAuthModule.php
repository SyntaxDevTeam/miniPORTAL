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

        foreach ($this->providers->all() as $provider) {
            $name = $provider->name();
            $router->get(
                "/admin/auth/{$name}",
                fn () => $this->startProviderLogin($name)
            );
            $router->get(
                "/admin/auth/{$name}/callback",
                fn (Request $request) => $this->completeProviderLogin($request, $name)
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

    private function startProviderLogin(string $name): void
    {
        $provider = $this->providers->get($name);

        if ($provider === null || !$provider->isConfigured()) {
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

        $oauth = $this->oauthStates->issue($name);
        header('Location: ' . $provider->authorizationUrl($oauth['state'], $oauth['challenge']), true, 302);
    }

    private function completeProviderLogin(Request $request, string $name): void
    {
        $provider = $this->providers->get($name);
        $state = $request->queryString('state');
        $verifier = $this->oauthStates->consume($name, $state);

        if ($provider === null || !$provider->isConfigured() || $verifier === null) {
            http_response_code(403);
            $this->renderLogin('Odpowiedź dostawcy ma nieprawidłowy lub wygasły parametr state.', 'danger');
            return;
        }

        if ($request->queryString('error') !== '') {
            http_response_code(401);
            $this->renderLogin('Logowanie zostało anulowane albo odrzucone przez dostawcę.', 'warning');
            return;
        }

        try {
            $identity = $provider->resolveIdentity($request->queryString('code'), $verifier);
            $user = $this->auth->loginIdentity($identity);
        } catch (Throwable) {
            http_response_code(502);
            $this->renderLogin('Nie udało się potwierdzić tożsamości u dostawcy.', 'danger');
            return;
        }

        if ($user === null) {
            http_response_code(403);
            $this->renderLogin(
                'Tożsamość została potwierdzona, ale nie jest jeszcze połączona z aktywnym kontem miniPORTAL.',
                'warning'
            );
            return;
        }

        header('Location: index.php?route=/admin', true, 303);
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
