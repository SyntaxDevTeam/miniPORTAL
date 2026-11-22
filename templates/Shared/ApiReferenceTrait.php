<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Templates\Shared;

trait ApiReferenceTrait
{
    public function render_api_reference(array $model): void
    {
        $escape = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        foreach (['spec_url', 'guide_url', 'back_url'] as $key) {
            if (preg_match('~^/[a-zA-Z0-9/_.-]+$~D', (string) ($model[$key] ?? '')) !== 1) {
                throw new \InvalidArgumentException('API reference requires local paths.');
            }
        }
        $assets = '/templates/shared/assets/';
        echo '<link rel="stylesheet" href="' . $assets . 'vendor/swagger-ui/5.34.1/swagger-ui.css">';
        echo '<link rel="stylesheet" href="' . $assets . 'css/api-reference.css?v=' . filemtime(__DIR__ . '/../shared/assets/css/api-reference.css') . '">';
        require __DIR__ . '/views/api-reference.php';
        echo '<script defer src="' . $assets . 'vendor/swagger-ui/5.34.1/swagger-ui-bundle.js"></script>';
        echo '<script defer src="' . $assets . 'js/api-reference.js?v=' . filemtime(__DIR__ . '/../shared/assets/js/api-reference.js') . '"></script>';
    }
}
