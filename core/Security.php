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
        $this->sendHeaders($request->isSecure(), $request->path());
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
        if (headers_sent()) {
            return;
        }
        session_regenerate_id(true);
    }

    private function sendHeaders(bool $secure, string $path): void
    {
        if (headers_sent()) {
            throw new RuntimeException('Nagłówki bezpieczeństwa muszą zostać wysłane przed treścią odpowiedzi.');
        }

        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

        if ($secure) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }

        $isRemoteTerminal = str_starts_with($path, '/admin/remote-terminal');
        $isRemoteTerminalFrame = str_starts_with($path, '/admin/remote-terminal/session/');
        header('X-Frame-Options: ' . ($isRemoteTerminalFrame ? 'SAMEORIGIN' : 'DENY'));

        $styleSrc = $isRemoteTerminalFrame
            ? "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; "
            : "style-src 'self' https://cdn.jsdelivr.net; ";
        $scriptSrc = $isRemoteTerminalFrame
            ? "script-src 'self' 'unsafe-inline'; "
            : "script-src 'self' https://cdn.jsdelivr.net https://cdn.amcharts.com https://pagead2.googlesyndication.com "
                . "https://googleads.g.doubleclick.net; ";
        $frameSrc = $isRemoteTerminal
            ? "frame-src 'self' https://googleads.g.doubleclick.net https://tpc.googlesyndication.com; "
            : "frame-src https://googleads.g.doubleclick.net https://tpc.googlesyndication.com; ";
        $frameAncestors = $isRemoteTerminalFrame ? "frame-ancestors 'self'; " : "frame-ancestors 'none'; ";

        header(
            "Content-Security-Policy: default-src 'self'; "
            . $styleSrc
            . $scriptSrc
            . "img-src 'self' data: https:; font-src 'self' data:; "
            . "connect-src 'self' https://metrics.faststats.dev https://flags.faststats.dev "
            . "https://pagead2.googlesyndication.com https://googleads.g.doubleclick.net; "
            . $frameSrc
            . "form-action 'self'; " . $frameAncestors . "base-uri 'self'"
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
