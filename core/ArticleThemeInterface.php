<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

interface ArticleThemeInterface
{
    /** @param array{path: string, language?: string, published?: string, modified?: string} $metadata */
    public function start_article_page(string $title, string $description, array $metadata, bool $indexable = true): void;

    public function render_article_catalog(array $model): void;
}
