<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\DatabaseManager;

use PDO;
use RuntimeException;
use SyntaxDevTeam\Cms\Database\CrudApp;

final class DatabaseManagerHistoryRepository
{
    public function __construct(
        private readonly CrudApp $database,
    ) {
    }

    public function record(
        ?int $userId,
        string $operation,
        string $result,
        ?string $targetTable = null,
        ?string $sqlPreview = null,
        ?int $rowsCount = null,
        ?string $message = null,
    ): void {
        $this->database->create('database_manager_history', [
            'user_id' => $userId,
            'operation' => substr($operation, 0, 40),
            'target_table' => $targetTable !== null ? substr($targetTable, 0, 191) : null,
            'sql_preview' => $sqlPreview !== null ? substr($this->singleLine($sqlPreview), 0, 500) : null,
            'result' => substr($result, 0, 40),
            'rows_count' => $rowsCount,
            'message' => $message !== null ? substr($this->singleLine($message), 0, 500) : null,
        ]);
    }

    /**
     * @return list<array<string, scalar|null>>
     */
    public function recent(int $limit = 10): array
    {
        return $this->page(1, $limit);
    }

    public function count(): int
    {
        $count = $this->database->query('SELECT COUNT(*) FROM database_manager_history')?->fetchColumn();

        return is_numeric($count) ? (int) $count : 0;
    }

    /**
     * @return list<array<string, scalar|null>>
     */
    public function page(int $page, int $perPage = 25): array
    {
        $page = max(1, $page);
        $perPage = max(10, min(100, $perPage));
        $offset = ($page - 1) * $perPage;
        $statement = $this->database->query(
            'SELECT database_manager_history.operation, database_manager_history.target_table, '
            . 'database_manager_history.result, database_manager_history.rows_count, '
            . 'database_manager_history.sql_preview, database_manager_history.message, '
            . 'database_manager_history.created_at, users.display_name '
            . 'FROM database_manager_history '
            . 'LEFT JOIN users ON users.id = database_manager_history.user_id '
            . 'ORDER BY database_manager_history.id DESC LIMIT :limit OFFSET :offset',
            [':limit' => $perPage, ':offset' => $offset]
        );
        if ($statement === null) {
            throw new RuntimeException('Nie można pobrać historii Managera SQL.');
        }

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    private function singleLine(string $value): string
    {
        return preg_replace('/\s+/u', ' ', trim($value)) ?? '';
    }
}
