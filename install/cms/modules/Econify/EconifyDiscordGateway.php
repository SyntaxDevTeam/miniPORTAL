<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\Econify;

use JsonException;
use RuntimeException;
use SyntaxDevTeam\Cms\Modules\CoreAuth\HttpClientInterface;
use SyntaxDevTeam\Cms\Modules\CoreAuth\HttpResponse;
use SyntaxDevTeam\Cms\Modules\CoreAuth\OAuthStateStore;

final class EconifyDiscordGateway
{
    private const AUTHORIZE_URL = 'https://discord.com/oauth2/authorize';
    private const TOKEN_URL = 'https://discord.com/api/v10/oauth2/token';
    private const USER_URL = 'https://discord.com/api/v10/users/@me';
    private const GUILDS_URL = 'https://discord.com/api/v10/users/@me/guilds';
    private const SESSION_KEY = '_econify_discord_guilds';
    private const CACHE_SECONDS = 600;
    private const ADMINISTRATOR = 0x8;
    private const MANAGE_GUILD = 0x20;

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly OAuthStateStore $states,
        private readonly EconifyConfig $config,
    ) {
    }

    public function discoveryUrl(int $userId): string
    {
        $this->assertConfigured();
        $oauth = $this->states->issue('econify_discord', 'guild_discovery', $userId);

        return self::AUTHORIZE_URL . '?' . http_build_query([
            'response_type' => 'code',
            'client_id' => $this->config->discordClientId,
            'scope' => 'identify guilds',
            'state' => $oauth['state'],
            'redirect_uri' => $this->config->discordCallbackUrl,
            'prompt' => 'consent',
            'code_challenge' => $oauth['challenge'],
            'code_challenge_method' => 'S256',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /** @return list<array{id:string,name:string,icon_url:?string,owner:bool,access:string}> */
    public function complete(string $state, string $code, int $userId): array
    {
        $this->assertConfigured();
        $context = $this->states->consume('econify_discord', $state);
        if ($context === null || $context->purpose !== 'guild_discovery' || $context->userId !== $userId || $code === '') {
            throw new RuntimeException('Nieprawidłowy lub wygasły stan autoryzacji Discord.');
        }
        $tokenData = $this->decode($this->http->request('POST', self::TOKEN_URL, [
            'Accept' => 'application/json',
            'User-Agent' => 'miniPORTAL Econify',
        ], [
            'client_id' => $this->config->discordClientId,
            'client_secret' => $this->config->discordClientSecret,
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->config->discordCallbackUrl,
            'code_verifier' => $context->verifier,
        ]), 'Discord odrzucił kod autoryzacyjny Econify.');
        $accessToken = $tokenData['access_token'] ?? null;
        if (!is_string($accessToken) || $accessToken === '') {
            throw new RuntimeException('Discord nie zwrócił tokenu dla listy serwerów.');
        }
        $headers = ['Accept' => 'application/json', 'Authorization' => 'Bearer ' . $accessToken, 'User-Agent' => 'miniPORTAL Econify'];
        $profile = $this->decode($this->http->request('GET', self::USER_URL, $headers), 'Nie można potwierdzić użytkownika Discord.');
        $discordUserId = $profile['id'] ?? null;
        if (!is_string($discordUserId) || preg_match('/^[0-9]{6,32}$/', $discordUserId) !== 1) {
            throw new RuntimeException('Discord zwrócił nieprawidłowy identyfikator użytkownika.');
        }
        $guildData = $this->decode($this->http->request('GET', self::GUILDS_URL, $headers), 'Nie można pobrać listy serwerów Discord.');
        $guilds = [];
        foreach ($guildData as $guild) {
            if (!is_array($guild)) { continue; }
            $id = $guild['id'] ?? null; $name = $guild['name'] ?? null;
            $permissions = is_numeric($guild['permissions'] ?? null) ? (int) $guild['permissions'] : 0;
            $owner = ($guild['owner'] ?? false) === true;
            if (!is_string($id) || preg_match('/^[0-9]{6,32}$/', $id) !== 1 || !is_string($name) || $name === '' || (!$owner && ($permissions & (self::ADMINISTRATOR | self::MANAGE_GUILD)) === 0)) {
                continue;
            }
            $icon = $guild['icon'] ?? null;
            $guilds[] = [
                'id' => $id,
                'name' => substr($name, 0, 120),
                'icon_url' => is_string($icon) && $icon !== '' ? "https://cdn.discordapp.com/icons/{$id}/{$icon}.png?size=128" : null,
                'owner' => $owner,
                'access' => $owner ? 'Właściciel' : (($permissions & self::ADMINISTRATOR) !== 0 ? 'Administrator' : 'Zarządzanie serwerem'),
            ];
        }
        usort($guilds, static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));
        $this->store($userId, $discordUserId, $guilds);

        return $guilds;
    }

    /** @return list<array{id:string,name:string,icon_url:?string,owner:bool,access:string}> */
    public function guilds(int $userId): array
    {
        $entry = $this->sessionEntry($userId);
        return $entry['guilds'] ?? [];
    }

    /** @return array{id:string,name:string,icon_url:?string,owner:bool,access:string}|null */
    public function guild(int $userId, string $guildId): ?array
    {
        foreach ($this->guilds($userId) as $guild) {
            if ($guild['id'] === $guildId) { return $guild; }
        }
        return null;
    }

    public function discordUserId(int $userId): ?string
    {
        return $this->sessionEntry($userId)['discord_user_id'] ?? null;
    }

    public function installationUrl(string $guildId): string
    {
        $this->assertConfigured();
        return self::AUTHORIZE_URL . '?' . http_build_query([
            'client_id' => $this->config->discordClientId,
            'scope' => 'bot applications.commands',
            'permissions' => $this->config->discordBotPermissions,
            'guild_id' => $guildId,
            'disable_guild_select' => 'true',
            'integration_type' => 0,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function botPresent(string $guildId): bool
    {
        if (!$this->config->botTokenConfigured()) { return false; }
        $response = $this->http->request('GET', 'https://discord.com/api/v10/guilds/' . rawurlencode($guildId), [
            'Accept' => 'application/json',
            'Authorization' => 'Bot ' . $this->config->discordBotToken,
            'User-Agent' => 'miniPORTAL Econify',
        ]);
        return $response->status >= 200 && $response->status < 300;
    }

    /** @param list<array{id:string,name:string,icon_url:?string,owner:bool,access:string}> $guilds */
    private function store(int $userId, string $discordUserId, array $guilds): void
    {
        $this->assertSession();
        $_SESSION[self::SESSION_KEY] = ['user_id' => $userId, 'discord_user_id' => $discordUserId, 'guilds' => $guilds, 'expires_at' => time() + self::CACHE_SECONDS];
    }

    /** @return array{discord_user_id:string,guilds:list<array{id:string,name:string,icon_url:?string,owner:bool,access:string}>}|array{} */
    private function sessionEntry(int $userId): array
    {
        $this->assertSession();
        $entry = $_SESSION[self::SESSION_KEY] ?? null;
        if (!is_array($entry) || (int) ($entry['user_id'] ?? 0) !== $userId || (int) ($entry['expires_at'] ?? 0) < time() || !is_array($entry['guilds'] ?? null) || !is_string($entry['discord_user_id'] ?? null)) {
            unset($_SESSION[self::SESSION_KEY]);
            return [];
        }
        return ['discord_user_id' => $entry['discord_user_id'], 'guilds' => $entry['guilds']];
    }

    /** @return array<mixed> */
    private function decode(HttpResponse $response, string $message): array
    {
        if ($response->status < 200 || $response->status >= 300) { throw new RuntimeException($message); }
        try { return $response->json(); } catch (JsonException) { throw new RuntimeException($message); }
    }

    private function assertConfigured(): void
    {
        if (!$this->config->discordApplicationConfigured()) { throw new RuntimeException('Dedykowana aplikacja Discord Econify nie jest skonfigurowana.'); }
    }

    private function assertSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) { throw new RuntimeException('Integracja Discord Econify wymaga aktywnej sesji.'); }
    }
}
