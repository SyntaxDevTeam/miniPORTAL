<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\Wikipedia;

final readonly class WikiPage
{
    public function __construct(
        public int $id,
        public int $projectId,
        public string $projectName,
        public string $projectSlug,
        public string $title,
        public string $slug,
        public string $summary,
        public string $content,
        public string $contentFormat,
        public string $status,
        public int $sortOrder,
        public int $authorId,
        public ?string $publishedAt,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }
}
