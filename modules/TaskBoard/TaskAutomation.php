<?php

declare(strict_types=1);
namespace SyntaxDevTeam\Cms\Modules\TaskBoard;

final class TaskAutomation
{
    public function __construct(private readonly TaskRepository $tasks, private readonly TaskIntegrations $integrations) {}

    public function apply(array $data): array
    {
        if (!in_array($data['status'], ['done', 'cancelled'], true) && (TaskInput::completed($data) || ($data['auto_close'] === 'release' && $this->integrations->releaseExists($data)))) $data['status'] = 'done';
        return $data;
    }

    public function run(?int $projectId = null): int
    {
        $after = 0; $count = 0;
        do {
            $batch = $this->tasks->automated($after, 100, $projectId);
            foreach ($batch as $task) {
                $after = $task['id'];
                $updated = $this->apply($task);
                if ($updated['status'] === $task['status']) continue;
                try { $this->tasks->update($task['id'], $task['revision'], $updated, null, 'Automatyczne zamknięcie: ' . ($task['auto_close'] === 'release' ? 'opublikowane wydanie' : 'ukończona checklista')); $count++; }
                catch (TaskConflict) { /* A concurrent edit wins; re-evaluate on the next run. */ }
            }
        } while (count($batch) === 100);
        return $count;
    }
}
