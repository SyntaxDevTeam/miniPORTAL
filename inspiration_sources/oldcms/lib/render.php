<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

function render_setup_problem(Throwable|string $problem): never
{
    http_response_code(503);

    $message = $problem instanceof Throwable ? $problem->getMessage() : $problem;
    ?>
<!doctype html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Konfiguracja wymagana - <?= e(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(site_url('/assets/css/style.css')) ?>">
</head>
<body>
    <main class="setup-box">
        <p class="eyebrow">Konfiguracja wymagana</p>
        <h1>CMS nie ma jeszcze poprawnej konfiguracji</h1>
        <p class="lead">Aplikacja dziala, ale nie moze polaczyc sie z baza danych albo brakuje ustawien OAuth.</p>
        <pre><?= e($message) ?></pre>
        <h2>Co ustawic</h2>
        <ol>
            <li>Zaimportuj <code>database/schema.sql</code> do MySQL.</li>
            <li>Uzupelnij <code>DB_HOST</code>, <code>DB_NAME</code>, <code>DB_USER</code> i <code>DB_PASS</code> w <code>config/config.php</code>.</li>
            <li>W Discord Developer Portal dodaj redirect URL: <code><?= e(discord_redirect_hint()) ?></code>.</li>
            <li>Uzupelnij <code>DISCORD_CLIENT_ID</code>, <code>DISCORD_CLIENT_SECRET</code> i <code>DISCORD_ALLOWED_USER_IDS</code>.</li>
        </ol>
    </main>
</body>
</html>
    <?php
    exit;
}

function discord_redirect_hint(): string
{
    if (defined('DISCORD_REDIRECT_URI') && DISCORD_REDIRECT_URI !== '') {
        return DISCORD_REDIRECT_URI;
    }

    if (defined('APP_URL') && APP_URL !== '') {
        return rtrim(APP_URL, '/') . '/auth/discord/callback';
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'twoja-domena.pl';

    return $scheme . '://' . $host . '/auth/discord/callback';
}
