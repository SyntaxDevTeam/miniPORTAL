<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\DatabaseManager;

use RuntimeException;

final class DatabaseTableSqlExporter
{
    private readonly \Closure $quoteValue;

    public function __construct(
        private readonly DatabaseExplorerRepository $database,
        ?\Closure $quoteValue = null,
    ) {
        $this->quoteValue = $quoteValue ?? fn (mixed $value): string => $this->database->quoteSqlValue($value);
    }

    /**
     * @param resource $stream
     * @param array{create: string, columns: list<string>, rows: list<array<string, mixed>>, total: int, exported: int} $export
     */
    public function write($stream, string $table, array $export): void
    {
        if (!is_resource($stream)) {
            throw new RuntimeException('Nie można otworzyć strumienia eksportu SQL.');
        }

        fwrite($stream, "-- miniPORTAL SQL export\n");
        fwrite($stream, '-- Table: ' . $table . "\n");
        fwrite($stream, '-- Exported rows: ' . $export['exported'] . ' / ' . $export['total'] . "\n\n");
        fwrite($stream, "SET NAMES utf8mb4;\n");
        fwrite($stream, "SET FOREIGN_KEY_CHECKS=0;\n\n");
        fwrite($stream, 'DROP TABLE IF EXISTS ' . $this->quoteIdentifier($table) . ";\n");
        fwrite($stream, rtrim($export['create'], ";\n\r\t ") . ";\n\n");

        $columns = $export['columns'];
        $quotedColumns = implode(', ', array_map($this->quoteIdentifier(...), $columns));
        foreach (array_chunk($export['rows'], 100) as $chunk) {
            if ($chunk === []) {
                continue;
            }
            fwrite($stream, 'INSERT INTO ' . $this->quoteIdentifier($table) . ' (' . $quotedColumns . ") VALUES\n");
            $values = [];
            foreach ($chunk as $row) {
                $values[] = '(' . implode(', ', array_map(
                    fn (string $column): string => ($this->quoteValue)($row[$column] ?? null),
                    $columns
                )) . ')';
            }
            fwrite($stream, implode(",\n", $values) . ";\n\n");
        }

        fwrite($stream, "SET FOREIGN_KEY_CHECKS=1;\n");
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }
}
