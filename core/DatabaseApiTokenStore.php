<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

use RuntimeException;
use SyntaxDevTeam\Cms\Database\CrudApp;

final class DatabaseApiTokenStore implements ApiTokenStoreInterface
{
    public function __construct(private readonly CrudApp $database)
    {
    }

    public function create(array $record): int
    {
        $id = $this->database->create('core_api_tokens', $record);
        if (!is_numeric($id) || (int) $id <= 0) {
            throw new RuntimeException('Nie można zapisać tokenu API.');
        }

        return (int) $id;
    }

    public function findByPublicId(string $publicId): ?array
    {
        $rows = $this->database->read(
            'core_api_tokens',
            '*',
            ['public_id' => $publicId, 'LIMIT' => 1]
        ) ?? [];
        $row = $rows[0] ?? null;

        return is_array($row) ? $row : null;
    }

    public function touch(int $id, string $usedAt): void
    {
        $this->database->update(
            'core_api_tokens',
            ['last_used_at' => $usedAt],
            ['id' => $id]
        );
    }

    public function revoke(int $id, string $revokedAt): void
    {
        $this->database->update(
            'core_api_tokens',
            ['revoked_at' => $revokedAt],
            ['id' => $id, 'revoked_at' => null]
        );
    }
}
