<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\Projects;

final readonly class ProjectCategory
{
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
        public int $sortOrder,
        public int $projectCount,
    ) {
    }
}
