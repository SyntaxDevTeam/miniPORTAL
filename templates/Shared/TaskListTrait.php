<?php

declare(strict_types=1);
namespace SyntaxDevTeam\Cms\Templates\Shared;

trait TaskListTrait
{
    public function render_task_list(array $model): void
    {
        $escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        $version = (string) filemtime(__DIR__ . '/../shared/assets/css/task-list.css');
        echo '<link rel="stylesheet" href="/templates/shared/assets/css/task-list.css?v=' . $escape($version) . '">';
        require __DIR__ . '/views/task-list.php';
        if (!empty($model['prompt']) && empty($model['public'])) {
            $scriptVersion = (string) filemtime(__DIR__ . '/../shared/assets/js/task-prompt.js');
            echo '<script defer src="/templates/shared/assets/js/task-prompt.js?v=' . $escape($scriptVersion) . '"></script>';
        }
    }
}
