<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\BuildExplorer;

final readonly class ProjectBuild
{
    public function __construct(
        public int $id,
        public int $projectId,
        public string $projectName,
        public string $projectSlug,
        public bool $projectPublished,
        public string $serverType,
        public string $versionLabel,
        public string $channel,
        public string $buildNumber,
        public string $filename,
        public string $storageKey,
        public string $downloadUrl,
        public string $checksumSha256,
        public ?int $fileSizeBytes,
        public string $changelog,
        public bool $published,
        public ?string $publishedAt,
        public ?int $ciBuildId,
        public ?string $ciBuildTime,
        /** @var list<array{sha: string, time: string, message: string}> */
        public array $commits,
    ) {
    }
}
