<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\CoreAuth;

final readonly class RoleAdminRecord
{
    /**
     * @param list<string> $permissions
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $label,
        public bool $system,
        public array $permissions,
        public int $usersCount,
    ) {
    }
}
