<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\System;

use RuntimeException;

final class AuditCsvExporter
{
    /**
     * @param resource $stream
     * @param list<array<string, scalar|null>> $events
     */
    public function write($stream, array $events): void
    {
        if (!is_resource($stream)) {
            throw new RuntimeException('Nie można otworzyć strumienia eksportu CSV.');
        }

        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv(
            $stream,
            ['Data', 'Użytkownik', 'Zdarzenie', 'Wynik', 'Kontekst', 'Skrót IP', 'User-Agent'],
            ';',
            '"',
            ''
        );

        foreach ($events as $event) {
            fputcsv(
                $stream,
                [
                    $this->cell($event['created_at'] ?? ''),
                    $this->cell($event['display_name'] ?? 'System / gość'),
                    $this->cell($event['event_type'] ?? ''),
                    $this->cell($event['result'] ?? ''),
                    $this->cell($event['provider'] ?? ''),
                    $this->cell(
                        $event['ip_hash'] !== null
                            ? substr((string) $event['ip_hash'], 0, 12)
                            : ''
                    ),
                    $this->cell($event['user_agent'] ?? ''),
                ],
                ';',
                '"',
                ''
            );
        }
    }

    private function cell(mixed $value): string
    {
        $value = is_scalar($value) ? (string) $value : '';
        $value = preg_replace('/[\r\n\t]+/u', ' ', $value) ?? '';

        return preg_match('/^[=+\-@]/', $value) === 1 ? "'" . $value : $value;
    }
}
