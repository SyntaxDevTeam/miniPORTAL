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
        public string $acrosticWords,
        public string $title,
        public string $contentHtml,
        public string $contentFormat,
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
     *     acrostic_words: string,
     *     title: string,
     *     content_html: string,
     *     content_format: string,
     *     layout: string,
     *     button_label: string,
     *     button_url: string,
     *     buttons: list<array{label: string, url: string}>,
     *     items: list<array{
     *         label: string,
     *         title: string,
     *         content: string,
     *         content_format: string,
     *         item_kind: string,
     *         icon_key: string,
     *         button_label: string,
     *         button_url: string,
     *         buttons: list<array{label: string, url: string}>,
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
            'acrostic_words' => $this->acrosticWords,
            'title' => $this->title,
            'content_html' => $this->contentHtml,
            'content_format' => $this->contentFormat,
            'layout' => $this->layout,
            'button_label' => $this->buttonLabel,
            'button_url' => $this->buttonUrl,
            'buttons' => $this->buttonList($this->buttonLabel, $this->buttonUrl),
            'items' => array_map(
                static fn (HomepageSectionItem $item): array => $item->toThemeData(),
                $items
            ),
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
