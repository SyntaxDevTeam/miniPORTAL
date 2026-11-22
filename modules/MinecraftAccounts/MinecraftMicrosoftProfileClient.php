<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\MinecraftAccounts;

use JsonException;
use RuntimeException;
use SyntaxDevTeam\Cms\Modules\CoreAuth\HttpResponse;

final class MinecraftMicrosoftProfileClient implements MinecraftProfileClientInterface
{
    private const AUTHORIZE_URL = 'https://login.live.com/oauth20_authorize.srf';
    private const TOKEN_URL = 'https://login.live.com/oauth20_token.srf';
    private const XBOX_USER_URL = 'https://user.auth.xboxlive.com/user/authenticate';
    private const XBOX_XSTS_URL = 'https://xsts.auth.xboxlive.com/xsts/authorize';
    private const MINECRAFT_LOGIN_URL = 'https://api.minecraftservices.com/authentication/login_with_xbox';
    private const MINECRAFT_PROFILE_URL = 'https://api.minecraftservices.com/minecraft/profile';

    public function __construct(
        private readonly MinecraftApiTransportInterface $http,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $callbackUrl,
    ) {
    }

    public function isConfigured(): bool
    {
        return trim($this->clientId) !== ''
            && filter_var($this->callbackUrl, FILTER_VALIDATE_URL) !== false
            && str_starts_with(strtolower($this->callbackUrl), 'https://');
    }

