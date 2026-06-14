<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\CorePages;

final readonly class Page
{
    public function __construct(
        public int $id,
        public string $title,
        public string $slug,
        public string $summary,
        public string $metaDescription,
        public string $content,
        public string $contentFormat,
        public string $pageType,
        public string $navigationArea,
        public string $navigationLabel,
        public int $sortOrder,
        public string $status,
        public int $authorId,
        public ?string $publishedAt,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }
}
