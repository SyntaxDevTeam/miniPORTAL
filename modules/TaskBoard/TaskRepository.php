<?php

declare(strict_types=1);
namespace SyntaxDevTeam\Cms\Modules\TaskBoard;

use PDO;
use RuntimeException;
use SyntaxDevTeam\Cms\Database\CrudApp;
use SyntaxDevTeam\Cms\Modules\CoreAuth\User;

final class TaskRepository
{
    public function __construct(private readonly CrudApp $database) {}

    public function find(int $id): ?array
    {
        $row = $this->database->query('SELECT * FROM task_board_tasks WHERE id = :id', [':id' => $id])->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function page(?User $user, array $filters, bool $public = false): array
    {
        $where = []; $params = [];
        if ($public) {
            $where[] = "t.visibility = 'public' AND p.is_published = 1";
            $columns = 't.public_title, t.public_summary, t.status, p.name AS project_name, p.slug AS project_slug';
        } else {
            if (!TaskPolicy::allows($user, 'tasks.use')) return ['tasks' => [], 'total' => 0, 'page' => 1, 'pages' => 1];
            $scope = $filters['scope'];
            if ($scope === 'oversight' && TaskPolicy::owner($user)) {
                $where[] = "t.visibility = 'private' AND t.author_id <> :actor";
            } elseif ($scope === 'mine') {
                $where[] = "(t.author_id = :actor OR (t.visibility <> 'private' AND EXISTS (SELECT 1 FROM task_board_assignees assigned WHERE assigned.task_id=t.id AND assigned.user_id=:assigned)))";
                $params[':assigned'] = $user->id;
            } else {
                $where[] = $scope === 'public' ? "t.visibility = 'public'" : "t.visibility = 'members'";
            }
            if (str_contains(implode(' ', $where), ':actor')) $params[':actor'] = $user->id;
            $columns = 't.*, p.name AS project_name, p.slug AS project_slug, COALESCE(u.display_name, \'Usunięte konto\') AS author_name, COALESCE(a.display_name, \'\') AS assignee_name';
        }
        if ($filters['project'] > 0) { $where[] = 't.project_id = :project'; $params[':project'] = $filters['project']; }
        if ($filters['status'] !== '') { $where[] = 't.status = :status'; $params[':status'] = $filters['status']; }
        if ($filters['q'] !== '') {
            $where[] = $public ? "(t.public_title LIKE :q1 ESCAPE '!' OR t.public_summary LIKE :q2 ESCAPE '!')" : "(t.title LIKE :q1 ESCAPE '!' OR t.description LIKE :q2 ESCAPE '!')";
            $params[':q1'] = $params[':q2'] = '%' . strtr($filters['q'], ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
        }
        $from = ' FROM task_board_tasks t LEFT JOIN projects p ON p.id = t.project_id';
        if (!$public) $from .= ' LEFT JOIN users u ON u.id = t.author_id LEFT JOIN users a ON a.id = t.assignee_id';
        $from .= ' WHERE ' . implode(' AND ', $where);
        $total = (int)$this->database->query('SELECT COUNT(*)' . $from, $params)->fetchColumn();
        $pages = max(1, (int)ceil($total / 24)); $page = max(1, min($pages, $filters['page']));
        $rows = $this->database->query('SELECT ' . $columns . $from . ' ORDER BY t.updated_at DESC, t.id DESC LIMIT 24 OFFSET :offset', $params + [':offset' => ($page - 1) * 24])->fetchAll(PDO::FETCH_ASSOC);
        return ['tasks' => $public ? $rows : $this->hydrateMany($rows), 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    public function create(array $data, int $author, bool $notify = false): int
    {
        return $this->transaction(function () use ($data, $author, $notify): int {
            $id = (int)$this->database->create('task_board_tasks', $this->storage($data) + ['author_id' => $author, 'revision' => 1, 'created_at' => gmdate('Y-m-d H:i:s')]);
            if ($id < 1) throw new RuntimeException('Nie zapisano zadania.');
            $this->replaceAssignees($id, $data);
            if ($notify) $this->enqueue($id, 1);
            $this->event($id, $author, 'Utworzono zadanie');
            return $id;
        });
    }

    public function update(int $id, int $revision, array $data, ?int $actor, string $event, bool $notify = false): void
    {
        $this->transaction(function () use ($id, $revision, $data, $actor, $event, $notify): void {
            $result = $this->database->update('task_board_tasks', $this->storage($data) + ['revision' => $revision + 1], ['id' => $id, 'revision' => $revision]);
            if (!$result || $result->rowCount() !== 1) throw new TaskConflict('Zadanie zmieniło się w innej karcie. Odśwież je przed zapisem.');
            $this->replaceAssignees($id, $data);
            if ($notify) $this->enqueue($id, $revision + 1);
            $this->event($id, $actor, $event);
        });
    }

    public function comments(int $id): array
    {
        return $this->database->query("SELECT c.body, c.created_at, COALESCE(u.display_name, 'Usunięte konto') AS author_name FROM task_board_comments c LEFT JOIN users u ON u.id = c.author_id WHERE c.task_id = :id ORDER BY c.id LIMIT 200", [':id' => $id])->fetchAll(PDO::FETCH_ASSOC);
    }

    public function notificationStatus(int $id): ?array
    {
        $row = $this->database->query('SELECT revision, status, attempts, last_status FROM task_board_notifications WHERE task_id=:id ORDER BY id DESC LIMIT 1', [':id' => $id])->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function attachments(int $id): array
    {
        return $this->database->query('SELECT id, media_id, name FROM task_board_attachments WHERE task_id = :id ORDER BY id LIMIT 10', [':id' => $id])->fetchAll(PDO::FETCH_ASSOC);
    }

    public function attachment(int $id): ?array
    {
        $row = $this->database->query('SELECT * FROM task_board_attachments WHERE id = :id', [':id' => $id])->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function addComment(int $id, int $actor, string $body): void
    {
        $this->addChild('task_board_comments', 200, $id, $actor, ['body' => $body], 'Dodano komentarz');
    }

    public function addAttachment(int $id, int $actor, array $data): void
    {
        $this->addChild('task_board_attachments', 10, $id, $actor, $data, 'Dodano grafikę');
    }

    public function history(int $id): array
    {
        return $this->database->query('SELECT event, created_at FROM task_board_history WHERE task_id = :id ORDER BY id DESC LIMIT 100', [':id' => $id])->fetchAll(PDO::FETCH_ASSOC);
    }

    public function automated(int $afterId, int $limit = 100, ?int $projectId = null): array
    {
        return array_map($this->hydrate(...), $this->database->query("SELECT * FROM task_board_tasks WHERE id > :after AND auto_close <> 'manual' AND status NOT IN ('done', 'cancelled') " . ($projectId !== null ? ' AND project_id = :project' : '') . " ORDER BY id LIMIT :limit", [':after' => $afterId, ':limit' => $limit] + ($projectId !== null ? [':project' => $projectId] : []))->fetchAll(PDO::FETCH_ASSOC));
    }

    private function addChild(string $table, int $limit, int $id, int $actor, array $data, string $event): void
    {
        $this->transaction(function () use ($table, $limit, $id, $actor, $data, $event): void {
            // Serialize per-task limits and visibility changes with an ordinary row lock.
            $suffix = $this->database->connection()->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
            $task = $this->database->query('SELECT * FROM task_board_tasks WHERE id = :id' . $suffix, [':id' => $id])->fetch(PDO::FETCH_ASSOC);
            if (!is_array($task) || ($task['visibility'] === 'private' && (int)$task['author_id'] !== $actor)) throw new TaskConflict('Dostęp do zadania zmienił się.');
            if ((int)$this->database->count($table, ['task_id' => $id]) >= $limit) throw new \InvalidArgumentException('Osiągnięto limit komentarzy lub screenshotów tego zadania.');
            $this->database->create($table, $data + ['task_id' => $id, 'author_id' => $actor, 'created_at' => gmdate('Y-m-d H:i:s')]);
            $this->event($id, $actor, $event);
        });
    }

    private function replaceAssignees(int $id, array $data): void
    {
        $ids = $data['assignee_ids'] ?? (empty($data['assignee_id']) ? [] : [(int)$data['assignee_id']]);
        $this->database->delete('task_board_assignees', ['task_id' => $id]);
        if ($data['visibility'] !== 'private') foreach ($ids as $user) $this->database->create('task_board_assignees', ['task_id' => $id, 'user_id' => $user]);
    }

    public function queueNotification(int $id, int $revision, User $actor): void
    {
        $this->transaction(function () use ($id, $revision, $actor): void {
            $suffix = $this->database->connection()->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
            $row = $this->database->query('SELECT * FROM task_board_tasks WHERE id=:id' . $suffix, [':id' => $id])->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row) || (int)$row['revision'] !== $revision || !TaskPolicy::notify($actor, $row)) throw new TaskConflict('Zadanie zmieniło się lub nie można wysłać powiadomienia.');
            $this->enqueue($id, $revision);
            $this->event($id, $actor->id, 'Zlecono powiadomienie Discord');
        });
    }

    private function enqueue(int $id, int $revision): void
    {
        $task = $this->find($id);
        if ($task === null || $task['visibility'] === 'private' || $task['assignee_ids'] === []) throw new \InvalidArgumentException('Powiadomienie wymaga wspólnego zadania z przypisanymi osobami.');
        $existing = $this->database->query('SELECT id, status FROM task_board_notifications WHERE task_id=:id AND revision=:revision', [':id' => $id, ':revision' => $revision])->fetch(PDO::FETCH_ASSOC);
        if (is_array($existing)) {
            if ($existing['status'] === 'failed') $this->database->update('task_board_notifications', ['status' => 'pending', 'attempts' => 0, 'next_attempt_at' => null], ['id' => $existing['id']]);
            return;
        }
        $this->database->create('task_board_notifications', ['task_id' => $id, 'revision' => $revision, 'status' => 'pending', 'attempts' => 0, 'created_at' => gmdate('Y-m-d H:i:s')]);
    }

    private function storage(array $data): array
    {
        $result = array_intersect_key($data, array_flip(['title', 'description', 'status', 'visibility', 'project_id', 'wiki_page_id', 'assignee_id', 'public_title', 'public_summary', 'auto_close', 'release_channel', 'release_version']));
        $result['assignee_id'] = array_key_exists('assignee_ids', $data) ? ($data['assignee_ids'][0] ?? null) : ($data['assignee_id'] ?? null);
        $result['checklist_json'] = json_encode($data['checklist'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $result['updated_at'] = gmdate('Y-m-d H:i:s');
        $result['closed_at'] = in_array($data['status'], ['done', 'cancelled'], true) ? ($data['closed_at'] ?? gmdate('Y-m-d H:i:s')) : null;
        return $result;
    }

    private function hydrateMany(array $rows): array
    {
        if ($rows === []) return [];
        $params = []; $slots = [];
        foreach ($rows as $index => $row) { $slots[] = ':task' . $index; $params[':task' . $index] = (int)$row['id']; }
        $assigned = $this->database->query('SELECT a.task_id, a.user_id, COALESCE(u.display_name, \'Usunięte konto\') AS display_name FROM task_board_assignees a LEFT JOIN users u ON u.id=a.user_id WHERE a.task_id IN (' . implode(',', $slots) . ') ORDER BY a.user_id', $params)->fetchAll(PDO::FETCH_ASSOC);
        $groups = [];
        foreach ($assigned as $person) $groups[(int)$person['task_id']][] = $person;
        foreach ($rows as &$row) $row['_assigned'] = $groups[(int)$row['id']] ?? [];
        unset($row);
        return array_map($this->hydrate(...), $rows);
    }

    private function hydrate(array $row): array
    {
        foreach (['id', 'author_id', 'revision'] as $key) $row[$key] = (int)$row[$key];
        foreach (['assignee_id', 'project_id', 'wiki_page_id'] as $key) $row[$key] = $row[$key] === null ? null : (int)$row[$key];
        $assigned = $row['_assigned'] ?? $this->database->query('SELECT a.user_id, COALESCE(u.display_name, \'Usunięte konto\') AS display_name FROM task_board_assignees a LEFT JOIN users u ON u.id=a.user_id WHERE a.task_id=:id ORDER BY a.user_id', [':id' => $row['id']])->fetchAll(PDO::FETCH_ASSOC);
        unset($row['_assigned']);
        $row['assignee_ids'] = array_map('intval', array_column($assigned, 'user_id'));
        $row['assignee_names'] = array_column($assigned, 'display_name');
        $row['assignee_name'] = implode(', ', $row['assignee_names']);
        $row['checklist'] = json_decode($row['checklist_json'], true, 64, JSON_THROW_ON_ERROR);
        unset($row['checklist_json']);
        return $row;
    }

    private function event(int $id, ?int $actor, string $event): void
    {
        $this->database->create('task_board_history', ['task_id' => $id, 'actor_id' => $actor, 'event' => $event, 'created_at' => gmdate('Y-m-d H:i:s')]);
    }

    private function transaction(callable $work): mixed
    {
        $pdo = $this->database->connection()->pdo;
        $pdo->beginTransaction();
        try { $result = $work(); $pdo->commit(); return $result; }
        catch (\Throwable $error) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $error; }
    }
}
