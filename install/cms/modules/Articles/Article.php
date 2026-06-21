<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\Articles;

final readonly class Article
{
    public function __construct(
        public int $id,
        public int $categoryId,
        public string $categoryName,
        public string $title,
        public string $slug,
        public string $summary,
        public string $content,
        public string $contentFormat,
        public string $status,
        public int $authorId,
        public ?string $publishedAt,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }
}
