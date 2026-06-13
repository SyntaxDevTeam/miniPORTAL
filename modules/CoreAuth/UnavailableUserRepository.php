<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\CoreAuth;

final class UnavailableUserRepository implements UserRepositoryInterface
{
    public function findById(int $id): ?User
    {
        return null;
    }

    public function findByIdentity(string $provider, string $subject): ?User
    {
        return null;
    }

    public function linkIdentity(int $userId, ExternalIdentity $identity): void
    {
    }

    public function unlinkIdentity(int $userId, string $provider, string $subject): bool
    {
        return false;
    }

    public function touchIdentity(int $userId, string $provider, string $subject): void
    {
    }
}
