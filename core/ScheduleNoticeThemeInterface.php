<?php

declare(strict_types=1);
namespace SyntaxDevTeam\Cms\Core;

/** Optional homepage notice; existing schedule themes remain compatible. */
interface ScheduleNoticeThemeInterface
{
    public function set_schedule_notice(bool $enabled): void;
}
