<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\CoreAuth;

final class DefaultAuthPolicyProvider implements AuthPolicyProviderInterface
{
    public function registrationMode(): ?string
    {
        return null;
    }

    public function defaultRole(): ?string
    {
        return null;
    }

    public function providerEnabled(string $provider): ?bool
    {
        return null;
    }

    public function providerVisible(string $provider): ?bool
    {
        return null;
    }

    public function providerOrder(): array
    {
        return [];
    }

    public function loginRedirectMode(): string
    {
        return 'core_default';
    }

    public function loginRedirectTarget(): ?string
    {
        return null;
    }

    public function loginEntryLocations(): array
    {
        return ['header'];
    }
}
