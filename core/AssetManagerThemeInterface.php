<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

/** Optional presentation contract for a searchable, selectable asset collection. */
interface AssetManagerThemeInterface
{
    /** @param array<string, mixed> $model */
    public function render_asset_manager(array $model): void;
}
