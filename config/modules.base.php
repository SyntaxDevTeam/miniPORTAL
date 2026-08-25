<?php

declare(strict_types=1);

use SyntaxDevTeam\Cms\Modules\Articles\ArticleRepository;
use SyntaxDevTeam\Cms\Modules\Articles\ArticlesModule;
use SyntaxDevTeam\Cms\Modules\AuthGatewayX\AuthGatewayXModule;
use SyntaxDevTeam\Cms\Modules\AuthGatewayX\AuthGatewayXApiController;
use SyntaxDevTeam\Cms\Modules\AuthGatewayX\AuthGatewayXRepository;
use SyntaxDevTeam\Cms\Modules\AuthGatewayX\AuthGatewayXService;
use SyntaxDevTeam\Cms\Modules\AuthGatewayX\MinecraftIdentityRepository;
use SyntaxDevTeam\Cms\Modules\AuthGatewayX\Security\ChallengeCodeGenerator;
use SyntaxDevTeam\Cms\Modules\AuthGatewayX\Security\IpBinding;
use SyntaxDevTeam\Cms\Modules\AuthGatewayX\Security\ProviderAuthenticator;
use SyntaxDevTeam\Cms\Modules\AccountPrivacy\AccountPrivacyModule;
use SyntaxDevTeam\Cms\Modules\AccountPrivacy\AccountPrivacyRepository;
use SyntaxDevTeam\Cms\Modules\BuildExplorer\BuildExplorerModule;
use SyntaxDevTeam\Cms\Modules\BuildExplorer\BuildArtifactStorage;
use SyntaxDevTeam\Cms\Modules\BuildExplorer\BuildRepository;
use SyntaxDevTeam\Cms\Modules\CoreAuth\CoreAuthModule;
use SyntaxDevTeam\Cms\Modules\CoreAuth\OAuthAttemptLimiter;
use SyntaxDevTeam\Cms\Modules\CoreAuth\OAuthStateStore;
use SyntaxDevTeam\Cms\Modules\CoreAuth\UserAdministrationRepository;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuthProviderConfigStore;
use SyntaxDevTeam\Cms\Modules\CorePages\CorePagesModule;
use SyntaxDevTeam\Cms\Modules\CorePages\HomepageSectionItemRepository;
use SyntaxDevTeam\Cms\Modules\CorePages\HomepageSectionRepository;
use SyntaxDevTeam\Cms\Modules\CorePages\PageRepository;
use SyntaxDevTeam\Cms\Modules\DistributionAccess\DistributionAccessModule;
use SyntaxDevTeam\Cms\Modules\DistributionAccess\DistributionAccessRepository;
use SyntaxDevTeam\Cms\Modules\CraftCatalog\CraftCatalogModule;
use SyntaxDevTeam\Cms\Modules\CraftCatalog\CustomResourcePricing;
use SyntaxDevTeam\Cms\Modules\CraftCatalog\HostingPlanRepository;
use SyntaxDevTeam\Cms\Modules\CraftCatalog\OfferContentRepository;
use SyntaxDevTeam\Cms\Modules\CraftPayments\CraftOrderRepository;
use SyntaxDevTeam\Cms\Modules\CraftPayments\CraftWalletRepository;
use SyntaxDevTeam\Cms\Modules\CraftPayments\CraftPaymentsModule;
use SyntaxDevTeam\Cms\Modules\CraftPayments\HotPayConfig;
use SyntaxDevTeam\Cms\Modules\CraftPayments\HotPayGateway;
use SyntaxDevTeam\Cms\Modules\CraftPayments\HotPayTransport;
use SyntaxDevTeam\Cms\Modules\CraftSupport\CraftSupportConfig;
use SyntaxDevTeam\Cms\Modules\CraftSupport\CraftSupportModule;
use SyntaxDevTeam\Cms\Modules\CraftSupport\CraftSupportRepository;
use SyntaxDevTeam\Cms\Modules\DatabaseManager\DatabaseExplorerRepository;
use SyntaxDevTeam\Cms\Modules\DatabaseManager\DatabaseManagerHistoryRepository;
use SyntaxDevTeam\Cms\Modules\DatabaseManager\DatabaseManagerModule;
use SyntaxDevTeam\Cms\Modules\Econizer\EconizerModule;
use SyntaxDevTeam\Cms\Modules\Econizer\EconizerConfig;
use SyntaxDevTeam\Cms\Modules\Econizer\EconizerDiscordGateway;
use SyntaxDevTeam\Cms\Modules\Econizer\EconizerRepository;
use SyntaxDevTeam\Cms\Modules\EconizerBotStudio\EconizerBotStudioModule;
use SyntaxDevTeam\Cms\Modules\EconizerBotStudio\EconizerBotStudioOptionParser;
use SyntaxDevTeam\Cms\Modules\EconizerBotStudio\EconizerBotStudioRepository;
use SyntaxDevTeam\Cms\Modules\EconizerBotStudio\EconizerBotStudioSimulator;
use SyntaxDevTeam\Cms\Modules\EconizerBotStudio\EconizerBotStudioWorkflowParser;
use SyntaxDevTeam\Cms\Modules\Licences\LicenceRepository;
use SyntaxDevTeam\Cms\Modules\Licences\LicencesModule;
use SyntaxDevTeam\Cms\Modules\Hosting\HostingModule;
use SyntaxDevTeam\Cms\Modules\Hosting\HostingRepository;
use SyntaxDevTeam\Cms\Modules\MediaLibrary\MediaAssetRepository;
use SyntaxDevTeam\Cms\Modules\MediaLibrary\MediaAssetStorage;
use SyntaxDevTeam\Cms\Modules\MediaLibrary\MediaOptimizationUsageRepository;
use SyntaxDevTeam\Cms\Modules\MediaLibrary\MediaLibraryModule;
use SyntaxDevTeam\Cms\Modules\MediaLibrary\TinifyImageOptimizer;
use SyntaxDevTeam\Cms\Modules\MCServerInstaller\EngineVersionCatalog;
use SyntaxDevTeam\Cms\Modules\MCServerInstaller\LeavesDownloadsClient;
use SyntaxDevTeam\Cms\Modules\MCServerInstaller\MCServerInstallationRepository;
use SyntaxDevTeam\Cms\Modules\MCServerInstaller\MCServerInstallationStorage;
use SyntaxDevTeam\Cms\Modules\MCServerInstaller\MCServerInstallerModule;
use SyntaxDevTeam\Cms\Modules\MCServerInstaller\PaperMcDownloadsClient;
use SyntaxDevTeam\Cms\Modules\MCServerInstaller\PufferfishDownloadsClient;
use SyntaxDevTeam\Cms\Modules\MCServerInstaller\PurpurDownloadsClient;
use SyntaxDevTeam\Cms\Modules\MCServerInstaller\MojangDownloadsClient;
use SyntaxDevTeam\Cms\Modules\MCServerInstaller\ServerEngineCatalog;
use SyntaxDevTeam\Cms\Modules\MCServerInstaller\ServerProfileCatalog;
use SyntaxDevTeam\Cms\Modules\MCServerInstaller\SpigotVersionsClient;
use SyntaxDevTeam\Cms\Modules\MCServerInstaller\VelocityDownloadsClient;
use SyntaxDevTeam\Cms\Modules\PluginManager\PluginManagerModule;
use SyntaxDevTeam\Cms\Modules\PluginManager\PluginManagerRepository;
use SyntaxDevTeam\Cms\Modules\PluginManager\BuildExplorerPluginResolver;
use SyntaxDevTeam\Cms\Modules\PluginManager\CommonPluginCatalog;
use SyntaxDevTeam\Cms\Modules\MinecraftConsole\MinecraftConsoleModule;
use SyntaxDevTeam\Cms\Modules\MinecraftConsole\MinecraftConsoleRepository;
use SyntaxDevTeam\Cms\Modules\NodeManager\NodeAgentConfig;
use SyntaxDevTeam\Cms\Modules\NodeManager\NodeManagerModule;
use SyntaxDevTeam\Cms\Modules\NodeManager\UnixSocketNodeAgentClient;
use SyntaxDevTeam\Cms\Modules\PluginTranslator\PluginTranslatorModule;
use SyntaxDevTeam\Cms\Modules\PluginTranslator\PluginTranslationRepository;
use SyntaxDevTeam\Cms\Modules\PluginTranslator\MinecraftFormatPreview;
use SyntaxDevTeam\Cms\Modules\PluginTranslator\PluginTranslatorYaml;
use SyntaxDevTeam\Cms\Modules\PluginStats\PluginStatsModule;
use SyntaxDevTeam\Cms\Modules\PluginStats\PluginStatsRepository;
use SyntaxDevTeam\Cms\Modules\Projects\ProjectRepository;
use SyntaxDevTeam\Cms\Modules\Projects\ProjectsModule;
use SyntaxDevTeam\Cms\Modules\RemoteTerminal\RemoteTerminalConfig;
use SyntaxDevTeam\Cms\Modules\RemoteTerminal\RemoteTerminalModule;
use SyntaxDevTeam\Cms\Modules\CoreAuth\RegistrationConfig;
use SyntaxDevTeam\Cms\Modules\System\SystemAdminModule;
use SyntaxDevTeam\Cms\Modules\System\SystemLogRepository;
use SyntaxDevTeam\Cms\Modules\System\SystemSettingsRepository;
use SyntaxDevTeam\Cms\Modules\Team\TeamModule;
use SyntaxDevTeam\Cms\Modules\Team\TeamRepository;
use SyntaxDevTeam\Cms\Modules\Uptime\UptimeModule;
use SyntaxDevTeam\Cms\Modules\Uptime\UptimeRepository;
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
            RegistrationConfig::fromArray($services['auth_config']),
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
        'directory' => 'NodeManager',
        'enabled' => static fn (array $services): bool => $services['database'] !== null,
        'factory' => static function (array $services): NodeManagerModule {
            $config = NodeAgentConfig::fromModuleConfig($services['config']['modules'] ?? []);
            return new NodeManagerModule(
                $services['theme'],
                $services['admin_menu'],
                new UnixSocketNodeAgentClient($config),
                $config,
                $services['auth'],
                $services['security'],
                $services['audit']
            );
        },
    ],
    [
        'directory' => 'Hosting',
        'enabled' => static fn (array $services): bool => $services['database'] !== null,
        'factory' => static function (array $services): HostingModule {
            $modules = is_array($services['config']['modules'] ?? null) ? $services['config']['modules'] : [];
            $config = NodeAgentConfig::fromModuleConfig($modules);
            return new HostingModule(
                $services['theme'],
                $services['admin_menu'],
                new HostingRepository($services['database']),
                new UnixSocketNodeAgentClient($config),
                $services['auth'],
                $services['security'],
                $services['audit'],
                $modules
            );
        },
    ],
    [
        'directory' => 'AccountPrivacy',
        'enabled' => static fn (array $services): bool => $services['database'] !== null,
        'factory' => static fn (array $services): AccountPrivacyModule => new AccountPrivacyModule(
            $services['theme'],
            $services['admin_menu'],
            new AccountPrivacyRepository($services['database']),
            $services['auth'],
            $services['security'],
            $services['audit'],
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
            $services['template_cache'],
            new MediaAssetRepository($services['database'])
        ),
    ],
    [
        'directory' => 'CraftCatalog',
        'enabled' => static fn (array $services): bool => $services['database'] !== null,
        'factory' => static fn (array $services): CraftCatalogModule => new CraftCatalogModule(
            $services['theme'],
            $services['admin_menu'],
            new HostingPlanRepository($services['database']),
            new OfferContentRepository($services['database']),
            new CustomResourcePricing(new OfferContentRepository($services['database'])),
            $services['auth'],
            $services['security'],
            $services['audit']
        ),
    ],
    [
        'directory' => 'CraftPayments',
        'enabled' => static fn (array $services): bool => $services['database'] !== null,
        'factory' => static function (array $services): CraftPaymentsModule {
            $hotPay = HotPayConfig::load(dirname(__DIR__) . '/modules/CraftPayments');
            return new CraftPaymentsModule(
                $services['theme'],
                $services['admin_menu'],
                new CraftOrderRepository($services['database']),
                new CraftWalletRepository($services['database']),
                new HostingPlanRepository($services['database']),
                new CustomResourcePricing(new OfferContentRepository($services['database'])),
                $hotPay,
                new HotPayGateway($hotPay, new HotPayTransport()),
                $services['auth'],
                $services['security'],
                $services['audit']
            );
        },
    ],
    [
        'directory' => 'CraftSupport',
        'enabled' => static fn (array $services): bool => $services['database'] !== null,
        'factory' => static fn (array $services): CraftSupportModule => new CraftSupportModule(
            $services['theme'],
            $services['admin_menu'],
            new CraftSupportRepository($services['database']),
            new CraftOrderRepository($services['database']),
            CraftSupportConfig::load(dirname(__DIR__) . '/modules/CraftSupport'),
            $services['auth'],
            $services['security'],
            $services['audit'],
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
            $services['template_cache'],
            new MediaAssetRepository($services['database'])
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
        'directory' => 'MediaLibrary',
        'enabled' => static fn (array $services): bool => $services['database'] !== null,
        'factory' => static function (array $services): MediaLibraryModule {
            $modulesConfig = is_array($services['config']['modules'] ?? null) ? $services['config']['modules'] : [];
            $usage = new MediaOptimizationUsageRepository($services['database']);
            $optimizer = ($modulesConfig['tinify_enabled'] ?? false) === true && trim((string) ($modulesConfig['tinify_api_key'] ?? '')) !== ''
                ? new TinifyImageOptimizer(
                    trim((string) $modulesConfig['tinify_api_key']),
                    $usage,
                    (int) ($modulesConfig['tinify_monthly_limit'] ?? 500)
                )
                : null;

            return new MediaLibraryModule(
                $services['theme'],
                $services['admin_menu'],
                new MediaAssetRepository($services['database']),
                new MediaAssetStorage(
                    dirname(__DIR__) . '/uploads/media',
                    (int) ($modulesConfig['media_upload_max_bytes'] ?? 5242880),
                    $optimizer
                ),
                $services['auth'],
                $services['security'],
                $services['audit']
            );
        },
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
            $services['audit'],
            new MediaAssetRepository($services['database'])
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
        'directory' => 'Licences',
        'enabled' => static fn (array $services): bool => $services['database'] !== null,
        'factory' => static fn (array $services): LicencesModule => new LicencesModule(
            $services['theme'],
            $services['admin_menu'],
            new LicenceRepository($services['database']),
            $services['auth'],
            $services['security'],
            $services['audit'],
            $services['rate_limiter'],
            $services['config']['modules'] ?? [],
            $services['template_cache']
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
        'directory' => 'PluginStats',
        'enabled' => static fn (array $services): bool => $services['database'] !== null,
        'factory' => static function (array $services): PluginStatsModule {
            $repository = new PluginStatsRepository($services['database']);
            $moduleConfig = is_array($services['config']['modules'] ?? null)
                ? $services['config']['modules']
                : [];
            return new PluginStatsModule(
                $services['theme'],
                $services['admin_menu'],
                $repository,
                $services['auth'],
                $services['security'],
                $services['audit'],
                $services['rate_limiter'],
                $moduleConfig,
                $services['template_cache'],
                new \SyntaxDevTeam\Cms\Modules\PluginStats\PluginStatsDashboardCache(
                    $repository,
                    dirname(__DIR__) . '/cache/plugin-stats',
                    (int) ($moduleConfig['plugin_stats_dashboard_ttl'] ?? 1800),
                    (int) ($moduleConfig['plugin_stats_dashboard_stale_ttl'] ?? 86400)
                )
            );
        },
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
        'directory' => 'EconizerBotStudio',
        'enabled' => static fn (array $services): bool => $services['database'] !== null,
        'factory' => static fn (array $services): EconizerBotStudioModule => new EconizerBotStudioModule(
            $services['theme'],
            $services['admin_menu'],
            new EconizerBotStudioRepository(
                $services['database'],
                new EconizerBotStudioOptionParser(),
                new EconizerBotStudioWorkflowParser()
            ),
            new EconizerBotStudioSimulator(),
            $services['auth'],
            $services['security'],
            $services['audit'],
        ),
    ],
    [
        'directory' => 'AuthGatewayX',
        'enabled' => static fn (array $services): bool => $services['database'] !== null,
        'factory' => static function (array $services): AuthGatewayXModule {
            $config = $services['config']['modules'] ?? [];
            $repository = new AuthGatewayXRepository($services['database']);
            return new AuthGatewayXModule(
                $services['theme'], $services['admin_menu'], $services['auth'], $services['security'], $services['audit'],
                new AuthGatewayXService(new MinecraftIdentityRepository($services['database'])),
                $repository,
                new AuthGatewayXApiController(
                    $repository,
                    new ProviderAuthenticator($repository),
                    new ChallengeCodeGenerator(),
                    new IpBinding((string) ($config['authgatewayx_hash_key'] ?? '')),
                    $services['rate_limiter'],
                    (string) ($config['authgatewayx_public_base_url'] ?? 'https://syntaxdevteam.pl'),
                    (int) ($config['authgatewayx_challenge_ttl_seconds'] ?? 120),
                ),
                filter_var($config['authgatewayx_local_self_service'] ?? false, FILTER_VALIDATE_BOOL),
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
            $services['hooks'],
        ),
    ],
    [
        'directory' => 'RemoteTerminal',
        'factory' => static fn (array $services): RemoteTerminalModule => new RemoteTerminalModule(
            $services['theme'],
            $services['admin_menu'],
            $services['auth'],
            $services['access'],
            $services['security'],
            $services['audit'],
            RemoteTerminalConfig::fromModuleConfig($services['config']['modules'] ?? []),
            dirname(__DIR__) . '/cache/remote-terminal'
        ),
    ],
    [
        'directory' => 'MinecraftConsole',
        'enabled' => static fn (array $services): bool => $services['database'] !== null,
        'factory' => static fn (array $services): MinecraftConsoleModule => new MinecraftConsoleModule(
            $services['theme'],
            $services['admin_menu'],
            new MinecraftConsoleRepository($services['database']),
            $services['auth'],
            $services['access'],
            $services['security'],
            $services['audit'],
            dirname(__DIR__) . '/cache/minecraft-console',
            (int) ($services['config']['modules']['minecraft_console_upload_max_bytes'] ?? 47185920)
        ),
    ],
    [
        'directory' => 'MCServerInstaller',
        'enabled' => static fn (array $services): bool => $services['database'] !== null,
        'factory' => static function (array $services): MCServerInstallerModule {
            $moduleConfig = $services['config']['modules'] ?? [];
            $userAgent = (string) ($moduleConfig['mc_server_installer_papermc_user_agent']
                ?? 'SyntaxDevTeam/ServerProvisioner/1.0.0 (https://syntaxdevteam.pl)');
            $timeout = (int) ($moduleConfig['mc_server_installer_papermc_timeout'] ?? 20);
            $maxBytes = (int) ($moduleConfig['mc_server_installer_upload_max_bytes'] ?? 536870912);
            $paperMc = new PaperMcDownloadsClient(
                $userAgent,
                dirname(__DIR__) . '/cache/mc-server-installer-downloads',
                $timeout,
                (int) ($moduleConfig['mc_server_installer_papermc_catalog_ttl'] ?? 900),
                $maxBytes
            );
            $purpur = new PurpurDownloadsClient(
                $userAgent,
                dirname(__DIR__) . '/cache/mc-server-installer-purpur',
                $timeout,
                $maxBytes
            );
            $mojang = new MojangDownloadsClient(
                $userAgent,
                dirname(__DIR__) . '/cache/mc-server-installer-mojang',
                $timeout,
                $maxBytes
            );
            $pufferfish = new PufferfishDownloadsClient(
                $userAgent,
                dirname(__DIR__) . '/cache/mc-server-installer-pufferfish',
                $timeout,
                $maxBytes
            );
            $leaves = new LeavesDownloadsClient(
                $userAgent,
                dirname(__DIR__) . '/cache/mc-server-installer-leaves',
                $timeout,
                $maxBytes
            );

            return new MCServerInstallerModule(
                $services['theme'],
                $services['admin_menu'],
                new MCServerInstallationRepository($services['database']),
                new ServerEngineCatalog(),
                new MCServerInstallationStorage(
                    (string) ($moduleConfig['mc_server_installer_root']
                        ?? '/home/debian/server'),
                    (string) ($moduleConfig['mc_server_installer_java_binary'] ?? '/usr/bin/java'),
                    (int) ($moduleConfig['mc_server_installer_upload_max_bytes'] ?? 536870912)
                ),
                $services['auth'],
                $services['security'],
                $services['audit'],
                $paperMc,
                $purpur,
                $mojang,
                new EngineVersionCatalog(
                    $paperMc,
                    $purpur,
                    $mojang,
                    $pufferfish,
                    $leaves,
                    new SpigotVersionsClient(
                        $userAgent,
                        dirname(__DIR__) . '/cache/mc-server-installer-spigot-versions',
                        $timeout,
                        (int) ($moduleConfig['mc_server_installer_papermc_catalog_ttl'] ?? 900)
                    ),
                    new VelocityDownloadsClient(
                        $userAgent,
                        dirname(__DIR__) . '/cache/mc-server-installer-velocity',
                        $timeout,
                        $maxBytes
                    )
                ),
                new MinecraftConsoleRepository($services['database'])
            );
        },
    ],
    [
        'directory' => 'PluginManager',
        'enabled' => static fn (array $services): bool => $services['database'] !== null,
        'factory' => static function (array $services): PluginManagerModule {
            $moduleConfig = $services['config']['modules'] ?? [];
            $userAgent = (string) ($moduleConfig['mc_server_installer_papermc_user_agent']
                ?? 'SyntaxDevTeam/PluginManager/1.0.0 (https://syntaxdevteam.pl)');
            $timeout = (int) ($moduleConfig['mc_server_installer_papermc_timeout'] ?? 20);
            $maxBytes = (int) ($moduleConfig['mc_server_installer_upload_max_bytes'] ?? 536870912);
            return new PluginManagerModule(
                $services['theme'],
                $services['admin_menu'],
                new PluginManagerRepository(
                    $services['database'],
                    new ServerProfileCatalog($services['database'])
                ),
                new MCServerInstallationStorage(
                    (string) ($moduleConfig['mc_server_installer_root'] ?? '/home/debian/server'),
                    (string) ($moduleConfig['mc_server_installer_java_binary'] ?? '/usr/bin/java'),
                    $maxBytes
                ),
                new BuildExplorerPluginResolver(
                    new BuildRepository($services['database']),
                    new BuildArtifactStorage(
                        dirname(__DIR__) . '/cache/build-artifacts',
                        (int) ($moduleConfig['build_upload_max_bytes'] ?? 20971520)
                    )
                ),
                new CommonPluginCatalog(
                    $userAgent,
                    dirname(__DIR__) . '/cache/plugin-manager-common-plugins',
                    $timeout,
                    min($maxBytes, 67108864)
                ),
                new \SyntaxDevTeam\Cms\Modules\PluginManager\SpigotPluginCatalog(
                    $userAgent,
                    dirname(__DIR__) . '/cache/plugin-manager-spigot-plugins',
                    $timeout,
                    min($maxBytes, 67108864)
                ),
                new \SyntaxDevTeam\Cms\Modules\PluginManager\PluginFilesystemInventory(),
                $services['auth'],
                $services['security'],
                $services['audit']
            );
        },
    ],
    [
        'directory' => 'Uptime',
        'enabled' => static fn (array $services): bool => $services['database'] !== null,
        'factory' => static fn (array $services): UptimeModule => new UptimeModule(
            $services['theme'],
            $services['admin_menu'],
            new UptimeRepository($services['database']),
            $services['auth'],
            $services['security'],
            $services['audit'],
            $services['template_cache'],
            $services['rate_limiter'],
            $services['config']['modules'] ?? [],
        ),
    ],
    [
        'directory' => 'DistributionAccess',
        'enabled' => static fn (array $services): bool => $services['database'] !== null
            && $services['distribution_access_gateway'] instanceof DistributionAccessRepository,
        'factory' => static fn (array $services): DistributionAccessModule => new DistributionAccessModule(
            $services['theme'],
            $services['admin_menu'],
            $services['distribution_access_gateway'],
            $services['module_manager'],
            $services['auth'],
            $services['security'],
            $services['audit'],
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
            $services['platform_release_publisher'],
            $services['github_release_dispatcher'],
            $services['auth_provider_config_store'],
            $services['module_updates'],
            $services['module_update_publisher'],
            $services['module_update_refresh_queue'],
            $services['distribution_access_credentials'],
            $services['distribution_access_gateway'],
        ),
    ],
];
