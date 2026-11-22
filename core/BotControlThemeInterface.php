<?php

declare(strict_types=1);
namespace SyntaxDevTeam\Cms\Core;
/** Optional presentation capability: data-only bot features, configuration and previews. */
interface BotControlThemeInterface
{
    public function render_bot_control(array $model): void;
}
