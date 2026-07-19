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
        public string $itemKind,
        public string $iconKey,
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
     *     item_kind: string,
     *     icon_key: string,
     *     button_label: string,
     *     button_url: string,
     *     buttons: list<array{label: string, url: string}>,
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
            'item_kind' => $this->itemKind,
            'icon_key' => $this->iconKey,
            'button_label' => $this->buttonLabel,
            'button_url' => $this->buttonUrl,
            'buttons' => $this->buttonList($this->buttonLabel, $this->buttonUrl),
            'variant' => $this->variant,
            'width' => $this->width,
            'page_slug' => $this->pageSlug,
        ];
    }

    /**
     * @return list<array{label: string, url: string}>
     */
    private function buttonList(string $labels, string $urls): array
    {
        $labelLines = preg_split('/\R/u', trim($labels)) ?: [];
        $urlLines = preg_split('/\R/u', trim($urls)) ?: [];
        $buttons = [];
        $count = max(count($labelLines), count($urlLines));
        for ($index = 0; $index < $count; $index++) {
            $label = trim((string) ($labelLines[$index] ?? ''));
            $url = trim((string) ($urlLines[$index] ?? ''));
            if ($label === '' || $url === '') {
                continue;
            }
            $buttons[] = ['label' => $label, 'url' => $url];
        }

        return $buttons;
    }
}
