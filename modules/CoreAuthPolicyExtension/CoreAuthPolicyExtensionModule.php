<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\CoreAuthPolicyExtension;

use InvalidArgumentException;
use SyntaxDevTeam\Cms\Core\AdminMenuRegistry;
use SyntaxDevTeam\Cms\Core\ModuleInterface;
use SyntaxDevTeam\Cms\Core\Request;
use SyntaxDevTeam\Cms\Core\Router;
use SyntaxDevTeam\Cms\Core\Security;
use SyntaxDevTeam\Cms\Core\TemplateCacheInterface;
use SyntaxDevTeam\Cms\Core\ThemeInterface;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AdminAccessGate;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuditLogService;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuthPolicyRuntime;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuthService;
use SyntaxDevTeam\Cms\Modules\CoreAuth\IdentityProviderRegistry;
use SyntaxDevTeam\Cms\Modules\CoreAuth\RegistrationConfig;
use SyntaxDevTeam\Cms\Modules\CoreAuth\UserAdministrationRepository;

final class CoreAuthPolicyExtensionModule implements ModuleInterface
{
    public function __construct(
        private readonly ThemeInterface $theme,
        private readonly AdminMenuRegistry $menu,
        private readonly AuthService $auth,
        private readonly AdminAccessGate $access,
        private readonly Security $security,
        private readonly AuditLogService $audit,
        private readonly IdentityProviderRegistry $providers,
        private readonly UserAdministrationRepository $users,
        private readonly AuthPolicySettingsRepository $settings,
        private readonly CoreAuthPolicyProvider $policy,
        private readonly TemplateCacheInterface $templateCache,
        private readonly string $coreRegistrationMode,
    ) {
        AuthPolicyRuntime::use($this->policy);
    }

    public function id(): string
    {
        return 'core_auth_policy_extension';
    }

    public function version(): string
    {
        return '0.2.2';
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
        return ['settings.manage'];
    }

    public function registerAdminMenu(AdminMenuRegistry $menu): void
    {
        $menu->add('Core', 'Polityka logowania', '/admin/auth-policy', 'AP', 'settings.manage', 48);
    }

    public function registerRoutes(Router $router): void
    {
        if ($this->auth->actualUser() === null) {
            AuthPolicyRuntime::applyLoginRedirectPolicy();
        }

        $router->get('/admin/auth-policy', fn (Request $request) => $this->guard(
            $request,
            fn () => $this->renderSettings()
        ));
        $router->post('/admin/auth-policy', fn (Request $request) => $this->guard(
            $request,
            fn () => $this->saveSettings($request)
        ));
    }


