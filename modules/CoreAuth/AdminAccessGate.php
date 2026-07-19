<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\CoreAuth;

final class AdminAccessGate
{
    public const ALLOWED = 200;
    public const UNAUTHENTICATED = 401;
    public const FORBIDDEN = 403;

    public function __construct(
        private readonly AuthService $auth,
        private readonly AuthorizationService $authorization,
    ) {
    }

    public function check(string $permission): int
    {
        $user = $this->auth->user();

        if ($user === null) {
            return self::UNAUTHENTICATED;
        }

        return $this->authorization->allows($user, $permission)
            ? self::ALLOWED
            : self::FORBIDDEN;
    }
}
