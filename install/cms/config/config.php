<?php

declare(strict_types=1);

$environmentFile = getenv('MINIPORTAL_ENV_FILE');
if ($environmentFile === false || $environmentFile === '') {
    $systemEnvironmentFile = '/etc/miniportal/miniportal.env';
    $localEnvironmentFile = __DIR__ . '/installed.env';
    $environmentFile = is_readable($localEnvironmentFile)
        ? $localEnvironmentFile
        : $systemEnvironmentFile;
}

if (is_readable($environmentFile)) {
    $environment = parse_ini_file($environmentFile, false, INI_SCANNER_RAW);

    if ($environment === false) {
        throw new RuntimeException("Nie można odczytać pliku środowiskowego: {$environmentFile}");
    }

    foreach ($environment as $name => $value) {
        if (getenv($name) !== false) {
            continue;
        }

        $value = (string) $value;
        putenv("{$name}={$value}");
        $_ENV[$name] = $value;
    }
}

$env = static function (string $name, mixed $default = null): mixed {
    $value = getenv($name);

    return $value === false ? $default : $value;
};

$envBool = static function (string $name, bool $default = false) use ($env): bool {
    return filter_var($env($name, $default), FILTER_VALIDATE_BOOL);
};

$envInt = static function (string $name, int $default, int $minimum = 1) use ($env): int {
    $value = filter_var($env($name, $default), FILTER_VALIDATE_INT);

    return $value === false ? $default : max($minimum, $value);
};

$databaseName = (string) $env('DB_NAME', '');
$databaseUser = (string) $env('DB_USER', '');

return [
    'meta' => [
        'environment_file' => $environmentFile,
        'environment_readable' => is_readable($environmentFile),
    ],
    'app' => [
        'name' => (string) $env('APP_NAME', 'miniPORTAL'),
        'version' => '0.1.0',
        'debug' => $envBool('APP_DEBUG', false),
        'timezone' => (string) $env('APP_TIMEZONE', 'Europe/Warsaw'),
        'theme' => (string) $env('APP_THEME', 'default'),
        'public_url' => (string) $env('SITE_URL', 'https://syntaxdevteam.pl'),
        'public_name' => (string) $env('SITE_NAME', 'SyntaxDevTeam'),
        'public_default_title' => (string) $env(
            'SITE_DEFAULT_TITLE',
            'SyntaxDevTeam - software dla serwerów, społeczności i urządzeń'
        ),
        'public_eyebrow' => (string) $env('SITE_EYEBROW', 'Software dla społeczności'),
        'public_meta_description' => (string) $env(
            'SITE_META_DESCRIPTION',
            'SyntaxDevTeam tworzy pluginy Minecraft, boty Discord, aplikacje Android i narzędzia backendowe.'
        ),
        'public_meta_keywords' => (string) $env(
            'SITE_META_KEYWORDS',
            'SyntaxDevTeam, miniPORTAL, pluginy Minecraft, boty Discord, aplikacje Android'
        ),
        'public_meta_author' => (string) $env('SITE_META_AUTHOR', 'SyntaxDevTeam'),
        'public_meta_robots' => (string) $env(
            'SITE_META_ROBOTS',
            'index, follow, max-image-preview:large'
        ),
        'public_locale' => (string) $env('SITE_LOCALE', 'pl_PL'),
        'public_social_image_url' => (string) $env('SITE_SOCIAL_IMAGE_URL', ''),
        'public_social_image_alt' => (string) $env('SITE_SOCIAL_IMAGE_ALT', 'Logo SyntaxDevTeam'),
        'public_twitter_site' => (string) $env('SITE_TWITTER_SITE', ''),
        'public_theme_color' => (string) $env('SITE_THEME_COLOR', '#080c12'),
        'public_google_site_verification' => (string) $env('SITE_GOOGLE_VERIFICATION', ''),
        'public_bing_site_verification' => (string) $env('SITE_BING_VERIFICATION', ''),
        'public_footer_text' => (string) $env('SITE_FOOTER_TEXT', 'Projektowane modułowo. Rozwijane świadomie.'),
    ],
    'session' => [
        'name' => (string) $env('SESSION_NAME', 'MINIPORTALSESSID'),
        'same_site' => (string) $env('SESSION_SAME_SITE', 'Lax'),
    ],
    'cache' => [
        'enabled' => $envBool('TEMPLATE_CACHE_ENABLED', true),
        'ttl' => $envInt('TEMPLATE_CACHE_TTL', 300, 1),
    ],
    'modules' => [
        'archive_max_bytes' => $envInt('MODULE_ARCHIVE_MAX_BYTES', 10485760, 1024),
        'build_upload_max_bytes' => $envInt('BUILD_UPLOAD_MAX_BYTES', 20971520, 1024),
        'build_ci_token' => (string) $env('BUILD_CI_TOKEN', ''),
    ],
    'auth' => [
        'storage' => (string) $env('AUTH_STORAGE', 'database'),
        'demo_enabled' => $envBool('AUTH_DEMO_ENABLED', false),
        'audit_hash_key' => (string) $env('AUTH_AUDIT_HASH_KEY', ''),
        'oauth_window_seconds' => $envInt('AUTH_OAUTH_WINDOW_SECONDS', 600, 60),
        'oauth_start_limit' => $envInt('AUTH_OAUTH_START_LIMIT', 10),
        'oauth_callback_limit' => $envInt('AUTH_OAUTH_CALLBACK_LIMIT', 20),
        'audit_retention_days' => $envInt('AUTH_AUDIT_RETENTION_DAYS', 180, 1),
        'audit_archive_limit' => $envInt('AUTH_AUDIT_ARCHIVE_LIMIT', 5000, 1),
        'providers' => [
            'github' => [
                'client_id' => (string) $env('GITHUB_CLIENT_ID', ''),
                'client_secret' => (string) $env('GITHUB_CLIENT_SECRET', ''),
                'callback_url' => (string) $env(
                    'GITHUB_CALLBACK_URL',
                    'https://new.syntaxdevteam.pl/index.php?route=/admin/auth/github/callback'
                ),
            ],
            'discord' => [
                'client_id' => (string) $env('DISCORD_CLIENT_ID', ''),
                'client_secret' => (string) $env('DISCORD_CLIENT_SECRET', ''),
                'callback_url' => (string) $env(
                    'DISCORD_CALLBACK_URL',
                    'https://new.syntaxdevteam.pl/index.php?route=/admin/auth/discord/callback'
                ),
            ],
            'google' => [
                'client_id' => (string) $env('GOOGLE_CLIENT_ID', ''),
                'client_secret' => (string) $env('GOOGLE_CLIENT_SECRET', ''),
                'callback_url' => (string) $env(
                    'GOOGLE_CALLBACK_URL',
                    'https://new.syntaxdevteam.pl/index.php?route=/admin/auth/google/callback'
                ),
            ],
        ],
    ],
    'database' => [
        'enabled' => $envBool('DB_ENABLED', $databaseName !== '' && $databaseUser !== ''),
        'database_type' => (string) $env('DB_DRIVER', 'mysql'),
        'server' => (string) $env('DB_HOST', '127.0.0.1'),
        'database_name' => $databaseName,
        'username' => $databaseUser,
        'password' => (string) $env('DB_PASS', ''),
        'charset' => (string) $env('DB_CHARSET', 'utf8mb4'),
        'collation' => (string) $env('DB_COLLATION', 'utf8mb4_general_ci'),
        'port' => (int) $env('DB_PORT', 3306),
        'logging' => $envBool('DB_LOGGING', false),
    ],
];
