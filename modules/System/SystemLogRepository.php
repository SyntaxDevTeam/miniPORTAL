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

    /**
     * @param array{event_type?: string, result?: string, date_from?: string, date_to?: string} $filters
     */
    public function count(array $filters = []): int
    {
        [$where, $parameters] = $this->where($filters);
        $statement = $this->database->query(
            'SELECT COUNT(*) FROM auth_events ' . $where,
            $parameters
        );

        return (int) ($statement?->fetchColumn() ?: 0);
    }

    public function hiddenRoutineAccessCount(): int
    {
        return $this->database->count('auth_events', [
            'event_type' => 'admin_access',
            'result' => 'allowed',
        ]);
    }

    /**
     * @return list<array<string, scalar|null>>
     */
    public function page(int $page, int $perPage = 50, array $filters = []): array
    {
        $page = max(1, $page);
        $perPage = max(10, min(100, $perPage));
        $offset = ($page - 1) * $perPage;
        [$where, $parameters] = $this->where($filters);
        $parameters[':limit'] = $perPage;
        $parameters[':offset'] = $offset;
        $statement = $this->database->query(
            'SELECT auth_events.id, auth_events.event_type, auth_events.result, '
            . 'auth_events.provider, auth_events.ip_hash, auth_events.user_agent, '
            . 'auth_events.created_at, users.display_name '
            . 'FROM auth_events LEFT JOIN users ON users.id = auth_events.user_id '
            . $where . ' '
            . 'ORDER BY auth_events.id DESC LIMIT :limit OFFSET :offset',
            $parameters
        );
        if ($statement === null) {
            throw new RuntimeException('Nie można pobrać dziennika zdarzeń.');
        }

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param array{event_type?: string, result?: string, date_from?: string, date_to?: string} $filters
     * @return list<array<string, scalar|null>>
     */
    public function export(array $filters = [], int $limit = 10000): array
    {
        $limit = max(1, min(10000, $limit));
        [$where, $parameters] = $this->where($filters);
        $parameters[':limit'] = $limit;
        $statement = $this->database->query(
            'SELECT auth_events.id, auth_events.event_type, auth_events.result, '
            . 'auth_events.provider, auth_events.ip_hash, auth_events.user_agent, '
            . 'auth_events.created_at, users.display_name '
            . 'FROM auth_events LEFT JOIN users ON users.id = auth_events.user_id '
            . $where . ' '
            . 'ORDER BY auth_events.id DESC LIMIT :limit',
            $parameters
        );
        if ($statement === null) {
            throw new RuntimeException('Nie można wyeksportować dziennika zdarzeń.');
        }

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array{event_types: list<string>, results: list<string>}
     */
    public function filterOptions(): array
    {
        $eventTypes = $this->database->query(
            'SELECT DISTINCT event_type FROM auth_events ORDER BY event_type'
        )?->fetchAll(PDO::FETCH_COLUMN) ?: [];
        $results = $this->database->query(
            'SELECT DISTINCT result FROM auth_events ORDER BY result'
        )?->fetchAll(PDO::FETCH_COLUMN) ?: [];

        return [
            'event_types' => array_map('strval', $eventTypes),
            'results' => array_map('strval', $results),
        ];
    }

    /**
     * @return array{cutoff: string, archived: int, deleted: int}
     */
    public function archiveOlderThan(int $retentionDays, int $limit): array
    {
        $retentionDays = max(1, min(3650, $retentionDays));
        $limit = max(1, min(10000, $limit));
        $cutoff = gmdate('Y-m-d H:i:s', time() - ($retentionDays * 86400));
        $pdo = $this->database->connection()->pdo;
        $ids = [];
        $select = $pdo->prepare(
            'SELECT id FROM auth_events WHERE created_at < :cutoff ORDER BY id ASC LIMIT :limit'
        );
        $select->bindValue(':cutoff', $cutoff);
        $select->bindValue(':limit', $limit, PDO::PARAM_INT);
        $select->execute();
        foreach ($select->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $ids[] = (int) $id;
        }
        if ($ids === []) {
            return ['cutoff' => $cutoff, 'archived' => 0, 'deleted' => 0];
        }

        $pdo->beginTransaction();
        try {
            $archived = 0;
            $insert = $pdo->prepare(
                'INSERT IGNORE INTO auth_events_archive '
                . '(source_id, user_id, provider, event_type, result, ip_hash, user_agent, created_at) '
                . 'SELECT id, user_id, provider, event_type, result, ip_hash, user_agent, created_at '
                . 'FROM auth_events WHERE id = :id'
            );
            foreach ($ids as $id) {
                $insert->execute([':id' => $id]);
                $archived += $insert->rowCount();
            }
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $delete = $pdo->prepare('DELETE FROM auth_events WHERE id IN (' . $placeholders . ')');
            $delete->execute($ids);
            $deleted = $delete->rowCount();
            $pdo->commit();
        } catch (\Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }

        return ['cutoff' => $cutoff, 'archived' => $archived, 'deleted' => $deleted];
    }

    /**
     * @param array{event_type?: string, result?: string, date_from?: string, date_to?: string} $filters
     * @return array{0: string, 1: array<string, scalar>}
     */
    private function where(array $filters): array
    {
        $conditions = ['NOT (auth_events.event_type = :routine_event AND auth_events.result = :routine_result)'];
        $parameters = [':routine_event' => 'admin_access', ':routine_result' => 'allowed'];
        if (($filters['event_type'] ?? '') !== '') {
            $conditions[] = 'auth_events.event_type = :filter_event';
            $parameters[':filter_event'] = $filters['event_type'];
        }
        if (($filters['result'] ?? '') !== '') {
            $conditions[] = 'auth_events.result = :filter_result';
            $parameters[':filter_result'] = $filters['result'];
        }
        if (($filters['date_from'] ?? '') !== '') {
            $conditions[] = 'auth_events.created_at >= :date_from';
            $parameters[':date_from'] = $filters['date_from'] . ' 00:00:00';
        }
        if (($filters['date_to'] ?? '') !== '') {
            $conditions[] = 'auth_events.created_at < DATE_ADD(:date_to, INTERVAL 1 DAY)';
            $parameters[':date_to'] = $filters['date_to'];
        }

        return ['WHERE ' . implode(' AND ', $conditions), $parameters];
    }
}
