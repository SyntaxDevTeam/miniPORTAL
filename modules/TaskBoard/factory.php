<?php

declare(strict_types=1);
use SyntaxDevTeam\Cms\Modules\TaskBoard\{TaskBoardModule, TaskRepository, TaskIntegrations, TaskDiscordConfig};

return static fn (array $services): TaskBoardModule => new TaskBoardModule(
    $services['theme'], $services['admin_menu'], new TaskRepository($services['database']),
    new TaskIntegrations($services['database']), $services['auth'], $services['security'], $services['audit'],
    new TaskDiscordConfig(dirname(__DIR__, 2) . '/config/modules/task-board-discord.json', (string)($services['config']['app']['public_url'] ?? '')),
);
