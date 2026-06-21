<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/discord.php';
require_once __DIR__ . '/../lib/render.php';

start_session();

$state = $_GET['state'] ?? '';
$code = $_GET['code'] ?? '';

if (!is_string($state) || !hash_equals($_SESSION['discord_oauth_state'] ?? '', $state)) {
    http_response_code(400);
    exit('Nieprawidlowy stan OAuth.');
}

unset($_SESSION['discord_oauth_state']);

if (!is_string($code) || $code === '') {
    http_response_code(400);
    exit('Brak kodu OAuth.');
}

try {
    $token = discord_exchange_code($code);
    $accessToken = (string) $token['access_token'];
    $user = discord_get_current_user($accessToken);
    $user['avatar_url'] = discord_avatar_url($user);
    $guilds = discord_get_current_user_guilds($accessToken);

    session_regenerate_id(true);
    $_SESSION[ADMIN_SESSION_KEY] = panel_access()->syncUserFromDiscord(
        $user,
        $guilds,
        DISCORD_ALLOWED_USER_IDS
    );

    redirect('/admin/');
} catch (Throwable $e) {
    render_setup_problem($e);
}
