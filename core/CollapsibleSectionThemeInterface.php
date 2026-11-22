<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

/** Optional presentation capability; other themes retain expanded sections. */
interface CollapsibleSectionThemeInterface
{
    public function start_collapsible_section(string $title, string $description = '', bool $open = false): void;
    public function end_collapsible_section(): void;
}
