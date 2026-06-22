<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\CorePages;

final readonly class HomepageSectionTranslation
{
    public function __construct(
        public int $sectionId,
        public string $locale,
        public string $eyebrow,
        public string $acrosticWords,
        public string $title,
        public string $contentHtml,
        public string $contentFormat,
        public string $buttonLabel,
        public string $status,
        public string $origin,
        public ?string $sourceUpdatedAt,
        public string $updatedAt,
    ) {
    }
}
