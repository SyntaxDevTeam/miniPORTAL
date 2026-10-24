<?php

declare(strict_types=1);

use SyntaxDevTeam\Cms\Core\Autoloader;
use SyntaxDevTeam\Cms\Core\AdminMenuRegistry;
use SyntaxDevTeam\Cms\Core\AdminSearchRegistry;
use SyntaxDevTeam\Cms\Core\Bootstrap;
use SyntaxDevTeam\Cms\Core\BrandIconGenerator;
use SyntaxDevTeam\Cms\Core\DashboardRegistry;
use SyntaxDevTeam\Cms\Core\DistributionAccessCredentialStore;
use SyntaxDevTeam\Cms\Core\CoreMigrationRunner;
use SyntaxDevTeam\Cms\Core\ErrorHandler;
use SyntaxDevTeam\Cms\Core\FilesystemPermissions;
use SyntaxDevTeam\Cms\Core\HookRegistry;
use SyntaxDevTeam\Cms\Core\GitHubReleaseDispatcher;
use SyntaxDevTeam\Cms\Core\InstallationState;
use SyntaxDevTeam\Cms\Core\ModuleBootstrapper;
use SyntaxDevTeam\Cms\Core\ModuleArchiveImporter;
use SyntaxDevTeam\Cms\Core\ModuleManifestValidator;
use SyntaxDevTeam\Cms\Core\ModuleInstaller;
use SyntaxDevTeam\Cms\Core\ModuleManagerService;
use SyntaxDevTeam\Cms\Core\ModulePackageSigner;
use SyntaxDevTeam\Cms\Core\ModuleRegistry;
use SyntaxDevTeam\Cms\Core\ModuleStateRepository;
use SyntaxDevTeam\Cms\Core\ModuleUpdatePublisher;
use SyntaxDevTeam\Cms\Core\ModuleUpdateRefreshQueue;
use SyntaxDevTeam\Cms\Core\ModuleUpdateRepository;
use SyntaxDevTeam\Cms\Core\PlatformReleaseRepository;
use SyntaxDevTeam\Cms\Core\PlatformReleasePublisher;
use SyntaxDevTeam\Cms\Core\PlatformUpdater;
use SyntaxDevTeam\Cms\Core\PublicNavigationRegistry;
use SyntaxDevTeam\Cms\Core\RateLimiter;
use SyntaxDevTeam\Cms\Core\Request;
use SyntaxDevTeam\Cms\Core\Router;
use SyntaxDevTeam\Cms\Core\SeoIndex;
use SyntaxDevTeam\Cms\Core\StorefrontHostResolverInterface;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AdminAccessGate;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuditLogService;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuthService;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuthProviderConfigStore;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuthPolicyRuntime;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuthorizationService;
use SyntaxDevTeam\Cms\Modules\CoreAuth\CrudAppUserRepository;
use SyntaxDevTeam\Cms\Modules\CoreAuth\DiscordIdentityProvider;
use SyntaxDevTeam\Cms\Modules\CoreAuth\GitHubIdentityProvider;
use SyntaxDevTeam\Cms\Modules\CoreAuth\GoogleIdentityProvider;
use SyntaxDevTeam\Cms\Modules\CoreAuth\FirstAdminBootstrapper;
use SyntaxDevTeam\Cms\Modules\CoreAuth\IdentityProviderRegistry;
use SyntaxDevTeam\Cms\Modules\CoreAuth\InMemoryUserRepository;
use SyntaxDevTeam\Cms\Modules\CoreAuth\NativeHttpClient;
use SyntaxDevTeam\Cms\Modules\CoreAuth\MicrosoftIdentityProvider;
use SyntaxDevTeam\Cms\Modules\CoreAuth\UnavailableUserRepository;
use SyntaxDevTeam\Cms\Modules\CoreAuth\UserRepositoryInterface;
use SyntaxDevTeam\Cms\Modules\CorePages\HomepageSectionRepository;
use SyntaxDevTeam\Cms\Modules\CorePages\HomepageSectionItemRepository;
use SyntaxDevTeam\Cms\Modules\CorePages\PageRepository;
use SyntaxDevTeam\Cms\Modules\DistributionAccess\DistributionAccessRepository;
use SyntaxDevTeam\Cms\Modules\System\SystemSettingsRepository;

