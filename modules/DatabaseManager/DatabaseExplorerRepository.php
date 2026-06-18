<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\DatabaseManager;

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
     *     pages: int,
     *     records: list<array<string, mixed>>
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
            'records' => $records,
        ];
    }

    public function primaryKey(string $table): ?string
    {
        $columns = array_values(array_filter(
            $this->columns($table),
            static fn (array $column): bool => $column['key'] === 'PRI'
        ));

        return count($columns) === 1 ? $columns[0]['name'] : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findRow(string $table, string $primaryKey, string $primaryValue): ?array
    {
        $statement = $this->database->query(
            'SELECT * FROM ' . $this->quoteIdentifier($table)
            . ' WHERE ' . $this->quoteIdentifier($primaryKey) . ' = :primary_value LIMIT 1',
            [':primary_value' => $primaryValue]
        );
        if ($statement === null) {
            throw new RuntimeException('Nie można pobrać rekordu.');
        }
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, scalar|null> $data
     */
    public function insertRow(string $table, array $data): int
    {
        if ($data === []) {
            throw new RuntimeException('Brak danych do dodania rekordu.');
        }

        $columns = array_keys($data);
        $placeholders = array_map(static fn (string $column): string => ':v_' . md5($column), $columns);
        $parameters = [];
        foreach ($columns as $index => $column) {
            $parameters[$placeholders[$index]] = $data[$column];
        }

        $statement = $this->database->query(
            'INSERT INTO ' . $this->quoteIdentifier($table)
            . ' (' . implode(', ', array_map($this->quoteIdentifier(...), $columns)) . ') VALUES ('
            . implode(', ', $placeholders) . ')',
            $parameters
        );
        if ($statement === null) {
            throw new RuntimeException('Nie można dodać rekordu.');
        }

        return $statement->rowCount();
    }

    /**
     * @param array<string, scalar|null> $data
     */
    public function updateRow(string $table, string $primaryKey, string $primaryValue, array $data): int
    {
        if ($data === []) {
            throw new RuntimeException('Brak danych do aktualizacji rekordu.');
        }

        $assignments = [];
        $parameters = [':primary_value' => $primaryValue];
        foreach ($data as $column => $value) {
            $placeholder = ':v_' . md5($column);
            $assignments[] = $this->quoteIdentifier($column) . ' = ' . $placeholder;
            $parameters[$placeholder] = $value;
        }

        $statement = $this->database->query(
            'UPDATE ' . $this->quoteIdentifier($table) . ' SET ' . implode(', ', $assignments)
            . ' WHERE ' . $this->quoteIdentifier($primaryKey) . ' = :primary_value LIMIT 1',
            $parameters
        );
        if ($statement === null) {
            throw new RuntimeException('Nie można zaktualizować rekordu.');
        }

        return $statement->rowCount();
    }

    public function deleteRow(string $table, string $primaryKey, string $primaryValue): int
    {
        $statement = $this->database->query(
            'DELETE FROM ' . $this->quoteIdentifier($table)
            . ' WHERE ' . $this->quoteIdentifier($primaryKey) . ' = :primary_value LIMIT 1',
            [':primary_value' => $primaryValue]
        );
        if ($statement === null) {
            throw new RuntimeException('Nie można usunąć rekordu.');
        }

        return $statement->rowCount();
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

    /**
     * @return array{create: string, columns: list<string>, rows: list<array<string, mixed>>, total: int, exported: int}
     */
    public function exportSqlData(string $table, int $limit = 10000): array
    {
        $limit = max(1, min(10000, $limit));
        $quotedTable = $this->quoteIdentifier($table);
        $createStatement = $this->database->query("SHOW CREATE TABLE {$quotedTable}");
        $createRow = $createStatement?->fetch(PDO::FETCH_ASSOC);
        $createSql = is_array($createRow) ? (string) ($createRow['Create Table'] ?? '') : '';
        if ($createSql === '') {
            throw new RuntimeException('Nie można pobrać definicji tabeli.');
        }

        $dataStatement = $this->database->query("SELECT * FROM {$quotedTable} LIMIT {$limit}");
        if ($dataStatement === null) {
            throw new RuntimeException('Nie można pobrać danych tabeli do eksportu SQL.');
        }
        $rows = $dataStatement->fetchAll(PDO::FETCH_ASSOC);

        return [
            'create' => $createSql,
            'columns' => array_map(
                static fn (array $column): string => $column['name'],
                $this->columns($table)
            ),
            'rows' => $rows,
            'total' => $this->countRows($table),
            'exported' => count($rows),
        ];
    }

    public function quoteSqlValue(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return $this->database->connection()->pdo->quote(is_scalar($value) ? (string) $value : '');
    }

    /**
     * @return array{headers: list<string>, rows: list<list<string>>, truncated: bool, sql: string}
     */
    public function readOnlyQuery(string $sql, int $limit = 100): array
    {
        $limit = max(1, min(200, $limit));
        $normalized = self::normalizeReadOnlyQuery($sql);
        $statement = $this->database->query($normalized);
        if ($statement === null) {
            throw new RuntimeException('Nie można wykonać zapytania SQL.');
        }

        $headers = [];
        for ($index = 0; $index < $statement->columnCount(); $index++) {
            $meta = $statement->getColumnMeta($index);
            $headers[] = is_array($meta) && isset($meta['name']) ? (string) $meta['name'] : 'column_' . ($index + 1);
        }

        $rows = [];
        while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            $rows[] = array_map(
                fn (string $column): string => $this->formatCell($row[$column] ?? null),
                $headers
            );
            if (count($rows) >= $limit + 1) {
                break;
            }
        }

        $truncated = count($rows) > $limit;
        if ($truncated) {
            array_pop($rows);
        }

        return [
            'headers' => $headers,
            'rows' => $rows,
            'truncated' => $truncated,
            'sql' => $normalized,
        ];
    }

    public static function normalizeReadOnlyQuery(string $sql): string
    {
        $sql = trim($sql);
        if ($sql === '') {
            throw new RuntimeException('Zapytanie SQL nie może być puste.');
        }
        if (strlen($sql) > 5000) {
            throw new RuntimeException('Zapytanie SQL jest zbyt długie.');
        }

        $sql = rtrim($sql);
        if (str_ends_with($sql, ';')) {
            $sql = rtrim(substr($sql, 0, -1));
        }
        if (str_contains($sql, ';')) {
            throw new RuntimeException('Konsola read-only przyjmuje tylko jedno zapytanie.');
        }
        if (preg_match('/^(select|show|describe|desc|explain)\b/i', $sql) !== 1) {
            throw new RuntimeException('Dozwolone są tylko zapytania SELECT, SHOW, DESCRIBE, DESC i EXPLAIN.');
        }

        return $sql;
    }

    /**
     * @return array{sql: string, operation: string, affected: int}
     */
    public function mutableQuery(string $sql): array
    {
        $normalized = self::normalizeMutableQuery($sql);
        $affected = $this->database->connection()->pdo->exec($normalized['sql']);
        if ($affected === false) {
            throw new RuntimeException('Nie można wykonać zapytania SQL.');
        }

        return [
            'sql' => $normalized['sql'],
            'operation' => $normalized['operation'],
            'affected' => $affected,
        ];
    }

    /**
     * @return array{sql: string, operation: string}
     */
    public static function normalizeMutableQuery(string $sql): array
    {
        $sql = self::normalizeSingleStatement($sql);
        if (preg_match('/^(insert|update|delete|replace|create|alter|drop|truncate|optimize|analyze|check|repair)\b/i', $sql, $matches) !== 1) {
            throw new RuntimeException(
                'Dozwolone są tylko pojedyncze zapytania INSERT, UPDATE, DELETE, REPLACE, CREATE, ALTER, '
                . 'DROP, TRUNCATE, OPTIMIZE, ANALYZE, CHECK i REPAIR.'
            );
        }

        return ['sql' => $sql, 'operation' => strtolower($matches[1])];
    }

    /**
     * @return array{affected: int, bytes: int}
     */
    public function importSql(string $sql): array
    {
        $normalized = self::normalizeImportSql($sql);
        $affected = $this->database->connection()->pdo->exec($normalized);
        if ($affected === false) {
            throw new RuntimeException('Nie można wykonać importu SQL.');
        }

        return ['affected' => $affected, 'bytes' => strlen($normalized)];
    }

    public static function normalizeImportSql(string $sql): string
    {
        $sql = trim($sql);
        if ($sql === '') {
            throw new RuntimeException('Import SQL nie może być pusty.');
        }
        if (strlen($sql) > 2 * 1024 * 1024) {
            throw new RuntimeException('Import SQL przekracza limit 2 MB.');
        }
        if (str_contains($sql, "\0")) {
            throw new RuntimeException('Import SQL zawiera niedozwolony bajt NUL.');
        }

        return $sql;
    }

    /**
     * @return array{operation: string, table: string, affected: int|null, rows: list<list<string>>, headers: list<string>}
     */
    public function tableOperation(string $table, string $operation): array
    {
        $operation = strtolower($operation);
        if (!in_array($operation, ['optimize', 'check', 'analyze', 'repair', 'truncate', 'drop'], true)) {
            throw new RuntimeException('Nieobsługiwana operacja tabeli.');
        }

        $quotedTable = $this->quoteIdentifier($table);
        $sql = strtoupper($operation) . ' TABLE ' . $quotedTable;
        if ($operation === 'truncate') {
            $affected = $this->database->connection()->pdo->exec($sql);
            if ($affected === false) {
                throw new RuntimeException('Nie można opróżnić tabeli.');
            }

            return ['operation' => $operation, 'table' => $table, 'affected' => $affected, 'headers' => [], 'rows' => []];
        }
        if ($operation === 'drop') {
            $affected = $this->database->connection()->pdo->exec($sql);
            if ($affected === false) {
                throw new RuntimeException('Nie można usunąć tabeli.');
            }

            return ['operation' => $operation, 'table' => $table, 'affected' => $affected, 'headers' => [], 'rows' => []];
        }

        $statement = $this->database->query($sql);
        if ($statement === null) {
            throw new RuntimeException('Nie można wykonać operacji tabeli.');
        }

        $headers = [];
        for ($index = 0; $index < $statement->columnCount(); $index++) {
            $meta = $statement->getColumnMeta($index);
            $headers[] = is_array($meta) && isset($meta['name']) ? (string) $meta['name'] : 'column_' . ($index + 1);
        }
        $rows = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rows[] = array_map(
                fn (string $column): string => $this->formatCell($row[$column] ?? null),
                $headers
            );
        }

        return ['operation' => $operation, 'table' => $table, 'affected' => null, 'headers' => $headers, 'rows' => $rows];
    }

    public function countRows(string $table): int
    {
        $quotedTable = $this->quoteIdentifier($table);
        $count = $this->database->query("SELECT COUNT(*) FROM {$quotedTable}")?->fetchColumn();

        return is_numeric($count) ? (int) $count : 0;
    }

    private static function normalizeSingleStatement(string $sql): string
    {
        $sql = trim($sql);
        if ($sql === '') {
            throw new RuntimeException('Zapytanie SQL nie może być puste.');
        }
        if (strlen($sql) > 10000) {
            throw new RuntimeException('Zapytanie SQL jest zbyt długie.');
        }

        $sql = rtrim($sql);
        if (str_ends_with($sql, ';')) {
            $sql = rtrim(substr($sql, 0, -1));
        }
        if (str_contains($sql, ';')) {
            throw new RuntimeException('Manager SQL przyjmuje tylko jedną instrukcję naraz.');
        }

        return $sql;
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
