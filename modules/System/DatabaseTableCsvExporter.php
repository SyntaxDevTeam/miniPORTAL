<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\System;

use RuntimeException;

final class DatabaseTableCsvExporter
{
    /**
     * @param resource $stream
     * @param list<string> $headers
     * @param list<list<string>> $rows
     */
    public function write($stream, array $headers, array $rows): void
    {
        if (!is_resource($stream)) {
            throw new RuntimeException('Nie można otworzyć strumienia eksportu CSV.');
        }

        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, array_map($this->cell(...), $headers), ';', '"', '');

        foreach ($rows as $row) {
            fputcsv($stream, array_map($this->cell(...), $row), ';', '"', '');
        }
    }

    private function cell(mixed $value): string
    {
        $value = is_scalar($value) ? (string) $value : '';
        $value = preg_replace('/[\r\n\t]+/u', ' ', $value) ?? '';

        return preg_match('/^[=+\-@]/', $value) === 1 ? "'" . $value : $value;
    }
}
