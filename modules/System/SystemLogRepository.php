<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\System;

use PDO;
use RuntimeException;
use SyntaxDevTeam\Cms\Database\CrudApp;

final class SystemLogRepository
{
    public function __construct(
        private readonly CrudApp $database,
    ) {
    }

    public function count(): int
    {
        return $this->database->count('auth_events');
    }

    /**
     * @return list<array<string, scalar|null>>
     */
    public function page(int $page, int $perPage = 50): array
    {
        $page = max(1, $page);
        $perPage = max(10, min(100, $perPage));
        $offset = ($page - 1) * $perPage;
        $statement = $this->database->query(
            'SELECT auth_events.id, auth_events.event_type, auth_events.result, '
            . 'auth_events.provider, auth_events.ip_hash, auth_events.user_agent, '
            . 'auth_events.created_at, users.display_name '
            . 'FROM auth_events LEFT JOIN users ON users.id = auth_events.user_id '
            . 'ORDER BY auth_events.id DESC LIMIT :limit OFFSET :offset',
            [':limit' => $perPage, ':offset' => $offset]
        );
        if ($statement === null) {
            throw new RuntimeException('Nie można pobrać dziennika zdarzeń.');
        }

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}
