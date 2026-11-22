<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

/** Optional presentation capability; legacy themes keep their existing fallback. */
interface DocumentationThemeInterface
{
    /** Render a documentation catalogue, project overview or reader from a data-only model. */
    public function render_documentation(array $model): void;
}
