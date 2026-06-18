<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\Team;

final readonly class TeamMember
{
    public function __construct(
        public int $id,
        public int $userId,
        public string $slug,
        public string $publicName,
        public string $roleLabel,
        public string $bio,
        public string $profileUrl,
        public int $sortOrder,
        public bool $visible,
        public string $displayName,
        public ?string $email,
        public ?string $avatarUrl,
        public string $userStatus,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }
}
