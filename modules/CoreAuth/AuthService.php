<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\CoreAuth;

use SyntaxDevTeam\Cms\Core\Security;

final class AuthService
{
    private const SESSION_USER_ID = '_miniportal_user_id';

    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly Security $security,
    ) {
    }

    public function user(): ?User
    {
        $userId = $_SESSION[self::SESSION_USER_ID] ?? null;

        if (!is_int($userId) && !ctype_digit((string) $userId)) {
            return null;
        }

        $user = $this->users->findById((int) $userId);

        if ($user === null || !$user->isActive()) {
            $this->logout();
            return null;
        }

        return $user;
    }

    public function loginIdentity(ExternalIdentity $identity): ?User
    {
        $user = $this->users->findByIdentity($identity->provider, $identity->subject);

        if ($user === null || !$user->isActive()) {
            return null;
        }

        $this->security->regenerateSession();
        $_SESSION[self::SESSION_USER_ID] = $user->id;

        return $user;
    }

    public function logout(): void
    {
        unset($_SESSION[self::SESSION_USER_ID]);
        $this->security->regenerateSession();
    }
}
