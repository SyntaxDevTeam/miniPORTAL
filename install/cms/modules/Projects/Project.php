<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\Projects;

final readonly class Project
{
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
        public string $summary,
        public string $lifecycleStatus,
        public ?int $pageId,
        public ?int $wikiProjectId,
        public int $sortOrder,
        public bool $published,
        public string $pageTitle,
        public string $pageSlug,
        public string $pageStatus,
        public string $wikiName,
        public string $wikiSlug,
        public string $wikiStatus,
    ) {
    }
}
