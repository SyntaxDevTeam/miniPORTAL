<?php

return [
    'app' => [
        'name' => 'SyntaxDevTeam',
        'base_url' => env_value('APP_URL', ''),
        'debug' => env_value('APP_DEBUG', true),
        'timezone' => env_value('APP_TIMEZONE', 'Europe/Warsaw'),
    ],

    'database' => [
        'database_type' => 'mysql',
        'server' => env_value('DB_HOST', 'localhost'),
        'database_name' => env_value('DB_NAME', 'syntax_test_db'),
        'username' => env_value('DB_USER', 'WieszczY'),
        'password' => env_value('DB_PASS', 'Ark@ntis2009'),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_general_ci',
        'port' => (int) env_value('DB_PORT', 3306),
        'logging' => true,
    ],

    'github' => [
        'client_id' => env_value('GITHUB_CLIENT_ID', 'Iv23ligE9FKig9IzX4uV'),
        'client_secret' => env_value('GITHUB_CLIENT_SECRET', '951d7f1cad9b73713118b6431ed3deb8ca098fe7'),
        'redirect_uri' => env_value('GITHUB_REDIRECT_URI', 'https://new.syntaxdevteam.pl/admin/callback.php'),
        'allowed_logins' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env_value('GITHUB_ALLOWED_LOGINS', 'WieszczY85'))
        ))),
    ],
];
