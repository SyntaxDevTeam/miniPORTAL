<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\CorePages;

final readonly class HomepageSectionItemTranslation
{
    public function __construct(
        public int $itemId,
        public string $locale,
        public string $label,
        public string $title,
        public string $content,
        public string $contentFormat,
        public string $buttonLabel,
        public string $status,
        public string $origin,
        public ?string $sourceUpdatedAt,
        public string $updatedAt,
    ) {
    }
}
