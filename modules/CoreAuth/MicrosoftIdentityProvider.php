<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\CoreAuth;

use JsonException;
use RuntimeException;

final class MicrosoftIdentityProvider implements IdentityProviderInterface
{
    private const AUTHORIZE_URL = 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize';
    private const TOKEN_URL = 'https://login.microsoftonline.com/common/oauth2/v2.0/token';
    private const USER_URL = 'https://graph.microsoft.com/v1.0/me?$select=id,displayName,mail,userPrincipalName';

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $callbackUrl,
    ) {
    }

    public function name(): string
    {
        return 'microsoft';
    }

    public function label(): string
    {
        return 'Microsoft';
    }

    public function isConfigured(): bool
    {
        return $this->clientId !== ''
            && $this->clientSecret !== ''
            && filter_var($this->callbackUrl, FILTER_VALIDATE_URL) !== false;
    }

    public function authorizationUrl(string $state, string $codeChallenge, string $_nonce): string
    {
        $this->assertConfigured();

        return self::AUTHORIZE_URL . '?' . http_build_query([
            'client_id' => $this->clientId,
            'response_type' => 'code',
            'redirect_uri' => $this->callbackUrl,
            'response_mode' => 'query',
            'scope' => 'openid profile email User.Read',
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function resolveIdentity(string $code, string $codeVerifier, string $_nonce): ExternalIdentity
    {
        $this->assertConfigured();
        if ($code === '' || $codeVerifier === '') {
            throw new RuntimeException('Microsoft nie zwrócił kompletnego kodu autoryzacyjnego.');
        }

        $token = $this->decode($this->http->request('POST', self::TOKEN_URL, [
            'Accept' => 'application/json',
            'User-Agent' => 'miniPORTAL',
        ], [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code' => $code,
            'code_verifier' => $codeVerifier,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->callbackUrl,
        ]), 'Microsoft odrzucił wymianę kodu.');
        $accessToken = $token['access_token'] ?? null;
        if (!is_string($accessToken) || $accessToken === '') {
            throw new RuntimeException('Microsoft nie zwrócił tokenu dostępu.');
        }

        $profile = $this->decode($this->http->request('GET', self::USER_URL, [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $accessToken,
            'User-Agent' => 'miniPORTAL',
        ]), 'Nie można pobrać profilu Microsoft.');
        $subject = $profile['id'] ?? null;
        $displayName = $profile['displayName'] ?? null;
        $email = $profile['mail'] ?? $profile['userPrincipalName'] ?? null;
        if (!is_string($subject) || $subject === '' || !is_string($displayName) || $displayName === '') {
            throw new RuntimeException('Profil Microsoft nie zawiera wymaganej tożsamości.');
        }

        return new ExternalIdentity(
            'microsoft',
            $subject,
            $displayName,
            is_string($email) && $email !== '' ? $email : null,
            is_string($email) && $email !== '',
            null
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
            throw new RuntimeException('Adapter Microsoft nie jest skonfigurowany.');
        }
    }
}
