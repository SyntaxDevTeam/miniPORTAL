<?php

declare(strict_types=1);

use SyntaxDevTeam\Cms\Core\Autoloader;
use SyntaxDevTeam\Cms\Core\AdminMenuRegistry;
use SyntaxDevTeam\Cms\Core\AdminSearchRegistry;
use SyntaxDevTeam\Cms\Core\BrandIconGenerator;
use SyntaxDevTeam\Cms\Core\ContentRenderer;
use SyntaxDevTeam\Cms\Core\DashboardRegistry;
use SyntaxDevTeam\Cms\Core\FileTemplateCache;
use SyntaxDevTeam\Cms\Core\HookProviderInterface;
use SyntaxDevTeam\Cms\Core\HookRegistry;
use SyntaxDevTeam\Cms\Core\ModuleArchiveImporter;
use SyntaxDevTeam\Cms\Core\ModuleBootstrapper;
use SyntaxDevTeam\Cms\Core\ModuleInterface;
use SyntaxDevTeam\Cms\Core\ModulePackageExporter;
use SyntaxDevTeam\Cms\Core\ModuleManifestValidator;
use SyntaxDevTeam\Cms\Core\ModuleRegistry;
use SyntaxDevTeam\Cms\Core\ModuleState;
use SyntaxDevTeam\Cms\Core\PublicNavigationProviderInterface;
use SyntaxDevTeam\Cms\Core\PublicNavigationRegistry;
use SyntaxDevTeam\Cms\Core\Request;
use SyntaxDevTeam\Cms\Core\RichTextSanitizer;
use SyntaxDevTeam\Cms\Core\Router;
use SyntaxDevTeam\Cms\Core\Security;
use SyntaxDevTeam\Cms\Core\ThemeEngine;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuthorizationService;
use SyntaxDevTeam\Cms\Modules\CoreAuth\HttpClientInterface;
use SyntaxDevTeam\Cms\Modules\CoreAuth\HttpResponse;
use SyntaxDevTeam\Cms\Modules\CoreAuth\OAuthAttemptLimiter;
use SyntaxDevTeam\Cms\Modules\CoreAuth\OAuthStateStore;
use SyntaxDevTeam\Cms\Modules\CoreAuth\User;
use SyntaxDevTeam\Cms\Modules\Articles\ArticlesModule;
use SyntaxDevTeam\Cms\Modules\System\AuditCsvExporter;
use SyntaxDevTeam\Cms\Modules\DatabaseManager\DatabaseExplorerRepository;
use SyntaxDevTeam\Cms\Modules\DatabaseManager\DatabaseTableCsvExporter;
use SyntaxDevTeam\Cms\Modules\DatabaseManager\DatabaseTableSqlExporter;
use SyntaxDevTeam\Cms\Modules\Econify\EconifyConfig;
use SyntaxDevTeam\Cms\Modules\Econify\EconifyDiscordGateway;
use SyntaxDevTeam\Cms\Modules\PluginTranslator\PluginTranslatorYaml;
use SyntaxDevTeam\Cms\Modules\PluginTranslator\MinecraftFormatPreview;
use SyntaxDevTeam\Cms\Modules\Widgets\Widget;
use SyntaxDevTeam\Cms\Modules\Widgets\WidgetLayout;
use SyntaxDevTeam\Cms\Templates\DefaultTheme\Theme as DefaultTheme;
use SyntaxDevTeam\Cms\Installer\Installer;

require_once dirname(__DIR__) . '/core/Autoloader.php';

Autoloader::register();

require_once dirname(__DIR__) . '/templates/default/theme.php';
require_once dirname(__DIR__) . '/install/cms-source/Installer.php';

$failures = [];
$test = static function (string $name, callable $callback) use (&$failures): void {
    try {
        $callback();
        echo "PASS {$name}\n";
    } catch (Throwable $exception) {
        $failures[] = "{$name}: {$exception->getMessage()}";
        echo "FAIL {$name}\n";
    }
};
$assert = static function (bool $condition, string $message = 'Assertion failed'): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

session_id('miniportal-tests-' . bin2hex(random_bytes(8)));
session_start();

$test('Request normalizes route and input', static function () use ($assert): void {
    $request = Request::fromArrays(
        ['route' => '//admin///pages/', 'id' => '12'],
        ['confirmed' => '1', 'roles' => ['editor', 'user', 'editor'], 'values' => ['title' => 'Demo']],
        ['REQUEST_METHOD' => 'post'],
        ['archive' => [
            'name' => '../Module.tar.gz',
            'type' => 'application/gzip',
            'tmp_name' => '/tmp/uploaded-module',
            'error' => UPLOAD_ERR_OK,
            'size' => 123,
        ]]
    );

    $assert($request->path() === '/admin/pages');
    $assert($request->method() === 'POST');
    $assert($request->queryInt('id') === 12);
    $assert($request->postBool('confirmed'));
    $assert($request->postStringList('roles') === ['editor', 'user']);
    $assert($request->postArray('values') === ['title' => 'Demo']);
    $file = $request->file('archive');
    $assert($file !== null && $file['name'] === 'Module.tar.gz');
    $assert($file['error'] === UPLOAD_ERR_OK && $file['size'] === 123);
});

$test('Request exposes bounded JSON and normalized headers', static function () use ($assert): void {
    $request = Request::fromArrays([], [], [
        'REQUEST_METHOD' => 'POST',
        'HTTP_X_BUILD_TOKEN' => 'secret-token',
        'CONTENT_TYPE' => 'application/json',
    ], [], '{"id":24,"channel":"DEV"}');

    $assert($request->header('X-Build-Token') === 'secret-token');
    $assert($request->header('Content-Type') === 'application/json');
    $assert($request->json() === ['id' => 24, 'channel' => 'DEV']);
});

$test('Econify loads an isolated module environment file', static function () use ($assert): void {
    $file = sys_get_temp_dir() . '/econify-env-' . bin2hex(random_bytes(6));
    file_put_contents($file, implode(PHP_EOL, [
        'ECONIFY_API_TOKEN="' . str_repeat('a', 64) . '"',
        'ECONIFY_DISCORD_CLIENT_ID="client-test"',
        'ECONIFY_DISCORD_CLIENT_SECRET="secret-test"',
        'ECONIFY_DISCORD_BOT_TOKEN="bot-test"',
        'ECONIFY_DISCORD_CALLBACK_URL="https://econify.example.test/callback"',
        'ECONIFY_DISCORD_BOT_PERMISSIONS=32',
    ]));
    putenv('ECONIFY_ENV_FILE=' . $file);
    try {
        $config = EconifyConfig::load(dirname(__DIR__) . '/modules/Econify');
        $assert($config->apiConfigured());
        $assert($config->discordApplicationConfigured());
        $assert($config->botTokenConfigured());
        $assert($config->discordBotPermissions === 32);
        $assert($config->environmentFile === $file);
    } finally {
        putenv('ECONIFY_ENV_FILE');
        unlink($file);
    }
});

$test('Econify discovers only manageable Discord guilds without storing token', static function () use ($assert): void {
    $http = new class implements HttpClientInterface {
        public function request(string $method, string $url, array $headers = [], array $form = []): HttpResponse
        {
            if (str_ends_with($url, '/oauth2/token')) { return new HttpResponse(200, '{"access_token":"access-test"}'); }
            if (str_ends_with($url, '/users/@me/guilds')) {
                return new HttpResponse(200, json_encode([
                    ['id' => '1000001', 'name' => 'Owner Guild', 'owner' => true, 'permissions' => '0', 'icon' => null],
                    ['id' => '1000002', 'name' => 'Managed Guild', 'owner' => false, 'permissions' => '32', 'icon' => 'iconhash'],
                    ['id' => '1000003', 'name' => 'Member Guild', 'owner' => false, 'permissions' => '0', 'icon' => null],
                ], JSON_THROW_ON_ERROR));
            }
            if (str_ends_with($url, '/users/@me')) { return new HttpResponse(200, '{"id":"9000001"}'); }
            return new HttpResponse(404, '{}');
        }
    };
    $config = new EconifyConfig(str_repeat('a', 64), 'bot-token', 'client-id', 'client-secret', 'https://portal.example.test/index.php?route=/econify/discord/callback', 32, '/tmp/econify-test', true);
    $gateway = new EconifyDiscordGateway($http, new OAuthStateStore(), $config);
    $authorizationUrl = $gateway->discoveryUrl(42);
    parse_str((string) parse_url($authorizationUrl, PHP_URL_QUERY), $query);
    $guilds = $gateway->complete((string) ($query['state'] ?? ''), 'discord-code', 42);
    $assert(count($guilds) === 2);
    $assert(array_column($guilds, 'id') === ['1000002', '1000001']);
    $assert($gateway->discordUserId(42) === '9000001');
    $installUrl = $gateway->installationUrl('1000001');
    $assert(str_contains($installUrl, 'scope=bot%20applications.commands'));
    $assert(str_contains($installUrl, 'guild_id=1000001'));
    $assert(!str_contains(serialize($_SESSION), 'access-test'));
    unset($_SESSION['_econify_discord_guilds']);
});

$test('Audit CSV export neutralizes spreadsheet formulas', static function () use ($assert): void {
    $stream = fopen('php://temp', 'w+b');
    (new AuditCsvExporter())->write($stream, [[
        'created_at' => '2026-06-15 12:00:00',
        'display_name' => '=HYPERLINK("https://example.test")',
        'event_type' => 'module_install',
        'result' => 'success',
        'provider' => "module\r\nname",
        'ip_hash' => str_repeat('a', 64),
        'user_agent' => '@command',
    ]]);
    rewind($stream);
    $csv = (string) stream_get_contents($stream);
    fclose($stream);

    $assert(str_starts_with($csv, "\xEF\xBB\xBF"));
    $assert(str_contains($csv, "'=HYPERLINK"));
    $assert(str_contains($csv, "'@command"));
    $assert(!str_contains($csv, "module\r\nname"));
});

$test('Database table CSV export neutralizes spreadsheet formulas', static function () use ($assert): void {
    $stream = fopen('php://temp', 'w+b');
    (new DatabaseTableCsvExporter())->write(
        $stream,
        ['id', '=bad-header'],
        [['1', '=cmd'], ['2', "@risk\nline"]]
    );
    rewind($stream);
    $csv = (string) stream_get_contents($stream);
    fclose($stream);

    $assert(str_starts_with($csv, "\xEF\xBB\xBF"));
    $assert(str_contains($csv, "'=bad-header"));
    $assert(str_contains($csv, "'=cmd"));
    $assert(str_contains($csv, "'@risk line"));
});

$test('Database table SQL export creates portable insert dump', static function () use ($assert): void {
    $stream = fopen('php://temp', 'w+b');
    $repository = (new ReflectionClass(SyntaxDevTeam\Cms\Modules\DatabaseManager\DatabaseExplorerRepository::class))
        ->newInstanceWithoutConstructor();
    $quote = static fn (mixed $value): string => $value === null
        ? 'NULL'
        : "'" . str_replace("'", "''", (string) $value) . "'";
    (new DatabaseTableSqlExporter($repository, $quote))->write($stream, 'demo_table', [
        'create' => 'CREATE TABLE `demo_table` (`id` int NOT NULL, `title` varchar(120) NULL)',
        'columns' => ['id', 'title'],
        'rows' => [['id' => 1, 'title' => "O'Hara"], ['id' => 2, 'title' => null]],
        'total' => 2,
        'exported' => 2,
    ]);
    rewind($stream);
    $sql = (string) stream_get_contents($stream);
    fclose($stream);

    $assert(str_contains($sql, 'DROP TABLE IF EXISTS `demo_table`;'));
    $assert(str_contains($sql, 'CREATE TABLE `demo_table`'));
    $assert(str_contains($sql, 'INSERT INTO `demo_table` (`id`, `title`) VALUES'));
    $assert(str_contains($sql, "('1', 'O''Hara')"));
    $assert(str_contains($sql, "('2', NULL)"));
});

