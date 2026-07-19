<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\Uptime;

use PDO;
use RuntimeException;
use SyntaxDevTeam\Cms\Database\CrudApp;

final class UptimeRepository
{
    /** @var array<string, bool> */
    private array $columns = [];

    public function __construct(private readonly CrudApp $database)
    {
    }

    /** @return list<UptimeMonitor> */
    public function all(): array
    {
        $statement = $this->database->query(
            'SELECT ' . $this->columns() . ' FROM uptime_monitors ORDER BY sort_order ASC, name ASC'
        );
        if ($statement === null) {
            throw new RuntimeException('Nie można pobrać monitorów uptime.');
        }

        return array_map($this->hydrate(...), $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return list<UptimeMonitor> */
    public function publicItems(): array
    {
        $statement = $this->database->query(
            'SELECT ' . $this->columns() . ' FROM uptime_monitors '
            . 'WHERE is_visible = 1 AND is_active = 1 ORDER BY sort_order ASC, name ASC'
        );
        if ($statement === null) {
            throw new RuntimeException('Nie można pobrać publicznych monitorów uptime.');
        }

        return array_map($this->hydrate(...), $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function find(int $id): ?UptimeMonitor
    {
        $statement = $this->database->query(
            'SELECT ' . $this->columns() . ' FROM uptime_monitors WHERE id = :id LIMIT 1',
            [':id' => $id]
        );
        $row = $statement?->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function findByUuid(string $uuid): ?UptimeMonitor
    {
        if (!$this->hasColumn('monitor_uuid')) {
            return null;
        }
        $statement = $this->database->query(
            'SELECT ' . $this->columns() . ' FROM uptime_monitors WHERE monitor_uuid = :uuid LIMIT 1',
            [':uuid' => $uuid]
        );
        $row = $statement?->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function keyExists(string $key, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM uptime_monitors WHERE monitor_key = :monitor_key';
        $parameters = [':monitor_key' => $key];
        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $parameters[':id'] = $exceptId;
        }

        return (int) $this->database->query($sql, $parameters)?->fetchColumn() > 0;
    }

    /** @param array<string, scalar|null> $data */
    public function create(array $data): int
    {
        foreach (array_keys($data) as $column) {
            if (!$this->hasColumn((string) $column)) {
                unset($data[$column]);
            }
        }
        $id = (int) $this->database->create('uptime_monitors', $data);
        if ($id < 1) {
            throw new RuntimeException('Nie można utworzyć monitora uptime.');
        }

        return $id;
    }

    /** @param array<string, scalar|null> $data */
    public function update(int $id, array $data): bool
    {
        foreach (array_keys($data) as $column) {
            if (!$this->hasColumn((string) $column)) {
                unset($data[$column]);
            }
        }
        return $this->database->update('uptime_monitors', $data, ['id' => $id]) !== null;
    }

    public function delete(int $id): bool
    {
        $statement = $this->database->delete('uptime_monitors', ['id' => $id]);

        return $statement !== null && $statement->rowCount() === 1;
    }

    /** @return array{all:int,active:int,down:int} */
    public function stats(): array
    {
        return [
            'all' => $this->database->count('uptime_monitors'),
            'active' => $this->database->count('uptime_monitors', ['is_active' => 1]),
            'down' => $this->database->count('uptime_monitors', ['last_status' => 'down']),
        ];
    }

    public function widgetContent(): string
    {
        $lines = [];
        foreach ($this->publicItems() as $monitor) {
            $value = $monitor->lastMessage !== '' ? $monitor->lastMessage : $this->statusLabel($monitor->lastStatus);
            $lines[] = $monitor->name . ' | ' . $value . ' | ' . $monitor->lastStatus;
        }

        return implode("\n", $lines);
    }

    public function recordEvent(UptimeMonitor $monitor, string $event, string $status, string $message): bool
    {
        return $this->update($monitor->id, [
            'last_event' => $event,
            'last_status' => $status,
            'last_message' => $message,
            'last_event_at' => date('Y-m-d H:i:s'),
            'last_checked_at' => date('Y-m-d H:i:s'),
            'notification_sent_at' => null,
        ]);
    }

    public function markStale(UptimeMonitor $monitor, string $message, bool $notified): bool
    {
        $data = [
            'last_status' => 'down',
            'last_message' => $message,
            'last_checked_at' => date('Y-m-d H:i:s'),
        ];
        if ($notified && $this->hasColumn('notification_sent_at')) {
            $data['notification_sent_at'] = date('Y-m-d H:i:s');
        }

        return $this->update($monitor->id, $data);
    }

    /** @return list<UptimeMonitor> */
    public function staleCandidates(): array
    {
        $reference = $this->hasColumn('last_event_at')
            ? 'COALESCE(last_event_at, last_checked_at, created_at)'
            : 'COALESCE(last_checked_at, created_at)';
        $statement = $this->database->query(
            'SELECT ' . $this->columns() . ' FROM uptime_monitors '
            . 'WHERE is_active = 1 AND last_status <> :down '
            . "AND TIMESTAMPDIFF(SECOND, {$reference}, CURRENT_TIMESTAMP) > (check_interval_minutes * 60) "
            . 'ORDER BY sort_order ASC, name ASC',
            [':down' => 'down']
        );
        if ($statement === null) {
            throw new RuntimeException('Nie można pobrać przeterminowanych monitorów uptime.');
        }

        return array_map($this->hydrate(...), $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'up' => 'Online',
            'warn' => 'Degraded',
            'down' => 'Offline',
            default => 'No data',
        };
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): UptimeMonitor
    {
        return new UptimeMonitor(
            (int) $row['id'],
            (string) $row['monitor_key'],
            (string) ($row['monitor_uuid'] ?? ''),
            (string) $row['name'],
            (string) $row['target_url'],
            (string) $row['monitor_type'],
            (string) ($row['expected_event'] ?? 'online'),
            (int) $row['expected_status'],
            (int) $row['check_interval_minutes'],
            (string) ($row['notification_type'] ?? 'none'),
            (string) ($row['notification_webhook_url'] ?? ''),
            (string) $row['last_status'],
            (string) ($row['last_event'] ?? ''),
            (string) $row['last_message'],
            $row['last_event_at'] !== null ? (string) $row['last_event_at'] : null,
            $row['last_checked_at'] !== null ? (string) $row['last_checked_at'] : null,
            $row['notification_sent_at'] !== null ? (string) $row['notification_sent_at'] : null,
            (int) $row['sort_order'],
            (bool) $row['is_visible'],
            (bool) $row['is_active'],
            (string) $row['created_at'],
            (string) $row['updated_at'],
        );
    }

    private function columns(): string
    {
        return implode(', ', [
            'id',
            'monitor_key',
            $this->hasColumn('monitor_uuid') ? 'monitor_uuid' : "'' AS monitor_uuid",
            'name',
            'target_url',
            'monitor_type',
            $this->hasColumn('expected_event') ? 'expected_event' : "'online' AS expected_event",
            'expected_status',
            'check_interval_minutes',
            $this->hasColumn('notification_type') ? 'notification_type' : "'none' AS notification_type",
            $this->hasColumn('notification_webhook_url') ? 'notification_webhook_url' : "'' AS notification_webhook_url",
            'last_status',
            $this->hasColumn('last_event') ? 'last_event' : "'' AS last_event",
            'last_message',
            $this->hasColumn('last_event_at') ? 'last_event_at' : 'NULL AS last_event_at',
            'last_checked_at',
            $this->hasColumn('notification_sent_at') ? 'notification_sent_at' : 'NULL AS notification_sent_at',
            'sort_order',
            'is_visible',
            'is_active',
            'created_at',
            'updated_at',
        ]);
    }

    private function hasColumn(string $column): bool
    {
        if (array_key_exists($column, $this->columns)) {
            return $this->columns[$column];
        }
        $statement = $this->database->query("SHOW COLUMNS FROM uptime_monitors LIKE :column", [':column' => $column]);

        return $this->columns[$column] = $statement !== null && $statement->fetch(PDO::FETCH_ASSOC) !== false;
    }
}
