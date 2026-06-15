<?php

declare(strict_types=1);

use SyntaxDevTeam\Cms\Modules\LearningModule\LearningModule;
use SyntaxDevTeam\Cms\Modules\LearningModule\LearningRepository;

/**
 * Deklaratywna fabryka uruchamiana dopiero dla zainstalowanego i aktywnego modułu.
 *
 * @return callable(array<string, mixed>): LearningModule
 */
return static fn (array $services): LearningModule => new LearningModule(
    $services['theme'],
    $services['admin_menu'],
    new LearningRepository($services['database']),
    $services['auth'],
    $services['access'],
    $services['security'],
    $services['audit']
);
