<?php

declare(strict_types=1);

use SyntaxDevTeam\Cms\Modules\Articles\ArticleRepository;
use SyntaxDevTeam\Cms\Modules\Articles\ArticlesModule;
use SyntaxDevTeam\Cms\Modules\CoreAuth\CoreAuthModule;
use SyntaxDevTeam\Cms\Modules\CoreAuth\OAuthAttemptLimiter;
use SyntaxDevTeam\Cms\Modules\CoreAuth\OAuthStateStore;
use SyntaxDevTeam\Cms\Modules\CoreAuth\UserAdministrationRepository;
use SyntaxDevTeam\Cms\Modules\CorePages\CorePagesModule;
use SyntaxDevTeam\Cms\Modules\CorePages\HomepageSectionItemRepository;
use SyntaxDevTeam\Cms\Modules\CorePages\HomepageSectionRepository;
use SyntaxDevTeam\Cms\Modules\CorePages\PageRepository;
use SyntaxDevTeam\Cms\Modules\System\SystemAdminModule;

return [
    [
        'directory' => 'CoreAuth',
        'factory' => static fn (array $services): CoreAuthModule => new CoreAuthModule(
            $services['theme'],
            $services['security'],
            $services['auth'],
            $services['providers'],
            new OAuthStateStore(),
            new OAuthAttemptLimiter(
                (int) ($services['auth_config']['oauth_window_seconds'] ?? 600),
                (int) ($services['auth_config']['oauth_start_limit'] ?? 10),
                (int) ($services['auth_config']['oauth_callback_limit'] ?? 20)
            ),
            $services['audit'],
            $services['admin_menu'],
            $services['access'],
            $services['database'] !== null
                ? new UserAdministrationRepository($services['database'])
                : null,
            $services['auth_demo_enabled']
        ),
    ],
    [
        'directory' => 'CorePages',
        'enabled' => static fn (array $services): bool => $services['database'] !== null,
        'factory' => static fn (array $services): CorePagesModule => new CorePagesModule(
            $services['theme'],
            $services['admin_menu'],
            new PageRepository($services['database']),
            new HomepageSectionRepository($services['database']),
            new HomepageSectionItemRepository($services['database']),
            $services['auth'],
            $services['access'],
            $services['security'],
            $services['audit']
        ),
    ],
    [
        'directory' => 'Articles',
        'enabled' => static fn (array $services): bool => $services['database'] !== null,
        'factory' => static fn (array $services): ArticlesModule => new ArticlesModule(
            $services['theme'],
            $services['admin_menu'],
            new ArticleRepository($services['database']),
            $services['auth'],
            $services['access'],
            $services['security'],
            $services['audit']
        ),
    ],
    [
        'directory' => 'System',
        'factory' => static fn (array $services): SystemAdminModule => new SystemAdminModule(
            $services['theme'],
            $services['admin_menu'],
            $services['auth'],
            $services['access'],
            $services['security'],
            $services['audit'],
            $services['module_manager']
        ),
    ],
];