    public function authorizationUrl(string $state, string $codeChallenge): string
    {
        $this->assertConfigured();
        return self::AUTHORIZE_URL . '?' . http_build_query([
            'client_id' => $this->clientId,
            'response_type' => 'code',
            'redirect_uri' => $this->callbackUrl,
            'scope' => 'XboxLive.signin',
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function resolveProfile(string $code, string $codeVerifier): MinecraftProfile
    {
        $this->assertConfigured();
        if ($code === '' || $codeVerifier === '') {
            throw new MinecraftAccountException('Microsoft nie zwrócił kompletnego kodu połączenia Minecraft.');
        }

        $tokenPayload = [
            'client_id' => $this->clientId,
            'code' => $code,
            'code_verifier' => $codeVerifier,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->callbackUrl,
        ];
        if (trim($this->clientSecret) !== '') {
            $tokenPayload['client_secret'] = $this->clientSecret;
        }
        $microsoft = $this->decode($this->http->request(
            'POST',
            self::TOKEN_URL,
            ['Accept' => 'application/json', 'User-Agent' => 'miniPORTAL Minecraft Accounts'],
            $tokenPayload,
        ), 'Microsoft odrzucił kod połączenia Minecraft.');
        $microsoftToken = $this->requiredString($microsoft, 'access_token', 'Microsoft nie zwrócił tokenu Xbox Live.');

        $xboxUser = $this->decode($this->http->request(
            'POST',
            self::XBOX_USER_URL,
            ['Accept' => 'application/json', 'User-Agent' => 'miniPORTAL Minecraft Accounts'],
            [
                'Properties' => [
                    'AuthMethod' => 'RPS',
                    'SiteName' => 'user.auth.xboxlive.com',
                    'RpsTicket' => 'd=' . $microsoftToken,
                ],
                'RelyingParty' => 'http://auth.xboxlive.com',
                'TokenType' => 'JWT',
            ],
            true,
        ), 'Nie udało się potwierdzić konta Xbox Live.');
        $xboxUserToken = $this->requiredString($xboxUser, 'Token', 'Xbox Live nie zwrócił tokenu użytkownika.');

        $xsts = $this->decode($this->http->request(
            'POST',
            self::XBOX_XSTS_URL,
            ['Accept' => 'application/json', 'User-Agent' => 'miniPORTAL Minecraft Accounts'],
            [
                'Properties' => ['SandboxId' => 'RETAIL', 'UserTokens' => [$xboxUserToken]],
                'RelyingParty' => 'rp://api.minecraftservices.com/',
                'TokenType' => 'JWT',
            ],
            true,
        ), 'Konto Xbox nie może zostać użyte do potwierdzenia profilu Minecraft.');
        $xstsToken = $this->requiredString($xsts, 'Token', 'Xbox XSTS nie zwrócił tokenu dostępu.');
        $userHash = $xsts['DisplayClaims']['xui'][0]['uhs'] ?? null;
        if (!is_string($userHash) || $userHash === '') {
            throw new MinecraftAccountException('Xbox XSTS nie zwrócił identyfikatora użytkownika.');
        }

        $minecraft = $this->decode($this->http->request(
            'POST',
            self::MINECRAFT_LOGIN_URL,
            ['Accept' => 'application/json', 'User-Agent' => 'miniPORTAL Minecraft Accounts'],
            ['identityToken' => 'XBL3.0 x=' . $userHash . ';' . $xstsToken],
            true,
        ), 'Minecraft Services odrzucił uwierzytelnienie konta.');
        $minecraftToken = $this->requiredString($minecraft, 'access_token', 'Minecraft Services nie zwrócił tokenu profilu.');

        $profileResponse = $this->http->request(
            'GET',
            self::MINECRAFT_PROFILE_URL,
            [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $minecraftToken,
                'User-Agent' => 'miniPORTAL Minecraft Accounts',
            ],
        );
        if ($profileResponse->status === 404) {
            throw new MinecraftAccountException('To konto Microsoft nie posiada profilu Minecraft: Java Edition.');
        }
        $profile = $this->decode($profileResponse, 'Nie udało się pobrać profilu Minecraft: Java Edition.');
        $uuid = strtolower($this->requiredString($profile, 'id', 'Profil Minecraft nie zawiera UUID.'));
        $username = $this->requiredString($profile, 'name', 'Profil Minecraft nie zawiera nazwy gracza.');
        if (preg_match('/^[0-9a-f]{32}$/D', $uuid) !== 1 || preg_match('/^[A-Za-z0-9_]{3,16}$/D', $username) !== 1) {
            throw new MinecraftAccountException('Minecraft Services zwrócił nieprawidłowy profil gracza.');
        }

        return new MinecraftProfile($this->formatUuid($uuid), $username);
    }

    /** @return array<string, mixed> */
    private function decode(HttpResponse $response, string $message): array
    {
        if ($response->status < 200 || $response->status >= 300) {
            if ($this->isInvalidAppRegistration($response)) {
                throw new MinecraftAppRegistrationException(
                    'Minecraft Services nie zatwierdził jeszcze identyfikatora aplikacji Microsoft.'
                );
            }
            throw new MinecraftAccountException($message . $this->diagnosticSuffix($response));
        }
        try {
            return $response->json();
        } catch (JsonException) {
            throw new MinecraftAccountException($message);
        }
    }

    private function diagnosticSuffix(HttpResponse $response): string
    {
        $parts = ['HTTP ' . $response->status];
        try {
            $payload = $response->json();
        } catch (JsonException) {
            $payload = [];
        }
        $xerr = $payload['XErr'] ?? null;
        if (is_int($xerr) || (is_string($xerr) && preg_match('/^\d{1,20}$/D', $xerr) === 1)) {
            $parts[] = 'XErr ' . $xerr;
        }
        $error = $payload['error'] ?? null;
        if (is_string($error) && preg_match('/^[A-Za-z0-9_.-]{1,80}$/D', $error) === 1) {
            $parts[] = 'error ' . $error;
        }
        return ' (' . implode(', ', $parts) . ').';
    }

    private function isInvalidAppRegistration(HttpResponse $response): bool
    {
        if ($response->status !== 403) {
            return false;
        }
        try {
            $message = $response->json()['errorMessage'] ?? null;
        } catch (JsonException) {
            return false;
        }
        return is_string($message)
            && str_starts_with(strtolower(trim($message)), 'invalid app registration');
    }

    /** @param array<string, mixed> $payload */
    private function requiredString(array $payload, string $key, string $message): string
    {
        $value = $payload[$key] ?? null;
        if (!is_string($value) || trim($value) === '') {
            throw new MinecraftAccountException($message);
        }
        return $value;
    }

    private function assertConfigured(): void
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Połączenie kont Minecraft przez Microsoft nie jest skonfigurowane.');
        }
    }

    private function formatUuid(string $hex): string
    {
        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4)
            . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
    }
}
