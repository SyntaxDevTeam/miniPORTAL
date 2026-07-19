<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\CoreAuth;

final readonly class UserAdminRecord
{
    /**
     * @param list<string> $roles
     * @param list<string> $providers
     */
    public function __construct(
        public int $id,
        public string $displayName,
        public ?string $email,
        public string $status,
        public array $roles,
        public array $providers,
        public ?string $lastLoginAt,
        public string $createdAt,
    ) {
    }
}
