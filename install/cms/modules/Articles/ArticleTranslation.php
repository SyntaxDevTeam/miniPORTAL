<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\Articles;

final readonly class ArticleTranslation
{
    public function __construct(
        public int $articleId,
        public string $locale,
        public string $title,
        public string $summary,
        public string $content,
        public string $contentFormat,
        public string $status,
        public string $origin,
        public ?string $sourceUpdatedAt,
        public string $updatedAt,
    ) {
    }
}
