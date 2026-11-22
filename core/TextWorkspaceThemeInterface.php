<?php

declare(strict_types=1);
namespace SyntaxDevTeam\Cms\Core;

interface TextWorkspaceThemeInterface
{
    /** @param list<array{token:string,label:string,value:string,protected:array}> $items */
    public function render_text_workspace(array $items, array $values): void;
}
