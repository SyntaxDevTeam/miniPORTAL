<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\CoreAuth;

use JsonException;
use RuntimeException;

final class GoogleIdentityProvider implements IdentityProviderInterface
{
    private const AUTHORIZE_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const CERTS_URL = 'https://www.googleapis.com/oauth2/v1/certs';

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $callbackUrl,
    ) {
    }

    public function name(): string
    {
        return 'google';
    }

    public function label(): string
    {
        return 'Google';
    }

    public function isConfigured(): bool
    {
        return $this->clientId !== ''
            && $this->clientSecret !== ''
            && filter_var($this->callbackUrl, FILTER_VALIDATE_URL) !== false;
    }

    public function authorizationUrl(string $state, string $codeChallenge, string $nonce): string
    {
        $this->assertConfigured();

        return self::AUTHORIZE_URL . '?' . http_build_query([
            'response_type' => 'code',
            'client_id' => $this->clientId,
            'redirect_uri' => $this->callbackUrl,
            'scope' => 'openid email profile',
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function resolveIdentity(string $code, string $codeVerifier, string $nonce): ExternalIdentity
    {
        $this->assertConfigured();

        if ($code === '' || $codeVerifier === '' || $nonce === '') {
            throw new RuntimeException('Google nie zwrócił kompletnego kodu autoryzacyjnego.');
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
        ]), 'Google odrzucił wymianę kodu.');
        $idToken = $token['id_token'] ?? null;

        if (!is_string($idToken) || $idToken === '') {
            throw new RuntimeException('Google nie zwrócił ID tokenu.');
        }

        $claims = $this->verifyIdToken($idToken, $nonce);
        $subject = $claims['sub'] ?? null;
        $name = $claims['name'] ?? $claims['email'] ?? null;

        if (!is_string($subject) || $subject === '' || !is_string($name) || $name === '') {
            throw new RuntimeException('ID token Google nie zawiera wymaganej tożsamości.');
        }

        return new ExternalIdentity(
            'google',
            $subject,
            $name,
            is_string($claims['email'] ?? null) ? $claims['email'] : null,
            ($claims['email_verified'] ?? false) === true,
            is_string($claims['picture'] ?? null) ? $claims['picture'] : null
        );
    }

    private function verifyIdToken(string $token, string $nonce): array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            throw new RuntimeException('ID token Google ma nieprawidłowy format.');
        }

        [$headerPart, $claimsPart, $signaturePart] = $parts;
        $header = $this->decodeSegment($headerPart);
        $claims = $this->decodeSegment($claimsPart);
        $kid = $header['kid'] ?? null;

        if (($header['alg'] ?? null) !== 'RS256' || !is_string($kid)) {
            throw new RuntimeException('ID token Google używa niedozwolonego algorytmu.');
        }

        $certificates = $this->decode(
            $this->http->request('GET', self::CERTS_URL, [
                'Accept' => 'application/json',
                'User-Agent' => 'miniPORTAL',
            ]),
            'Nie można pobrać certyfikatów Google.'
        );
        $certificate = $certificates[$kid] ?? null;
        $signature = $this->base64UrlDecode($signaturePart);

        if (!is_string($certificate) || $signature === false
            || openssl_verify(
                $headerPart . '.' . $claimsPart,
                $signature,
                $certificate,
                OPENSSL_ALGO_SHA256
            ) !== 1) {
            throw new RuntimeException('Podpis ID tokenu Google jest nieprawidłowy.');
        }

        $issuer = $claims['iss'] ?? null;
        $audience = $claims['aud'] ?? null;
        $expiresAt = $claims['exp'] ?? null;
        $issuedAt = $claims['iat'] ?? null;

        if (!in_array($issuer, ['https://accounts.google.com', 'accounts.google.com'], true)
            || !$this->audienceContains($audience, $this->clientId)
            || (is_array($audience) && count($audience) > 1
                && !hash_equals($this->clientId, (string) ($claims['azp'] ?? '')))
            || !is_int($expiresAt) || $expiresAt < time()
            || !is_int($issuedAt) || $issuedAt > time() + 60
            || (isset($claims['nbf']) && (!is_int($claims['nbf']) || $claims['nbf'] > time() + 60))
            || !is_string($claims['nonce'] ?? null)
            || !hash_equals($nonce, $claims['nonce'])) {
            throw new RuntimeException('Claims ID tokenu Google nie przeszły walidacji.');
        }

        return $claims;
    }

    private function audienceContains(mixed $audience, string $clientId): bool
    {
        return is_string($audience)
            ? hash_equals($clientId, $audience)
            : is_array($audience) && in_array($clientId, $audience, true);
    }

    private function decodeSegment(string $segment): array
    {
        $decoded = $this->base64UrlDecode($segment);

        if ($decoded === false) {
            throw new RuntimeException('ID token Google zawiera nieprawidłowe kodowanie.');
        }

        try {
            $data = json_decode($decoded, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new RuntimeException('ID token Google zawiera nieprawidłowy JSON.');
        }

        return is_array($data) ? $data : [];
    }

    private function base64UrlDecode(string $value): string|false
    {
        $value = strtr($value, '-_', '+/');
        $value .= str_repeat('=', (4 - strlen($value) % 4) % 4);

        return base64_decode($value, true);
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
            throw new RuntimeException('Adapter Google nie jest skonfigurowany.');
        }
    }
}
