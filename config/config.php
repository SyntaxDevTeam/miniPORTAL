<?php

declare(strict_types=1);

use SyntaxDevTeam\Cms\Core\InstallationState;

$environmentFile = InstallationState::environmentFile(dirname(__DIR__));
$environment = [];

$explicitEnvironmentFile = getenv('MINIPORTAL_ENV_FILE');
$explicitEnvironmentFile = is_string($explicitEnvironmentFile) ? trim($explicitEnvironmentFile) : '';
if ($explicitEnvironmentFile !== '' && !is_readable($environmentFile)) {
    throw new RuntimeException(
        "Plik wskazany przez MINIPORTAL_ENV_FILE nie jest dostępny: {$environmentFile}"
    );
}

if (is_readable($environmentFile)) {
    $parsedEnvironment = parse_ini_file($environmentFile, false, INI_SCANNER_RAW);

    if ($parsedEnvironment === false) {
        throw new RuntimeException("Nie można odczytać pliku środowiskowego: {$environmentFile}");
    }
    $environment = $parsedEnvironment;
}

$authProvidersFile = dirname(__DIR__) . '/config/modules/auth-providers.env';
if (is_readable($authProvidersFile)) {
    $authProvidersEnvironment = parse_ini_file($authProvidersFile, false, INI_SCANNER_RAW);
    if ($authProvidersEnvironment === false) {
        throw new RuntimeException("Nie można odczytać konfiguracji dostawców logowania: {$authProvidersFile}");
    }
    $environment = array_replace($environment, $authProvidersEnvironment);
}

