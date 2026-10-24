<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

/** Optional presentation of ordered slots; null entries retain their position. */
interface ItemGridThemeInterface
{
    /**
     * @param list<array|null> $slots Nonempty entries may provide a local icon_url.
     * @param array{slot_background_url?:?string,empty_slot_urls?:array<int,string>} $assets Optional presentation assets owned by the caller.
     */
    public function render_item_grid(string $title, array $slots, bool $equipment = false, array $assets = []): void;
}
