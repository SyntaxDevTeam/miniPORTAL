<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\CoreAuth;

use JsonException;
use RuntimeException;

final class DiscordIdentityProvider implements IdentityProviderInterface
{
    private const AUTHORIZE_URL = 'https://discord.com/oauth2/authorize';
    private const TOKEN_URL = 'https://discord.com/api/v10/oauth2/token';
    private const USER_URL = 'https://discord.com/api/v10/users/@me';

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $callbackUrl,
    ) {
    }

    public function name(): string
    {
        return 'discord';
    }

    public function label(): string
    {
        return 'Discord';
    }

    public function isConfigured(): bool
    {
        return $this->clientId !== ''
            && $this->clientSecret !== ''
            && filter_var($this->callbackUrl, FILTER_VALIDATE_URL) !== false;
    }

    public function authorizationUrl(string $state, string $_codeChallenge, string $_nonce): string
    {
        $this->assertConfigured();

        return self::AUTHORIZE_URL . '?' . http_build_query([
            'response_type' => 'code',
            'client_id' => $this->clientId,
            'scope' => 'identify email',
            'state' => $state,
            'redirect_uri' => $this->callbackUrl,
            'prompt' => 'consent',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function resolveIdentity(string $code, string $_codeVerifier, string $_nonce): ExternalIdentity
    {
        $this->assertConfigured();

        if ($code === '') {
            throw new RuntimeException('Discord nie zwrócił kompletnego kodu autoryzacyjnego.');
        }

        $tokenResponse = $this->http->request('POST', self::TOKEN_URL, [
            'Accept' => 'application/json',
            'User-Agent' => 'miniPORTAL',
        ], [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->callbackUrl,
        ]);
        $token = $this->decode($tokenResponse, 'Discord odrzucił wymianę kodu.');
        $accessToken = $token['access_token'] ?? null;

        if (!is_string($accessToken) || $accessToken === '') {
            throw new RuntimeException('Discord nie zwrócił tokenu dostępu.');
        }

        $profile = $this->decode(
            $this->http->request('GET', self::USER_URL, [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $accessToken,
                'User-Agent' => 'miniPORTAL',
            ]),
            'Nie można pobrać profilu Discord.'
        );
        $subject = $profile['id'] ?? null;
        $login = $profile['global_name'] ?? $profile['username'] ?? null;

        if (!is_string($subject) || $subject === '' || !is_string($login) || $login === '') {
            throw new RuntimeException('Profil Discord nie zawiera wymaganego identyfikatora.');
        }

        $avatarHash = $profile['avatar'] ?? null;
        $avatarUrl = is_string($avatarHash) && $avatarHash !== ''
            ? "https://cdn.discordapp.com/avatars/{$subject}/{$avatarHash}.png"
            : null;
        $email = $profile['email'] ?? null;

        return new ExternalIdentity(
            'discord',
            $subject,
            $login,
            is_string($email) && $email !== '' ? $email : null,
            ($profile['verified'] ?? false) === true,
            $avatarUrl
        );
    }

    private function decode(HttpResponse $response, string $message): array
    {
        if ($response->status < 200 || $response->status >= 300) {
            throw new RuntimeException($message);
        }

        try {
            return $response->json();
        } catch (JsonException) {
            throw new RuntimeException($message);
        }
    }

    private function assertConfigured(): void
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Adapter Discord nie jest skonfigurowany.');
        }
    }
}
