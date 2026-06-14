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
        private readonly bool $demoEnabled = false,
    ) {
    }

    public function id(): string
    {
        return 'core_auth';
    }

    public function version(): string
    {
        return '1.0.0';
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
        $router->get('/admin/identities', fn (Request $request) => $this->renderIdentitiesNotice($request));
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
            $this->audit->record($request, 'login', 'identity_unlinked', $name);
            http_response_code(403);
            $this->renderLogin(
                'Tożsamość została potwierdzona, ale nie jest jeszcze połączona z aktywnym kontem miniPORTAL.',
                'warning'
            );
            return;
        }

        $this->audit->record($request, 'login', 'success', $name, $user->id);
        header('Location: index.php?route=/admin', true, 303);
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

        $this->audit->record($request, 'login_demo', 'success', 'demo', $user->id);
        header('Location: index.php?route=/admin', true, 303);
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
}
