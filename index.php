<?php

declare(strict_types=1);

use SyntaxDevTeam\Cms\Core\Autoloader;
use SyntaxDevTeam\Cms\Core\AdminMenuRegistry;
use SyntaxDevTeam\Cms\Core\Bootstrap;
use SyntaxDevTeam\Cms\Core\ModuleRegistry;
use SyntaxDevTeam\Cms\Core\Request;
use SyntaxDevTeam\Cms\Core\Router;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AdminAccessGate;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuditLogService;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuthService;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuthorizationService;
use SyntaxDevTeam\Cms\Modules\CoreAuth\CoreAuthModule;
use SyntaxDevTeam\Cms\Modules\CoreAuth\CrudAppUserRepository;
use SyntaxDevTeam\Cms\Modules\CoreAuth\DiscordIdentityProvider;
use SyntaxDevTeam\Cms\Modules\CoreAuth\GitHubIdentityProvider;
use SyntaxDevTeam\Cms\Modules\CoreAuth\GoogleIdentityProvider;
use SyntaxDevTeam\Cms\Modules\CoreAuth\IdentityProviderRegistry;
use SyntaxDevTeam\Cms\Modules\CoreAuth\InMemoryUserRepository;
use SyntaxDevTeam\Cms\Modules\CoreAuth\NativeHttpClient;
use SyntaxDevTeam\Cms\Modules\CoreAuth\OAuthStateStore;
use SyntaxDevTeam\Cms\Modules\CoreAuth\OAuthAttemptLimiter;
use SyntaxDevTeam\Cms\Modules\CoreAuth\UnavailableUserRepository;
use SyntaxDevTeam\Cms\Modules\CoreAuth\UserRepositoryInterface;
use SyntaxDevTeam\Cms\Modules\Articles\ArticleRepository;
use SyntaxDevTeam\Cms\Modules\Articles\ArticlesModule;
use SyntaxDevTeam\Cms\Modules\CorePages\CorePagesModule;
use SyntaxDevTeam\Cms\Modules\CorePages\HomepageSectionRepository;
use SyntaxDevTeam\Cms\Modules\CorePages\HomepageSectionItemRepository;
use SyntaxDevTeam\Cms\Modules\CorePages\PageRepository;
use SyntaxDevTeam\Cms\Modules\System\DemoAdminModule;

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
$coreAuthModule = new CoreAuthModule(
    $theme,
    $security,
    $auth,
    $providers,
    new OAuthStateStore(),
    new OAuthAttemptLimiter(
        (int) ($authConfig['oauth_window_seconds'] ?? 600),
        (int) ($authConfig['oauth_start_limit'] ?? 10),
        (int) ($authConfig['oauth_callback_limit'] ?? 20)
    ),
    $audit,
    $authDemoEnabled
);
$modules->add($coreAuthModule);
$pageRepository = $application->database() !== null
    ? new PageRepository($application->database())
    : null;
$homepageSectionRepository = $application->database() !== null
    ? new HomepageSectionRepository($application->database())
    : null;
$homepageSectionItemRepository = $application->database() !== null
    ? new HomepageSectionItemRepository($application->database())
    : null;
$corePagesModule = $pageRepository !== null
    && $homepageSectionRepository !== null
    && $homepageSectionItemRepository !== null
    ? new CorePagesModule(
        $theme,
        $adminMenu,
        $pageRepository,
        $homepageSectionRepository,
        $homepageSectionItemRepository,
        $auth,
        $access,
        $security,
        $audit
    )
    : null;

if ($corePagesModule !== null) {
    $modules->add($corePagesModule);
}

$articlesModule = $application->database() !== null
    ? new ArticlesModule(
        $theme,
        $adminMenu,
        new ArticleRepository($application->database()),
        $auth,
        $access,
        $security,
        $audit
    )
    : null;

if ($articlesModule !== null) {
    $modules->add($articlesModule);
}

$demoAdminModule = new DemoAdminModule(
    $theme,
    $adminMenu,
    $auth,
    $access,
    $security,
    $audit
);
$modules->add($demoAdminModule);
$modules->boot($adminMenu, $router);

$renderStart = static function (string $title, string $lead) use ($theme): void {
    $theme->start_page(
        $title . ' - miniPORTAL',
        $lead
    );
    $theme->start_header($title, $lead);
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
            static fn ($page): array => ['title' => $page->title, 'slug' => $page->slug],
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
