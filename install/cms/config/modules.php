<?php

declare(strict_types=1);

use SyntaxDevTeam\Cms\Modules\Articles\ArticleRepository;
use SyntaxDevTeam\Cms\Modules\Articles\ArticlesModule;
use SyntaxDevTeam\Cms\Modules\BuildExplorer\BuildExplorerModule;
use SyntaxDevTeam\Cms\Modules\BuildExplorer\BuildArtifactStorage;
use SyntaxDevTeam\Cms\Modules\BuildExplorer\BuildRepository;
use SyntaxDevTeam\Cms\Modules\CoreAuth\CoreAuthModule;
use SyntaxDevTeam\Cms\Modules\CoreAuth\OAuthAttemptLimiter;
use SyntaxDevTeam\Cms\Modules\CoreAuth\OAuthStateStore;
use SyntaxDevTeam\Cms\Modules\CoreAuth\UserAdministrationRepository;
use SyntaxDevTeam\Cms\Modules\CorePages\CorePagesModule;
use SyntaxDevTeam\Cms\Modules\CorePages\HomepageSectionItemRepository;
use SyntaxDevTeam\Cms\Modules\CorePages\HomepageSectionRepository;
use SyntaxDevTeam\Cms\Modules\CorePages\PageRepository;
use SyntaxDevTeam\Cms\Modules\DatabaseManager\DatabaseExplorerRepository;
use SyntaxDevTeam\Cms\Modules\DatabaseManager\DatabaseManagerHistoryRepository;
use SyntaxDevTeam\Cms\Modules\DatabaseManager\DatabaseManagerModule;
use SyntaxDevTeam\Cms\Modules\Econizer\EconizerModule;
use SyntaxDevTeam\Cms\Modules\Econizer\EconizerConfig;
use SyntaxDevTeam\Cms\Modules\Econizer\EconizerDiscordGateway;
use SyntaxDevTeam\Cms\Modules\Econizer\EconizerRepository;
use SyntaxDevTeam\Cms\Modules\PluginTranslator\PluginTranslatorModule;
use SyntaxDevTeam\Cms\Modules\PluginTranslator\PluginTranslationRepository;
use SyntaxDevTeam\Cms\Modules\PluginTranslator\MinecraftFormatPreview;
use SyntaxDevTeam\Cms\Modules\PluginTranslator\PluginTranslatorYaml;
use SyntaxDevTeam\Cms\Modules\Projects\ProjectRepository;
use SyntaxDevTeam\Cms\Modules\Projects\ProjectsModule;
use SyntaxDevTeam\Cms\Modules\System\SystemAdminModule;
use SyntaxDevTeam\Cms\Modules\System\SystemLogRepository;
use SyntaxDevTeam\Cms\Modules\System\SystemSettingsRepository;
use SyntaxDevTeam\Cms\Modules\Team\TeamModule;
use SyntaxDevTeam\Cms\Modules\Team\TeamRepository;
use SyntaxDevTeam\Cms\Modules\UserProfile\UserProfileModule;
use SyntaxDevTeam\Cms\Modules\Wikipedia\WikipediaModule;
use SyntaxDevTeam\Cms\Modules\Wikipedia\WikiRepository;
use SyntaxDevTeam\Cms\Modules\Widgets\WidgetRepository;
use SyntaxDevTeam\Cms\Modules\Widgets\WidgetsModule;

