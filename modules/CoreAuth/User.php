<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\CoreAuth;

final readonly class User
{
    /**
     * @param list<string> $roles
     * @param list<string> $permissions
     * @param list<ExternalIdentity> $identities
     */
    public function __construct(
        public int $id,
        public string $displayName,
        public ?string $email,
        public ?string $avatarUrl,
        public string $status,
        public array $roles,
        public array $permissions,
        public array $identities = [],
    ) {
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function primaryRole(): string
    {
        return $this->roles[0] ?? 'user';
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->displayName)) ?: [];
        $initials = '';

        foreach (array_slice($parts, 0, 2) as $part) {
            $initials .= function_exists('mb_substr')
                ? mb_strtoupper(mb_substr($part, 0, 1))
                : strtoupper(substr($part, 0, 1));
        }

        return $initials !== '' ? $initials : 'US';
    }
}
