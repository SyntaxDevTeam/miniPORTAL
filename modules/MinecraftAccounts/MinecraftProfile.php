<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\MinecraftAccounts;

final readonly class MinecraftProfile
{
    public function __construct(
        public string $uuid,
        public string $username,
    ) {
    }
}
