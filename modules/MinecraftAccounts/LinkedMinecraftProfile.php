<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\MinecraftAccounts;

final readonly class LinkedMinecraftProfile
{
    public function __construct(
        public int $userId,
        public string $uuid,
        public string $username,
        public string $verifiedAt,
        public string $createdAt,
        public string $updatedAt,
        public string $verificationMethod = 'microsoft_xbox',
    ) {
    }
}
