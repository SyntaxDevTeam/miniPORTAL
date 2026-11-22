<?php

declare(strict_types=1);
use SyntaxDevTeam\Cms\Core\{Autoloader, ModuleStateRepository};
use SyntaxDevTeam\Cms\Database\CrudApp;
use SyntaxDevTeam\Cms\Modules\TaskBoard\{TaskDiscordConfig, TaskDiscordWorker, DiscordWebhookTransport};

if (PHP_SAPI !== 'cli') { http_response_code(404); exit(1); }
require dirname(__DIR__) . '/core/Autoloader.php'; Autoloader::register();
$database = null; $locked = false;
try {
    $config = require dirname(__DIR__) . '/config/config.php';
    if (empty($config['database']['enabled'])) throw new RuntimeException('Baza nie jest skonfigurowana.');
    $database = CrudApp::make($config['database']);
    $states = new ModuleStateRepository($database);
    foreach (['task_board', 'core_auth'] as $module) if (!$states->find($module)?->isActive()) throw new RuntimeException('Moduł powiadomień jest nieaktywny.');
    $locked = (int)$database->query("SELECT GET_LOCK('miniportal-task-board-discord', 0)")->fetchColumn() === 1;
    if (!$locked) { echo "task_board discord busy\n"; exit; }
    $discord = new TaskDiscordConfig(dirname(__DIR__) . '/config/modules/task-board-discord.json', (string)$config['app']['public_url']);
    if (!$discord->ready()) { echo "task_board discord disabled\n"; exit; }
    $transport = new DiscordWebhookTransport();
    $worker = new TaskDiscordWorker($database, $discord, $transport->send(...));
    $count = 0;
    while ($count < 10 && $worker->workOne()) $count++;
    echo 'task_board processed=' . $count . PHP_EOL;
} catch (Throwable $error) {
    error_log('[miniPORTAL] task_board_dispatch_failed ' . $error::class);
    fwrite(STDERR, "Nie można uruchomić powiadomień Task Board. Sprawdź konfigurację i stan modułu.\n"); exit(1);
} finally {
    if ($locked && $database !== null) $database->query("SELECT RELEASE_LOCK('miniportal-task-board-discord')");
}
