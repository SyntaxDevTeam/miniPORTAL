<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\CoreAuth;

interface UserRepositoryInterface
{
    public function findById(int $id): ?User;

    public function findByIdentity(string $provider, string $subject): ?User;

    public function createPendingFromIdentity(ExternalIdentity $identity): User;

    public function linkIdentity(int $userId, ExternalIdentity $identity): void;

    public function unlinkIdentity(int $userId, string $provider, string $subject): bool;

    public function touchIdentity(int $userId, string $provider, string $subject): void;
}
