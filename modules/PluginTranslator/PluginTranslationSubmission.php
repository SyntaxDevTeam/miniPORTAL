<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\PluginTranslator;

final readonly class PluginTranslationSubmission
{
    public function __construct(
        public int $id,
        public int $projectId,
        public string $projectName,
        public string $projectSlug,
        public ?int $userId,
        public string $authorName,
        public string $authorEmail,
        public string $title,
        public string $sourceFilename,
        public string $pluginVersion,
        public string $submissionKind,
        public string $targetLanguage,
        public string $sourceYaml,
        public string $translationsJson,
        public string $outputYaml,
        public int $totalItems,
        public int $translatedItems,
        public int $progressPercent,
        public string $status,
        public ?int $reviewerId,
        public string $reviewNote,
        public ?string $reviewedAt,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }
}
