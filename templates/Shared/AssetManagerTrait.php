<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Templates\Shared;

trait AssetManagerTrait
{
    public function render_asset_manager(array $model): void
    {
        $escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        $version = (string) filemtime(__DIR__ . '/../shared/assets/js/asset-manager.js');
        $cssVersion = (string) filemtime(__DIR__ . '/../shared/assets/css/asset-manager.css');
        echo '<link rel="stylesheet" href="/templates/shared/assets/css/asset-manager.css?v=' . $escape($cssVersion) . '">';
        require __DIR__ . '/views/asset-manager.php';
        echo '<script defer src="/templates/shared/assets/js/asset-manager.js?v=' . $escape($version) . '"></script>';
    }
}
