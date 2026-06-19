<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\PluginTranslator;

final readonly class PluginTranslationProject
{
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
        public string $description,
        public string $websiteUrl,
        public string $status,
        public ?int $createdBy,
        public int $approvedFiles,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }
}