return [
    [
        'directory' => 'CoreAuth',
        'required' => true,
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
        'directory' => 'UserProfile',
        'enabled' => static fn (array $services): bool => $services['database'] !== null,
        'factory' => static fn (array $services): UserProfileModule => new UserProfileModule(
            $services['theme'],
            $services['admin_menu'],
            $services['auth'],
            $services['access'],
            $services['security'],
            $services['audit']
        ),
    ],
    [
        'directory' => 'CorePages',
        'required' => true,
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
            $services['audit'],
            $services['template_cache']
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
            $services['audit'],
            $services['template_cache']
        ),
    ],
    [
        'directory' => 'Wikipedia',
        'enabled' => static fn (array $services): bool => $services['database'] !== null,
        'factory' => static fn (array $services): WikipediaModule => new WikipediaModule(
            $services['theme'],
            $services['admin_menu'],
            new WikiRepository($services['database']),
            $services['auth'],
            $services['access'],
            $services['security'],
            $services['audit'],
            $services['template_cache']
        ),
    ],
    [
        'directory' => 'DatabaseManager',
        'enabled' => static fn (array $services): bool => $services['database'] !== null,
        'factory' => static fn (array $services): DatabaseManagerModule => new DatabaseManagerModule(
            $services['theme'],
            $services['admin_menu'],
            $services['auth'],
            $services['access'],
            $services['security'],
            $services['audit'],
            new DatabaseExplorerRepository($services['database']),
            new DatabaseManagerHistoryRepository($services['database'])
        ),
    ],
    [
        'directory' => 'Projects',
        'enabled' => static fn (array $services): bool => $services['database'] !== null,
        'factory' => static fn (array $services): ProjectsModule => new ProjectsModule(
            $services['theme'],
            $services['admin_menu'],
            new ProjectRepository($services['database']),
            $services['auth'],
            $services['security'],
            $services['audit']
        ),
    ],
    [
        'directory' => 'BuildExplorer',
        'enabled' => static fn (array $services): bool => $services['database'] !== null,
        'factory' => static fn (array $services): BuildExplorerModule => new BuildExplorerModule(
            $services['theme'],
            $services['admin_menu'],
            new BuildRepository($services['database']),
            $services['auth'],
            $services['security'],
            $services['audit'],
            new BuildArtifactStorage(
                dirname(__DIR__) . '/cache/build-artifacts',
                (int) ($services['config']['modules']['build_upload_max_bytes'] ?? 20971520)
            ),
            (string) ($services['config']['modules']['build_ci_token'] ?? '')
        ),
    ],
    [
        'directory' => 'PluginTranslator',
        'factory' => static fn (array $services): PluginTranslatorModule => new PluginTranslatorModule(
            $services['theme'],
            $services['admin_menu'],
            $services['auth'],
            $services['access'],
            $services['security'],
            $services['audit'],
            new PluginTranslatorYaml(),
            new MinecraftFormatPreview(),
            $services['database'] !== null ? new PluginTranslationRepository($services['database']) : null
        ),
    ],
    [
        'directory' => 'Econizer',
        'enabled' => static fn (array $services): bool => $services['database'] !== null,
        'factory' => static function (array $services): EconizerModule {
            $config = EconizerConfig::load(dirname(__DIR__) . '/modules/Econizer');
            return new EconizerModule(
                $services['theme'],
                $services['admin_menu'],
                new EconizerRepository($services['database']),
                $services['auth'],
                $services['access'],
                $services['security'],
                $services['audit'],
                $config,
                new EconizerDiscordGateway($services['http_client'], new OAuthStateStore(), $config),
                new OAuthAttemptLimiter(600, 10, 20),
            );
        },
    ],
    [
        'directory' => 'Team',
        'enabled' => static fn (array $services): bool => $services['database'] !== null,
        'factory' => static fn (array $services): TeamModule => new TeamModule(
            $services['theme'],
            $services['admin_menu'],
            new TeamRepository($services['database']),
            $services['auth'],
            $services['access'],
            $services['security'],
            $services['audit']
        ),
    ],
    [
        'directory' => 'Widgets',
        'enabled' => static fn (array $services): bool => $services['database'] !== null,
        'factory' => static fn (array $services): WidgetsModule => new WidgetsModule(
            $services['theme'],
            $services['admin_menu'],
            new WidgetRepository($services['database']),
            $services['auth'],
            $services['security'],
            $services['audit'],
            $services['template_cache'],
            $services['available_themes'],
        ),
    ],
    [
        'directory' => 'System',
        'required' => true,
        'factory' => static fn (array $services): SystemAdminModule => new SystemAdminModule(
            $services['theme'],
            $services['admin_menu'],
            $services['auth'],
            $services['access'],
            $services['security'],
            $services['audit'],
            $services['module_manager'],
            $services['module_archive_importer'],
            $services['database'] !== null ? new SystemSettingsRepository($services['database']) : null,
            $services['database'] !== null ? new SystemLogRepository($services['database']) : null,
            $services['config'],
            $services['diagnostics'],
            $services['available_themes'],
            $services['template_cache'],
            $services['trusted_module_publishers'],
            $services['public_navigation'],
            $services['dashboard'],
            $services['brand_icon_generator'],
            $services['platform_releases'],
            $services['platform_updater'],
            $services['core_migration_runner'],
        ),
    ],
];
