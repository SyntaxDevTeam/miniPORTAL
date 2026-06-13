<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\CoreAuth;

use Closure;
use RuntimeException;

final class OAuthAttemptLimiter
{
    private const SESSION_KEY = '_miniportal_oauth_attempts';

    public function __construct(
        private readonly int $windowSeconds = 600,
        private readonly int $startLimit = 10,
        private readonly int $callbackLimit = 20,
        private readonly ?Closure $clock = null,
    ) {
        if ($this->windowSeconds < 60 || $this->startLimit < 1 || $this->callbackLimit < 1) {
            throw new RuntimeException('Konfiguracja limitera OAuth jest nieprawidłowa.');
        }
    }

    public function allowStart(string $provider): bool
    {
        return $this->allow('start:' . $provider, $this->startLimit);
    }

    public function allowCallback(string $provider): bool
    {
        return $this->allow('callback:' . $provider, $this->callbackLimit);
    }

    public function retryAfter(): int
    {
        return $this->windowSeconds;
    }

    private function allow(string $bucket, int $limit): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new RuntimeException('Limiter OAuth wymaga aktywnej sesji.');
        }

        $now = $this->now();
        $minimumTime = $now - $this->windowSeconds;
        $attempts = $_SESSION[self::SESSION_KEY][$bucket] ?? [];
        $attempts = is_array($attempts)
            ? array_values(array_filter(
                $attempts,
                static fn (mixed $timestamp): bool => is_int($timestamp) && $timestamp >= $minimumTime
            ))
            : [];

        if (count($attempts) >= $limit) {
            $_SESSION[self::SESSION_KEY][$bucket] = $attempts;
            return false;
        }

        $attempts[] = $now;
        $_SESSION[self::SESSION_KEY][$bucket] = $attempts;
        return true;
    }

    private function now(): int
    {
        return $this->clock !== null ? (int) ($this->clock)() : time();
    }
}
