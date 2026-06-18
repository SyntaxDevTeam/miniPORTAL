<?php

declare(strict_types=1);

use SyntaxDevTeam\Cms\Core\Autoloader;
use SyntaxDevTeam\Cms\Core\AdminMenuRegistry;
use SyntaxDevTeam\Cms\Core\ContentRenderer;
use SyntaxDevTeam\Cms\Core\FileTemplateCache;
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
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuthorizationService;
use SyntaxDevTeam\Cms\Modules\CoreAuth\OAuthAttemptLimiter;
use SyntaxDevTeam\Cms\Modules\CoreAuth\OAuthStateStore;
use SyntaxDevTeam\Cms\Modules\CoreAuth\User;
use SyntaxDevTeam\Cms\Modules\Articles\ArticlesModule;
use SyntaxDevTeam\Cms\Modules\System\AuditCsvExporter;
use SyntaxDevTeam\Cms\Modules\DatabaseManager\DatabaseExplorerRepository;
use SyntaxDevTeam\Cms\Modules\DatabaseManager\DatabaseTableCsvExporter;
use SyntaxDevTeam\Cms\Modules\DatabaseManager\DatabaseTableSqlExporter;
use SyntaxDevTeam\Cms\Modules\PluginTranslator\PluginTranslatorYaml;
use SyntaxDevTeam\Cms\Modules\PluginTranslator\MinecraftFormatPreview;
use SyntaxDevTeam\Cms\Templates\DefaultTheme\Theme as DefaultTheme;

require_once dirname(__DIR__) . '/core/Autoloader.php';

Autoloader::register();

require_once dirname(__DIR__) . '/templates/default/theme.php';

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
    $segments = $preview->segments('&aOK <bold>ważne <#ff00aa>RGB');

    $assert($segments !== []);
    $assert($segments[0]['color'] === '#55FF55');
    $assert($segments[1]['bold'] || $segments[2]['bold']);
    $assert($segments[count($segments) - 1]['color'] === '#FF00AA');
    $assert($preview->segments('§cBłąd')[0]['color'] === '#FF5555');
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
    $assert($cache->stats()['entries'] === 1);
    $cache->clear();
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

    $assert($authorization->allows($active, 'pages.view'));
    $assert(!$authorization->allows($active, 'users.manage'));
    $assert(!$authorization->allows($blocked, 'pages.view'));
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

$test('Public navigation supports custom labels and multiple placements', static function () use ($assert): void {
    $navigation = new PublicNavigationRegistry();
    $navigation->add('module.docs', 'Dokumentacja', '/wiki', 'none', 10);

    $legacy = $navigation->items(['module.docs' => 'footer'])[0];
    $assert($legacy['label'] === 'Dokumentacja');
    $assert($legacy['area'] === 'footer');
    $assert(!$legacy['show_main']);
    $assert($legacy['show_footer']);

    $configured = $navigation->items([
        'module.docs' => [
            'label' => 'Baza wiedzy',
            'main' => true,
            'footer' => true,
        ],
    ])[0];
    $assert($configured['default_label'] === 'Dokumentacja');
    $assert($configured['label'] === 'Baza wiedzy');
    $assert($configured['area'] === 'main');
    $assert($configured['show_main']);
    $assert($configured['show_footer']);
});

$test('Articles module exposes a configurable public navigation link', static function () use ($assert): void {
    $assert(is_subclass_of(ArticlesModule::class, PublicNavigationProviderInterface::class));
});

$test('Public theme exposes common Home and Kontakt navigation on subpages', static function () use ($assert): void {
    $theme = new DefaultTheme([
        'public_name' => 'SyntaxDevTeam',
        'public_meta_description' => 'Opis testowy',
    ]);

    ob_start();
    $theme->start_page('Test', 'Opis');
    $theme->end_page();
    $html = (string) ob_get_clean();

    $assert(str_contains($html, 'href="/">Home</a>'));
    $assert(str_contains($html, 'href="/#contact">Kontakt</a>'));
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
    $assert(!str_contains($html, 'admin-sidebar-footer'));
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

    $system = $validator->validate(dirname(__DIR__) . '/modules/System');
    $assert($system->id === 'system_admin');
    $assert($system->version === '1.4.0');
    $assert($system->protected);

    $database = $validator->validate(dirname(__DIR__) . '/modules/DatabaseManager');
    $assert($database->id === 'database_manager');
    $assert($database->version === '1.4.0');
    $assert($database->type === 'extension');
    $assert($database->installFile === 'install.sql');
    $assert($database->uninstallFile === 'uninstall.sql');
    $assert($database->requiredModules === ['core_auth']);

    $translator = $validator->validate(dirname(__DIR__) . '/modules/PluginTranslator');
    $assert($translator->id === 'plugin_translator');
    $assert($translator->version === '1.2.0');
    $assert($translator->type === 'extension');
    $assert($translator->installFile === 'install.sql');
    $assert($translator->uninstallFile === 'uninstall.sql');
    $assert($translator->requiredModules === ['core_auth']);

    $team = $validator->validate(dirname(__DIR__) . '/modules/Team');
    $assert($team->id === 'team');
    $assert($team->version === '1.0.0');
    $assert($team->type === 'extension');
    $assert($team->installFile === 'install.sql');
    $assert($team->uninstallFile === 'uninstall.sql');
    $assert($team->requiredModules === ['core_auth']);

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

    $teamInstallSql = (string) file_get_contents(dirname(__DIR__) . '/modules/Team/install.sql');
    $assert(str_contains($teamInstallSql, 'CREATE TABLE team_members'));
    $assert(str_contains($teamInstallSql, 'fk_team_members_user'));
    $assert(str_contains($teamInstallSql, "'team.manage'"));
});

$test('Module archive import extracts only to quarantine and inspects manifest', static function () use ($assert): void {
    $root = sys_get_temp_dir() . '/miniportal-import-root-' . bin2hex(random_bytes(6));
    $source = $root . '/ExampleModule';
    $quarantine = $root . '/quarantine';
    mkdir($source, 0700, true);
    mkdir($quarantine, 0700, true);
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
