<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

const DISCORD_API_BASE = 'https://discord.com/api/v10';
const DISCORD_OAUTH_SCOPES = 'identify guilds';

function request_origin(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    return $scheme . '://' . $host;
}

function discord_redirect_uri(): string
{
    if (DISCORD_REDIRECT_URI !== '') {
        return DISCORD_REDIRECT_URI;
    }

    $base = APP_URL !== '' ? rtrim(APP_URL, '/') : request_origin();

    return $base . '/auth/discord/callback';
}

function discord_oauth_ready(): bool
{
    return DISCORD_CLIENT_ID !== '' && DISCORD_CLIENT_SECRET !== '';
}

function discord_authorize_url(): string
{
    start_session();

    $_SESSION['discord_oauth_state'] = bin2hex(random_bytes(24));

    return 'https://discord.com/oauth2/authorize?' . http_build_query([
        'client_id' => DISCORD_CLIENT_ID,
        'redirect_uri' => discord_redirect_uri(),
        'response_type' => 'code',
        'scope' => DISCORD_OAUTH_SCOPES,
        'state' => $_SESSION['discord_oauth_state'],
        'prompt' => 'consent',
    ]);
}

function discord_request(string $method, string $url, array $options = []): array
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('Rozszerzenie PHP cURL jest wymagane do logowania Discord OAuth.');
    }

    $ch = curl_init($url);

    $headers = $options['headers'] ?? [];
    $body = $options['body'] ?? null;

    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_HTTPHEADER => $headers,
    ]);

    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }

    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        throw new RuntimeException('Blad polaczenia z Discord API: ' . $error);
    }

    $data = json_decode($response, true);

    if (!is_array($data)) {
        throw new RuntimeException('Discord API zwrocilo nieprawidlowa odpowiedz.');
    }

    if ($status < 200 || $status >= 300) {
        $message = $data['error_description'] ?? $data['message'] ?? 'Blad Discord API.';
        throw new RuntimeException((string) $message);
    }

    return $data;
}

function discord_exchange_code(string $code): array
{
    return discord_request('POST', DISCORD_API_BASE . '/oauth2/token', [
        'headers' => ['Content-Type: application/x-www-form-urlencoded'],
        'body' => http_build_query([
            'client_id' => DISCORD_CLIENT_ID,
            'client_secret' => DISCORD_CLIENT_SECRET,
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => discord_redirect_uri(),
        ]),
    ]);
}

function discord_get_current_user(string $accessToken): array
{
    return discord_request('GET', DISCORD_API_BASE . '/users/@me', [
        'headers' => ['Authorization: Bearer ' . $accessToken],
    ]);
}

function discord_get_current_user_guilds(string $accessToken): array
{
    return discord_request('GET', DISCORD_API_BASE . '/users/@me/guilds', [
        'headers' => ['Authorization: Bearer ' . $accessToken],
    ]);
}

function discord_user_is_allowed(string $discordUserId): bool
{
    $allowed = array_filter(DISCORD_ALLOWED_USER_IDS);

    if ($allowed === []) {
        return false;
    }

    return in_array($discordUserId, $allowed, true);
}

function discord_avatar_url(array $user): ?string
{
    if (empty($user['avatar']) || empty($user['id'])) {
        return null;
    }

    $extension = str_starts_with((string) $user['avatar'], 'a_') ? 'gif' : 'png';

    return sprintf(
        'https://cdn.discordapp.com/avatars/%s/%s.%s',
        rawurlencode((string) $user['id']),
        rawurlencode((string) $user['avatar']),
        $extension
    );
}
