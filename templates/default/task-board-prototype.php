<?php
// Standalone development prototype using the production presentation contract.
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/core/Autoloader.php';
\SyntaxDevTeam\Cms\Core\Autoloader::register();
$theme = (new \SyntaxDevTeam\Cms\Core\ThemeEngine(dirname(__DIR__)))->load('default');
$theme->start_page('Zadania — prototyp', 'Dane przykładowe', false);
$theme->render_task_list(['public' => false, 'scope' => 'mine', 'statuses' => ['idea' => 'Pomysł', 'in_progress' => 'W toku'], 'projects' => [1 => 'miniPORTAL'], 'total' => 2, 'page' => 1, 'pages' => 1, 'tasks' => [
 ['id' => 1, 'title' => 'Uprość pierwsze kroki w projekcie', 'status' => 'idea', 'visibility' => 'private', 'project_name' => 'miniPORTAL', 'author_name' => 'Użytkownik', 'assignee_name' => '', 'checklist' => []],
 ['id' => 2, 'title' => 'Przygotuj czytelny plan rozwoju', 'status' => 'in_progress', 'visibility' => 'members', 'project_name' => 'miniPORTAL', 'author_name' => 'Użytkownik', 'assignee_name' => 'Zespół', 'checklist' => [['text' => 'Opisz pomysł', 'done' => true], ['text' => 'Sprawdź widok na telefonie', 'done' => false]]],
]]);
$theme->end_page();