$test('Plugin translator parses and exports translated YAML', static function () use ($assert): void {
    $translator = new PluginTranslatorYaml();
    $source = <<<'YAML'
general:
  enabled: Plugin jest włączony.
  disabled: Plugin jest wyłączony.
commands:
  reload:
    success: Przeładowano konfigurację.
YAML;

    $parsed = $translator->parse($source);
    $items = $translator->flatten($parsed);
    $assert(count($items) === 3);
    $assert($items[0]['label'] === 'general.enabled');

    $translated = $translator->translated($parsed, [
        $items[0]['token'] => 'Plugin is enabled.',
        $items[2]['token'] => 'Configuration reloaded.',
    ]);
    $assert($translator->translatedCount($items, [
        $items[0]['token'] => 'Plugin is enabled.',
        $items[1]['token'] => 'Plugin jest wyłączony.',
        $items[2]['token'] => 'Configuration reloaded.',
    ]) === 2);
    $dump = $translator->dump($translated);
    $roundTrip = $translator->parse($dump);

    $assert($roundTrip['general']['enabled'] === 'Plugin is enabled.');
    $assert($roundTrip['general']['disabled'] === 'Plugin jest wyłączony.');
    $assert($roundTrip['commands']['reload']['success'] === 'Configuration reloaded.');
});

$test('Plugin translator rejects empty YAML', static function () use ($assert): void {
    try {
        (new PluginTranslatorYaml())->parse('   ');
    } catch (InvalidArgumentException) {
        $assert(true);
        return;
    }

    throw new RuntimeException('Empty YAML should be rejected.');
});

$test('Minecraft formatting preview parses legacy and MiniMessage styles', static function () use ($assert): void {
    $preview = new MinecraftFormatPreview();
    $result = $preview->preview('&aOK <bold>ważne</bold> <#ff00aa>RGB</#ff00aa> <player>');
    $segments = $result['segments'];

    $assert($segments !== []);
    $assert($segments[0]['color'] === '#55FF55');
    $assert($segments[1]['bold'] || $segments[2]['bold']);
    $assert($segments[count($segments) - 2]['color'] === '#FF00AA');
    $assert($result['issues'] === []);
    $assert($result['variables'] === ['<player>']);
    $assert($preview->segments('§cBłąd')[0]['color'] === '#FF5555');
    $broken = $preview->preview('<bold>Brak zamknięcia <#12zzzz> <player>');
    $assert($broken['issues'] !== []);
    $assert(in_array('<player>', $broken['variables'], true));
});

$test('Database SQL console accepts only one read-only statement', static function () use ($assert): void {
    $assert(DatabaseExplorerRepository::normalizeReadOnlyQuery(' SELECT * FROM users; ') === 'SELECT * FROM users');
    $assert(DatabaseExplorerRepository::normalizeReadOnlyQuery('SHOW TABLES') === 'SHOW TABLES');

    foreach (['UPDATE users SET status = "active"', 'SELECT 1; DROP TABLE users', ''] as $sql) {
        $rejected = false;
        try {
            DatabaseExplorerRepository::normalizeReadOnlyQuery($sql);
        } catch (RuntimeException) {
            $rejected = true;
        }
        $assert($rejected);
    }
});

$test('Database SQL console validates managed write statements', static function () use ($assert): void {
    $insert = DatabaseExplorerRepository::normalizeMutableQuery(' INSERT INTO logs (message) VALUES ("ok"); ');
    $drop = DatabaseExplorerRepository::normalizeMutableQuery('DROP TABLE temporary_table');

    $assert($insert['sql'] === 'INSERT INTO logs (message) VALUES ("ok")');
    $assert($insert['operation'] === 'insert');
    $assert($drop['operation'] === 'drop');

    foreach (['SELECT * FROM users', 'GRANT ALL ON *.* TO root', 'UPDATE users SET status = "active"; DROP TABLE users', ''] as $sql) {
        $rejected = false;
        try {
            DatabaseExplorerRepository::normalizeMutableQuery($sql);
        } catch (RuntimeException) {
            $rejected = true;
        }
        $assert($rejected);
    }
});

$test('Database SQL import validates dump payloads', static function () use ($assert): void {
    $sql = DatabaseExplorerRepository::normalizeImportSql("SET NAMES utf8mb4;\nCREATE TABLE demo_import (id INT);");

    $assert(str_contains($sql, 'CREATE TABLE demo_import'));

    foreach (['', "SELECT\0 1;", str_repeat('a', (2 * 1024 * 1024) + 1)] as $payload) {
        $rejected = false;
        try {
            DatabaseExplorerRepository::normalizeImportSql($payload);
        } catch (RuntimeException) {
            $rejected = true;
        }
        $assert($rejected);
    }
});

$test('Template cache remembers output and invalidates matching tags', static function () use ($assert): void {
    $directory = sys_get_temp_dir() . '/miniportal-template-cache-' . bin2hex(random_bytes(6));
    mkdir($directory, 0700, true);
    $cache = new FileTemplateCache($directory, true, 60);
    $renders = 0;
    $renderer = static function () use (&$renders): string {
        $renders++;
        return 'render-' . $renders;
    };

    $assert($cache->remember('public', 'homepage', $renderer, ['homepage', 'theme']) === 'render-1');
    $assert($cache->remember('public', 'homepage', $renderer, ['homepage', 'theme']) === 'render-1');
    $assert($renders === 1);
    $assert($cache->invalidateTags(['articles']) === 0);
    $assert($cache->invalidateTags(['homepage']) === 1);
    $assert($cache->remember('public', 'homepage', $renderer, ['homepage']) === 'render-2');
    $stats = $cache->stats();
    $assert($stats['entries'] === 1);
    $assert($stats['expired'] === 0);
    $assert($stats['bytes'] > 0 && $stats['ttl'] === 60 && $stats['writable']);
    $cache->clear();
    @rmdir($directory);
});

$test('Brand icon generator creates browser and mobile variants', static function () use ($assert): void {
    $directory = sys_get_temp_dir() . '/miniportal-brand-icons-' . bin2hex(random_bytes(6));
    $source = dirname(__DIR__) . '/theme/ico/SyntaxDevTeam_logo.no_bg.png';
    $generator = new BrandIconGenerator(dirname(__DIR__), $directory);
    $generator->generate([
        'name' => 'brand.png',
        'type' => 'image/png',
        'tmp_name' => $source,
        'error' => UPLOAD_ERR_OK,
        'size' => filesize($source),
    ], 'Portal testowy', '#123456');

    foreach (['favicon.ico', 'favicon-16x16.png', 'favicon-256x256.png', 'apple-touch-icon.png', 'icon-512.png', 'site.webmanifest'] as $file) {
        $assert(is_file($directory . '/' . $file) && filesize($directory . '/' . $file) > 0, 'Brak wariantu: ' . $file);
    }
    $iconSize = getimagesize($directory . '/icon-512.png');
    $assert(is_array($iconSize) && $iconSize[0] === 512 && $iconSize[1] === 512);
    $manifest = json_decode((string) file_get_contents($directory . '/site.webmanifest'), true);
    $assert(($manifest['name'] ?? '') === 'Portal testowy');
    $assert(($manifest['theme_color'] ?? '') === '#123456');
    foreach (glob($directory . '/*') ?: [] as $file) {
        @unlink($file);
    }
    @rmdir($directory);
});

$test('Unknown identity becomes a pending account', static function () use ($assert): void {
    $_SESSION = [];
    $repository = new SyntaxDevTeam\Cms\Modules\CoreAuth\InMemoryUserRepository();
    $auth = new SyntaxDevTeam\Cms\Modules\CoreAuth\AuthService($repository, new Security());
    $identity = new SyntaxDevTeam\Cms\Modules\CoreAuth\ExternalIdentity(
        'test',
        'new-subject',
        'Nowy użytkownik'
    );

    $logged = $auth->loginIdentity($identity);
    $pending = $repository->findByIdentity('test', 'new-subject');
    $assert($pending !== null && $pending->status === 'pending');
    $assert($logged !== null && $logged->status === 'pending');
    $assert($auth->user() !== null && $auth->user()?->status === 'pending');
    $assert($pending->roles === ['user']);
});

$test('Authenticated user can update profile and avatar through auth service', static function () use ($assert): void {
    $_SESSION = [];
    $repository = new SyntaxDevTeam\Cms\Modules\CoreAuth\InMemoryUserRepository();
    $auth = new SyntaxDevTeam\Cms\Modules\CoreAuth\AuthService($repository, new Security());
    $_SESSION['_miniportal_user_id'] = 1;

    $assert($auth->updateProfile('Nowa nazwa', 'nowy@example.test'));
    $assert($auth->updateAvatar('https://example.test/avatar.png'));
    $user = $auth->user();
    $assert($user !== null);
    $assert($user->displayName === 'Nowa nazwa');
    $assert($user->email === 'nowy@example.test');
    $assert($user->avatarUrl === 'https://example.test/avatar.png');
});

$test('OAuth state is one-time and provider-bound', static function () use ($assert): void {
    $_SESSION = [];
    $store = new OAuthStateStore();
    $issued = $store->issue('google', 'login');
    $assert($store->consume('github', $issued['state']) === null);

    $issued = $store->issue('google', 'link', 7);
    $context = $store->consume('google', $issued['state']);
    $assert($context !== null && $context->purpose === 'link' && $context->userId === 7);
    $assert($store->consume('google', $issued['state']) === null, 'Replay callback was accepted');
});

$test('OAuth limiter rejects excess attempts and separates providers', static function () use ($assert): void {
    $_SESSION = [];
    $now = 1000;
    $limiter = new OAuthAttemptLimiter(60, 2, 2, static fn (): int => $now);

    $assert($limiter->allowStart('google'));
    $assert($limiter->allowStart('google'));
    $assert(!$limiter->allowStart('google'));
    $assert($limiter->allowStart('github'));
    $assert($limiter->allowCallback('google'));
    $assert($limiter->allowCallback('google'));
    $assert(!$limiter->allowCallback('google'));
});

$test('CSRF accepts only the session token', static function () use ($assert): void {
    $_SESSION = [];
    $security = new Security();
    $token = $security->csrfToken();

    $assert(strlen($token) === 64);
    $assert($security->validateCsrfToken($token));
    $assert(!$security->validateCsrfToken(str_repeat('0', 64)));
});

$test('Rich text keeps formatting and removes executable markup', static function () use ($assert): void {
    $sanitizer = new RichTextSanitizer();
    $result = $sanitizer->sanitize(
        '<h2 class="x">Nagłówek</h2><p onclick="alert(1)">Treść <strong>ważna</strong>'
        . '<script>alert(1)</script><img src=x onerror=alert(2)></p>'
    );

    $assert($result === '<h2>Nagłówek</h2><p>Treść <strong>ważna</strong></p>');
});

