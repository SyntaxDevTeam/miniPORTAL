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
     *     button_url: string,
     *     items: list<array{
     *         label: string,
     *         title: string,
     *         content: string,
     *         button_label: string,
     *         button_url: string,
     *         variant: string,
     *         width: string,
     *         page_slug: string
     *     }>
     * }
     */
    public function toThemeData(array $items = []): array
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
            'items' => array_map(
                static fn (HomepageSectionItem $item): array => $item->toThemeData(),
                $items
            ),
        ];
    }
}
