<?php

declare(strict_types=1);

$environmentFile = getenv('MINIPORTAL_ENV_FILE');
$environmentFile = $environmentFile === false || $environmentFile === ''
    ? '/etc/miniportal/miniportal.env'
    : $environmentFile;

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

$databaseName = (string) $env('DB_NAME', '');
$databaseUser = (string) $env('DB_USER', '');

return [
    'app' => [
        'name' => (string) $env('APP_NAME', 'miniPORTAL'),
        'debug' => $envBool('APP_DEBUG', false),
        'timezone' => (string) $env('APP_TIMEZONE', 'Europe/Warsaw'),
        'theme' => (string) $env('APP_THEME', 'default'),
    ],
    'session' => [
        'name' => (string) $env('SESSION_NAME', 'MINIPORTALSESSID'),
        'same_site' => (string) $env('SESSION_SAME_SITE', 'Lax'),
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
