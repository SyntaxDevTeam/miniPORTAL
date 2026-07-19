<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\Wikipedia;

final readonly class WikiProject
{
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
        public string $summary,
        public string $status,
        public int $sortOrder,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }
}
