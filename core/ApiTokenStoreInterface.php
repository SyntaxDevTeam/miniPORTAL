<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

interface ApiTokenStoreInterface
{
    /**
     * @param array{
     *     public_id: string,
     *     label: string,
     *     token_hash: string,
     *     scopes_json: string,
     *     subject_user_id: ?int,
     *     tenant_type: ?string,
     *     tenant_id: ?string,
     *     created_by: ?int,
     *     expires_at: ?string
     * } $record
     */
    public function create(array $record): int;

    /**
     * @return array<string, mixed>|null
     */
    public function findByPublicId(string $publicId): ?array;

    public function touch(int $id, string $usedAt): void;

    public function revoke(int $id, string $revokedAt): void;
}
