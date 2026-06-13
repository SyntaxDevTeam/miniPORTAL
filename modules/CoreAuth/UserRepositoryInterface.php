<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\CoreAuth;

interface UserRepositoryInterface
{
    public function findById(int $id): ?User;

    public function findByIdentity(string $provider, string $subject): ?User;
}
