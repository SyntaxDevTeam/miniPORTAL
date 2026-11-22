<?php
declare(strict_types=1);
namespace SyntaxDevTeam\Cms\Templates\Shared;
trait PhotoCollectionTrait
{
    public function render_photo_collection(array $model): void
    {
        $e=static fn(mixed $v):string=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
        if (($model['scope'] ?? '') === 'moderate') {
            echo '<link rel="stylesheet" href="/templates/shared/assets/css/gallery-admin.css?v='.filemtime(__DIR__.'/../shared/assets/css/gallery-admin.css').'">';
            require __DIR__.'/views/gallery-admin.php';
            echo '<script defer src="/templates/shared/assets/js/photo-collection.js?v='.filemtime(__DIR__.'/../shared/assets/js/photo-collection.js').'"></script>';
            return;
        }
        echo '<link rel="stylesheet" href="/templates/shared/assets/css/photo-collection.css?v='.filemtime(__DIR__.'/../shared/assets/css/photo-collection.css').'">';
        require __DIR__.'/views/photo-collection.php';
        echo '<script defer src="/templates/shared/assets/js/photo-collection.js?v='.filemtime(__DIR__.'/../shared/assets/js/photo-collection.js').'"></script>';
    }
}
