<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\Articles;

final readonly class Category
{
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
    ) {
    }
}
