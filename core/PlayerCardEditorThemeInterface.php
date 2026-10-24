<?php

declare(strict_types=1);
namespace SyntaxDevTeam\Cms\Core;

/** Optional visual editor; all options and public-only preview data come from the module. */
interface PlayerCardEditorThemeInterface
{
    public function render_player_card_editor(array $editor): void;
}