$test('Markdown renders GitHub-style blocks without executable HTML', static function () use ($assert): void {
    $renderer = new ContentRenderer();
    $markdown = <<<'MD'
# Dokument

**Ważne** i [odnośnik](https://example.com).

- [x] Gotowe
- [ ] Plan

| Nazwa | Stan |
| --- | --- |
| Core | OK |

```html
<script>alert(1)</script>
```

[atak](javascript:alert(1))
MD;
    $result = $renderer->render($markdown, 'markdown');

    $assert(str_contains($result, '<h1>Dokument</h1>'));
    $assert(str_contains(
        $renderer->render('przykładowy `tekst`', 'markdown'),
        '<p>przykładowy <code>tekst</code></p>'
    ));
    $assert(str_contains(
        $renderer->render('![Status](https://img.shields.io/badge/status-online-green)', 'markdown'),
        '<img src="https://img.shields.io/badge/status-online-green" alt="Status" loading="lazy">'
    ));
    $assert(str_contains(
        $renderer->render(
            '[![Build](https://example.com/build.svg)](https://example.com/actions)',
            'markdown'
        ),
        '<a href="https://example.com/actions" rel="nofollow noopener noreferrer">'
        . '<img src="https://example.com/build.svg" alt="Build" loading="lazy"></a>'
    ));
    $assert(
        $renderer->render('Myślą, większą kontrolą i kończącą się zawartością.', 'markdown')
        === '<p>Myślą, większą kontrolą i kończącą się zawartością.</p>'
    );
    $assert(str_contains($result, '<table>'));
    $assert(str_contains($result, 'type="checkbox" disabled checked'));
    $assert(str_contains($result, '&lt;script&gt;alert(1)&lt;/script&gt;'));
    $assert(!str_contains($result, '<script>'));
    $assert(!str_contains($result, 'href="javascript:'));
    $assert($renderer->prepareForStorage("  # Źródło\n", 'markdown') === '# Źródło');
});

$test('Authorization rejects missing permission and blocked account', static function () use ($assert): void {
    $authorization = new AuthorizationService();
    $active = new User(1, 'Active', null, null, 'active', ['editor'], ['pages.view']);
    $blocked = new User(2, 'Blocked', null, null, 'blocked', ['administrator'], ['*']);
    $owner = new User(3, 'Owner', null, null, 'active', ['owner'], ['*']);

    $assert($authorization->allows($active, 'pages.view'));
    $assert(!$authorization->allows($active, 'users.manage'));
    $assert(!$authorization->allows($blocked, 'pages.view'));
    $assert($authorization->allows($owner, 'future_module.manage'));
});

$test('Module registry boots each module once', static function () use ($assert): void {
    $registry = new ModuleRegistry();
    $menu = new AdminMenuRegistry();
    $router = new Router();
    $publicNavigation = new PublicNavigationRegistry();
    $module = new class implements ModuleInterface, PublicNavigationProviderInterface {
        public function id(): string
        {
            return 'test_module';
        }

        public function version(): string
        {
            return '1.0.0';
        }

        public function dependencies(): array
        {
            return [];
        }

        public function isProtected(): bool
        {
            return false;
        }

        public function requiredPermissions(): array
        {
            return [];
        }

        public function registerAdminMenu(AdminMenuRegistry $menu): void
        {
            $menu->add('Test', 'Test', '/test-module', 'TS', 'test.view');
        }

        public function registerRoutes(Router $router): void
        {
            $router->get('/test-module', static function (): void {
            });
        }

        public function registerPublicNavigation(PublicNavigationRegistry $navigation): void
        {
            $navigation->add('test_module.index', 'Test publiczny', '/test-module', 'footer', 20);
        }
    };

    $registry->add($module);
    $registry->boot($menu, $router, $publicNavigation);
    $request = Request::fromArrays(['route' => '/test-module'], [], ['REQUEST_METHOD' => 'GET']);
    $publicItems = $publicNavigation->items();

    $assert($registry->ids() === ['test_module']);
    $assert($router->dispatch($request) === 200);
    $assert(count($menu->visibleFor(['test.view'])) === 1);
    $assert($publicItems[0]['id'] === 'test_module.index');
    $assert($publicItems[0]['area'] === 'footer');
    $assert(!$publicItems[0]['show_main']);
    $assert($publicItems[0]['show_footer']);
});

$test('Admin menu keeps entries from the same section together', static function () use ($assert): void {
    $menu = new AdminMenuRegistry();
    $menu->add('Przestrzeń robocza', 'Dashboard', '/admin', 'DB', 'admin.access', 10);
    $menu->add('System', 'Użytkownicy', '/admin/users', 'US', 'users.view', 40);
    $menu->add('Treść', 'Strona główna', '/admin/homepage', 'HG', 'pages.view', 15);
    $menu->add('Treść', 'Zespół', '/admin/team', 'TM', 'team.manage', 40);
    $menu->add('System', 'Role', '/admin/roles', 'RL', 'roles.view', 45);

    $items = $menu->visibleFor(['*']);
    $assert(array_column($items, 'section') === [
        'Przestrzeń robocza',
        'Treść',
        'Treść',
        'System',
        'System',
    ]);
    $assert(array_column($items, 'label') === [
        'Dashboard',
        'Strona główna',
        'Zespół',
        'Użytkownicy',
        'Role',
    ]);
});

$test('Public navigation supports custom labels and multiple placements', static function () use ($assert): void {
    $navigation = new PublicNavigationRegistry();
    $navigation->add('module.docs', 'Dokumentacja', '/wiki', 'none', 10);
    $navigation->add('module.projects', 'Projekty', '/projects', 'none', 20);

    $legacy = array_values(array_filter(
        $navigation->items(['module.docs' => 'footer']),
        static fn (array $item): bool => $item['id'] === 'module.docs'
    ))[0];
    $assert($legacy['label'] === 'Dokumentacja');
    $assert($legacy['area'] === 'footer');
    $assert(!$legacy['show_main']);
    $assert($legacy['show_footer']);

    $configured = $navigation->items([
        'module.docs' => [
            'label' => 'Baza wiedzy',
            'main' => true,
            'footer' => true,
            'order' => 90,
        ],
        'module.projects' => ['label' => 'Projekty', 'main' => true, 'footer' => false, 'order' => 5],
    ]);
    $assert($configured[0]['id'] === 'module.projects');
    $configured = $configured[1];
    $assert($configured['default_label'] === 'Dokumentacja');
    $assert($configured['label'] === 'Baza wiedzy');
    $assert($configured['area'] === 'main');
    $assert($configured['show_main']);
    $assert($configured['show_footer']);
    $assert($configured['order'] === 90);
});

$test('Hook registry runs actions and filters by priority', static function () use ($assert): void {
    $hooks = new HookRegistry();
    $actions = [];
    $hooks->addAction('content.saved', static function (string $id) use (&$actions): void {
        $actions[] = 'late:' . $id;
    }, 200);
    $hooks->addAction('content.saved', static function (string $id) use (&$actions): void {
        $actions[] = 'early:' . $id;
    }, 10);
    $hooks->addFilter('homepage.sections', static fn (array $sections): array => [...$sections, ['id' => 'widget']]);
    $hooks->addFilter('homepage.sections', static fn (array $sections): array => [...$sections, ['id' => 'first']], 10);

    $hooks->doAction('content.saved', '42');
    $sections = $hooks->applyFilters('homepage.sections', []);

    $assert($actions === ['early:42', 'late:42']);
    $assert(array_column($sections, 'id') === ['first', 'widget']);
});

$test('Module registry registers optional hook providers', static function () use ($assert): void {
    $registry = new ModuleRegistry();
    $hooks = new HookRegistry();
    $module = new class implements ModuleInterface, HookProviderInterface {
        public function id(): string { return 'hook_module'; }
        public function version(): string { return '1.0.0'; }
        public function dependencies(): array { return []; }
        public function isProtected(): bool { return false; }
        public function requiredPermissions(): array { return []; }
        public function registerAdminMenu(AdminMenuRegistry $menu): void {}
        public function registerRoutes(Router $router): void {}
        public function registerHooks(HookRegistry $hooks): void
        {
            $hooks->addFilter('homepage.sections', static fn (array $sections): array => [...$sections, ['id' => 'module-widget']]);
        }
    };
    $registry->add($module);
    $registry->boot(new AdminMenuRegistry(), new Router(), hooks: $hooks);

    $assert($hooks->applyFilters('homepage.sections', []) === [['id' => 'module-widget']]);
});

$test('Router resolves validated slug parameters without database-built routes', static function () use ($assert): void {
    $router = new Router();
    $resolved = [];
    $router->get('/article/archive', static function () use (&$resolved): void {
        $resolved = ['static'];
    });
    $router->get('/article/{slug}', static function (Request $request) use (&$resolved): void {
        $resolved = [$request->routeString('slug')];
    });

    $status = $router->dispatch(Request::fromArrays([], [], [
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI' => '/article/nowy%20wpis',
    ]));
    $assert($status === 200 && $resolved === ['nowy wpis']);

    $router->dispatch(Request::fromArrays([], [], [
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI' => '/article/archive',
    ]));
    $assert($resolved === ['static']);
    $assert($router->dispatch(Request::fromArrays([], [], [
        'REQUEST_METHOD' => 'POST',
        'REQUEST_URI' => '/article/test',
    ])) === 405);
    $assert($router->dispatch(Request::fromArrays([], [], [
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI' => '/article/path%2Fescape',
    ])) === 404);
});

$test('Admin search index filters entries by ACL and imports menu keywords', static function () use ($assert): void {
    $menu = new AdminMenuRegistry();
    $menu->add('Treść', 'Zespół', '/admin/team', 'TM', 'team.manage', 40);
    $search = new AdminSearchRegistry();
    $search->add('team.create', 'Dodaj członka', 'Nowy profil zespołu', '/admin/team/create', ['team'], 'team.manage');
    $search->importMenu($menu->items());

    $assert($search->visibleFor([]) === []);
    $items = $search->visibleFor(['team.manage']);
    $assert(count($items) === 2);
    $assert(str_contains(implode(' ', array_column($items, 'keywords')), 'team'));
});

$test('Dashboard registry exposes configurable module metrics', static function () use ($assert): void {
    $dashboard = new DashboardRegistry();
    $dashboard->addMetric('team.members', 'Zespół', 'Profile', 'TM', static fn (): array => [
        'value' => 3,
        'detail' => '2 widoczne',
    ], 'team.manage');

    $assert($dashboard->metrics([], []) === []);
    $metrics = $dashboard->metrics(['team.manage'], []);
    $assert($metrics[0]['value'] === '3');
    $assert($dashboard->metrics(['team.manage'], ['team.members' => false]) === []);
});

$test('Articles module exposes a configurable public navigation link', static function () use ($assert): void {
    $assert(is_subclass_of(ArticlesModule::class, PublicNavigationProviderInterface::class));
});

$test('Public theme exposes common Home and Kontakt navigation on subpages', static function () use ($assert): void {
    $theme = new DefaultTheme([
        'public_path' => '/test',
        'public_name' => 'SyntaxDevTeam',
        'public_meta_description' => 'Opis testowy',
    ]);

    ob_start();
    $theme->start_page('Test', 'Opis');
    $theme->end_page();
    $html = (string) ob_get_clean();

    $assert(str_contains($html, 'href="/">Home</a>'));
    $assert(str_contains($html, 'href="/#contact">Kontakt</a>'));

    $publicNavigation = [
        [
            'title' => 'Econify',
            'slug' => '',
            'href' => '/econify',
            'summary' => '',
            'type' => 'module',
            'navigation_area' => 'main',
            'navigation_label' => 'Econify',
            'sort_order' => 35,
        ],
        [
            'title' => 'Projekty',
            'slug' => '',
            'href' => '/projects',
            'summary' => '',
            'type' => 'module',
            'navigation_area' => 'main',
            'navigation_label' => 'Projekty',
            'sort_order' => 55,
        ],
    ];
    $theme->set_public_navigation($publicNavigation, true);
    $navbar = new ReflectionMethod($theme, 'renderPublicNavbar');
    $navbar->setAccessible(true);

    ob_start();
    $navbar->invoke($theme, $publicNavigation, true, [
        ['type' => 'hero', 'key' => 'hero', 'layout' => 'split', 'eyebrow' => '', 'title' => 'Hero'],
        ['type' => 'content', 'key' => 'contact', 'layout' => 'contact', 'eyebrow' => '03 / Kontakt', 'title' => 'Kontakt'],
    ], true);
    $html = (string) ob_get_clean();

    $econifyPosition = strpos($html, '>Econify</a>');
    $projectsPosition = strpos($html, '>Projekty</a>');
    $contactPosition = strpos($html, '>Kontakt</a>');
    $panelPosition = strpos($html, '>Otwórz panel</a>');
    $assert($econifyPosition !== false && $projectsPosition !== false && $contactPosition !== false);
    $assert($econifyPosition < $contactPosition);
    $assert($projectsPosition < $contactPosition);
    $assert($contactPosition < $panelPosition);
});

$test('Theme exposes SyntaxDevTeam brand assets for browsers and social previews', static function () use ($assert): void {
    $theme = new DefaultTheme([
        'public_url' => 'https://syntaxdevteam.pl',
        'public_path' => '/projects',
        'public_name' => 'SyntaxDevTeam',
        'public_default_title' => 'Domyślny tytuł',
        'public_meta_description' => 'Opis testowy',
        'public_meta_author' => 'SyntaxDevTeam',
        'public_meta_robots' => 'index, follow, max-image-preview:large',
        'public_locale' => 'pl_PL',
        'public_social_image_url' => '/social/cover.png',
        'public_social_image_alt' => 'Okładka SyntaxDevTeam',
        'public_twitter_site' => 'SyntaxDevTeam',
        'public_theme_color' => '#112233',
        'public_google_site_verification' => 'google-token_123',
        'public_bing_site_verification' => 'bing-token_123',
        'public_footer_text' => 'Powered by miniPORTAL by SyntaxDevTeam',
    ]);
    $theme->set_public_navigation([[
        'title' => 'Projekty',
        'slug' => 'projects',
        'href' => '/projects',
        'summary' => '',
        'type' => 'module',
        'navigation_area' => 'main',
        'navigation_label' => 'Projekty',
    ]], false);

    ob_start();
    $theme->start_page('Test', 'Opis');
    $theme->end_page();
    $html = (string) ob_get_clean();

    $assert(str_contains($html, 'img/brand/favicon.ico'));
    $assert(str_contains($html, 'img/brand/favicon-256x256.png'));
    $assert(str_contains($html, 'apple-touch-icon'));
    $assert(str_contains($html, 'apple-mobile-web-app-capable'));
    $assert(str_contains($html, 'site.webmanifest'));
    $assert(str_contains($html, 'class="site-brand-logo"'));
    $assert(str_contains($html, '<html lang="pl-PL"'));
    $assert(str_contains($html, 'name="theme-color" content="#112233"'));
    $assert(str_contains($html, 'rel="canonical" href="https://syntaxdevteam.pl/projects"'));
    $assert(str_contains($html, 'property="og:locale" content="pl_PL"'));
    $assert(str_contains($html, 'property="og:image" content="https://syntaxdevteam.pl/social/cover.png"'));
    $assert(str_contains($html, 'property="og:image:alt" content="Okładka SyntaxDevTeam"'));
    $assert(str_contains($html, 'name="twitter:card" content="summary_large_image"'));
    $assert(str_contains($html, 'name="twitter:site" content="@SyntaxDevTeam"'));
    $assert(str_contains($html, 'name="google-site-verification" content="google-token_123"'));
    $assert(str_contains($html, 'name="msvalidate.01" content="bing-token_123"'));
    $assert(str_contains($html, 'href="/projects" aria-current="page"'));
    $assert(str_contains($html, '&copy; ' . date('Y') . ' Powered by '));
    $assert(str_contains($html, 'href="https://syntaxdevteam.pl/p/miniportal">miniPORTAL</a>'));
    $assert(str_contains($html, 'href="https://syntaxdevteam.pl">SyntaxDevTeam</a>'));
    $assert(!str_contains($html, '<span>Powered by miniPORTAL by SyntaxDevTeam</span>'));
    $assert(str_contains($html, '<main id="content" tabindex="-1">'));
    $assert(str_contains($html, 'application/ld+json'));
    $assert(str_contains($html, '"@type":"WebSite"'));
    $assert(preg_match('~<script type="application/ld\+json">(.*?)</script>~s', $html, $jsonLd) === 1);
    $assert(is_array(json_decode($jsonLd[1], true, flags: JSON_THROW_ON_ERROR)));
    $assert(!str_contains($html, '<span aria-hidden="true">&lt;/&gt;</span>'));
});

$test('Theme uses generated favicon set with cache version', static function () use ($assert): void {
    $theme = new DefaultTheme([
        'public_favicon_path' => '/uploads/branding',
        'public_favicon_version' => '123456',
    ]);
    ob_start();
    $theme->start_page('Ikony', 'Test ikon');
    $theme->end_page();
    $html = (string) ob_get_clean();

    $assert(str_contains($html, '/uploads/branding/favicon.ico?v=123456'));
    $assert(str_contains($html, '/uploads/branding/apple-touch-icon.png?v=123456'));
    $assert(str_contains($html, '/uploads/branding/site.webmanifest?v=123456'));
});

$test('Future theme is discoverable and renders its own assets', static function () use ($assert): void {
    $engine = new ThemeEngine(dirname(__DIR__) . '/templates');
    $available = $engine->available();
    $assert(($available['future'] ?? '') === 'Future');

    $theme = $engine->load('future', [
        'public_name' => 'SyntaxDevTeam',
        'public_meta_description' => 'Motyw Future',
    ]);
    ob_start();
    $theme->start_page('Future', 'Motyw alternatywny');
    $theme->start_header('Future', 'Układ inspirowany starszym projektem.', 'Alternatywny motyw');
    $theme->end_header();
    $theme->end_page();
    $html = (string) ob_get_clean();

    $assert(str_contains($html, '/templates/future/assets/css/stylebook.css'));
    $assert(str_contains($html, 'class="stylebook-hero border-bottom"'));
    $assert(str_contains($html, 'Alternatywny motyw'));
});

$test('Public error metadata prevents indexing', static function () use ($assert): void {
    $theme = new DefaultTheme([
        'public_url' => 'https://syntaxdevteam.pl',
        'public_path' => '/missing',
    ]);
    ob_start();
    $theme->render_public_error(404, 'Brak', 'Nie znaleziono.');
    $html = (string) ob_get_clean();

    $assert(str_contains($html, 'name="robots" content="noindex, nofollow"'));
    $assert(!str_contains($html, 'rel="canonical"'));
});

$test('Hero split renders a vertical acrostic from configured words', static function () use ($assert): void {
    $theme = new DefaultTheme();
    ob_start();
    $theme->render_homepage([[
        'key' => 'top',
        'type' => 'hero',
        'eyebrow' => 'Software',
        'acrostic_words' => "SYSTEM\nYIELDING\nNEXT-GEN\nTOOLS\nAPPS\nX-PLATFORM",
        'title' => 'SyntaxDevTeam',
        'content_html' => '<p>Opis.</p>',
        'content_format' => 'html',
        'layout' => 'split',
        'button_label' => '',
        'button_url' => '',
        'items' => [],
        'widgets_aside' => [[
            'id' => 1,
            'key' => 'syntax-terminal',
            'name' => 'Terminal SyntaxDevTeam',
            'type' => 'terminal',
            'title' => 'syntaxdevteam.pl/build',
            'content' => 'Witaj w SyntaxDevTerminal 0.1.5.',
            'button_label' => '',
            'button_url' => '',
        ]],
    ]], [], false);
    $html = (string) ob_get_clean();

    $assert(str_contains($html, '<h1 class="hero-acrostic" aria-label="SYSTEM YIELDING NEXT-GEN TOOLS APPS X-PLATFORM">'));
    $assert(str_contains($html, '<strong class="hero-acrostic-initial">S</strong><span>YSTEM</span>'));
    $assert(str_contains($html, '<strong class="hero-acrostic-initial">X</strong><span>-PLATFORM</span>'));
    $assert(str_contains($html, 'class="terminal"'));
    $assert(str_contains($html, 'data-home-terminal data-authenticated="false"'));
    $assert(str_contains($html, 'data-terminal-output role="log" aria-live="polite"'));
    $assert(str_contains($html, '<template data-terminal-welcome>Witaj w SyntaxDevTerminal 0.1.5.</template>'));
    $assert(str_contains($html, 'id="widget-terminal-syntax-terminal-1-command"'));
    $assert(!str_contains($html, '>Panel administracyjny</a>'));
    $assert(!str_contains($html, '>Przejdź do panelu</a>'));
    $assert(!str_contains($html, '[ OK ]'));
    $assert(str_contains($html, 'data-terminal-form'));
    $assert(str_contains($html, 'data-terminal-input'));
    $assert(!str_contains($html, '<h1 class="home-title fw-bold">SyntaxDevTeam</h1>'));
    $css = (string) file_get_contents(dirname(__DIR__) . '/templates/default/assets/css/homepage.css');
    $js = (string) file_get_contents(dirname(__DIR__) . '/templates/default/assets/js/site.js');
    $assert(str_contains($css, '.hero-acrostic-word'));
    $assert(str_contains($css, '.terminal-command:focus-within'));
    $assert(str_contains($css, '.terminal-screen .terminal-status'));
    $assert(str_contains($js, '"help"'));
    $assert(str_contains($js, 'window.location.assign(route)'));
    $assert(str_contains($js, 'event.key !== "ArrowUp"'));
    $assert(str_contains($js, 'querySelectorAll("[data-home-terminal]")'));
    $assert(str_contains($css, 'white-space: nowrap'));
    $assert(!str_contains($css, '.hero-acrostic::before'));
    $assert(!str_contains($css, 'flex: 0 0 0.95em'));
});

$test('Widget layout attaches terminal and cards to named homepage slots', static function () use ($assert): void {
    $widget = static fn (
        int $id,
        string $key,
        string $type,
        string $placement,
        string $target = '',
        string $theme = '*',
    ): Widget => new Widget(
        $id,
        $key,
        ucfirst($key),
        $type,
        $placement,
        $target,
        $theme,
        ucfirst($key),
        'Treść widgetu',
        '',
        '',
        10,
        true,
        '2026-06-22 00:00:00',
        '2026-06-22 00:00:00',
    );
    $sections = (new WidgetLayout())->attach([
        ['key' => 'top', 'type' => 'hero'],
        ['key' => 'projects', 'type' => 'content'],
    ], [
        $widget(1, 'terminal', 'terminal', 'hero_aside'),
        $widget(2, 'start', 'card', 'homepage_start'),
        $widget(3, 'before-projects', 'card', 'before_section', 'projects'),
        $widget(4, 'footer', 'card', 'before_footer'),
    ]);

    $assert(array_column($sections[0]['widgets_aside'], 'key') === ['terminal']);
    $assert(array_column($sections[0]['widgets_before'], 'key') === ['start']);
    $assert(array_column($sections[1]['widgets_before'], 'key') === ['before-projects']);
    $assert(array_column($sections[1]['widgets_after'], 'key') === ['footer']);
});

$test('Homepage renders widget cards safely and has no hardcoded terminal', static function () use ($assert): void {
    $theme = new DefaultTheme();
    $section = [
        'key' => 'top',
        'type' => 'hero',
        'eyebrow' => '',
        'acrostic_words' => '',
        'title' => 'Hero bez widgetu',
        'content_html' => '<p>Opis.</p>',
        'content_format' => 'html',
        'layout' => 'split',
        'button_label' => '',
        'button_url' => '',
        'items' => [],
        'widgets_before' => [[
            'key' => 'safe-card',
            'name' => 'Karta',
            'type' => 'card',
            'title' => '<script>alert(1)</script>',
            'content' => '<img src=x onerror=alert(1)>',
            'button_label' => 'Zobacz',
            'button_url' => 'javascript:alert(1)',
        ]],
    ];
    ob_start();
    $theme->render_homepage([$section], [], false);
    $html = (string) ob_get_clean();

    $assert(str_contains($html, '&lt;script&gt;alert(1)&lt;/script&gt;'));
    $assert(str_contains($html, '&lt;img src=x onerror=alert(1)&gt;'));
    $assert(!str_contains($html, 'javascript:'));
    $assert(!str_contains($html, 'data-home-terminal'));
    $assert(str_contains($html, 'class="col-12 reveal is-visible"'));
});

$test('Every active theme can replace the Hero terminal with another widget', static function () use ($assert): void {
    $engine = new ThemeEngine(dirname(__DIR__) . '/templates');
    $section = [
        'key' => 'top',
        'type' => 'hero',
        'eyebrow' => 'Widget test',
        'acrostic_words' => '',
        'title' => 'Wymienny element Hero',
        'content_html' => '<p>Opis.</p>',
        'content_format' => 'html',
        'layout' => 'split',
        'button_label' => '',
        'button_url' => '',
        'items' => [],
        'widgets_aside' => [[
            'id' => 7,
            'key' => 'theme-card',
            'name' => 'Zamiennik terminala',
            'type' => 'card',
            'title' => 'Widget motywu',
            'content' => 'Ta karta zastępuje terminal.',
            'button_label' => '',
            'button_url' => '',
        ]],
    ];

    foreach (['default', 'glassnight', 'future'] as $name) {
        ob_start();
        $engine->load($name)->render_homepage([$section], [], false);
        $html = (string) ob_get_clean();
        $assert(str_contains($html, 'data-widget="theme-card"'), 'Motyw nie renderuje karty: ' . $name);
        $assert(str_contains($html, 'Widget motywu'), 'Motyw pomija treść widgetu: ' . $name);
        $assert(!str_contains($html, 'data-home-terminal'), 'Motyw zachował terminal: ' . $name);
    }
});

$test('Homepage headings preserve intentional line breaks safely', static function () use ($assert): void {
    $theme = new DefaultTheme();
    ob_start();
    $theme->render_homepage([[
        'key' => 'minecraft',
        'type' => 'content',
        'eyebrow' => '',
        'acrostic_words' => '',
        'title' => "Minecraft plugins?\nThe highest <standard> of quality.",
        'content_html' => '<p>Opis.</p>',
        'content_format' => 'html',
        'layout' => 'full',
        'button_label' => '',
        'button_url' => '',
        'items' => [],
    ]], [], false);
    $html = (string) ob_get_clean();

    $assert(str_contains($html, '<h2 class="fw-bold">Minecraft plugins?<br>The highest &lt;standard&gt; of quality.</h2>'));
    $assert(str_contains($html, '>Minecraft plugins? The highest &lt;standard&gt; of quality.</a>'));
    $assert(!str_contains($html, '<standard>'));
});

$test('Theme form controls expose browser validation and accessible help', static function () use ($assert): void {
    $theme = new DefaultTheme();
    ob_start();
    $theme->render_form('/save', [[
        'name' => 'site_url',
        'label' => 'Adres',
        'type' => 'url',
        'value' => 'https://example.test',
        'required' => true,
        'maxlength' => 255,
        'autocomplete' => 'url',
        'placeholder' => 'https://example.test',
        'help' => 'Publiczny adres HTTPS.',
    ]], 'Zapisz', 'csrf-token');
    $html = (string) ob_get_clean();

    $assert(str_contains($html, 'type="url"'));
    $assert(str_contains($html, ' required'));
    $assert(str_contains($html, 'maxlength="255"'));
    $assert(str_contains($html, 'autocomplete="url"'));
    $assert(str_contains($html, 'aria-describedby="site_url-help"'));
    $assert(str_contains($html, 'id="site_url-help"'));
});

$test('Theme renders an accessible data-only line chart', static function () use ($assert): void {
    $theme = new DefaultTheme(['public_name' => 'Test']);
    ob_start();
    $theme->render_line_chart([
        ['label' => '2026-06-20', 'value' => 100],
        ['label' => '2026-06-21', 'value' => 140],
    ], 'Historia ceny ECO');
    $html = (string) ob_get_clean();
    $assert(str_contains($html, '<polyline points="'));
    $assert(str_contains($html, '<title id="line-chart-title-'));
    $assert(str_contains($html, 'Historia ceny ECO'));
});

$test('Public theme renders avatar image component', static function () use ($assert): void {
    $theme = new DefaultTheme([
        'public_name' => 'SyntaxDevTeam',
        'public_meta_description' => 'Opis testowy',
    ]);

    ob_start();
    $theme->render_avatar('Admin Test', 'https://example.test/avatar.png', 'lg');
    $html = (string) ob_get_clean();

    $assert(str_contains($html, 'public-avatar public-avatar-lg'));
    $assert(str_contains($html, '<img src="https://example.test/avatar.png" alt="" loading="lazy">'));
});

$test('Public theme renders safe local related links', static function () use ($assert): void {
    $theme = new DefaultTheme();
    ob_start();
    $theme->render_link_list([
        ['label' => 'Build Explorer', 'href' => '/builds/punisherx', 'meta' => 'DEV'],
        ['label' => 'Blocked', 'href' => '//attacker.example', 'meta' => ''],
    ]);
    $html = (string) ob_get_clean();
    $assert(str_contains($html, 'href="/builds/punisherx"'));
    $assert(!str_contains($html, 'attacker.example'));
});

$test('Admin login hides implementation details of OAuth security', static function () use ($assert): void {
    $theme = new DefaultTheme();
    ob_start();
    $theme->render_admin_login('/admin/login', [[
        'provider' => 'github',
        'subject' => '',
        'label' => 'GitHub',
        'description' => 'Zaloguj się kontem GitHub',
        'href' => '/admin/auth/github',
    ]], 'csrf-token');
    $html = (string) ob_get_clean();

    $assert(str_contains($html, 'Zaloguj się do panelu'));
    $assert(!str_contains($html, '[SEC]'));
    $assert(!str_contains($html, 'PKCE'));
    $assert(!str_contains($html, 'state'));
});

$test('Public error page is friendly and does not mention dashboard', static function () use ($assert): void {
    $theme = new DefaultTheme([
        'public_name' => 'SyntaxDevTeam',
        'public_meta_description' => 'Opis testowy',
    ]);

    ob_start();
    $theme->render_public_error(404, 'Nie znaleziono strony', 'Adres nie pasuje do aktywnego widoku.');
    $html = (string) ob_get_clean();

    $assert(str_contains($html, 'Kod odpowiedzi 404'));
    $assert(str_contains($html, 'Wróć do strony głównej'));
    $assert(!str_contains($html, 'dashboard'));
});

$test('Admin panel grid renders compact responsive layout wrappers', static function () use ($assert): void {
    $theme = new DefaultTheme([
        'public_name' => 'SyntaxDevTeam',
        'public_meta_description' => 'Opis testowy',
    ]);

    ob_start();
    $theme->start_admin_panel_grid('compact');
    $theme->start_admin_panel('Pierwszy panel');
    $theme->end_admin_panel();
    $theme->start_admin_panel('Drugi panel');
    $theme->end_admin_panel();
    $theme->end_admin_panel_grid();
    $html = (string) ob_get_clean();

    $assert(str_contains($html, 'admin-panel-grid admin-panel-grid-compact'));
    $assert(substr_count($html, 'class="admin-panel"') === 2);
});

$test('Admin fact grid marks healthy integration states', static function () use ($assert): void {
    $theme = new DefaultTheme(['public_name' => 'Test']);
    ob_start();
    $theme->render_admin_fact_grid([
        ['label' => 'Token API', 'value' => 'Skonfigurowany', 'variant' => 'success'],
        ['label' => 'Aplikacja Discord', 'value' => 'Niekompletna', 'variant' => 'warning'],
    ]);
    $html = (string) ob_get_clean();
    $assert(str_contains($html, 'profile-fact-success'));
    $assert(str_contains($html, 'profile-fact-warning'));
});

$test('Admin settings grid renders balanced panel columns', static function () use ($assert): void {
    $theme = new DefaultTheme();
    ob_start();
    $theme->start_admin_panel_grid('settings');
    $theme->start_admin_panel_column();
    $theme->start_admin_panel('Branding');
    $theme->end_admin_panel();
    $theme->end_admin_panel_column();
    $theme->start_admin_panel_column();
    $theme->start_admin_panel('SEO');
    $theme->end_admin_panel();
    $theme->end_admin_panel_column();
    $theme->end_admin_panel_grid();
    $html = (string) ob_get_clean();

    $assert(str_contains($html, 'admin-panel-grid admin-panel-grid-settings'));
    $assert(substr_count($html, 'class="admin-panel-column"') === 2);
});

$test('Admin content renders module actions in a full-width toolbar', static function () use ($assert): void {
    $theme = new DefaultTheme([
        'public_name' => 'SyntaxDevTeam',
        'public_meta_description' => 'Opis testowy',
    ]);

    ob_start();
    $theme->start_admin_content(
        'Manager SQL',
        'Opis testowy',
        [['label' => 'Panel', 'href' => 'index.php?route=/admin']],
        [
            ['label' => 'Konsola SQL', 'href' => 'index.php?route=/admin/database/query', 'variant' => 'primary'],
            ['label' => 'Historia', 'href' => 'index.php?route=/admin/database/history'],
        ]
    );
    $html = (string) ob_get_clean();

    $assert(str_contains($html, 'class="admin-module-actions"'));
    $assert(str_contains($html, 'index.php?route=/admin/database/query'));
    $assert(str_contains($html, 'index.php?route=/admin/database/history'));
    $assert(strpos($html, 'admin-module-actions') > strpos($html, 'admin-page-heading'));
});

$test('Admin topbar exposes profile dropdown actions', static function () use ($assert): void {
    $theme = new DefaultTheme([
        'public_name' => 'SyntaxDevTeam',
        'public_meta_description' => 'Opis testowy',
    ]);
    $theme->set_admin_search_items([[
        'id' => 'team.create',
        'label' => 'Dodaj członka zespołu',
        'description' => 'Nowy profil zespołu',
        'href' => 'index.php?route=/admin/team/create',
        'section' => 'Treść',
        'keywords' => 'team profil użytkownik',
        'order' => 40,
    ]]);

    ob_start();
    $theme->start_admin_page('Panel', [], '/admin', [
        'name' => 'Admin Test',
        'role' => 'Administrator',
        'initials' => 'AT',
        'avatar_url' => 'https://example.test/avatar.png',
        'logout_action' => 'index.php?route=/admin/logout',
        'logout_token' => 'csrf-token',
    ]);
    $theme->end_admin_page();
    $html = (string) ob_get_clean();

    $assert(str_contains($html, 'admin-user-menu-toggle'));
    $assert(str_contains($html, 'dropdown-menu dropdown-menu-end admin-user-dropdown'));
    $assert(str_contains($html, 'index.php?route=/admin/profile'));
    $assert(str_contains($html, 'index.php?route=/admin/profile/edit'));
    $assert(str_contains($html, 'index.php?route=/admin/profile/avatar'));
    $assert(str_contains($html, 'index.php?route=/admin/profile/security'));
    $assert(str_contains($html, 'index.php?route=/admin/profile/identities'));
    $assert(str_contains($html, '<img src="https://example.test/avatar.png" alt="" loading="lazy">'));
    $assert(str_contains($html, '>Wyloguj</button>'));
    $assert(str_contains($html, 'class="admin-brand-logo"'));
    $assert(str_contains($html, 'img/brand/admin-logo.png'));
    $assert(str_contains($html, 'name="robots" content="noindex, nofollow"'));
    $assert(str_contains($html, 'data-admin-search-input'));
    $assert(str_contains($html, 'data-admin-search-item'));
    $assert(str_contains($html, 'Dodaj członka zespołu'));
    $assert(!str_contains($html, 'admin-sidebar-footer'));
});

$test('Admin profile dropdown stays above dashboard content', static function () use ($assert): void {
    foreach (['default', 'glassnight', 'future'] as $theme) {
        $css = (string) file_get_contents(dirname(__DIR__) . '/templates/' . $theme . '/assets/css/admin.css');
        $assert(preg_match('/\.admin-topbar\s*\{[^}]*position:\s*relative;[^}]*z-index:\s*1100;/s', $css) === 1, 'Topbar bez warstwy w motywie ' . $theme);
        $assert(preg_match('/\.admin-user-menu\s*\{[^}]*position:\s*relative;[^}]*z-index:\s*1110;/s', $css) === 1, 'Menu profilu bez warstwy w motywie ' . $theme);
    }
});

$test('Admin action table aligns links and forms in one action group', static function () use ($assert): void {
    $theme = new DefaultTheme();
    ob_start();
    $theme->render_admin_action_table(['Nazwa'], [[
        'cells' => ['Test'],
        'actions' => [
            ['label' => 'Edytuj', 'href' => '/edit', 'variant' => 'primary'],
            ['label' => 'Usuń', 'action' => '/delete', 'variant' => 'danger'],
        ],
    ]], 'csrf-token');
    $html = (string) ob_get_clean();

    $assert(str_contains($html, 'class="admin-table-actions"'));
    $assert(str_contains($html, 'class="admin-table-action-form"'));
    $assert(!str_contains($html, ' me-1'));
});

$test('Installer exposes a complete installable module catalog', static function () use ($assert): void {
    $installer = new Installer(dirname(__DIR__));
    $modules = $installer->moduleOptions();
    $ids = array_column($modules, 'id');

    $assert($modules !== []);
    $assert(in_array('core_auth', $ids, true));
    $assert(in_array('core_pages', $ids, true));
    $assert(in_array('system_admin', $ids, true));
    $assert(in_array('widgets', $ids, true));
    $required = array_column(array_filter(
        $modules,
        static fn (array $module): bool => $module['required']
    ), 'id');
    $assert($required === ['core_auth', 'core_pages', 'system_admin']);
});

$test('CMS distribution contains installer and excludes local state', static function () use ($assert): void {
    $distribution = dirname(__DIR__) . '/install/cms';

    foreach (['index.php', 'install.php', 'installer/Installer.php', 'INSTALL.md', '.htaccess'] as $file) {
        $assert(is_file($distribution . '/' . $file), 'Brak pliku dystrybucji: ' . $file);
    }
    $assert(!file_exists($distribution . '/config/installed.env'));
    $assert(!file_exists($distribution . '/config/installed.lock'));
    $assert(!file_exists($distribution . '/tests'));
    $assert(!file_exists($distribution . '/docs'));
    $assert(!file_exists($distribution . '/bin/build-cms-distribution.php'));
    $assert(!file_exists($distribution . '/modules/Econify/.env'));
    $assert(is_file($distribution . '/modules/Econify/.env.example'));
    $distributionBuilder = (string) file_get_contents(dirname(__DIR__) . '/bin/build-cms-distribution.php');
    $assert(str_contains($distributionBuilder, "\$basename === '.env'"));
    $assert(str_contains($distributionBuilder, "\$basename !== '.env.example'"));
});

$test('Connected identities page returns to profile', static function () use ($assert): void {
    $theme = new DefaultTheme([
        'public_name' => 'SyntaxDevTeam',
        'public_meta_description' => 'Opis testowy',
    ]);

    ob_start();
    $theme->render_admin_identities(
        ['name' => 'Admin Test', 'role' => 'Administrator'],
        [['name' => 'github', 'label' => 'GitHub', 'configured' => true, 'linked' => true]],
        'index.php?route=/admin/identity/unlink',
        'csrf-token'
    );
    $html = (string) ob_get_clean();

    $assert(str_contains($html, 'index.php?route=/admin/profile">Wróć do profilu'));
    $assert(!str_contains($html, 'Wróć do panelu'));
});

$test('Module manifests are validated against runtime requirements', static function () use ($assert): void {
    $publishers = require dirname(__DIR__) . '/config/module_publishers.php';
    $validator = new ModuleManifestValidator('0.1.0', $publishers);
    $manifest = $validator->validate(dirname(__DIR__) . '/modules/Articles');

    $assert($manifest->id === 'articles');
    $assert($manifest->version === '1.0.4');
    $assert($manifest->installFile === 'install.sql');
    $assert($manifest->uninstallFile === 'uninstall.sql');
    $assert($manifest->requiredModules === ['core_auth']);

    $wiki = $validator->validate(dirname(__DIR__) . '/modules/Wikipedia');
    $assert($wiki->id === 'wikipedia');
    $assert($wiki->version === '1.0.4');
    $assert($wiki->installFile === 'install.sql');
    $assert($wiki->uninstallFile === 'uninstall.sql');
    $assert($wiki->requiredModules === ['core_auth']);

    $corePages = $validator->validate(dirname(__DIR__) . '/modules/CorePages');
    $assert($corePages->id === 'core_pages');
    $assert($corePages->version === '1.3.0');
    $assert($corePages->protected);

    $system = $validator->validate(dirname(__DIR__) . '/modules/System');
    $assert($system->id === 'system_admin');
    $assert($system->version === '1.8.0');
    $assert($system->protected);

    $coreAuth = $validator->validate(dirname(__DIR__) . '/modules/CoreAuth');
    $assert($coreAuth->id === 'core_auth');
    $assert($coreAuth->version === '1.5.0');
    $assert($coreAuth->protected);

    $database = $validator->validate(dirname(__DIR__) . '/modules/DatabaseManager');
    $assert($database->id === 'database_manager');
    $assert($database->version === '1.4.0');
    $assert($database->type === 'extension');
    $assert($database->installFile === 'install.sql');
    $assert($database->uninstallFile === 'uninstall.sql');
    $assert($database->requiredModules === ['core_auth']);

    $translator = $validator->validate(dirname(__DIR__) . '/modules/PluginTranslator');
    $assert($translator->id === 'plugin_translator');
    $assert($translator->version === '1.4.0');
    $assert($translator->type === 'extension');
    $assert($translator->installFile === 'install.sql');
    $assert($translator->uninstallFile === 'uninstall.sql');
    $assert($translator->requiredModules === ['core_auth', 'core_pages']);

    $team = $validator->validate(dirname(__DIR__) . '/modules/Team');
    $assert($team->id === 'team');
    $assert($team->version === '1.1.0');
    $assert($team->type === 'extension');
    $assert($team->installFile === 'install.sql');
    $assert($team->uninstallFile === 'uninstall.sql');
    $assert($team->requiredModules === ['core_auth']);

    $widgets = $validator->validate(dirname(__DIR__) . '/modules/Widgets');
    $assert($widgets->id === 'widgets');
    $assert($widgets->version === '1.0.0');
    $assert($widgets->type === 'extension');
    $assert($widgets->requiredModules === ['core_auth', 'core_pages']);
    $assert($widgets->installFile === 'install.sql');
    $assert($widgets->uninstallFile === 'uninstall.sql');

    $projects = $validator->validate(dirname(__DIR__) . '/modules/Projects');
    $assert($projects->id === 'projects');
    $assert($projects->version === '1.2.0');
    $assert($projects->type === 'extension');
    $assert($projects->requiredModules === ['core_auth', 'core_pages', 'wikipedia']);
    $assert($projects->installFile === 'install.sql');
    $assert($projects->uninstallFile === 'uninstall.sql');

    $builds = $validator->validate(dirname(__DIR__) . '/modules/BuildExplorer');
    $assert($builds->id === 'build_explorer');
    $assert($builds->version === '1.3.0');
    $assert($builds->type === 'extension');
    $assert($builds->requiredModules === ['core_auth', 'projects']);
    $assert($builds->installFile === 'install.sql');
    $assert($builds->uninstallFile === 'uninstall.sql');

    $profile = $validator->validate(dirname(__DIR__) . '/modules/UserProfile');
    $assert($profile->id === 'user_profile');
    $assert($profile->version === '1.0.0');
    $assert($profile->type === 'extension');
    $assert($profile->requiredModules === ['core_auth']);
    $assert($profile->installFile === 'install.sql');
    $assert($profile->uninstallFile === 'uninstall.sql');

    $econify = $validator->validate(dirname(__DIR__) . '/modules/Econify');
    $assert($econify->id === 'econify');
    $assert($econify->version === '1.2.1');
    $assert($econify->type === 'extension');
    $assert($econify->requiredModules === ['core_auth']);
    $assert($econify->installFile === 'install.sql');
    $assert($econify->uninstallFile === 'uninstall.sql');

    $profileSource = (string) file_get_contents(
        dirname(__DIR__) . '/modules/UserProfile/UserProfileModule.php'
    );
    $authSource = (string) file_get_contents(
        dirname(__DIR__) . '/modules/CoreAuth/CoreAuthModule.php'
    );
    $assert(str_contains($profileSource, "\$router->get('/admin/profile'"));
    $assert(str_contains($profileSource, "\$router->post('/admin/profile/edit'"));
    $assert(str_contains($profileSource, "\$router->post('/admin/profile/avatar'"));
    $assert(!str_contains($authSource, "\$router->get('/admin/profile'"));
    $assert(str_contains($authSource, "\$router->get('/admin/profile/identities'"));

    $learning = $validator->validate(dirname(__DIR__) . '/install/mod/LearningModule');
    $assert($learning->id === 'learning_module');
    $assert($learning->version === '1.1.0');
    $assert($learning->factoryFile === 'factory.php');
    $assert($learning->uninstallFile === 'uninstall.sql');
    $assert($learning->originType === 'repository');
    $assert($learning->signatureStatus === 'verified');
    $assert($learning->signatureKeyId === 'syntaxdevteam-learning-2026-rotated');
    $assert(is_callable(require $learning->directory . '/' . $learning->factoryFile));
});

$test('CoreAuth declares database explorer permission', static function () use ($assert): void {
    $installSql = (string) file_get_contents(dirname(__DIR__) . '/modules/CoreAuth/install.sql');
    $migrationSql = (string) file_get_contents(
        dirname(__DIR__) . '/modules/CoreAuth/migrations/20260617_database_view_permission.sql'
    );

    $assert(str_contains($installSql, "'database.view'"));
    $assert(str_contains($migrationSql, "'database.view'"));

    $rolesMigrationSql = (string) file_get_contents(
        dirname(__DIR__) . '/modules/CoreAuth/migrations/20260620_owner_and_operational_roles.sql'
    );
    foreach (['owner', 'administrator', 'maintainer', 'editor', 'auditor', 'support'] as $role) {
        $assert(str_contains($rolesMigrationSql, "'{$role}'"));
    }
    $assert(str_contains($rolesMigrationSql, "permissions.name = '*'"));
    $assert(str_contains($rolesMigrationSql, "roles.name = 'administrator' AND permissions.name <> '*'"));
    $assert(str_contains($rolesMigrationSql, 'ORDER BY users.created_at ASC, users.id ASC'));

    $authSource = (string) file_get_contents(dirname(__DIR__) . '/modules/CoreAuth/UserAdministrationRepository.php');
    $assert(str_contains($authSource, 'Tylko Owner może zarządzać kontem lub rolą Owner.'));
    $assert(str_contains($authSource, 'Nie można zmienić ostatniego aktywnego Ownera.'));

    $systemSource = (string) file_get_contents(dirname(__DIR__) . '/modules/System/SystemAdminModule.php');
    $assert(str_contains($systemSource, "return '1.8.0';"));
    $assert(!str_contains($systemSource, "'/admin/design-system'"));
    $assert(!str_contains($systemSource, 'Admin stylebook'));

    $databaseInstallSql = (string) file_get_contents(dirname(__DIR__) . '/modules/DatabaseManager/install.sql');
    $databaseMigrationSql = (string) file_get_contents(
        dirname(__DIR__) . '/modules/DatabaseManager/migrations/20260617_database_manage_permission.sql'
    );
    $assert(str_contains($databaseInstallSql, 'CREATE TABLE database_manager_history'));
    $assert(str_contains($databaseInstallSql, 'fk_database_manager_history_user'));
    $assert(str_contains($databaseInstallSql, "'database.manage'"));
    $assert(str_contains($databaseMigrationSql, "'database.manage'"));

    $translatorInstallSql = (string) file_get_contents(dirname(__DIR__) . '/modules/PluginTranslator/install.sql');
    $assert(str_contains($translatorInstallSql, "'plugin_translator.use'"));
    $assert(str_contains($translatorInstallSql, "'plugin_translator.review'"));
    $assert(str_contains($translatorInstallSql, 'CREATE TABLE plugin_translation_submissions'));
    $assert(str_contains($translatorInstallSql, 'CREATE TABLE plugin_translation_projects'));
    $assert(str_contains($translatorInstallSql, 'submission_kind'));
    $assert(str_contains($translatorInstallSql, 'project_id'));
    $assert(str_contains($translatorInstallSql, 'page_id'));
    $assert(!str_contains($translatorInstallSql, 'website_url'));
    $assert(str_contains($translatorInstallSql, "ready_for_review"));
    $assert(str_contains($translatorInstallSql, 'target_language'));

    $translatorMigrationSql = (string) file_get_contents(
        dirname(__DIR__) . '/modules/PluginTranslator/migrations/20260618_public_translation_workflow.sql'
    );
    $assert(str_contains($translatorMigrationSql, 'CREATE TABLE plugin_translation_submissions'));
    $assert(str_contains($translatorMigrationSql, "'plugin_translator.review'"));

    $translatorLanguageMigrationSql = (string) file_get_contents(
        dirname(__DIR__) . '/modules/PluginTranslator/migrations/20260618_translation_language_and_ux.sql'
    );
    $assert(str_contains($translatorLanguageMigrationSql, 'target_language'));
    $translatorCatalogMigrationSql = (string) file_get_contents(
        dirname(__DIR__) . '/modules/PluginTranslator/migrations/20260619_translation_project_catalog.sql'
    );
    $assert(str_contains($translatorCatalogMigrationSql, 'plugin_translation_projects'));
    $assert(str_contains($translatorCatalogMigrationSql, 'completed_upload'));
    $translatorPageMigrationSql = (string) file_get_contents(
        dirname(__DIR__) . '/modules/PluginTranslator/migrations/20260619_translation_page_link_and_manager_actions.sql'
    );
    $assert(str_contains($translatorPageMigrationSql, 'page_id'));
    $assert(str_contains($translatorPageMigrationSql, 'DROP COLUMN description'));
    $translatorModuleSource = (string) file_get_contents(
        dirname(__DIR__) . '/modules/PluginTranslator/PluginTranslatorModule.php'
    );
    $assert(str_contains($translatorModuleSource, "'messages_' . strtolower(\$submission->targetLanguage) . '.yml'"));
    $assert(str_contains($translatorModuleSource, "'source_filename'"));
    $assert(str_contains($translatorModuleSource, 'Kategorie tłumaczeń'));
    $assert(str_contains($translatorModuleSource, '/admin/plugin-translator/plugins/edit'));
    $assert(str_contains($translatorModuleSource, 'Moje wersje robocze'));
    $assert(str_contains($translatorModuleSource, '/translations/suggest'));
    $assert(str_contains($translatorModuleSource, 'Zaproponuj poprawkę'));
    $translatorRepositorySource = (string) file_get_contents(
        dirname(__DIR__) . '/modules/PluginTranslator/PluginTranslationRepository.php'
    );
    $assert(str_contains($translatorRepositorySource, "['project_id' => \$fallbackId]"));

    $teamInstallSql = (string) file_get_contents(dirname(__DIR__) . '/modules/Team/install.sql');
    $assert(str_contains($teamInstallSql, 'CREATE TABLE team_members'));
    $assert(str_contains($teamInstallSql, 'fk_team_members_user'));
    $assert(str_contains($teamInstallSql, "'team.manage'"));

    $econifyInstallSql = (string) file_get_contents(dirname(__DIR__) . '/modules/Econify/install.sql');
    $econifySource = (string) file_get_contents(dirname(__DIR__) . '/modules/Econify/EconifyModule.php');
    $econifyRepository = (string) file_get_contents(dirname(__DIR__) . '/modules/Econify/EconifyRepository.php');
    foreach (['econify_platform_settings', 'econify_guilds', 'econify_memberships', 'econify_wallets', 'econify_transactions', 'econify_shop_items', 'econify_shop_orders', 'econify_market_assets', 'econify_market_quotes', 'econify_market_holdings'] as $table) {
        $assert(str_contains($econifyInstallSql, 'CREATE TABLE ' . $table), 'Brak tabeli Econify: ' . $table);
    }
    $assert(str_contains($econifyInstallSql, "'econify.platform.manage'"));
    $assert(str_contains($econifySource, "'/api/econify/events'"));
    $assert(str_contains($econifySource, "'/api/econify/guilds'"));
    $assert(str_contains($econifySource, "hash_equals(\$this->config->apiToken"));
    $assert(str_contains($econifySource, "\$this->config->apiConfigured()"));
    $assert(str_contains($econifySource, "? 'unauthenticated' : 'forbidden'"));
    $assert(!str_contains($econifySource, "'econify_acl', \$decision"));
    $assert(str_contains($econifySource, "\$membership['plan'] === 'freemium'"));
    $assert(!str_contains($econifySource, 'Dodaj serwer Discord'));
    $assert(!str_contains($econifySource, '/admin/econify/discord/activate'));
    $assert(!str_contains($econifySource, '/admin/econify/discord/connect'));
    $assert(!str_contains($econifySource, 'Powiąż użytkownika'));
    $assert(!str_contains($econifySource, '/econify/server/member'));
    $assert(str_contains($econifySource, '/econify/servers'));
    $assert(str_contains($econifySource, '/econify/discord/connect'));
    $assert(str_contains($econifyRepository, 'user_identities'));
    $assert(str_contains($econifyRepository, 'FOR UPDATE'));
    $assert(str_contains($econifyRepository, 'external_reference'));
    $assert(str_contains($econifyRepository, 'upsertDiscordGuild'));

    $projectsInstallSql = (string) file_get_contents(dirname(__DIR__) . '/modules/Projects/install.sql');
    $assert(str_contains($projectsInstallSql, 'CREATE TABLE projects'));
    $assert(str_contains($projectsInstallSql, 'fk_projects_page'));
    $assert(str_contains($projectsInstallSql, 'fk_projects_wiki'));
    $assert(str_contains($projectsInstallSql, "'projects.manage'"));
    $projectsSummaryMigration = (string) file_get_contents(
        dirname(__DIR__) . '/modules/Projects/migrations/20260620_remove_required_summary.sql'
    );
    $assert(str_contains($projectsSummaryMigration, "DEFAULT ''"));
    $projectsSource = (string) file_get_contents(dirname(__DIR__) . '/modules/Projects/ProjectsModule.php');
    $assert(str_contains($projectsSource, "\$router->get('/projects'"));
    $assert(str_contains($projectsSource, "\$router->get('/admin/projects'"));
    $assert(str_contains($projectsSource, "navigation->add('projects.index', 'Projekty', '/projects', 'main'"));
    $assert(str_contains($projectsSource, 'render_link_list'));
    $assert(!str_contains($projectsSource, "'name' => 'summary'"));

    $buildsInstallSql = (string) file_get_contents(dirname(__DIR__) . '/modules/BuildExplorer/install.sql');
    $assert(str_contains($buildsInstallSql, 'CREATE TABLE project_builds'));
    $assert(str_contains($buildsInstallSql, 'fk_project_builds_project'));
    $assert(str_contains($buildsInstallSql, "ENUM('release', 'snapshot', 'dev', 'wip')"));
    $assert(str_contains($buildsInstallSql, "'builds.manage'"));
    $assert(str_contains($buildsInstallSql, 'storage_key'));
    $buildsMigrationSql = (string) file_get_contents(
        dirname(__DIR__) . '/modules/BuildExplorer/migrations/20260619_local_artifact_upload.sql'
    );
    $assert(str_contains($buildsMigrationSql, 'server_type'));
    $assert(str_contains($buildsMigrationSql, 'build_number'));
    $assert(str_contains($buildsMigrationSql, 'storage_key'));
    $buildsCiMigrationSql = (string) file_get_contents(
        dirname(__DIR__) . '/modules/BuildExplorer/migrations/20260620_ci_build_history.sql'
    );
    $assert(str_contains($buildsCiMigrationSql, 'ci_build_id'));
    $assert(str_contains($buildsCiMigrationSql, 'commits_json'));
    $assert(str_contains($buildsCiMigrationSql, 'uq_project_builds_ci'));
    $buildsSource = (string) file_get_contents(dirname(__DIR__) . '/modules/BuildExplorer/BuildExplorerModule.php');
    $assert(str_contains($buildsSource, "\$router->get('/builds'"));
    $assert(str_contains($buildsSource, "\$router->post('/api/builds/ci/{project}'"));
    $assert(str_contains($buildsSource, "\$router->get('/builds/{project}/{channel}'"));
    $assert(str_contains($buildsSource, "hash_equals(\$this->ciToken"));
    $assert(str_contains($buildsSource, "\$router->get('/admin/builds'"));
    $assert(str_contains($buildsSource, "\$router->get('/builds/download'"));
    $assert(str_contains($buildsSource, "\$request->file('artifact')"));
    $assert(str_contains($buildsSource, "navigation->add('build_explorer.index', 'Pliki do pobrania', '/builds', 'main'"));
});

$test('Build artifact storage generates names and calculates upload metadata', static function () use ($assert): void {
    $directory = sys_get_temp_dir() . '/miniportal-build-storage-' . bin2hex(random_bytes(6));
    $temporary = tempnam(sys_get_temp_dir(), 'miniportal-jar-');
    file_put_contents($temporary, "PK\x03\x04test-build");
    try {
        $assert(
            SyntaxDevTeam\Cms\Modules\BuildExplorer\BuildArtifactStorage::filename(
                'PunisherX', 'Spigot', '1.7.3', 'dev', '14c0e44'
            ) === 'PunisherX-Spigot-1.7.3-DEV-14c0e44.jar'
        );
        $assert(
            SyntaxDevTeam\Cms\Modules\BuildExplorer\BuildArtifactStorage::filename(
                'PunisherX', 'Paper', '1.7.3-R0.1', 'snapshot', ''
            ) === 'PunisherX-Paper-1.7.3-R0.1-SNAPSHOT.jar'
        );
        $storage = new SyntaxDevTeam\Cms\Modules\BuildExplorer\BuildArtifactStorage($directory, 1024);
        $stored = $storage->store([
            'name' => 'source.jar',
            'type' => 'application/java-archive',
            'tmp_name' => $temporary,
            'error' => UPLOAD_ERR_OK,
            'size' => 14,
        ]);
        $path = $storage->path($stored['storage_key']);
        $assert($path !== null && is_file($path));
        $assert($stored['size'] === 14);
        $assert($stored['checksum'] === hash_file('sha256', $path));
        $storage->delete($stored['storage_key']);
        $assert($storage->path($stored['storage_key']) === null);
        $storage->delete(null);
    } finally {
        if (is_file($temporary)) { unlink($temporary); }
        if (is_dir($directory)) { rmdir($directory); }
    }
});

$test('Module archive import extracts only to quarantine and inspects manifest', static function () use ($assert): void {
    $root = sys_get_temp_dir() . '/miniportal-import-root-' . bin2hex(random_bytes(6));
    $source = $root . '/ExampleModule';
    $quarantine = $root . '/quarantine';
    $modules = $root . '/modules';
    mkdir($source, 0700, true);
    mkdir($quarantine, 0700, true);
    mkdir($modules, 0700, true);
    file_put_contents($source . '/info.json', json_encode([
        'id' => 'example_module',
        'name' => 'Example Module',
        'version' => '1.0.0',
        'type' => 'extension',
        'author' => 'Tests',
        'requires' => ['php' => '>=8.4', 'miniportal' => '>=0.1.0', 'modules' => []],
        'protected' => false,
        'origin' => ['type' => 'archive', 'url' => 'https://example.test/module'],
        'install' => null,
    ], JSON_THROW_ON_ERROR));
    $archive = $root . '/example.tar';
    $phar = new PharData($archive);
    $phar->buildFromDirectory($root, '~^' . preg_quote($source, '~') . '~');

    try {
        $importer = new ModuleArchiveImporter($quarantine, new ModuleManifestValidator('0.1.0'));
        $result = $importer->importFile($archive, 'example.tar');
        $assert($result['manifest'] !== null && $result['manifest']->id === 'example_module');
        $assert(str_contains($result['directory'], $quarantine));
        $assert(is_file($result['directory'] . '/source/ExampleModule/info.json'));
        try {
            $importer->approve(basename($result['directory']), $modules);
            $assert(false, 'Niepodpisany pakiet opuścił kwarantannę');
        } catch (RuntimeException $exception) {
            $assert(str_contains($exception->getMessage(), 'podpisu'));
        }
        $assert(!is_dir($modules . '/ExampleModule'));
        exec('cd ' . escapeshellarg($root) . ' && zip -qr example.zip ExampleModule', $output, $code);
        if ($code === 0) {
            $zipResult = $importer->importFile($root . '/example.zip', 'example.zip');
            $assert($zipResult['manifest'] !== null && $zipResult['manifest']->id === 'example_module');
            $assert(count($importer->imports()) === 2);
        } else {
            $assert(count($importer->imports()) === 1);
        }
    } finally {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($root);
    }
});

$test('Verified module archive can be approved without executing its factory', static function () use ($assert): void {
    $root = sys_get_temp_dir() . '/miniportal-approve-root-' . bin2hex(random_bytes(6));
    $exports = $root . '/exports';
    $quarantine = $root . '/quarantine';
    $modules = $root . '/modules';
    mkdir($quarantine, 0700, true);
    mkdir($modules, 0700, true);

    try {
        $validator = new ModuleManifestValidator(
            '0.1.0',
            require dirname(__DIR__) . '/config/module_publishers.php'
        );
        $sourceManifest = $validator->validate(dirname(__DIR__) . '/install/mod/LearningModule');
        $archive = (new ModulePackageExporter())->exportZip($sourceManifest, $exports);
        $importer = new ModuleArchiveImporter($quarantine, $validator);
        $import = $importer->importFile($archive['path'], $archive['filename']);
        $assert($import['manifest']?->signatureStatus === 'verified');

        $approved = $importer->approve(basename($import['directory']), $modules);
        $assert($approved['manifest']->id === 'learning_module');
        $assert($approved['manifest']->signatureStatus === 'verified');
        $assert(is_file($modules . '/LearningModule/factory.php'));
        $assert(!is_dir($import['directory']));

        try {
            $secondImport = $importer->importFile($archive['path'], $archive['filename']);
            $importer->approve(basename($secondImport['directory']), $modules);
            $assert(false, 'Zaakceptowano konflikt katalogu modułu');
        } catch (RuntimeException $exception) {
            $assert(str_contains($exception->getMessage(), 'już istnieje'));
        }
    } finally {
        if (is_dir($root)) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($files as $file) {
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
            rmdir($root);
        }
    }
});

$test('Module package exporter creates an importable ZIP archive', static function () use ($assert): void {
    $root = sys_get_temp_dir() . '/miniportal-export-root-' . bin2hex(random_bytes(6));
    $source = $root . '/ExportModule';
    $exports = $root . '/exports';
    $quarantine = $root . '/quarantine';
    mkdir($source, 0700, true);
    mkdir($quarantine, 0700, true);
    file_put_contents($source . '/info.json', json_encode([
        'id' => 'export_module',
        'name' => 'Export Module',
        'version' => '1.2.3',
        'type' => 'extension',
        'author' => 'Tests',
        'requires' => ['php' => '>=8.4', 'miniportal' => '>=0.1.0', 'modules' => []],
        'protected' => false,
        'origin' => ['type' => 'archive', 'url' => 'https://example.test/export'],
        'install' => null,
    ], JSON_THROW_ON_ERROR));
    file_put_contents($source . '/Module.php', "<?php\n");

    try {
        $manifest = (new ModuleManifestValidator('0.1.0'))->validate($source);
        $export = (new ModulePackageExporter())->exportZip($manifest, $exports);
        $assert($export['filename'] === 'export_module-1.2.3.zip');
        $assert(is_file($export['path']) && filesize($export['path']) > 0);

        $import = (new ModuleArchiveImporter($quarantine, new ModuleManifestValidator('0.1.0')))
            ->importFile($export['path'], $export['filename']);
        $assert($import['manifest'] !== null && $import['manifest']->id === 'export_module');
        $assert(is_file($import['directory'] . '/source/ExportModule/info.json'));
    } finally {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($root);
    }
});

$test('Revoked publisher key blocks an otherwise valid module signature', static function () use ($assert): void {
    $publishers = require dirname(__DIR__) . '/config/module_publishers.php';
    $keyId = 'syntaxdevteam-learning-2026-rotated';
    $publishers[$keyId]['status'] = 'revoked';
    $manifest = (new ModuleManifestValidator('0.1.0', $publishers))
        ->validate(dirname(__DIR__) . '/install/mod/LearningModule');

    $assert($manifest->signatureStatus === 'revoked');
});

$test('Signed module rejects modified package content', static function () use ($assert): void {
    $source = dirname(__DIR__) . '/install/mod/LearningModule';
    $directory = sys_get_temp_dir() . '/miniportal-signed-module-' . bin2hex(random_bytes(6));
    mkdir($directory, 0700, true);
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iterator as $item) {
        $target = $directory . '/' . $iterator->getSubPathName();
        if ($item->isDir()) {
            mkdir($target, 0700, true);
        } else {
            copy($item->getPathname(), $target);
        }
    }
    file_put_contents($directory . '/README.md', "\nZmodyfikowano", FILE_APPEND);

    try {
        $publishers = require dirname(__DIR__) . '/config/module_publishers.php';
        $inspection = (new ModuleManifestValidator('0.1.0', $publishers))->inspect($directory);
        $assert($inspection['manifest'] === null);
        $assert(str_contains((string) $inspection['error'], 'Mapa SHA-256'));
    } finally {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($directory);
    }
});