$env = static function (string $name, mixed $default = null) use ($environment): mixed {
    if (array_key_exists($name, $environment)) {
        return $environment[$name];
    }
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
        'auth_providers_file' => $authProvidersFile,
        'auth_providers_readable' => is_readable($authProvidersFile),
    ],
    'app' => [
        'name' => (string) $env('APP_NAME', 'miniPORTAL'),
        'version' => '0.7.1',
        'debug' => $envBool('APP_DEBUG', false),
        'timezone' => (string) $env('APP_TIMEZONE', 'Europe/Warsaw'),
        'request_max_body_bytes' => $envInt('REQUEST_MAX_BODY_BYTES', 1048576, 1024),
        'trusted_proxies' => array_values(array_filter(array_map(
            static fn (string $proxy): string => trim($proxy),
            explode(',', (string) $env('TRUSTED_PROXIES', ''))
        ), static fn (string $proxy): bool => $proxy !== '')),
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
        'public_favicon_path' => '',
        'public_favicon_version' => '',
        'public_faststats_enabled' => (string) $env('FASTSTATS_ENABLED', '0'),
        'public_faststats_site_key' => (string) $env('FASTSTATS_SITE_KEY', ''),
        'public_faststats_cookieless' => (string) $env('FASTSTATS_COOKIELESS', '1'),
        'public_faststats_web_vitals' => (string) $env('FASTSTATS_WEB_VITALS', '1'),
        'public_faststats_error_tracking' => (string) $env('FASTSTATS_ERROR_TRACKING', '1'),
        'public_faststats_session_replays' => (string) $env('FASTSTATS_SESSION_REPLAYS', '0'),
        'public_faststats_debug' => (string) $env('FASTSTATS_DEBUG', '0'),
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
        'archive_max_files' => $envInt('MODULE_ARCHIVE_MAX_FILES', 2000, 1),
        'archive_max_file_bytes' => $envInt('MODULE_ARCHIVE_MAX_FILE_BYTES', 10485760, 1024),
        'archive_max_unpacked_bytes' => $envInt('MODULE_ARCHIVE_MAX_UNPACKED_BYTES', 52428800, 1024),
        'quarantine_retention_days' => $envInt('MODULE_QUARANTINE_RETENTION_DAYS', 7, 1),
        'update_catalog_url' => trim((string) $env(
            'MODULE_UPDATE_CATALOG_URL',
            'https://syntaxdevteam.pl/api/module-updates/catalog'
        )),
        'update_catalog_ttl' => $envInt('MODULE_UPDATE_CATALOG_TTL', 900, 1),
        'build_upload_max_bytes' => $envInt('BUILD_UPLOAD_MAX_BYTES', 20971520, 1024),
        'media_upload_max_bytes' => $envInt('MEDIA_UPLOAD_MAX_BYTES', 5242880, 1024),
        'minecraft_console_upload_max_bytes' => $envInt('MINECRAFT_CONSOLE_UPLOAD_MAX_BYTES', 5242880, 1024),
        'tinify_enabled' => $envBool('TINIFY_ENABLED', (string) $env('TINIFY_API_KEY', '') !== ''),
        'tinify_api_key' => trim((string) $env('TINIFY_API_KEY', '')),
        'tinify_monthly_limit' => $envInt('TINIFY_MONTHLY_LIMIT', 500, 1),
        'build_ci_token' => (string) $env('BUILD_CI_TOKEN', ''),
        'plugin_stats_allow_anonymous' => $envBool('PLUGIN_STATS_ALLOW_ANONYMOUS', false),
        'plugin_stats_allowed_api_keys' => implode(',', array_filter([
            trim((string) $env('SYNTAXCORE_ALLOWED_API_KEYS', '')),
            trim((string) $env('SYNTAX_METRICS_ALLOWED_API_KEYS', '')),
            trim((string) $env('METRICS_ALLOWED_API_KEYS', '')),
        ], static fn (string $value): bool => $value !== '')),
        'plugin_stats_ip_blacklist' => trim((string) $env(
            'SYNTAX_METRICS_IP_BLACKLIST',
            (string) $env('METRICS_IP_BLACKLIST', '')
        )),
        'plugin_stats_secret_key' => (string) $env('SYNTAXCORE_SECRET_KEY', 'jH47ZjoaNsrj94ja'),
        'plugin_stats_secret_iv' => (string) $env('SYNTAXCORE_SECRET_IV', 'wTAeyF6V7xNET9WB'),
        'plugin_stats_rate_limit' => $envInt('PLUGIN_STATS_RATE_LIMIT', 90, 1),
        'plugin_stats_rate_window' => $envInt('PLUGIN_STATS_RATE_WINDOW', 120, 1),
        'plugin_stats_metrics_v1_rate_limit' => $envInt('PLUGIN_STATS_METRICS_V1_RATE_LIMIT', 180, 1),
        'plugin_stats_metrics_v1_rate_window' => $envInt('PLUGIN_STATS_METRICS_V1_RATE_WINDOW', 120, 1),
        'plugin_stats_online_window_minutes' => $envInt('PLUGIN_STATS_ONLINE_WINDOW_MINUTES', 30, 5),
        'plugin_stats_retention_days' => $envInt('PLUGIN_STATS_RETENTION_DAYS', 180, 1),
        'licences_rate_limit' => $envInt('LICENCES_RATE_LIMIT', 60, 1),
        'licences_rate_window' => $envInt('LICENCES_RATE_WINDOW', 120, 1),
        'licences_check_retention_days' => $envInt('LICENCES_CHECK_RETENTION_DAYS', 180, 1),
        'uptime_rate_limit' => $envInt('UPTIME_RATE_LIMIT', 120, 1),
        'uptime_rate_window' => $envInt('UPTIME_RATE_WINDOW', 120, 1),
        'rate_limit_retention_days' => $envInt('RATE_LIMIT_RETENTION_DAYS', 2, 1),
        'remote_terminal_enabled' => $envBool('REMOTE_TERMINAL_ENABLED', false),
        'remote_terminal_mode' => trim((string) $env('REMOTE_TERMINAL_MODE', '')),
        'remote_terminal_gateway_url' => trim((string) $env('REMOTE_TERMINAL_GATEWAY_URL', '')),
        'remote_terminal_shared_secret' => trim((string) $env('REMOTE_TERMINAL_SHARED_SECRET', '')),
        'remote_terminal_token_parameter' => trim((string) $env('REMOTE_TERMINAL_TOKEN_PARAMETER', 'mp_token')),
        'remote_terminal_token_ttl' => $envInt('REMOTE_TERMINAL_TOKEN_TTL', 60, 15),
        'remote_terminal_ssh_host' => trim((string) $env('REMOTE_TERMINAL_SSH_HOST', '')),
        'remote_terminal_ssh_port' => $envInt('REMOTE_TERMINAL_SSH_PORT', 22, 1),
        'remote_terminal_ssh_user' => trim((string) $env('REMOTE_TERMINAL_SSH_USER', '')),
        'remote_terminal_ssh_key_file' => trim((string) $env('REMOTE_TERMINAL_SSH_KEY_FILE', '')),
        'remote_terminal_hosts' => trim((string) $env('REMOTE_TERMINAL_HOSTS', '')),
        'remote_terminal_ssh_binary' => trim((string) $env('REMOTE_TERMINAL_SSH_BINARY', '/usr/bin/ssh')),
        'remote_terminal_pty_binary' => trim((string) $env('REMOTE_TERMINAL_PTY_BINARY', '/usr/bin/script')),
        'remote_terminal_allowed_hosts' => trim((string) $env('REMOTE_TERMINAL_ALLOWED_HOSTS', '127.0.0.1,localhost,::1')),
        'remote_terminal_session_ttl' => $envInt('REMOTE_TERMINAL_SESSION_TTL', 3600, 60),
        'remote_terminal_require_secure_request' => $envBool('REMOTE_TERMINAL_REQUIRE_HTTPS', true),
        'signing_key_id' => trim((string) $env('MODULE_SIGNING_KEY_ID', '')),
        'signing_private_key_file' => trim((string) $env('MODULE_SIGNING_PRIVATE_KEY_FILE', '')),
        'signing_public_key_file' => trim((string) $env('MODULE_SIGNING_PUBLIC_KEY_FILE', '')),
    ],
    'updates' => [
        'catalog_url' => (string) $env('PLATFORM_RELEASE_CATALOG_URL', ''),
        'archive_max_bytes' => $envInt('PLATFORM_RELEASE_MAX_BYTES', 52428800, 1048576),
        'archive_max_files' => $envInt('PLATFORM_RELEASE_MAX_FILES', 10000, 1),
        'archive_max_file_bytes' => $envInt('PLATFORM_RELEASE_MAX_FILE_BYTES', 52428800, 1024),
        'archive_max_unpacked_bytes' => $envInt('PLATFORM_RELEASE_MAX_UNPACKED_BYTES', 209715200, 1048576),
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
                'enabled' => $envBool('GITHUB_ENABLED', (string) $env('GITHUB_CLIENT_ID', '') !== ''),
                'client_id' => (string) $env('GITHUB_CLIENT_ID', ''),
                'client_secret' => (string) $env('GITHUB_CLIENT_SECRET', ''),
                'callback_url' => (string) $env(
                    'GITHUB_CALLBACK_URL',
                    'https://syntaxdevteam.pl/index.php?route=/admin/auth/github/callback'
                ),
            ],
            'discord' => [
                'enabled' => $envBool('DISCORD_ENABLED', (string) $env('DISCORD_CLIENT_ID', '') !== ''),
                'client_id' => (string) $env('DISCORD_CLIENT_ID', ''),
                'client_secret' => (string) $env('DISCORD_CLIENT_SECRET', ''),
                'callback_url' => (string) $env(
                    'DISCORD_CALLBACK_URL',
                    'https://syntaxdevteam.pl/index.php?route=/admin/auth/discord/callback'
                ),
            ],
            'google' => [
                'enabled' => $envBool('GOOGLE_ENABLED', (string) $env('GOOGLE_CLIENT_ID', '') !== ''),
                'client_id' => (string) $env('GOOGLE_CLIENT_ID', ''),
                'client_secret' => (string) $env('GOOGLE_CLIENT_SECRET', ''),
                'callback_url' => (string) $env(
                    'GOOGLE_CALLBACK_URL',
                    'https://syntaxdevteam.pl/index.php?route=/admin/auth/google/callback'
                ),
            ],
            'microsoft' => [
                'enabled' => $envBool('MICROSOFT_ENABLED', (string) $env('MICROSOFT_CLIENT_ID', '') !== ''),
                'client_id' => (string) $env('MICROSOFT_CLIENT_ID', ''),
                'client_secret' => (string) $env('MICROSOFT_CLIENT_SECRET', ''),
                'callback_url' => (string) $env(
                    'MICROSOFT_CALLBACK_URL',
                    'https://syntaxdevteam.pl/admin/auth/microsoft/callback'
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
