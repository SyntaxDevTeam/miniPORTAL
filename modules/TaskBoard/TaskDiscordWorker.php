<?php

declare(strict_types=1);
namespace SyntaxDevTeam\Cms\Modules\TaskBoard;

use PDO;
use SyntaxDevTeam\Cms\Database\CrudApp;

final class TaskDiscordWorker
{
    /** @param \Closure(string,array):array $send */
    public function __construct(private readonly CrudApp $database, private readonly TaskDiscordConfig $config, private readonly \Closure $send) {}

    /** Production callers hold the single DB worker lock across this bounded operation. */
    public function workOne(): bool
    {
        if (!$this->config->ready()) return false;
        $event = $this->database->query("SELECT * FROM task_board_notifications WHERE status='pending' AND (next_attempt_at IS NULL OR next_attempt_at<=:now) ORDER BY id LIMIT 1", [':now' => gmdate('Y-m-d H:i:s')])->fetch(PDO::FETCH_ASSOC);
        if (!is_array($event)) return false;
        $task = (new TaskRepository($this->database))->find((int)$event['task_id']);
        if ($task === null || $task['visibility'] === 'private' || $task['revision'] !== (int)$event['revision'] || $task['assignee_ids'] === []) {
            $this->database->update('task_board_notifications', ['status' => 'skipped'], ['id' => $event['id']]); return true;
        }
        $ids = [];
        foreach ($task['assignee_ids'] as $user) {
            $subjects = $this->database->query("SELECT i.provider_subject FROM user_identities i JOIN users u ON u.id=i.user_id WHERE i.user_id=:id AND i.provider='discord' AND u.status='active'", [':id' => $user])->fetchAll(PDO::FETCH_COLUMN);
            foreach ($subjects as $subject) if (preg_match('/\A[0-9]{17,20}\z/D', (string)$subject)) { $ids[] = (string)$subject; break; }
        }
        $ids = array_values(array_unique($ids));
        // Only IDs from linked accounts enter content; task text cannot create arbitrary mentions.
        $payload = ['content' => 'Zadanie dla testerów #' . $task['id'] . "\n" . implode(' ', array_map(static fn (string $id): string => '<@' . $id . '>', $ids)) . "\n" . $this->config->taskUrl($task['id']), 'allowed_mentions' => ['parse' => [], 'users' => $ids]];
        try { $result = ($this->send)($this->config->webhook(), $payload); }
        catch (\Throwable) { $result = ['status' => 0, 'retry_after' => 0]; }
        $status = (int)($result['status'] ?? 0);
        $attempts = (int)$event['attempts'] + 1;
        if ($status >= 200 && $status < 300) {
            $changes = ['status' => 'sent', 'attempts' => $attempts, 'sent_at' => gmdate('Y-m-d H:i:s'), 'last_status' => $status];
        } else {
            $permanent = $status >= 400 && $status < 500 && $status !== 429;
            $delay = max(min(3600, (int)($result['retry_after'] ?? 0)), min(3600, 30 * (2 ** $attempts)));
            $changes = ['status' => $permanent || $attempts >= 5 ? 'failed' : 'pending', 'attempts' => $attempts, 'last_status' => $status, 'next_attempt_at' => gmdate('Y-m-d H:i:s', time() + $delay)];
        }
        if ($status===429) {$changes['attempts']=(int)$event['attempts'];$changes['status']='pending';}
        $this->database->update('task_board_notifications', $changes, ['id' => $event['id']]);
        return true;
    }
}
