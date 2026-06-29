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
        private readonly ?FirstAdminBootstrapper $firstOwnerBootstrapper = null,
    ) {
    }

    public function user(): ?User
    {
        $userId = $_SESSION[self::SESSION_USER_ID] ?? null;

        if (!is_int($userId) && !ctype_digit((string) $userId)) {
            return null;
        }

        $user = $this->users->findById((int) $userId);

        if ($user === null || $user->status === 'blocked') {
            $this->logout();
            return null;
        }

        return $user;
    }

    public function loginIdentity(ExternalIdentity $identity): ?User
    {
        $user = $this->users->findByIdentity($identity->provider, $identity->subject);

        if ($user === null) {
            if ($this->firstOwnerBootstrapper?->isAvailable()) {
                try {
                    $user = $this->firstOwnerBootstrapper->bootstrap(
                        $identity,
                        $identity->login !== '' ? $identity->login : $identity->provider
                    );
                } catch (\RuntimeException) {
                    $user = $this->users->findByIdentity($identity->provider, $identity->subject);
                }
            }
            $user ??= $this->users->createPendingFromIdentity($identity);
        }
        if ($user->status === 'blocked') {
            return null;
        }

        $this->security->regenerateSession();
        $_SESSION[self::SESSION_USER_ID] = $user->id;
        $this->users->touchIdentity($user->id, $identity->provider, $identity->subject);

        return $user;
    }

    public function linkIdentity(ExternalIdentity $identity): bool
    {
        $user = $this->user();

        if ($user === null || $this->users->findByIdentity($identity->provider, $identity->subject) !== null) {
            return false;
        }

        $this->users->linkIdentity($user->id, $identity);
        return true;
    }

    public function unlinkIdentity(string $provider, string $subject): bool
    {
        $user = $this->user();

        return $user !== null && $this->users->unlinkIdentity($user->id, $provider, $subject);
    }

    public function updateProfile(string $displayName, ?string $email): bool
    {
        $user = $this->user();

        return $user !== null && $this->users->updateProfile($user->id, $displayName, $email);
    }

    public function updateAvatar(?string $avatarUrl): bool
    {
        $user = $this->user();

        return $user !== null && $this->users->updateAvatar($user->id, $avatarUrl);
    }

    public function logout(): void
    {
        unset($_SESSION[self::SESSION_USER_ID]);
        $this->security->regenerateSession();
    }
}
