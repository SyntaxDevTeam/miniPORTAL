<?php

declare(strict_types=1);

use SyntaxDevTeam\Cms\Database\CrudApp;
use SyntaxDevTeam\Cms\Modules\CoreAuth\ExternalIdentity;
use SyntaxDevTeam\Cms\Modules\CoreAuth\FirstAdminBootstrapper;
use SyntaxDevTeam\Cms\Modules\CoreAuth\NativeHttpClient;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

require_once dirname(__DIR__) . '/core/Autoloader.php';

SyntaxDevTeam\Cms\Core\Autoloader::register();

$dryRun = ($argv[1] ?? '') === '--dry-run';
$login = trim((string) ($argv[$dryRun ? 2 : 1] ?? ''));

if ($login === '' || in_array($login, ['-h', '--help'], true)) {
    fwrite(STDERR, "Użycie: php bin/bootstrap-admin.php [--dry-run] LOGIN_GITHUB\n");
    exit($login === '' ? 2 : 0);
}

try {
    $config = require dirname(__DIR__) . '/config/config.php';
    $databaseConfig = $config['database'] ?? [];

    if (($databaseConfig['enabled'] ?? false) !== true) {
        throw new RuntimeException('Baza danych jest wyłączona.');
    }

    unset($databaseConfig['enabled']);
    $response = (new NativeHttpClient())->request(
        'GET',
        'https://api.github.com/users/' . rawurlencode($login),
        [
            'Accept' => 'application/vnd.github+json',
            'X-GitHub-Api-Version' => '2022-11-28',
            'User-Agent' => 'miniPORTAL',
        ]
    );

    if ($response->status !== 200) {
        throw new RuntimeException('Nie znaleziono wskazanego konta GitHub.');
    }

    $profile = $response->json();
    $subject = $profile['id'] ?? null;
    $resolvedLogin = $profile['login'] ?? null;

    if ((!is_int($subject) && !is_string($subject)) || !is_string($resolvedLogin)) {
        throw new RuntimeException('Profil GitHub nie zawiera wymaganego identyfikatora.');
    }

    $identity = new ExternalIdentity(
        'github',
        (string) $subject,
        $resolvedLogin,
        null,
        false,
        is_string($profile['avatar_url'] ?? null) ? $profile['avatar_url'] : null
    );

    if ($dryRun) {
        fwrite(
            STDOUT,
            "Rozpoznano GitHub: {$identity->login} (subject: {$identity->subject}). "
            . "Baza nie została zmieniona.\n"
        );
        exit(0);
    }

    $user = (new FirstAdminBootstrapper(
        CrudApp::getInstance($databaseConfig)
    ))->bootstrap($identity, $resolvedLogin);

    fwrite(
        STDOUT,
        "Utworzono administratora #{$user->id}: {$user->displayName} "
        . "(github:{$identity->subject}).\n"
    );
} catch (Throwable $exception) {
    fwrite(STDERR, 'Bootstrap nie został wykonany: ' . $exception->getMessage() . "\n");
    exit(1);
}
