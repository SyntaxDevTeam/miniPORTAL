<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\MediaLibrary;

final readonly class MediaAsset
{
    public function __construct(
        public int $id,
        public string $title,
        public string $slug,
        public string $category,
        public string $altText,
        public string $originalName,
        public string $storedName,
        public string $publicPath,
        public string $mimeType,
        public int $fileSize,
        public ?int $width,
        public ?int $height,
        public ?int $createdBy,
        public string $createdAt,
    ) {
    }
}
