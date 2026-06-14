<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

use RuntimeException;

final class Security
{
    private const CSRF_SESSION_KEY = '_miniportal_csrf';

    public function __construct(
        private readonly array $config = [],
    ) {
    }

    public function boot(Request $request): void
    {
        $this->sendHeaders($request->isSecure());
        $this->startSession($request->isSecure());
    }

    public function csrfToken(): string
    {
        $this->assertSessionStarted();

        $token = $_SESSION[self::CSRF_SESSION_KEY] ?? null;

        if (!is_string($token) || strlen($token) < 64) {
            $token = bin2hex(random_bytes(32));
            $_SESSION[self::CSRF_SESSION_KEY] = $token;
        }

        return $token;
    }

    public function validateCsrfToken(string $token): bool
    {
        $this->assertSessionStarted();
        $storedToken = $_SESSION[self::CSRF_SESSION_KEY] ?? '';

        return $token !== ''
            && is_string($storedToken)
            && hash_equals($storedToken, $token);
    }

    public function regenerateSession(): void
    {
        $this->assertSessionStarted();
        session_regenerate_id(true);
    }

    private function sendHeaders(bool $secure): void
    {
        if (headers_sent()) {
            throw new RuntimeException('Nagłówki bezpieczeństwa muszą zostać wysłane przed treścią odpowiedzi.');
        }

        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

        if ($secure) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }

        header(
            "Content-Security-Policy: default-src 'self'; "
            . "style-src 'self' https://cdn.jsdelivr.net; "
            . "script-src 'self' https://cdn.jsdelivr.net; "
            . "img-src 'self' data: https:; font-src 'self' data:; "
            . "connect-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'"
        );
    }

    private function startSession(bool $secure): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $sessionName = (string) ($this->config['name'] ?? 'MINIPORTALSESSID');
        $sameSite = (string) ($this->config['same_site'] ?? 'Lax');

        if (preg_match('/^[A-Za-z0-9_-]{1,64}$/', $sessionName) !== 1) {
            throw new RuntimeException('Nazwa sesji zawiera niedozwolone znaki.');
        }

        if (!in_array($sameSite, ['Lax', 'Strict', 'None'], true)) {
            throw new RuntimeException('Nieprawidłowa wartość SameSite dla sesji.');
        }

        if ($sameSite === 'None' && !$secure) {
            throw new RuntimeException('SameSite=None wymaga połączenia HTTPS.');
        }

        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.cookie_httponly', '1');
        session_name($sessionName);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => $sameSite,
        ]);

        if (!session_start()) {
            throw new RuntimeException('Nie można uruchomić bezpiecznej sesji.');
        }
    }

    private function assertSessionStarted(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new RuntimeException('Sesja nie została uruchomiona przez Security.');
        }
    }
}
