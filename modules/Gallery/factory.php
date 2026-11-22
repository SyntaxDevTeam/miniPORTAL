<?php

declare(strict_types=1);

use SyntaxDevTeam\Cms\Modules\Gallery\{GalleryModule, GalleryRepository, GalleryStorage, GalleryPublicationService};
use SyntaxDevTeam\Cms\Modules\MediaLibrary\{TinifyImageOptimizer, MediaOptimizationUsageRepository};

return static function (array $services): GalleryModule {
    $config = $services['config']['modules'] ?? [];
    $optimizer = null;
    if (($config['tinify_enabled'] ?? false) === true && trim((string) ($config['tinify_api_key'] ?? '')) !== '') {
        // Resolve at publication time: factory ordering must not decide optional availability.
        $optimizer = static function (string $path) use ($services, $config): bool {
            if (!(($services['module_registry'] ?? null)?->has('media_library') ?? false)) return false;
            return (new TinifyImageOptimizer(
                trim((string) $config['tinify_api_key']),
                new MediaOptimizationUsageRepository($services['database']),
                (int) ($config['tinify_monthly_limit'] ?? 500),
            ))->optimize($path);
        };
    }
    $photos = new GalleryRepository($services['database']);
    $storage = new GalleryStorage(dirname(__DIR__, 2) . '/cache/gallery', $optimizer);
    return new GalleryModule(
        $services['theme'], $services['admin_menu'], $photos, $storage,
        $services['auth'], $services['access'], $services['security'], $services['audit'],
        new GalleryPublicationService($photos, $storage),
    );
};