    private function renderSettings(string $message = '', string $variant = 'info'): void
    {
        $user = $this->auth->user();
        if ($user === null) {
            return;
        }

        $roles = array_filter(
            $this->users->roles(),
            fn (string $label, string $name): bool => $this->policy->isAllowedDefaultRole($name),
            ARRAY_FILTER_USE_BOTH
        );
        $defaultRole = $this->policy->defaultRole();
        if ($defaultRole === null || !array_key_exists($defaultRole, $roles)) {
            $defaultRole = array_key_exists('user', $roles) ? 'user' : (string) array_key_first($roles);
        }

        $providerOrder = $this->policy->providerOrder();
        if ($providerOrder === []) {
            $providerOrder = array_map(static fn ($provider): string => $provider->name(), $this->providers->all());
        }

        $this->startAdminPage(
            'Polityka logowania',
            'Opcjonalna warstwa sterująca zachowaniem CoreAuth. Wyłączenie modułu przywraca ustawienia Core.'
        );
        if ($message !== '') {
            $this->theme->render_alert($message, $variant);
        }

        $loginEntryLocations = $this->policy->loginEntryLocations();
        $loginEntryMode = match (true) {
            $loginEntryLocations === [] => 'none',
            in_array('header', $loginEntryLocations, true) && in_array('footer', $loginEntryLocations, true) => 'header_footer',
            in_array('footer', $loginEntryLocations, true) => 'footer',
            default => 'header',
        };

        $fields = [
            [
                'name' => 'redirect_mode',
                'label' => 'Po pomyślnym logowaniu',
                'type' => 'select',
                'value' => $this->policy->loginRedirectMode(),
                'options' => [
                    'core_default' => 'Zachowanie Core / bezpieczny ostatni cel',
                    'admin' => 'Panel administracyjny',
                    'home' => 'Strona główna',
                    'custom' => 'Wybrana lokalna strona',
                ],
                'help' => 'Tryb Core zachowuje parametr next i dotychczasową logikę bezpiecznego powrotu.',
            ],
            [
                'name' => 'redirect_target',
                'label' => 'Wybrana strona po logowaniu',
                'value' => $this->policy->loginRedirectTarget() ?? '',
                'help' => 'Tylko lokalna ścieżka, np. /projects albo index.php?route=/projects. Zewnętrzne URL-e są odrzucane.',
            ],
            [
                'name' => 'registration_mode',
                'label' => 'Nowe konto',
                'type' => 'select',
                'value' => $this->policy->registrationMode() ?? $this->coreRegistrationMode,
                'options' => [
                    RegistrationConfig::SELF_SERVICE => 'Samodzielna aktywacja po akceptacji regulaminu',
                    RegistrationConfig::APPROVAL => 'Wymagana akceptacja administratora',
                ],
            ],
            [
                'name' => 'default_role',
                'label' => 'Domyślna rola nowego konta',
                'type' => 'select',
                'value' => $defaultRole,
                'options' => $roles,
                'help' => 'Role z krytycznymi uprawnieniami administracyjnymi są celowo wykluczone.',
            ],
            [
                'name' => 'provider_order',
                'label' => 'Kolejność dostawców logowania',
                'value' => implode(', ', $providerOrder),
                'help' => 'Nazwy providerów rozdzielone przecinkami. Nieznane wartości są pomijane.',
            ],
            [
                'name' => 'login_entry_location',
                'label' => 'Przycisk Sign in / Panel',
                'type' => 'select',
                'value' => $loginEntryMode,
                'options' => [
                    'header' => 'Górne menu',
                    'footer' => 'Stopka',
                    'header_footer' => 'Górne menu i stopka',
                    'none' => 'Nie pokazuj publicznego przycisku',
                ],
                'help' => 'Steruje standardowym wejściem CoreAuth. Dedykowane logowania innych modułów pozostają niezależne.',
            ],
        ];

        foreach ($this->providers->all() as $provider) {
            $name = $provider->name();
            $enabled = $this->policy->providerEnabled($name) ?? true;
            $visible = $this->policy->providerVisible($name) ?? true;
            $status = $provider->isConfigured() ? 'Provider skonfigurowany w CoreAuth.' : 'Provider nie jest jeszcze skonfigurowany w CoreAuth.';

            $fields[] = [
                'name' => 'provider_' . $name . '_enabled',
                'label' => $provider->label() . ' — zezwalaj na logowanie',
                'type' => 'checkbox',
                'checked' => $enabled,
                'help' => $status . ' Wyłączenie blokuje także bezpośrednie wejście na trasę OAuth.',
            ];
            $fields[] = [
                'name' => 'provider_' . $name . '_visible',
                'label' => $provider->label() . ' — pokazuj na ekranie logowania',
                'type' => 'checkbox',
                'checked' => $enabled && $visible,
                'help' => 'Ukryty, ale włączony provider nie jest prezentowany na liście; może nadal służyć do świadomego bezpośredniego logowania.',
            ];
        }

        $this->theme->start_admin_panel('Zachowanie uwierzytelniania', 'CoreAuth Policy Extension 0.2.0');
        $this->theme->render_form(
            'index.php?route=/admin/auth-policy',
            $fields,
            'Zapisz politykę',
            $this->security->csrfToken()
        );
        $this->theme->end_admin_panel();
        $this->endAdminPage();
    }

