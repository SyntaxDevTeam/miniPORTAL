<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Templates\Shared;

trait DocumentationTrait
{
    public function render_documentation(array $model): void
    {
        $escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        $base = '/templates/shared/assets/';
        echo '<link rel="stylesheet" href="' . $base . 'css/documentation.css?v=' . filemtime(__DIR__ . '/../shared/assets/css/documentation.css') . '">';
        require __DIR__ . '/views/documentation.php';
        echo '<script defer src="' . $base . 'js/documentation.js?v=' . filemtime(__DIR__ . '/../shared/assets/js/documentation.js') . '"></script>';
    }
}
