<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\CoreAuth;

use RuntimeException;

final class OAuthStateStore
{
    private const SESSION_KEY = '_miniportal_oauth_states';
    private const MAX_AGE = 600;

    /**
     * @return array{state: string, verifier: string, challenge: string, nonce: string}
     */
    public function issue(string $provider, string $purpose = 'login', ?int $userId = null): array
    {
        $this->assertSession();
        $this->prune();

        $state = $this->base64Url(random_bytes(32));
        $verifier = $this->base64Url(random_bytes(64));
        $challenge = $this->base64Url(hash('sha256', $verifier, true));
        $nonce = $this->base64Url(random_bytes(32));

        $_SESSION[self::SESSION_KEY][$state] = [
            'provider' => $provider,
            'verifier' => $verifier,
            'nonce' => $nonce,
            'purpose' => $purpose,
            'user_id' => $userId,
            'created_at' => time(),
        ];

        return [
            'state' => $state,
            'verifier' => $verifier,
            'challenge' => $challenge,
            'nonce' => $nonce,
        ];
    }

    public function consume(string $provider, string $state): ?OAuthStateContext
    {
        $this->assertSession();
        $this->prune();
        $entry = $_SESSION[self::SESSION_KEY][$state] ?? null;
        unset($_SESSION[self::SESSION_KEY][$state]);

        if (!is_array($entry) || !hash_equals((string) ($entry['provider'] ?? ''), $provider)) {
            return null;
        }

        $verifier = $entry['verifier'] ?? null;
        $nonce = $entry['nonce'] ?? null;
        $purpose = $entry['purpose'] ?? null;
        $userId = $entry['user_id'] ?? null;

        if (!is_string($verifier) || $verifier === '' || !is_string($nonce) || !is_string($purpose)) {
            return null;
        }

        return new OAuthStateContext(
            $verifier,
            $nonce,
            $purpose,
            is_int($userId) ? $userId : null
        );
    }

    private function prune(): void
    {
        $states = $_SESSION[self::SESSION_KEY] ?? [];
        $minimumTime = time() - self::MAX_AGE;

        if (!is_array($states)) {
            $_SESSION[self::SESSION_KEY] = [];
            return;
        }

        $_SESSION[self::SESSION_KEY] = array_filter(
            $states,
            static fn (mixed $entry): bool => is_array($entry)
                && (int) ($entry['created_at'] ?? 0) >= $minimumTime
        );
    }

    private function assertSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new RuntimeException('Magazyn OAuth wymaga aktywnej sesji.');
        }
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
