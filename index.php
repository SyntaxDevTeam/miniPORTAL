<?php

declare(strict_types=1);

use SyntaxDevTeam\Cms\Core\Autoloader;
use SyntaxDevTeam\Cms\Core\AdminMenuRegistry;
use SyntaxDevTeam\Cms\Core\AdminSearchRegistry;
use SyntaxDevTeam\Cms\Core\Bootstrap;
use SyntaxDevTeam\Cms\Core\BrandIconGenerator;
use SyntaxDevTeam\Cms\Core\DashboardRegistry;
use SyntaxDevTeam\Cms\Core\CoreMigrationRunner;
use SyntaxDevTeam\Cms\Core\FilesystemPermissions;
use SyntaxDevTeam\Cms\Core\HookRegistry;
use SyntaxDevTeam\Cms\Core\InstallationState;
use SyntaxDevTeam\Cms\Core\ModuleBootstrapper;
use SyntaxDevTeam\Cms\Core\ModuleArchiveImporter;
use SyntaxDevTeam\Cms\Core\ModuleManifestValidator;
use SyntaxDevTeam\Cms\Core\ModuleInstaller;
use SyntaxDevTeam\Cms\Core\ModuleManagerService;
use SyntaxDevTeam\Cms\Core\ModulePackageSigner;
use SyntaxDevTeam\Cms\Core\ModuleRegistry;
use SyntaxDevTeam\Cms\Core\ModuleStateRepository;
use SyntaxDevTeam\Cms\Core\PlatformReleaseRepository;
use SyntaxDevTeam\Cms\Core\PlatformReleasePublisher;
use SyntaxDevTeam\Cms\Core\PlatformUpdater;
use SyntaxDevTeam\Cms\Core\PublicNavigationRegistry;
use SyntaxDevTeam\Cms\Core\Request;
use SyntaxDevTeam\Cms\Core\Router;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AdminAccessGate;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuditLogService;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuthService;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuthProviderConfigStore;
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
use SyntaxDevTeam\Cms\Modules\System\SystemSettingsRepository;

require_once __DIR__ . '/core/Autoloader.php';

Autoloader::register();

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

$config = require __DIR__ . '/config/config.php';
$application = Bootstrap::boot($config);
$theme = $application->theme();
$security = $application->security();
$templateCache = $application->templateCache();
$router = new Router();
$adminMenu = new AdminMenuRegistry();
$adminSearch = new AdminSearchRegistry();
$dashboard = new DashboardRegistry();
$publicNavigation = new PublicNavigationRegistry();
$hooks = new HookRegistry();
$modules = new ModuleRegistry();
$authConfig = $config['auth'] ?? [];
$authStorage = (string) ($authConfig['storage'] ?? 'database');
$authDemoEnabled = ($authConfig['demo_enabled'] ?? false) === true;
/** @var UserRepositoryInterface $userRepository */
$userRepository = match ($authStorage) {
    'database' => $application->database() !== null
        ? new CrudAppUserRepository($application->database())
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
    (int) ($config['modules']['archive_max_bytes'] ?? 10485760)
);
$updatesConfig = is_array($config['updates'] ?? null) ? $config['updates'] : [];
$platformReleases = new PlatformReleaseRepository(
    __DIR__ . '/releases',
    (string) ($updatesConfig['catalog_url'] ?? ''),
    __DIR__ . '/cache/platform-updates/downloads',
    (int) ($updatesConfig['archive_max_bytes'] ?? 52428800)
);
$platformUpdater = new PlatformUpdater(__DIR__, __DIR__ . '/cache/platform-updates');
$platformReleasePublisher = new PlatformReleasePublisher(
    __DIR__,
    __DIR__ . '/cache/platform-updates/publisher'
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
    'platform_releases' => $platformReleases,
    'platform_updater' => $platformUpdater,
    'platform_release_publisher' => $platformReleasePublisher,
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
], $modules);
$modules->boot($adminMenu, $router, $publicNavigation, $adminSearch, $dashboard, $hooks);
$theme->set_admin_search_items($adminSearch->visibleFor($auth->user()?->permissions ?? []));

$publicNavigationItems = static function () use ($pageRepository, $publicNavigation, $application): array {
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
$theme->set_public_navigation($publicNavigationItems(), $auth->user() !== null);

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

$status = $router->dispatch($application->request());

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
