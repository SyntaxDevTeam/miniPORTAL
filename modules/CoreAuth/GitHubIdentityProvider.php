<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\CoreAuth;

use JsonException;
use RuntimeException;

final class GitHubIdentityProvider implements IdentityProviderInterface
{
    private const AUTHORIZE_URL = 'https://github.com/login/oauth/authorize';
    private const TOKEN_URL = 'https://github.com/login/oauth/access_token';
    private const USER_URL = 'https://api.github.com/user';
    private const EMAILS_URL = 'https://api.github.com/user/emails';

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $callbackUrl,
    ) {
    }

    public function name(): string
    {
        return 'github';
    }

    public function label(): string
    {
        return 'GitHub';
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
            'redirect_uri' => $this->callbackUrl,
            'scope' => 'read:user user:email',
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function resolveIdentity(string $code, string $codeVerifier, string $_nonce): ExternalIdentity
    {
        $this->assertConfigured();

        if ($code === '' || $codeVerifier === '') {
            throw new RuntimeException('GitHub nie zwrócił kompletnego kodu autoryzacyjnego.');
        }

        $tokenResponse = $this->http->request('POST', self::TOKEN_URL, [
            'Accept' => 'application/json',
            'User-Agent' => 'miniPORTAL',
        ], [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code' => $code,
            'redirect_uri' => $this->callbackUrl,
            'code_verifier' => $codeVerifier,
        ]);
        $tokenData = $this->decode($tokenResponse, 'GitHub odrzucił wymianę kodu.');
        $accessToken = $tokenData['access_token'] ?? null;

        if (!is_string($accessToken) || $accessToken === '') {
            throw new RuntimeException('GitHub nie zwrócił tokenu dostępu.');
        }

        $headers = [
            'Accept' => 'application/vnd.github+json',
            'Authorization' => 'Bearer ' . $accessToken,
            'X-GitHub-Api-Version' => '2022-11-28',
            'User-Agent' => 'miniPORTAL',
        ];
        $profile = $this->decode(
            $this->http->request('GET', self::USER_URL, $headers),
            'Nie można pobrać profilu GitHub.'
        );
        $subject = $profile['id'] ?? null;
        $login = $profile['login'] ?? null;

        if ((!is_int($subject) && !is_string($subject)) || !is_string($login) || $login === '') {
            throw new RuntimeException('Profil GitHub nie zawiera wymaganego identyfikatora.');
        }

        [$email, $verified] = $this->resolveEmail($profile, $headers);

        return new ExternalIdentity(
            'github',
            (string) $subject,
            $login,
            $email,
            $verified,
            is_string($profile['avatar_url'] ?? null) ? $profile['avatar_url'] : null
        );
    }

    /**
     * @return array{0: ?string, 1: bool}
     */
    private function resolveEmail(array $profile, array $headers): array
    {
        $publicEmail = $profile['email'] ?? null;

        if (is_string($publicEmail) && $publicEmail !== '') {
            return [$publicEmail, false];
        }

        $response = $this->http->request('GET', self::EMAILS_URL, $headers);

        if ($response->status < 200 || $response->status >= 300) {
            return [null, false];
        }

        try {
            $emails = $response->json();
        } catch (JsonException) {
            return [null, false];
        }

        foreach ($emails as $email) {
            if (is_array($email) && ($email['primary'] ?? false) === true && ($email['verified'] ?? false) === true) {
                $address = $email['email'] ?? null;

                return [is_string($address) ? $address : null, true];
            }
        }

        return [null, false];
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
            throw new RuntimeException('Adapter GitHub nie jest skonfigurowany.');
        }
    }
}
