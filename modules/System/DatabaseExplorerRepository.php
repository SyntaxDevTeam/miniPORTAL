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
