<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

/** Public, closed display model only; the implementation owns markup and artwork. */
interface PlayerShowcaseThemeInterface
{
    public function render_player_showcase(array $card): void;
}
