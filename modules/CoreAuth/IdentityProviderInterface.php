<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\CoreAuth;

interface IdentityProviderInterface
{
    public function name(): string;

    public function label(): string;

    public function isConfigured(): bool;

    public function authorizationUrl(string $state, string $codeChallenge): string;

    public function resolveIdentity(string $code, string $codeVerifier): ExternalIdentity;
}
