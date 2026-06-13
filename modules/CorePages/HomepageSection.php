<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\CorePages;

final readonly class HomepageSection
{
    public function __construct(
        public int $id,
        public string $sectionKey,
        public string $sectionType,
        public string $eyebrow,
        public string $title,
        public string $contentHtml,
        public string $layout,
        public string $buttonLabel,
        public string $buttonUrl,
        public int $sortOrder,
        public bool $isVisible,
        public int $authorId,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }

    /**
     * @return array{
     *     key: string,
     *     type: string,
     *     eyebrow: string,
     *     title: string,
     *     content_html: string,
     *     layout: string,
     *     button_label: string,
     *     button_url: string
     * }
     */
    public function toThemeData(): array
    {
        return [
            'key' => $this->sectionKey,
            'type' => $this->sectionType,
            'eyebrow' => $this->eyebrow,
            'title' => $this->title,
            'content_html' => $this->contentHtml,
            'layout' => $this->layout,
            'button_label' => $this->buttonLabel,
            'button_url' => $this->buttonUrl,
        ];
    }
}
