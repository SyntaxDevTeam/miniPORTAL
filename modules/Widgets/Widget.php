<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\Widgets;

final readonly class Widget
{
    public function __construct(
        public int $id,
        public string $key,
        public string $name,
        public string $type,
        public string $placement,
        public string $targetSectionKey,
        public string $themeName,
        public string $title,
        public string $content,
        public string $buttonLabel,
        public string $buttonUrl,
        public int $sortOrder,
        public bool $visible,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }

    /** @return array<string, scalar> */
    public function toThemeData(): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'name' => $this->name,
            'type' => $this->type,
            'placement' => $this->placement,
            'target_section_key' => $this->targetSectionKey,
            'theme_name' => $this->themeName,
            'title' => $this->title,
            'content' => $this->content,
            'button_label' => $this->buttonLabel,
            'button_url' => $this->buttonUrl,
            'sort_order' => $this->sortOrder,
        ];
    }
}
