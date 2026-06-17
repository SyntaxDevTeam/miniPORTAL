<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\System;

use PDO;
use RuntimeException;
use SyntaxDevTeam\Cms\Database\CrudApp;

final class DatabaseExplorerRepository
{
    public function __construct(
        private readonly CrudApp $database,
    ) {
    }

    public function databaseName(): string
    {
        $name = $this->database->query('SELECT DATABASE()')?->fetchColumn();

        return is_string($name) ? $name : '';
    }

    /**
     * @return list<array{name: string, engine: string, rows: string, size: string, collation: string}>
     */
    public function tables(): array
    {
        $statement = $this->database->query(
            'SELECT TABLE_NAME, ENGINE, TABLE_ROWS, DATA_LENGTH, INDEX_LENGTH, TABLE_COLLATION '
            . 'FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME'
        );
        if ($statement === null) {
            throw new RuntimeException('Nie można pobrać listy tabel bazy danych.');
        }

        return array_map(
            fn (array $row): array => [
                'name' => (string) $row['TABLE_NAME'],
                'engine' => (string) ($row['ENGINE'] ?? 'Nieznany'),
                'rows' => number_format((int) ($row['TABLE_ROWS'] ?? 0), 0, ',', ' '),
                'size' => $this->formatBytes((int) ($row['DATA_LENGTH'] ?? 0) + (int) ($row['INDEX_LENGTH'] ?? 0)),
                'collation' => (string) ($row['TABLE_COLLATION'] ?? ''),
            ],
            $statement->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    /**
     * @return list<array{name: string, type: string, nullable: string, key: string, default: string, extra: string}>
     */
    public function columns(string $table): array
    {
        $statement = $this->database->query(
            'SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_KEY, COLUMN_DEFAULT, EXTRA '
            . 'FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table ORDER BY ORDINAL_POSITION',
            [':table' => $table]
        );
        if ($statement === null) {
            throw new RuntimeException('Nie można pobrać kolumn tabeli.');
        }

        return array_map(
            static fn (array $row): array => [
                'name' => (string) $row['COLUMN_NAME'],
                'type' => (string) $row['COLUMN_TYPE'],
                'nullable' => (string) $row['IS_NULLABLE'],
                'key' => (string) ($row['COLUMN_KEY'] ?? ''),
                'default' => $row['COLUMN_DEFAULT'] !== null ? (string) $row['COLUMN_DEFAULT'] : 'NULL',
                'extra' => (string) ($row['EXTRA'] ?? ''),
            ],
            $statement->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    /**
     * @return array{
     *     headers: list<string>,
     *     rows: list<list<string>>,
     *     total: int,
     *     page: int,
     *     per_page: int,
     *     pages: int
     * }
     */
    public function data(string $table, int $page = 1, int $perPage = 25): array
    {
        $page = max(1, $page);
        $perPage = max(10, min(50, $perPage));
        $total = $this->countRows($table);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;
        $quotedTable = $this->quoteIdentifier($table);
        $statement = $this->database->query(
            "SELECT * FROM {$quotedTable} LIMIT {$perPage} OFFSET {$offset}"
        );
        if ($statement === null) {
            throw new RuntimeException('Nie można pobrać danych tabeli.');
        }

        $records = $statement->fetchAll(PDO::FETCH_ASSOC);
        $headers = array_map(
            static fn (array $column): string => $column['name'],
            $this->columns($table)
        );

        return [
            'headers' => $headers,
            'rows' => array_map(
                fn (array $record): array => array_map(
                    fn (string $column): string => $this->formatCell($record[$column] ?? null),
                    $headers
                ),
                $records
            ),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => $pages,
        ];
    }

    /**
     * @return array{headers: list<string>, rows: list<list<string>>, total: int, exported: int}
     */
    public function exportData(string $table, int $limit = 10000): array
    {
        $limit = max(1, min(10000, $limit));
        $quotedTable = $this->quoteIdentifier($table);
        $statement = $this->database->query("SELECT * FROM {$quotedTable} LIMIT {$limit}");
        if ($statement === null) {
            throw new RuntimeException('Nie można wyeksportować danych tabeli.');
        }

        $records = $statement->fetchAll(PDO::FETCH_ASSOC);
        $headers = array_map(
            static fn (array $column): string => $column['name'],
            $this->columns($table)
        );

        return [
            'headers' => $headers,
            'rows' => array_map(
                fn (array $record): array => array_map(
                    fn (string $column): string => $this->formatCell($record[$column] ?? null, 2048),
                    $headers
                ),
                $records
            ),
            'total' => $this->countRows($table),
            'exported' => count($records),
        ];
    }

    public function countRows(string $table): int
    {
        $quotedTable = $this->quoteIdentifier($table);
        $count = $this->database->query("SELECT COUNT(*) FROM {$quotedTable}")?->fetchColumn();

        return is_numeric($count) ? (int) $count : 0;
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    private function formatCell(mixed $value, int $maxLength = 180): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (!is_scalar($value)) {
            return '[value]';
        }

        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', ' ', (string) $value) ?? '';
        if (strlen($text) > $maxLength) {
            return substr($text, 0, max(0, $maxLength - 3)) . '...';
        }

        return $text;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        $units = ['KB', 'MB', 'GB'];
        $value = $bytes / 1024;
        foreach ($units as $unit) {
            if ($value < 1024 || $unit === 'GB') {
                return number_format($value, 2, ',', ' ') . ' ' . $unit;
            }
            $value /= 1024;
        }

        return (string) $bytes;
    }
}