require_once __DIR__ . '/core/Autoloader.php';

Autoloader::register();

$requestStartedAt = hrtime(true);
$requestId = '';
$moduleBootstrapDurationMs = 0.0;
$config = [];
$application = null;
$theme = null;
ErrorHandler::register(static function () use (&$theme, &$application, &$config): array {
    return [
        'theme' => $theme,
        'request' => $application?->request(),
        'config' => $config,
    ];
});

if (!InstallationState::isInstalled(__DIR__)) {
    $scriptDirectory = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php')));
    $installerUrl = rtrim($scriptDirectory, '/') . '/install.php';
    header('Location: ' . ($installerUrl !== '' ? $installerUrl : '/install.php'), true, 302);
    exit;
}

$permissionIssues = FilesystemPermissions::missing(__DIR__);
if ($permissionIssues !== []) {
    http_response_code(503);
    header('Content-Type: text/html; charset=UTF-8');
    $escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $directories = implode(', ', array_map(
        static fn (string $directory): string => $directory . '/',
        $permissionIssues
    ));
    $command = FilesystemPermissions::remediationCommand(__DIR__);
    ?>
    <!doctype html>
    <html lang="pl-PL"><head>
      <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
      <meta name="robots" content="noindex, nofollow">
      <title>miniPORTAL — wymagane uprawnienia</title>
      <style>
        :root{color-scheme:dark;--bg:#07101d;--panel:#101b2b;--line:#29405c;--text:#edf6ff;--muted:#a9b8ca;--accent:#64c7ff}
        *{box-sizing:border-box}body{display:grid;min-height:100vh;margin:0;place-items:center;padding:1rem;color:var(--text);background:var(--bg);font:16px/1.55 system-ui,sans-serif}
        main{width:min(800px,100%);padding:1.5rem;background:var(--panel);border:1px solid var(--line);border-radius:1rem}
        p{color:var(--muted)}code{color:var(--accent)}pre{overflow:auto;padding:1rem;background:#050b13;border:1px solid var(--line);border-radius:.55rem;white-space:pre-wrap}
      </style>
    </head><body><main>
      <h1>miniPORTAL wymaga poprawienia uprawnień</h1>
      <p>PHP nie może zapisywać katalogów: <code><?= $escape($directories) ?></code></p>
      <p>Wykonaj poniższe polecenia, a następnie odśwież stronę:</p>
      <pre><code><?= $escape($command) ?></code></pre>
    </main></body></html>
    <?php
    exit;
}

try {
$config = require __DIR__ . '/config/config.php';
$application = Bootstrap::boot($config);
$requestId = bin2hex(random_bytes(8));
header('X-Request-ID: ' . $requestId);
$theme = $application->theme();
$security = $application->security();
$templateCache = $application->templateCache();
$router = new Router();
$adminMenu = new AdminMenuRegistry();
$adminSearch = new AdminSearchRegistry();
$dashboard = new DashboardRegistry();
$publicNavigation = new PublicNavigationRegistry();
$hooks = new HookRegistry();
$rateLimiter = new RateLimiter(__DIR__ . '/cache/rate-limits');
$seo = new SeoIndex((string) ($application->config()['app']['public_url'] ?? ''));
$modules = new ModuleRegistry();
$authConfig = $config['auth'] ?? [];
$authStorage = (string) ($authConfig['storage'] ?? 'database');
$authDemoEnabled = ($authConfig['demo_enabled'] ?? false) === true;
/** @var UserRepositoryInterface $userRepository */
$userRepository = match ($authStorage) {
    'database' => $application->database() !== null
        ? new CrudAppUserRepository($application->database(), $hooks)
        : new UnavailableUserRepository(),
    'memory' => $authDemoEnabled
        ? new InMemoryUserRepository()
        : new UnavailableUserRepository(),
    default => throw new RuntimeException('Nieobsługiwany magazyn AUTH_STORAGE.'),
};
$firstOwnerBootstrapper = $application->database() !== null
    ? new FirstAdminBootstrapper($application->database())
    : null;
$auth = new AuthService($userRepository, $security, $firstOwnerBootstrapper);
$previewAwarePath = $application->request()->path();
$auth->useRolePreview(
    str_starts_with($previewAwarePath, '/admin')
    || str_starts_with($previewAwarePath, '/account')
);
$authorization = new AuthorizationService();
$access = new AdminAccessGate($auth, $authorization);
$audit = new AuditLogService(
    $application->database(),
    (string) ($authConfig['audit_hash_key'] ?? '')
);
$providerConfig = is_array($authConfig['providers'] ?? null) ? $authConfig['providers'] : [];
$githubConfig = is_array($providerConfig['github'] ?? null) ? $providerConfig['github'] : [];
$discordConfig = is_array($providerConfig['discord'] ?? null) ? $providerConfig['discord'] : [];
$googleConfig = is_array($providerConfig['google'] ?? null) ? $providerConfig['google'] : [];
$microsoftConfig = is_array($providerConfig['microsoft'] ?? null) ? $providerConfig['microsoft'] : [];
$providers = new IdentityProviderRegistry();
$httpClient = new NativeHttpClient();
$providerCredentials = static fn (array $provider): array => ($provider['enabled'] ?? false) === true
    ? [(string) ($provider['client_id'] ?? ''), (string) ($provider['client_secret'] ?? '')]
    : ['', ''];
[$githubClientId, $githubClientSecret] = $providerCredentials($githubConfig);
[$discordClientId, $discordClientSecret] = $providerCredentials($discordConfig);
[$googleClientId, $googleClientSecret] = $providerCredentials($googleConfig);
[$microsoftClientId, $microsoftClientSecret] = $providerCredentials($microsoftConfig);
$providers->add(new GitHubIdentityProvider(
    $httpClient,
    $githubClientId,
    $githubClientSecret,
    (string) ($githubConfig['callback_url'] ?? '')
));
$providers->add(new DiscordIdentityProvider(
    $httpClient,
    $discordClientId,
    $discordClientSecret,
    (string) ($discordConfig['callback_url'] ?? '')
));
$providers->add(new GoogleIdentityProvider(
    $httpClient,
    $googleClientId,
    $googleClientSecret,
    (string) ($googleConfig['callback_url'] ?? '')
));
$providers->add(new MicrosoftIdentityProvider(
    $httpClient,
    $microsoftClientId,
    $microsoftClientSecret,
    (string) ($microsoftConfig['callback_url'] ?? '')
));
$authProviderConfigStore = new AuthProviderConfigStore(
    (string) ($config['meta']['auth_providers_file'] ?? (__DIR__ . '/config/modules/auth-providers.env'))
);
$pageRepository = $application->database() !== null
    ? new PageRepository($application->database())
    : null;
$homepageSectionRepository = $application->database() !== null
    ? new HomepageSectionRepository($application->database())
    : null;
$homepageSectionItemRepository = $application->database() !== null
    ? new HomepageSectionItemRepository($application->database())
    : null;
$moduleDefinitions = require __DIR__ . '/config/modules.php';
$trustedModulePublishers = require __DIR__ . '/config/module_publishers.php';
$modulesConfig = is_array($config['modules'] ?? null) ? $config['modules'] : [];
$moduleSigningKeyId = (string) ($modulesConfig['signing_key_id'] ?? '');
$moduleSigningPublicKeyFile = (string) ($modulesConfig['signing_public_key_file'] ?? '');
if (
    $moduleSigningKeyId !== ''
    && $moduleSigningPublicKeyFile !== ''
    && is_readable($moduleSigningPublicKeyFile)
) {
    $trustedModulePublishers[$moduleSigningKeyId] = [
        'name' => 'Lokalny wydawca miniPORTAL',
        'public_key' => (string) file_get_contents($moduleSigningPublicKeyFile),
        'status' => 'active',
        'valid_from' => null,
        'valid_until' => null,
        'replacement_key_id' => null,
    ];
}
$moduleSigningPrivateKeyFile = (string) ($modulesConfig['signing_private_key_file'] ?? '');
$modulePackageSigner = $moduleSigningKeyId !== '' && $moduleSigningPrivateKeyFile !== ''
    ? new ModulePackageSigner($moduleSigningPrivateKeyFile, $moduleSigningKeyId)
    : null;
$manifestValidator = new ModuleManifestValidator(
    (string) ($config['app']['version'] ?? '0.1.0'),
    $trustedModulePublishers
);
$moduleStates = $application->database() !== null
    ? new ModuleStateRepository($application->database())
    : null;
$moduleInstaller = $application->database() !== null && $moduleStates !== null
    ? new ModuleInstaller($application->database(), $moduleStates)
    : null;
$moduleManager = $moduleStates !== null && $moduleInstaller !== null
    ? new ModuleManagerService(
        __DIR__ . '/modules',
        $manifestValidator,
        $moduleStates,
        $moduleInstaller,
        array_values(array_map(
            static fn (array $definition): string => (string) ($definition['directory'] ?? ''),
            $moduleDefinitions
        )),
        $modulePackageSigner
    )
    : null;
$moduleArchiveImporter = new ModuleArchiveImporter(
    __DIR__ . '/cache/module-quarantine',
    $manifestValidator,
    (int) ($config['modules']['archive_max_bytes'] ?? 10485760),
    (int) ($config['modules']['archive_max_files'] ?? 2000),
    (int) ($config['modules']['archive_max_file_bytes'] ?? 10485760),
    (int) ($config['modules']['archive_max_unpacked_bytes'] ?? 52428800)
);
$distributionAccessCredentials = new DistributionAccessCredentialStore(
    __DIR__ . '/config/modules/distribution-access.env',
    __DIR__ . '/cache/module-updates/catalog.json'
);
$distributionAccessGateway = null;
if ($application->database() !== null && class_exists(DistributionAccessRepository::class)) {
    try {
        if ($moduleStates?->find('distribution_access')?->isActive() === true) {
            $distributionAccessGateway = new DistributionAccessRepository(
                $application->database(),
                (string) ($modulesConfig['distribution_access_hash_key'] ?? '')
            );
        }
    } catch (\Throwable) {
        $distributionAccessGateway = null;
    }
}
$moduleUpdates = new ModuleUpdateRepository(
    (string) ($config['modules']['update_catalog_url'] ?? ''),
    __DIR__ . '/cache/module-updates',
    (int) ($config['modules']['archive_max_bytes'] ?? 10485760),
    (int) ($config['modules']['update_catalog_ttl'] ?? 900),
    $distributionAccessCredentials
);
$moduleUpdatePublisher = new ModuleUpdatePublisher(
    $moduleManager,
    __DIR__ . '/cache/module-repository'
);
$moduleUpdateRefreshQueue = new ModuleUpdateRefreshQueue(__DIR__ . '/cache/module-repository');
$updatesConfig = is_array($config['updates'] ?? null) ? $config['updates'] : [];
$platformReleases = new PlatformReleaseRepository(
    __DIR__ . '/releases',
    (string) ($updatesConfig['catalog_url'] ?? ''),
    __DIR__ . '/cache/platform-updates/downloads',
    (int) ($updatesConfig['archive_max_bytes'] ?? 52428800)
);
$platformUpdater = new PlatformUpdater(
    __DIR__,
    __DIR__ . '/cache/platform-updates',
    (int) ($updatesConfig['archive_max_files'] ?? 10000),
    (int) ($updatesConfig['archive_max_file_bytes'] ?? 52428800),
    (int) ($updatesConfig['archive_max_unpacked_bytes'] ?? 209715200)
);
$platformReleasePublisher = new PlatformReleasePublisher(
    __DIR__,
    __DIR__ . '/cache/platform-updates/publisher'
);
$githubReleaseDispatcher = new GitHubReleaseDispatcher(
    (string) ($updatesConfig['github_token'] ?? ''),
    (string) ($updatesConfig['github_repository'] ?? 'SyntaxDevTeam/miniPORTAL-source'),
    (string) ($updatesConfig['github_workflow'] ?? 'platform-release.yml'),
    (string) ($updatesConfig['github_ref'] ?? 'main')
);
$coreMigrationRunner = $application->database() !== null
    ? new CoreMigrationRunner($application->database(), __DIR__ . '/core/migrations')
    : null;
$brandIconGenerator = new BrandIconGenerator(__DIR__, __DIR__ . '/uploads/branding');
$moduleBootstrapper = new ModuleBootstrapper(
    __DIR__ . '/modules',
    $manifestValidator,
    $moduleStates
);
$requestPath = $application->request()->path();
$pollingModuleDirectories = match (true) {
    str_starts_with($requestPath, '/admin/remote-terminal/session/'),
    str_starts_with($requestPath, '/admin/remote-terminal/assets/'),
    str_starts_with($requestPath, '/admin/remote-terminal/output/'),
    str_starts_with($requestPath, '/admin/remote-terminal/input/') => ['CoreAuth', 'RemoteTerminal'],
    $requestPath === '/admin/minecraft-console/output',
    $requestPath === '/admin/minecraft-console/command' => ['CoreAuth', 'MinecraftConsole'],
    default => null,
};
// Authorization providers must also be present in the lightweight polling kernel.
if ($pollingModuleDirectories !== null) {
    $pollingModuleDirectories = array_merge($pollingModuleDirectories, ['PlayerProfile', 'ServerStaff']);
}
$isPollingKernel = $pollingModuleDirectories !== null;
$moduleBootstrapStartedAt = hrtime(true);
$moduleBootstrapper->register($moduleDefinitions, [
    'theme' => $theme,
    'security' => $security,
    'auth' => $auth,
    'providers' => $providers,
    'audit' => $audit,
    'access' => $access,
    'admin_menu' => $adminMenu,
    'database' => $application->database(),
    'auth_config' => $authConfig,
    'auth_demo_enabled' => $authDemoEnabled,
    'auth_provider_config_store' => $authProviderConfigStore,
    'module_manager' => $moduleManager,
    'module_archive_importer' => $moduleArchiveImporter,
    'module_updates' => $moduleUpdates,
    'module_update_publisher' => $moduleUpdatePublisher,
    'module_update_refresh_queue' => $moduleUpdateRefreshQueue,
    'distribution_access_credentials' => $distributionAccessCredentials,
    'distribution_access_gateway' => $distributionAccessGateway,
    'platform_releases' => $platformReleases,
    'platform_updater' => $platformUpdater,
    'platform_release_publisher' => $platformReleasePublisher,
    'github_release_dispatcher' => $githubReleaseDispatcher,
    'core_migration_runner' => $coreMigrationRunner,
    'config' => $application->config(),
    'diagnostics' => $application->diagnostics(),
    'available_themes' => $application->availableThemes(),
    'template_cache' => $templateCache,
    'trusted_module_publishers' => $trustedModulePublishers,
    'public_navigation' => $publicNavigation,
    'dashboard' => $dashboard,
    'brand_icon_generator' => $brandIconGenerator,
    'http_client' => $httpClient,
    'hooks' => $hooks,
    'rate_limiter' => $rateLimiter,
], $modules, $pollingModuleDirectories);
$modules->boot(
    $adminMenu,
    $router,
    $isPollingKernel ? null : $publicNavigation,
    $isPollingKernel ? null : $adminSearch,
    $isPollingKernel ? null : $dashboard,
    $isPollingKernel ? null : $hooks,
);
$theme->set_auth_entry_locations(AuthPolicyRuntime::provider()->loginEntryLocations());
if (!$isPollingKernel) {
    // Establish module/account context even for public previews rendered by admin routes.
    $theme->set_public_navigation([], $auth->user() !== null, $modules->ids());
}

$moduleBootstrapDurationMs = (hrtime(true) - $moduleBootstrapStartedAt) / 1_000_000;
if (!$isPollingKernel) {
    $theme->set_admin_search_items($adminSearch->visibleFor($auth->user()?->permissions ?? []));
    $rolePreview = $auth->rolePreview();
    $theme->set_admin_role_preview($rolePreview !== null ? [
        'role' => $rolePreview['role'],
        'label' => $rolePreview['label'],
        'stop_action' => 'index.php?route=/admin/roles/preview/stop',
        'csrf_token' => $security->csrfToken(),
    ] : null);
}

$publicNavigationItems = static function () use (
    $pageRepository,
    $publicNavigation,
    $application,
    $templateCache
): array {
    static $resolved = null;
    if (is_array($resolved)) {
        return $resolved;
    }

    $loader = static function () use ($pageRepository, $publicNavigation, $application): array {
    $navigation = [];
    if ($pageRepository !== null) {
        $navigation = array_map(
            static fn ($page): array => [
                'title' => $page->title,
                'slug' => $page->slug,
                'href' => '/p/' . rawurlencode($page->slug),
                'summary' => $page->summary,
                'type' => $page->pageType,
                'navigation_area' => $page->navigationArea,
                'navigation_label' => $page->navigationLabel,
                'sort_order' => $page->sortOrder,
            ],
            $pageRepository->published()
        );
    }
    $settings = $application->database() !== null
        ? (new SystemSettingsRepository($application->database()))->publicNavigationSettings()
        : [];
    foreach ($publicNavigation->items($settings) as $item) {
        foreach (['main' => $item['show_main'], 'footer' => $item['show_footer']] as $area => $enabled) {
            if (!$enabled) {
                continue;
            }
            $navigation[] = [
                'title' => $item['label'],
                'slug' => '',
                'href' => $item['href'],
                'summary' => '',
                'type' => 'module',
                'navigation_area' => $area,
                'navigation_label' => $item['label'],
                'sort_order' => $item['order'],
            ];
        }
    }
    usort(
        $navigation,
        static fn (array $left, array $right): int => [$left['sort_order'], $left['title']]
            <=> [$right['sort_order'], $right['title']]
    );

        return $navigation;
    };

    $json = $templateCache->remember(
        'public-data',
        'navigation-v1',
        static fn (): string => json_encode(
            $loader(),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ),
        ['navigation', 'pages', 'theme']
    );
    try {
        $navigation = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        $navigation = $loader();
    }

    return $resolved = is_array($navigation) ? $navigation : [];
};
$requestPath = $application->request()->path();
$requiresPublicNavigation = !str_starts_with($requestPath, '/admin')
    && !str_starts_with($requestPath, '/api/')
    && !str_starts_with($requestPath, '/metrics/')
    && !in_array($requestPath, ['/robots.txt', '/sitemap.xml', '/ads.txt'], true);
if ($requiresPublicNavigation) {
    $theme->set_public_navigation($publicNavigationItems(), $auth->user() !== null, $modules->ids());
    if ($theme instanceof \SyntaxDevTeam\Cms\Core\PublicSectionNavigationInterface) {
        $theme->set_public_section_navigation(array_map(
            static fn ($section): array => $section->toThemeData(),
            $homepageSectionRepository?->visible() ?? []
        ));
    }
}

$siteMaintenanceEnabled = static function () use ($application): bool {
    $app = is_array($application->config()['app'] ?? null)
        ? $application->config()['app']
        : [];

    return filter_var($app['public_maintenance_enabled'] ?? '0', FILTER_VALIDATE_BOOL);
};

$renderStart = static function (string $title, string $lead) use ($theme): void {
    $theme->start_page(
        $title . ' - SyntaxDevTeam',
        $lead
    );
    $theme->start_header($title, $lead, 'SyntaxDevTeam / System');
    $theme->end_header();
    $theme->start_section();
};

$renderEnd = static function () use ($theme): void {
    $theme->end_section();
    $theme->end_page();
};

$router->get('/', static function () use (
    $pageRepository,
    $homepageSectionRepository,
    $homepageSectionItemRepository,
    $theme,
    $auth,
    $templateCache,
    $publicNavigationItems,
    $hooks,
    $application
): void {
    $renderer = static function () use (
        $homepageSectionRepository,
        $homepageSectionItemRepository,
        $theme,
        $auth,
        $publicNavigationItems,
        $hooks,
        $application
    ): string {
        $navigation = $publicNavigationItems();
        $sections = [];
        if ($homepageSectionRepository !== null) {
            $sections = array_map(
                static fn ($section): array => $section->toThemeData(
                    $homepageSectionItemRepository?->forSection($section->id, true) ?? []
                ),
                $homepageSectionRepository->visible()
            );
        }
        $sections = $hooks->applyFilters('homepage.sections', $sections, [
            'authenticated' => $auth->user() !== null,
            'theme' => (string) ($application->config()['app']['theme'] ?? 'default'),
        ]);
        if (!is_array($sections)) {
            throw new UnexpectedValueException('Filtr homepage.sections musi zwrócić tablicę sekcji.');
        }

        ob_start();
        $theme->render_homepage($sections, $navigation, $auth->user() !== null);
        return (string) ob_get_clean();
    };

    echo $auth->user() === null
        ? $templateCache->remember('public', 'homepage', $renderer, ['homepage', 'pages', 'widgets', 'theme'])
        : $renderer();
});

$router->get('/sitemap.xml', static function () use ($seo, $modules, $siteMaintenanceEnabled): void {
    header('Content-Type: application/xml; charset=UTF-8');
    if ($siteMaintenanceEnabled()) {
        http_response_code(503);
        header('Cache-Control: no-store');
        header('Retry-After: 3600');
        echo '<?xml version="1.0" encoding="UTF-8"?>'
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>';
        return;
    }
    $modules->registerSeo($seo);
    header('Cache-Control: public, max-age=900');
    echo $seo->sitemapXml();
});

$router->get('/robots.txt', static function () use ($seo, $application, $siteMaintenanceEnabled): void {
    header('Content-Type: text/plain; charset=UTF-8');
    if ($siteMaintenanceEnabled()) {
        header('Cache-Control: no-store');
        echo "User-agent: *\nDisallow: /\n";
        return;
    }
    header('Cache-Control: public, max-age=900');
    echo $seo->robotsTxt((string) ($application->config()['app']['public_meta_robots'] ?? 'index, follow'));
});

$router->get('/ads.txt', static function (): void {
    header('Content-Type: text/plain; charset=UTF-8');
    header('Cache-Control: public, max-age=3600');
    echo "google.com, pub-3086258607996589, DIRECT, f08c47fec0942fa0\n";
});

$router->get('/security-demo', static function () use ($security, $theme, $renderStart, $renderEnd): void {
    $renderStart(
        'Test Security i Request',
        'Wyślij formularz, aby sprawdzić filtrowanie wejścia, sesję i ochronę CSRF.'
    );

    $theme->render_form(
        'index.php?route=/security-demo',
        [
            [
                'name' => 'message',
                'label' => 'Wiadomość testowa',
                'type' => 'text',
                'value' => 'miniPORTAL działa bezpiecznie',
            ],
            [
                'name' => 'level',
                'label' => 'Poziom',
                'type' => 'select',
                'value' => 'core',
                'options' => [
                    'core' => 'Core',
                    'module' => 'Module',
                    'template' => 'Template',
                ],
            ],
            [
                'name' => 'confirmed',
                'label' => 'Potwierdzam wysłanie danych',
                'type' => 'checkbox',
            ],
        ],
        'Wyślij bezpiecznie',
        $security->csrfToken()
    );

    $renderEnd();
});

$router->post('/security-demo', static function (Request $request) use ($security, $theme, $renderStart, $renderEnd): void {
    $renderStart(
        'Wynik bezpiecznego żądania',
        'Dane zostały pobrane przez Request i zweryfikowane przed przekazaniem do widoku.'
    );

    if (!$security->validateCsrfToken($request->postString('_token'))) {
        http_response_code(403);
        $theme->render_alert('Token CSRF jest nieprawidłowy lub wygasł.', 'danger');
        $renderEnd();
        return;
    }

    $message = $request->postString('message');
    $level = $request->postString('level');
    $confirmed = $request->postBool('confirmed') ? 'Tak' : 'Nie';

    $theme->render_alert('Token CSRF został poprawnie zweryfikowany.', 'success');
    $theme->render_table(
        ['Pole', 'Wartość'],
        [
            ['Wiadomość', $message],
            ['Poziom', $level],
            ['Potwierdzenie', $confirmed],
        ]
    );

    $renderEnd();
});

$request = $application->request();
$maintenanceTechnicalPath = str_starts_with($request->path(), '/admin')
    || str_starts_with($request->path(), '/api/')
    || str_starts_with($request->path(), '/metrics/')
    || in_array($request->path(), [
        '/robots.txt',
        '/sitemap.xml',
        '/ads.txt',
        '/account/registration',
        '/p/miniportal-warunki-uzytkowania',
        '/p/miniportal-prywatnosc',
        '/p/regulamin',
        '/p/polityka-prywatnosci',
    ], true);
$maintenanceVisitor = $auth->user();
$showMaintenance = $siteMaintenanceEnabled()
    && ($maintenanceVisitor === null || !$maintenanceVisitor->isActive())
    && in_array($request->method(), ['GET', 'HEAD'], true)
    && !$maintenanceTechnicalPath;

if ($showMaintenance) {
    $app = is_array($application->config()['app'] ?? null) ? $application->config()['app'] : [];
    $title = trim((string) ($app['public_maintenance_title'] ?? 'Site under construction'));
    $message = trim((string) (
        $app['public_maintenance_message']
        ?? 'We are preparing the content and configuration. Please check back soon.'
    ));
    http_response_code(503);
    header('Cache-Control: no-store, private');
    header('Retry-After: 3600');
    $theme->set_public_navigation($publicNavigationItems(), false, $modules->ids());
    $theme->render_public_error(
        503,
        $title !== '' ? $title : 'Site under construction',
        $message !== '' ? $message : 'Please check back soon.',
        'Sign in',
        '/admin/login?next=' . rawurlencode($request->path())
    );
    $status = 200;
} elseif ($auth->rolePreviewBlocks($request)) {
    $actor = $auth->actualUser();
    $audit->record(
        $request,
        'role_preview_write_blocked',
        'forbidden',
        'path=' . $request->path(),
        $actor?->id
    );
    http_response_code(403);
    $theme->render_admin_access_state(
        403,
        'Tryb podglądu jest tylko do odczytu',
        'Zakończ podgląd roli, aby wykonać operację zmieniającą dane.',
        'index.php?route=/admin',
        'Wróć do dashboardu'
    );
    $status = 200;
} else {
    if (
        $modules->capabilities()->hasImplementation('itemshop.storefront_host', 'itemshop')
        && ($hostResolver = $modules->capabilities()->implementation('itemshop.storefront_host', 'itemshop')) instanceof StorefrontHostResolverInterface
        && ($hostRoute = $hostResolver->resolve($request)) !== null
    ) {
        $request = $request->withPath($hostRoute);
        header('Vary: Host', false);
    }
    $status = $router->dispatch($request);
}

if ($status === 404) {
    http_response_code(404);
    $theme->render_public_error(
        404,
        'Nie znaleziono strony',
        'Adres nie pasuje do żadnej opublikowanej strony ani aktywnego modułu.',
        'Wróć do strony głównej',
        '/'
    );
} elseif ($status === 405) {
    http_response_code(405);
    $theme->render_public_error(
        405,
        'Nie można wykonać tej akcji',
        'Ten adres istnieje, ale nie obsługuje użytej metody HTTP.',
        'Wróć do strony głównej',
        '/'
    );
}
} catch (Throwable $exception) {
    ErrorHandler::handle($exception, $theme, $application?->request(), $config);
}

$requestDurationMs = (hrtime(true) - $requestStartedAt) / 1_000_000;
$slowRequestMs = max(50, (int) ($config['app']['performance_slow_request_ms'] ?? 250));
if ($requestDurationMs >= $slowRequestMs) {
    $performanceEntry = json_encode([
        'request_id' => $requestId,
        'method' => $application?->request()->method() ?? (string) ($_SERVER['REQUEST_METHOD'] ?? ''),
        'path' => $application?->request()->path() ?? '/',
        'status' => http_response_code(),
        'duration_ms' => round($requestDurationMs, 2),
        'module_boot_ms' => round($moduleBootstrapDurationMs, 2),
        'peak_memory_bytes' => memory_get_peak_usage(true),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    error_log('[miniPORTAL performance] ' . (is_string($performanceEntry) ? $performanceEntry : '{}'));
}
