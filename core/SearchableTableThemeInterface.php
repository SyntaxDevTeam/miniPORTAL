<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

/** Optional compact public search, result table and pagination; no HTML in input. */
interface SearchableTableThemeInterface
{
    /**
     * @param array{label:string,action:string,filters:list<array>,headers:list<string>,
     * rows:list<array{cells:list<string>,actions:list<array>}>,summary:string,
     * empty_message:string,reset_url:string,actions:list<array{label:string,href:string}>,
     * pagination:list<array{label:string,href:string}>} $table
     */
    public function render_searchable_table(array $table): void;
}
