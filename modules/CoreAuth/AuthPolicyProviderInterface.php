<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\CoreAuth;

interface AuthPolicyProviderInterface
{
    public function registrationMode(): ?string;

    public function defaultRole(): ?string;

    public function providerEnabled(string $provider): ?bool;

    public function providerVisible(string $provider): ?bool;

    /**
     * @return list<string>
     */
    public function providerOrder(): array;

    public function loginRedirectMode(): string;

    public function loginRedirectTarget(): ?string;

    /**
     * @return list<string>
     */
    public function loginEntryLocations(): array;
}
