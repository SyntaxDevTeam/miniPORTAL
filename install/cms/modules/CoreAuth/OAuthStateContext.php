<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\CoreAuth;

final readonly class OAuthStateContext
{
    public function __construct(
        public string $verifier,
        public string $nonce,
        public string $purpose,
        public ?int $userId,
    ) {
    }
}
