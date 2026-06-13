<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\CorePages;

final readonly class Page
{
    public function __construct(
        public int $id,
        public string $title,
        public string $slug,
        public string $content,
        public string $status,
        public int $authorId,
        public ?string $publishedAt,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }
}
