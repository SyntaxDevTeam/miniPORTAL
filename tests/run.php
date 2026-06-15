<?php

declare(strict_types=1);

use SyntaxDevTeam\Cms\Core\Autoloader;
use SyntaxDevTeam\Cms\Core\AdminMenuRegistry;
use SyntaxDevTeam\Cms\Core\ContentRenderer;
use SyntaxDevTeam\Cms\Core\ModuleBootstrapper;
use SyntaxDevTeam\Cms\Core\ModuleInterface;
use SyntaxDevTeam\Cms\Core\ModuleManifestValidator;
use SyntaxDevTeam\Cms\Core\ModuleRegistry;
use SyntaxDevTeam\Cms\Core\ModuleState;
use SyntaxDevTeam\Cms\Core\Request;
use SyntaxDevTeam\Cms\Core\RichTextSanitizer;
use SyntaxDevTeam\Cms\Core\Router;
use SyntaxDevTeam\Cms\Core\Security;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuthorizationService;
use SyntaxDevTeam\Cms\Modules\CoreAuth\OAuthAttemptLimiter;
use SyntaxDevTeam\Cms\Modules\CoreAuth\OAuthStateStore;
use SyntaxDevTeam\Cms\Modules\CoreAuth\User;

require_once dirname(__DIR__) . '/core/Autoloader.php';

Autoloader::register();

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
        ['confirmed' => '1', 'roles' => ['editor', 'user', 'editor']],
        ['REQUEST_METHOD' => 'post']
    );

    $assert($request->path() === '/admin/pages');
    $assert($request->method() === 'POST');
    $assert($request->queryInt('id') === 12);
    $assert($request->postBool('confirmed'));
    $assert($request->postStringList('roles') === ['editor', 'user']);
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

    $assert($auth->loginIdentity($identity) === null);
    $pending = $repository->findByIdentity('test', 'new-subject');
    $assert($pending !== null && $pending->status === 'pending');
    $assert($pending->roles === ['user']);
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
    $module = new class implements ModuleInterface {
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
    };

    $registry->add($module);
    $registry->boot($menu, $router);
    $request = Request::fromArrays(['route' => '/test-module'], [], ['REQUEST_METHOD' => 'GET']);

    $assert($registry->ids() === ['test_module']);
    $assert($router->dispatch($request) === 200);
    $assert(count($menu->visibleFor(['test.view'])) === 1);
});

$test('Module manifests are validated against runtime requirements', static function () use ($assert): void {
    $publishers = require dirname(__DIR__) . '/config/module_publishers.php';
    $validator = new ModuleManifestValidator('0.1.0', $publishers);
    $manifest = $validator->validate(dirname(__DIR__) . '/modules/Articles');

    $assert($manifest->id === 'articles');
    $assert($manifest->version === '1.0.1');
    $assert($manifest->installFile === 'install.sql');
    $assert($manifest->uninstallFile === 'uninstall.sql');
    $assert($manifest->requiredModules === ['core_auth']);

    $system = $validator->validate(dirname(__DIR__) . '/modules/System');
    $assert($system->id === 'system_admin');
    $assert($system->protected);

    $learning = $validator->validate(dirname(__DIR__) . '/install/mod/LearningModule');
    $assert($learning->id === 'learning_module');
    $assert($learning->version === '1.1.0');
    $assert($learning->factoryFile === 'factory.php');
    $assert($learning->uninstallFile === 'uninstall.sql');
    $assert($learning->originType === 'repository');
    $assert($learning->signatureStatus === 'verified');
    $assert(is_callable(require $learning->directory . '/' . $learning->factoryFile));
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
