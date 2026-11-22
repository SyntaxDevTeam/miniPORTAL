<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\MinecraftAccounts;

interface MinecraftProfileClientInterface
{
    public function isConfigured(): bool;

    public function authorizationUrl(string $state, string $codeChallenge): string;

    public function resolveProfile(string $code, string $codeVerifier): MinecraftProfile;
}
