<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/discord.php';
require_once __DIR__ . '/../lib/render.php';

start_session();

if (current_admin()) {
    redirect('/admin/');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (!discord_oauth_ready()) {
        $error = 'Brakuje DISCORD_CLIENT_ID albo DISCORD_CLIENT_SECRET w config/config.php.';
    } else {
        redirect(discord_authorize_url());
    }
}
?>
<!doctype html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Logowanie - <?= e(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(site_url('/assets/css/style.css')) ?>">
</head>
<body class="admin-body">
    <main class="auth-box">
        <p class="eyebrow">Panel miniCMS</p>
        <h1>Logowanie Discord</h1>
        <?php if ($error): ?>
            <p class="alert"><?= e($error) ?></p>
        <?php endif; ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <button type="submit">Zaloguj przez Discord</button>
        </form>
        <p class="hint">Wlasciciele z `DISCORD_ALLOWED_USER_IDS` dostaja panel globalny. Pozostali uzytkownicy widza tylko swoje konteksty.</p>
        <p class="hint">Redirect URL: <code><?= e(discord_redirect_uri()) ?></code></p>
    </main>
</body>
</html>
