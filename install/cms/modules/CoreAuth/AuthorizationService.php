<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\CoreAuth;

final class AuthorizationService
{
    public function allows(?User $user, string $permission): bool
    {
        if ($user === null || !$user->isActive()) {
            return false;
        }

        return in_array('*', $user->permissions, true)
            || in_array($permission, $user->permissions, true);
    }
}