$test('Invalid module inspection isolates a package error', static function () use ($assert): void {
    $directory = sys_get_temp_dir() . '/miniportal-invalid-module-' . bin2hex(random_bytes(6));
    mkdir($directory, 0700, true);
    file_put_contents($directory . '/info.json', '{"id":');

    try {
        $inspection = (new ModuleManifestValidator('0.1.0'))->inspect($directory);
        $assert($inspection['manifest'] === null);
        $assert(is_string($inspection['error']) && str_contains($inspection['error'], 'Nieprawidłowy JSON'));
    } finally {
        unlink($directory . '/info.json');
        rmdir($directory);
    }
});

$test('Optional module with invalid manifest is skipped during bootstrap', static function () use ($assert): void {
    $root = sys_get_temp_dir() . '/miniportal-bootstrap-' . bin2hex(random_bytes(6));
    $directory = $root . '/BrokenModule';
    mkdir($directory, 0700, true);
    file_put_contents($directory . '/info.json', '{"id":');
    $factoryCalled = false;

    try {
        $registry = new ModuleRegistry();
        $bootstrapper = new ModuleBootstrapper($root, new ModuleManifestValidator('0.1.0'));
        $bootstrapper->register([[
            'directory' => 'BrokenModule',
            'factory' => static function () use (&$factoryCalled): ModuleInterface {
                $factoryCalled = true;
                throw new RuntimeException('Fabryka wadliwego modułu nie może zostać uruchomiona.');
            },
        ]], [], $registry);

        $assert(!$factoryCalled);
        $assert($registry->ids() === []);

        try {
            $bootstrapper->register([[
                'directory' => 'BrokenModule',
                'required' => true,
                'factory' => static fn (): ModuleInterface => throw new RuntimeException(
                    'Fabryka wymaganego wadliwego modułu nie może zostać uruchomiona.'
                ),
            ]], [], new ModuleRegistry());
            $assert(false, 'Wadliwy wymagany moduł został pominięty bez błędu.');
        } catch (RuntimeException $exception) {
            $assert(str_contains($exception->getMessage(), 'Wymagany moduł BrokenModule'));
        }
    } finally {
        unlink($directory . '/info.json');
        rmdir($directory);
        rmdir($root);
    }
});

