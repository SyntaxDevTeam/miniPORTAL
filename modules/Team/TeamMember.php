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
        public string $headline,
        public string $bio,
        public string $focusTags,
        public string $highlights,
        public string $skills,
        public string $featuredProjects,
        public string $profileUrl,
        public string $contactEmail,
        public string $contactDiscord,
        public string $primaryCtaLabel,
        public string $primaryCtaUrl,
        public string $secondaryCtaLabel,
        public string $secondaryCtaUrl,
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
