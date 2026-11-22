<?php
declare(strict_types=1);
namespace SyntaxDevTeam\Cms\Core;
/** Optional presentation contract for moderated photographic collections. */
interface PhotoCollectionThemeInterface
{
    public function render_photo_collection(array $model): void;
}
