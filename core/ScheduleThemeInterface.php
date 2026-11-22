<?php

declare(strict_types=1);
namespace SyntaxDevTeam\Cms\Core;

/** Optional data-only schedule presentation, independent of a CSS framework. */
interface ScheduleThemeInterface
{
    public function render_schedule(array $model): void;
}
