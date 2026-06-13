<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\CoreAuth;

final readonly class ExternalIdentity
{
    public function __construct(
        public string $provider,
        public string $subject,
        public string $login,
        public ?string $email = null,
        public bool $emailVerified = false,
        public ?string $avatarUrl = null,
    ) {
    }
}