    private function saveSettings(Request $request): void
    {
        $actor = $this->auth->user();
        if ($actor === null) {
            return;
        }
        if (!$this->security->validateCsrfToken($request->postString('_token'))) {
            $this->audit->record($request, 'auth_policy_update', 'invalid_csrf', null, $actor->id);
            http_response_code(403);
            $this->renderSettings('Token CSRF jest nieprawidłowy lub wygasł.', 'danger');
            return;
        }

        try {
            $redirectMode = strtolower(trim($request->postString('redirect_mode')));
            if (!in_array($redirectMode, ['core_default', 'admin', 'home', 'custom'], true)) {
                throw new InvalidArgumentException('Wybrano nieobsługiwany sposób przekierowania po logowaniu.');
            }
            $redirectTarget = trim($request->postString('redirect_target'));
            if ($redirectMode === 'custom') {
                $normalizedTarget = AuthPolicyRuntime::normalizeLocalRedirect($redirectTarget);
                if ($normalizedTarget === null) {
                    throw new InvalidArgumentException('Wybrana strona po logowaniu musi być bezpieczną lokalną ścieżką.');
                }
                $redirectTarget = $normalizedTarget;
            } else {
                $redirectTarget = '';
            }

            $registrationMode = strtolower(trim($request->postString('registration_mode')));
            if (!in_array($registrationMode, [RegistrationConfig::SELF_SERVICE, RegistrationConfig::APPROVAL], true)) {
                throw new InvalidArgumentException('Wybrano nieobsługiwany tryb rejestracji.');
            }

            $defaultRole = strtolower(trim($request->postString('default_role')));
            if (!$this->policy->isAllowedDefaultRole($defaultRole)) {
                throw new InvalidArgumentException('Wybrana rola nie może być automatycznie nadawana nowym kontom.');
            }

            $loginEntryLocation = strtolower(trim($request->postString('login_entry_location')));
            $loginLocations = match ($loginEntryLocation) {
                'header' => 'header',
                'footer' => 'footer',
                'header_footer' => 'header,footer',
                'none' => 'none',
                default => throw new InvalidArgumentException('Wybrano nieobsługiwane miejsce przycisku logowania.'),
            };

            $knownProviders = [];
            $providerSettings = [];
            foreach ($this->providers->all() as $provider) {
                $name = $provider->name();
                $knownProviders[$name] = true;
                $enabled = $request->postBool('provider_' . $name . '_enabled');
                $visible = $enabled && $request->postBool('provider_' . $name . '_visible');
                $providerSettings[AuthPolicySettingsRepository::providerEnabledKey($name)] = $enabled ? '1' : '0';
                $providerSettings[AuthPolicySettingsRepository::providerVisibleKey($name)] = $visible ? '1' : '0';
            }

            $requestedOrder = array_values(array_unique(array_filter(array_map(
                static fn (string $provider): string => strtolower(trim($provider)),
                explode(',', $request->postString('provider_order'))
            ), static fn (string $provider): bool => $provider !== '')));
            $providerOrder = [];
            foreach ($requestedOrder as $provider) {
                if (isset($knownProviders[$provider])) {
                    $providerOrder[] = $provider;
                }
            }
            foreach (array_keys($knownProviders) as $provider) {
                if (!in_array($provider, $providerOrder, true)) {
                    $providerOrder[] = $provider;
                }
            }

            $this->settings->save([
                'redirect.mode' => $redirectMode,
                'redirect.target' => $redirectTarget,
                'registration.mode' => $registrationMode,
                'registration.default_role' => $defaultRole,
                'providers.order' => implode(',', $providerOrder),
                'ui.login_locations' => $loginLocations,
                'ui.login_footer' => '0',
                ...$providerSettings,
            ]);
            $this->policy->reload();
            AuthPolicyRuntime::use($this->policy);
            AuthPolicyRuntime::applyLoginRedirectPolicy();
            $this->templateCache->invalidateTags(['navigation', 'homepage', 'theme']);
            $this->audit->record($request, 'auth_policy_update', 'success', null, $actor->id);
            $this->renderSettings('Polityka logowania została zapisana.', 'success');
        } catch (InvalidArgumentException $exception) {
            $this->audit->record($request, 'auth_policy_update', 'validation_failed', null, $actor->id);
            http_response_code(422);
            $this->renderSettings($exception->getMessage(), 'warning');
        } catch (\Throwable $exception) {
            error_log(sprintf('miniPORTAL auth policy update failed: exception=%s', $exception::class));
            $this->audit->record($request, 'auth_policy_update', 'failed', null, $actor->id);
            http_response_code(503);
            $this->renderSettings('Nie udało się zapisać polityki logowania.', 'danger');
        }
    }

    private function startAdminPage(string $title, string $lead): void
    {
        $user = $this->auth->user();
        if ($user === null) {
            return;
        }

        $this->theme->start_admin_page($title, $this->menu->visibleFor($user->permissions), '/admin/auth-policy', [
            'name' => $user->displayName,
            'role' => ucfirst($user->primaryRole()),
            'initials' => $user->initials(),
            'avatar_url' => $user->avatarUrl ?? '',
            'logout_action' => 'index.php?route=/admin/logout',
            'logout_token' => $this->security->csrfToken(),
        ]);
        $this->theme->start_admin_content($title, $lead, [
            ['label' => 'Panel', 'href' => 'index.php?route=/admin'],
            ['label' => $title, 'href' => ''],
        ]);
    }

    private function endAdminPage(): void
    {
        $this->theme->end_admin_content();
        $this->theme->end_admin_page();
    }

    private function guard(Request $request, callable $handler): void
    {
        $decision = $this->access->check('settings.manage');
        if ($decision === AdminAccessGate::ALLOWED) {
            $handler();
            return;
        }

        $status = $decision === AdminAccessGate::UNAUTHENTICATED ? 401 : 403;
        $this->audit->record(
            $request,
            'auth_policy_access',
            $status === 401 ? 'unauthenticated' : 'forbidden',
            null,
            $this->auth->user()?->id
        );
        http_response_code($status);
        $this->theme->render_admin_access_state(
            $status,
            $status === 401 ? 'Wymagane logowanie' : 'Brak uprawnienia',
            $status === 401 ? 'Ta trasa wymaga aktywnej sesji.' : 'Twoje konto nie może zarządzać polityką logowania.',
            $status === 401 ? 'index.php?route=/admin/login' : 'index.php?route=/admin',
            $status === 401 ? 'Przejdź do logowania' : 'Wróć do dashboardu'
        );
    }
}
