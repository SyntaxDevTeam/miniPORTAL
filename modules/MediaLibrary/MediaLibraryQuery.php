<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\MediaLibrary;

use SyntaxDevTeam\Cms\Core\Request;

final readonly class MediaLibraryQuery
{
    public const SORTS = ['newest' => 'Najnowsze', 'oldest' => 'Najstarsze', 'title' => 'Nazwa A–Z', 'size' => 'Największe'];
    public const TYPES = ['image/png' => 'PNG', 'image/jpeg' => 'JPG', 'image/webp' => 'WEBP', 'image/gif' => 'GIF', 'image/svg+xml' => 'SVG'];

    public function __construct(public array $values)
    {
    }

    public static function fromRequest(Request $request, string $preferredView = 'thumbnails'): self
    {
        $category = $request->queryString('category');
        $type = $request->queryString('type');
        $sort = $request->queryString('sort', 'newest');
        $view = $request->queryString('view', $preferredView);
        return new self([
            'view' => in_array($view, ['list', 'thumbnails'], true) ? $view : 'thumbnails',
            'q' => mb_substr($request->queryString('q'), 0, 180),
            'category' => isset(MediaAssetRepository::CATEGORIES[$category]) ? $category : '',
            'type' => isset(self::TYPES[$type]) ? $type : '',
            'alt' => $request->queryString('alt') === 'missing' ? 'missing' : '',
            'sort' => isset(self::SORTS[$sort]) ? $sort : 'newest',
            'page' => max(1, min(1000000, $request->queryInt('page', 1) ?? 1)),
        ]);
    }

    public function url(string $route = '/admin/media', array $changes = []): string
    {
        return 'index.php?' . http_build_query(['route' => $route] + array_replace($this->values, $changes), '', '&', PHP_QUERY_RFC3986);
    }
}