$test('Module state distinguishes discovery, installation and activation', static function () use ($assert): void {
    $discovered = new ModuleState('example', '1.0.0', 'discovered', false, false, null, '2026-06-14 00:00:00');
    $disabled = new ModuleState('example', '1.0.0', 'disabled', false, false, '2026-06-14 00:00:00', '2026-06-14 00:00:00');
    $active = new ModuleState('example', '1.0.0', 'active', false, false, '2026-06-14 00:00:00', '2026-06-14 00:00:00');
    $preserved = new ModuleState('example', '1.0.0', 'uninstalled', false, true, null, '2026-06-14 00:00:00');

    $assert(!$discovered->isInstalled() && !$discovered->isActive());
    $assert($disabled->isInstalled() && !$disabled->isActive());
    $assert($active->isInstalled() && $active->isActive());
    $assert(!$preserved->isInstalled() && $preserved->canRestorePreservedData());
});

$test('Module registry rejects a missing dependency', static function () use ($assert): void {
    $registry = new ModuleRegistry();
    $menu = new AdminMenuRegistry();
    $router = new Router();
    $module = new class implements ModuleInterface {
        public function id(): string { return 'dependent_module'; }
        public function version(): string { return '1.0.0'; }
        public function dependencies(): array { return ['missing_module']; }
        public function isProtected(): bool { return false; }
        public function requiredPermissions(): array { return []; }
        public function registerAdminMenu(AdminMenuRegistry $menu): void {}
        public function registerRoutes(Router $router): void {}
    };
    $registry->add($module);

    try {
        $registry->boot($menu, $router);
        $assert(false, 'Missing module dependency was accepted');
    } catch (RuntimeException $exception) {
        $assert(str_contains($exception->getMessage(), 'missing_module'));
    }
});

session_destroy();

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "All tests passed.\n";
