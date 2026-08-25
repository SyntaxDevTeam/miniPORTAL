<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

final readonly class IssuedApiToken
{
    /**
     * @param list<string> $scopes
     */
    public function __construct(
        public int $id,
        public string $publicId,
        public string $token,
        public string $label,
        public array $scopes,
        public ?int $subjectUserId,
        public TenantContext $tenant,
        public ?string $expiresAt,
    ) {
    }
}
