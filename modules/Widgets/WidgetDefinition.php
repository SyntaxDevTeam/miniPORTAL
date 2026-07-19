<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\Widgets;

use Closure;

final readonly class WidgetDefinition
{
    /**
     * @param array<string, scalar> $defaults
     * @param Closure(Widget): array<string, scalar> $runtimeData
     */
    public function __construct(
        public string $id,
        public string $moduleId,
        public string $label,
        public string $description,
        public string $category,
        public string $icon,
        public string $type,
        public array $defaults = [],
        private ?Closure $runtimeData = null,
    ) {
    }

    /** @return array<string, scalar> */
    public function runtimeData(Widget $widget): array
    {
        if ($this->runtimeData === null) {
            return [];
        }

        $data = ($this->runtimeData)($widget);

        return array_filter(
            $data,
            static fn (mixed $value): bool => is_scalar($value)
        );
    }
}
