<?php

declare(strict_types=1);
namespace SyntaxDevTeam\Cms\Modules\MinecraftAccounts;

use SyntaxDevTeam\Cms\Core\{AdminMenuRegistry,HookProviderInterface,HookRegistry,ModuleInterface,Request,Router,Security,ThemeInterface};
use SyntaxDevTeam\Cms\Modules\CoreAuth\{AuthService,AuditLogService,LinkedAccountNoticeStore,LinkedAccountSection,OAuthStateStore,OAuthAttemptLimiter,User};

final class MinecraftAccountsModule implements ModuleInterface, HookProviderInterface
{
    private ?HookRegistry $hooks = null;

    public function __construct(private readonly ThemeInterface $theme, private readonly AuthService $auth,
        private readonly Security $security, private readonly AuditLogService $audit,
        private readonly LinkedMinecraftProfileRepository $profiles, private readonly MinecraftProfileClientInterface $client,
        private readonly OAuthStateStore $states, private readonly OAuthAttemptLimiter $limiter) {}

    public function id(): string { return 'minecraft_accounts'; }
    public function version(): string { return '1.0.0'; }
    public function dependencies(): array { return ['core_auth']; }
    public function isProtected(): bool { return false; }
    public function requiredPermissions(): array { return []; }
    public function registerAdminMenu(AdminMenuRegistry $menu): void {}

    public function registerHooks(HookRegistry $hooks): void
    {
        $this->hooks = $hooks;
        $hooks->addFilter('core_auth.account_relations', function (array $relations, int $userId, User $actor): array {
            if (!$actor->isActive() || (!in_array('*', $actor->permissions, true) && !in_array('users.view', $actor->permissions, true))) return $relations;
            $profile = $this->profiles->findByUserId($userId);
            if ($profile !== null) $relations[] = ['label' => 'Minecraft Java', 'value' => $profile->username . ' • UUID ' . $profile->uuid,
                'detail' => 'Potwierdzono (UTC): ' . $profile->verifiedAt];
            return $relations;
        });
        $hooks->addFilter('core_auth.linked_account_sections', function (array $sections, User $user): array {
            if (!$user->isActive() || $this->auth->rolePreview() !== null) return $sections;
            $profile = $this->profiles->findByUserId($user->id);
            $facts = [['label' => 'Połączenie', 'value' => $profile !== null ? ($profile->verificationMethod === 'gateway_online_mode' ? 'Potwierdzone logowanie Minecraft Premium (historia)' : 'Zweryfikowano własność Minecraft Java') : 'Niepołączone']];
            if ($profile !== null) {
                $facts[] = ['label' => 'Nick Minecraft', 'value' => $profile->username];
                $facts[] = ['label' => 'UUID', 'value' => $profile->uuid];
                $facts[] = ['label' => 'Zweryfikowano (UTC)', 'value' => $profile->verifiedAt];
            }
            $sections[] = new LinkedAccountSection('Microsoft / Xbox', 'Minecraft Java Edition',
                'Połącz swoje konto Microsoft, aby potwierdzić własność gry i uzyskać oficjalny nick oraz UUID. Powiązania z konkretnymi serwerami pozostają osobne.'
                . (!$this->client->isConfigured() ? ' Administrator nie skonfigurował jeszcze połączenia Microsoft.' : ''), $facts,
                forms: $this->client->isConfigured() ? [['action' => 'index.php?route=/account/minecraft/connect', 'fields' => [],
                    'label' => $profile !== null ? 'Zweryfikuj ponownie' : 'Połącz przez Microsoft']] : [],
                commands: $profile !== null ? [['label' => 'Odłącz Minecraft Java', 'action' => 'index.php?route=/account/minecraft/disconnect',
                    'fields' => ['uuid' => $profile->uuid], 'variant' => 'outline-danger',
                    'confirm' => 'Odłączyć profil Minecraft Java? Usługi wymagające tej weryfikacji mogą przestać działać. Połączenia z serwerami pozostaną osobne.']] : []);
            return $sections;
        });
    }

    public function registerRoutes(Router $router): void
    {
        $router->get('/account/minecraft', fn (Request $r) => $this->guard($r, fn (User $u) => $this->notice($u, '')));
        $router->post('/account/minecraft/connect', fn (Request $r) => $this->guard($r, fn (User $u) => $this->connect($r, $u)));
        // Existing bookmarks and Microsoft callback registrations remain valid.
        $router->get('/account/minecraft/connect', fn (Request $r) => $this->guard($r, fn (User $u) => $this->connect($r, $u)));
        $router->get('/account/minecraft/connect/callback', fn (Request $r) => $this->guard($r, fn (User $u) => $this->callback($r, $u)));
        $router->post('/account/minecraft/disconnect', fn (Request $r) => $this->guard($r, fn (User $u) => $this->disconnect($r, $u)));
    }

