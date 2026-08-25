<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\CoreAuthPolicyExtension;

use SyntaxDevTeam\Cms\Modules\CoreAuth\AuthPolicyProviderInterface;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuthPolicyRuntime;
use SyntaxDevTeam\Cms\Modules\CoreAuth\RegistrationConfig;
use SyntaxDevTeam\Cms\Modules\CoreAuth\UserAdministrationRepository;

final class CoreAuthPolicyProvider implements AuthPolicyProviderInterface
{
    private const REDIRECT_MODES = ['core_default', 'admin', 'home', 'custom'];

    private const FORBIDDEN_DEFAULT_ROLES = [
        'owner',
        'administrator',
        'maintainer',
        'server_owner',
    ];

    private const FORBIDDEN_DEFAULT_ROLE_PERMISSIONS = [
        '*',
        'users.manage',
        'roles.manage',
        'settings.manage',
        'modules.install',
        'modules.toggle',
        'modules.remove',
        'itemshop.stores.manage',
    ];

    /** @var array<string, string> */
    private array $settings = [];

    public function __construct(
        private readonly AuthPolicySettingsRepository $repository,
        private readonly UserAdministrationRepository $users,
    ) {
        $this->reload();
    }

    public function reload(): void
    {
        $this->settings = $this->repository->all();
    }

    public function registrationMode(): ?string
    {
        $mode = strtolower(trim($this->settings['registration.mode'] ?? ''));

        return in_array($mode, [RegistrationConfig::SELF_SERVICE, RegistrationConfig::APPROVAL], true)
            ? $mode
            : null;
    }

    public function defaultRole(): ?string
    {
        $role = strtolower(trim($this->settings['registration.default_role'] ?? ''));

        return $this->isAllowedDefaultRole($role) ? $role : null;
    }

    public function providerEnabled(string $provider): ?bool
    {
        return $this->optionalBool(AuthPolicySettingsRepository::providerEnabledKey($provider));
    }

    public function providerVisible(string $provider): ?bool
    {
        if ($this->providerEnabled($provider) === false) {
            return false;
        }

        return $this->optionalBool(AuthPolicySettingsRepository::providerVisibleKey($provider));
    }

    public function providerOrder(): array
    {
        $raw = trim($this->settings['providers.order'] ?? '');
        if ($raw === '') {
            return [];
        }

        $providers = array_values(array_unique(array_filter(array_map(
            static fn (string $provider): string => strtolower(trim($provider)),
            explode(',', $raw)
        ), static fn (string $provider): bool => preg_match('/^[a-z][a-z0-9_-]{1,31}$/', $provider) === 1)));

        return $providers;
    }

    public function loginRedirectMode(): string
    {
        $mode = strtolower(trim($this->settings['redirect.mode'] ?? 'core_default'));

        return in_array($mode, self::REDIRECT_MODES, true) ? $mode : 'core_default';
    }

    public function loginRedirectTarget(): ?string
    {
        if ($this->loginRedirectMode() !== 'custom') {
            return null;
        }

        return AuthPolicyRuntime::normalizeLocalRedirect($this->settings['redirect.target'] ?? '');
    }

    public function loginEntryLocations(): array
    {
        $configured = strtolower(trim($this->settings['ui.login_locations'] ?? ''));
        if ($configured !== '') {
            if ($configured === 'none') {
                return [];
            }
            $locations = array_values(array_unique(array_filter(
                array_map(static fn (string $location): string => trim($location), explode(',', $configured)),
                static fn (string $location): bool => in_array($location, ['header', 'footer'], true)
            )));

            return $locations;
        }

        $locations = ['header'];
        if ($this->boolValue('ui.login_footer')) {
            $locations[] = 'footer';
        }

        return $locations;
    }

    public function isAllowedDefaultRole(string $role): bool
    {
        if (
            preg_match('/^[a-z][a-z0-9_]{1,63}$/', $role) !== 1
            || in_array($role, self::FORBIDDEN_DEFAULT_ROLES, true)
        ) {
            return false;
        }

        $record = $this->users->findRole($role);
        if ($record === null) {
            return false;
        }

        return array_intersect(self::FORBIDDEN_DEFAULT_ROLE_PERMISSIONS, $record->permissions) === [];
    }

    private function optionalBool(string $key): ?bool
    {
        if (!array_key_exists($key, $this->settings)) {
            return null;
        }

        return filter_var($this->settings[$key], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
    }

    private function boolValue(string $key): bool
    {
        return filter_var($this->settings[$key] ?? '0', FILTER_VALIDATE_BOOL);
    }
}
