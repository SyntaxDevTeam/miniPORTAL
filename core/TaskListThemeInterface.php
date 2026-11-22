<?php

declare(strict_types=1);
namespace SyntaxDevTeam\Cms\Core;

/** Optional, data-only presentation of tasks, progress and a public plan. */
interface TaskListThemeInterface
{
    /** @param array<string, mixed> $model */
    public function render_task_list(array $model): void;
}