    private function connect(Request $request, User $user): void
    {
        if ($request->method() === 'POST' && !$this->csrf($request, $user)) return;
        if (!$this->client->isConfigured()) { $this->notice($user, 'Połączenie Microsoft nie zostało jeszcze skonfigurowane.', 'warning'); return; }
        if (!$this->limiter->allowStart('minecraft_accounts')) { $this->rateLimit($request, $user); return; }
        $oauth = $this->states->issue('minecraft_accounts', 'minecraft_link', $user->id);
        $this->audit->record($request, 'minecraft_account_link_start', 'success', 'minecraft_accounts', $user->id);
        header('Location: ' . $this->client->authorizationUrl($oauth['state'], $oauth['challenge']), true, 302);
    }

    private function callback(Request $request, User $user): void
    {
        if (!$this->limiter->allowCallback('minecraft_accounts')) { $this->rateLimit($request, $user); return; }
        $context = $this->states->consume('minecraft_accounts', $request->queryString('state'));
        if ($context === null || $context->purpose !== 'minecraft_link' || $context->userId !== $user->id) {
            $this->audit->record($request, 'minecraft_account_link_callback', 'invalid_state', null, $user->id);
            $this->notice($user, 'Odpowiedź Microsoft wygasła albo należy do innego połączenia. Rozpocznij ponownie.', 'warning'); return;
        }
        if ($request->queryString('error') !== '') { $this->audit->record($request, 'minecraft_account_link_callback', 'provider_denied', null, $user->id); $this->notice($user, 'Połączenie Minecraft zostało anulowane.', 'warning'); return; }
        try {
            $profile = $this->client->resolveProfile($request->queryString('code'), $context->verifier);
            $saved = $this->profiles->save($user->id, $profile,
                fn () => $this->audit->record($request, 'minecraft_account_link_callback', 'success', 'minecraft_accounts', $user->id));
            $this->hooks?->doAction('minecraft_accounts.linked', $user->id, $saved);
            $this->notice($user, 'Profil Minecraft Java został zweryfikowany i połączony.', 'success');
        } catch (MinecraftAppRegistrationException) {
            $this->audit->record($request, 'minecraft_account_link_callback', 'app_review_required', null, $user->id);
            $this->notice($user, 'Aplikacja Microsoft oczekuje na zatwierdzenie dostępu do Minecraft Services. Nie oznacza to braku gry na Twoim koncie.', 'warning');
        } catch (\Throwable) {
            $this->audit->record($request, 'minecraft_account_link_callback', 'failed', null, $user->id);
            $this->notice($user, 'Nie udało się połączyć konta. Sprawdź dostęp do Minecraft Java i spróbuj ponownie. Profil może być już połączony z innym kontem.', 'warning');
        }
    }

    private function disconnect(Request $request, User $user): void
    {
        if (!$this->csrf($request, $user)) return;
        try {
            $removed = $this->profiles->unlink($user->id, $request->postString('uuid'),
                fn () => $this->audit->record($request, 'minecraft_account_unlink', 'success', 'minecraft_accounts', $user->id));
            $this->hooks?->doAction('minecraft_accounts.unlinked', $user->id, $removed);
            $this->notice($user, 'Profil Minecraft Java został odłączony. Połączenia z serwerami pozostały osobne.', 'success');
        } catch (\Throwable) {
            $this->audit->record($request, 'minecraft_account_unlink', 'failed', null, $user->id);
            $this->notice($user, 'Połączenie nie istnieje, zostało zmienione albo zapis się nie powiódł. Odśwież stronę.', 'warning');
        }
    }

    private function csrf(Request $request, User $user): bool
    {
        if ($this->security->validateCsrfToken($request->postString('_token'))) return true;
        $this->audit->record($request, 'minecraft_account_change', 'invalid_csrf', null, $user->id);
        $this->error(403, 'Token CSRF jest nieprawidłowy lub wygasł.');
        return false;
    }

    private function guard(Request $request, callable $handler): void
    {
        if (!headers_sent()) { header('Cache-Control: private, no-store'); header('Cloudflare-CDN-Cache-Control: no-store'); header('X-Robots-Tag: noindex'); }
        $user = $this->auth->user();
        if ($user === null || !$user->isActive()) { $this->error($user === null ? 401 : 403, 'Zaloguj się na aktywne konto, aby zarządzać połączeniem Minecraft.'); return; }
        if ($this->auth->rolePreview() !== null) { $this->error(403, 'Połączenia własnego konta są niedostępne w podglądzie roli.'); return; }
        $handler($user);
    }

    private function notice(User $user, string $message, string $variant = 'info'): void
    {
        if ($message !== '') LinkedAccountNoticeStore::put($user->id, $message, $variant);
        header('Location: index.php?route=/admin/profile/identities', true, 303);
    }

    private function rateLimit(Request $request, User $user): void
    {
        $this->audit->record($request, 'minecraft_account_link_limit', 'rejected', null, $user->id);
        header('Retry-After: ' . $this->limiter->retryAfter());
        $this->error(429, 'Przekroczono limit prób połączenia. Spróbuj później.');
    }

    private function error(int $status, string $message): void
    {
        http_response_code($status);
        $this->theme->render_admin_access_state($status, 'Połączone konto Minecraft', $message, 'index.php?route=/admin/profile/identities', 'Wróć do połączonych kont');
    }
}
