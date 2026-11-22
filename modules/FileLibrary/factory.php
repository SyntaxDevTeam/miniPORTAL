<?php

declare(strict_types=1);
use SyntaxDevTeam\Cms\Modules\FileLibrary\{FileLibraryModule,FileRepository,FileStore};
return static fn(array $s): FileLibraryModule => new FileLibraryModule(
    $s['theme'],$s['admin_menu'],new FileRepository($s['database']),new FileStore(dirname(__DIR__,2).'/config/storage/file-library'),
    $s['auth'],$s['access'],$s['security'],$s['audit'],$s['template_cache'],$s['hooks'] ?? null,
);
