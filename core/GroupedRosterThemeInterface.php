<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

/** Optional grouped roster. Rows contain public display data only, never HTML. */
interface GroupedRosterThemeInterface
{
    /** @param list<array{label:string,members:list<array>}> $groups */
    public function render_grouped_roster(array $groups, string $refreshUrl = ''): void;
}
