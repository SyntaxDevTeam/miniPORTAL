<?php

declare(strict_types=1);

use SyntaxDevTeam\Cms\Core\Autoloader;
use SyntaxDevTeam\Cms\Core\AdminMenuRegistry;
use SyntaxDevTeam\Cms\Core\Bootstrap;
use SyntaxDevTeam\Cms\Core\ModuleBootstrapper;
use SyntaxDevTeam\Cms\Core\ModuleManifestValidator;
use SyntaxDevTeam\Cms\Core\ModuleInstaller;
use SyntaxDevTeam\Cms\Core\ModuleManagerService;
use SyntaxDevTeam\Cms\Core\ModuleRegistry;
use SyntaxDevTeam\Cms\Core\ModuleStateRepository;
use SyntaxDevTeam\Cms\Core\Request;
use SyntaxDevTeam\Cms\Core\Router;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AdminAccessGate;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuditLogService;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuthService;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuthorizationService;
use SyntaxDevTeam\Cms\Modules\CoreAuth\CrudAppUserRepository;
use SyntaxDevTeam\Cms\Modules\CoreAuth\DiscordIdentityProvider;
use SyntaxDevTeam\Cms\Modules\CoreAuth\GitHubIdentityProvider;
use SyntaxDevTeam\Cms\Modules\CoreAuth\GoogleIdentityProvider;
use SyntaxDevTeam\Cms\Modules\CoreAuth\IdentityProviderRegistry;
use SyntaxDevTeam\Cms\Modules\CoreAuth\InMemoryUserRepository;
use SyntaxDevTeam\Cms\Modules\CoreAuth\NativeHttpClient;
use SyntaxDevTeam\Cms\Modules\CoreAuth\UnavailableUserRepository;
use SyntaxDevTeam\Cms\Modules\CoreAuth\UserRepositoryInterface;
use SyntaxDevTeam\Cms\Modules\CorePages\HomepageSectionRepository;
use SyntaxDevTeam\Cms\Modules\CorePages\HomepageSectionItemRepository;
use SyntaxDevTeam\Cms\Modules\CorePages\PageRepository;

require_once __DIR__ . '/core/Autoloader.php';

Autoloader::register();

$config = require __DIR__ . '/config/config.php';
$application = Bootstrap::boot($config);
$theme = $application->theme();
$security = $application->security();
$router = new Router();
$adminMenu = new AdminMenuRegistry();
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
$auth = new AuthService($userRepository, $security);
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
$providers = new IdentityProviderRegistry();
$httpClient = new NativeHttpClient();
$providers->add(new GitHubIdentityProvider(
    $httpClient,
    (string) ($githubConfig['client_id'] ?? ''),
    (string) ($githubConfig['client_secret'] ?? ''),
    (string) ($githubConfig['callback_url'] ?? '')
));
$providers->add(new DiscordIdentityProvider(
    $httpClient,
    (string) ($discordConfig['client_id'] ?? ''),
    (string) ($discordConfig['client_secret'] ?? ''),
    (string) ($discordConfig['callback_url'] ?? '')
));
$providers->add(new GoogleIdentityProvider(
    $httpClient,
    (string) ($googleConfig['client_id'] ?? ''),
    (string) ($googleConfig['client_secret'] ?? ''),
    (string) ($googleConfig['callback_url'] ?? '')
));
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
        ))
    )
    : null;
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
    'module_manager' => $moduleManager,
    'config' => $application->config(),
    'diagnostics' => $application->diagnostics(),
    'available_themes' => $application->availableThemes(),
], $modules);
$modules->boot($adminMenu, $router);

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
    $auth
): void {
    $pages = [];
    $sections = [];

    if ($pageRepository !== null) {
        $pages = array_map(
            static fn ($page): array => [
                'title' => $page->title,
                'slug' => $page->slug,
                'summary' => $page->summary,
                'type' => $page->pageType,
                'navigation_area' => $page->navigationArea,
                'navigation_label' => $page->navigationLabel,
            ],
            $pageRepository->published()
        );
    }

    if ($homepageSectionRepository !== null) {
        $sections = array_map(
            static fn ($section): array => $section->toThemeData(
                $homepageSectionItemRepository?->forSection($section->id, true) ?? []
            ),
            $homepageSectionRepository->visible()
        );
    }

    $theme->render_homepage($sections, $pages, $auth->user() !== null);
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
    $renderStart('Nie znaleziono trasy', 'Router nie ma zarejestrowanego widoku dla podanego adresu.');
    $theme->render_alert('Błąd 404: sprawdź adres lub wróć do dashboardu rdzenia.', 'warning');
    $theme->render_button('Wróć do dashboardu', 'index.php');
    $renderEnd();
} elseif ($status === 405) {
    http_response_code(405);
    $renderStart('Niedozwolona metoda', 'Trasa istnieje, ale nie obsługuje użytej metody HTTP.');
    $theme->render_alert('Błąd 405: użyj metody przewidzianej dla tej operacji.', 'danger');
    $theme->render_button('Wróć do dashboardu', 'index.php');
    $renderEnd();
}
