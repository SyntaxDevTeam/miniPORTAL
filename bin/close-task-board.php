<?php

declare(strict_types=1);
use SyntaxDevTeam\Cms\Core\{Autoloader, ModuleStateRepository};
use SyntaxDevTeam\Cms\Database\CrudApp;
use SyntaxDevTeam\Cms\Modules\TaskBoard\{TaskAutomation, TaskIntegrations, TaskRepository};

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/core/Autoloader.php';
Autoloader::register();
$config = require dirname(__DIR__) . '/config/config.php';
$db = CrudApp::make($config['database']);
$states = new ModuleStateRepository($db);
foreach (['task_board', 'core_auth', 'projects', 'wikipedia', 'media_library', 'build_explorer'] as $dependency) {
    if (!$states->find($dependency)?->isActive()) { fwrite(STDERR, 'Nieaktywny moduł: ' . $dependency . PHP_EOL); exit(1); }
}
try {
    $count = (new TaskAutomation(new TaskRepository($db), new TaskIntegrations($db)))->run();
    echo 'Automatycznie zamknięte zadania: ' . $count . PHP_EOL;
} catch (Throwable $error) {
    error_log('[miniPORTAL] task_board_automation_failed ' . $error::class);
    fwrite(STDERR, "Automatyzacja nie powiodła się; sprawdź log serwera.\n"); exit(1);
}
