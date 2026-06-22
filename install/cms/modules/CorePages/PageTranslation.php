<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\CorePages;

final readonly class PageTranslation
{
    public function __construct(
        public int $pageId,
        public string $locale,
        public string $title,
        public string $eyebrow,
        public string $summary,
        public string $metaDescription,
        public string $content,
        public string $contentFormat,
        public string $navigationLabel,
        public string $status,
        public string $origin,
        public ?string $sourceUpdatedAt,
        public string $updatedAt,
    ) {
    }
}
