<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\CorePages;

final readonly class HomepageSectionItem
{
    public function __construct(
        public int $id,
        public int $sectionId,
        public ?int $pageId,
        public string $pageSlug,
        public string $label,
        public string $title,
        public string $content,
        public string $contentFormat,
        public string $buttonLabel,
        public string $buttonUrl,
        public string $variant,
        public string $width,
        public int $sortOrder,
        public bool $isVisible,
    ) {
    }

    /**
     * @return array{
     *     label: string,
     *     title: string,
     *     content: string,
     *     content_format: string,
     *     button_label: string,
     *     button_url: string,
     *     variant: string,
     *     width: string
     * }
     */
    public function toThemeData(): array
    {
        return [
            'label' => $this->label,
            'title' => $this->title,
            'content' => $this->content,
            'content_format' => $this->contentFormat,
            'button_label' => $this->buttonLabel,
            'button_url' => $this->buttonUrl,
            'variant' => $this->variant,
            'width' => $this->width,
            'page_slug' => $this->pageSlug,
        ];
    }
}
