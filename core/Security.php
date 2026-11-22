<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

use RuntimeException;

final class Security
{
    private const CSRF_SESSION_KEY = '_miniportal_csrf';

    private bool $secureRequest = false;

    public function __construct(
        private readonly array $config = [],
    ) {
    }

    public function boot(Request $request): void
    {
        $this->secureRequest = $request->isSecure();
        $this->sendHeaders($this->secureRequest, $request->path());
        if ($this->shouldStartSession($request)) {
            $this->startSession($this->secureRequest);
        }
    }

    public function csrfToken(): string
    {
        $this->ensureSessionStarted();

        $token = $_SESSION[self::CSRF_SESSION_KEY] ?? null;

        if (!is_string($token) || strlen($token) < 64) {
            $token = bin2hex(random_bytes(32));
            $_SESSION[self::CSRF_SESSION_KEY] = $token;
        }

        return $token;
    }

    public function validateCsrfToken(string $token): bool
    {
        $this->ensureSessionStarted();
        $storedToken = $_SESSION[self::CSRF_SESSION_KEY] ?? '';

        return $token !== ''
            && is_string($storedToken)
            && hash_equals($storedToken, $token);
    }

    public function regenerateSession(): void
    {
        $this->ensureSessionStarted();
        if (headers_sent()) {
            return;
        }
        session_regenerate_id(true);
    }

    public function rememberCookieName(): string
    {
        return 'REMEMBER_' . (string) ($this->config['name'] ?? 'MINIPORTALSESSID');
    }

    public function rememberCookie(): string
    {
        $value = $_COOKIE[$this->rememberCookieName()] ?? '';
        return is_string($value) ? $value : '';
    }

    public function canRememberLogin(): bool
    {
        return $this->secureRequest && !headers_sent();
    }

    public function setRememberCookie(string $value, int $expires): void
    {
        if (!$this->canRememberLogin()) {
            return;
        }
        setcookie($this->rememberCookieName(), $value, [
            'expires' => $expires,
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        if ($value === '') {
            unset($_COOKIE[$this->rememberCookieName()]);
        } else {
            $_COOKIE[$this->rememberCookieName()] = $value;
        }
        header('Cache-Control: private, no-store');
        header('Cloudflare-CDN-Cache-Control: no-store');
    }

    public function closeSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
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
            : "style-src 'self' https://cdn.jsdelivr.net; style-src-attr 'unsafe-inline'; ";
        $scriptSrc = $isRemoteTerminalFrame
            ? "script-src 'self' 'unsafe-inline'; "
            : "script-src 'self' https://cdn.jsdelivr.net https://cdn.amcharts.com https://pagead2.googlesyndication.com "
                . "https://googleads.g.doubleclick.net; ";
        $isArticle = $path === '/article' || str_starts_with($path, '/article/');
        $frameSrc = ($isRemoteTerminal || $isArticle)
            ? "frame-src 'self' https://googleads.g.doubleclick.net https://tpc.googlesyndication.com; "
            : "frame-src https://googleads.g.doubleclick.net https://tpc.googlesyndication.com; ";
        $frameAncestors = $isRemoteTerminalFrame ? "frame-ancestors 'self'; " : "frame-ancestors 'none'; ";
        // Browsers enforce form-action across OAuth redirects after a POST.
        // Permit only our fixed provider origins, and only in the login flow.
        $isOAuthLogin = $path === '/admin/login' || str_starts_with($path, '/admin/auth/');
        $formAction = $isOAuthLogin
            ? "form-action 'self' https://github.com https://discord.com https://accounts.google.com "
                . "https://login.microsoftonline.com https://login.live.com; "
            : "form-action 'self'; ";
        if ($path === '/itemshop/checkout') {
            $formAction = "form-action 'self' https://manager.game-host.pl; ";
        }

        header(
            "Content-Security-Policy: default-src 'self'; "
            . $styleSrc
            . $scriptSrc
            . "img-src 'self' data: https:; font-src 'self' data:; "
            . "connect-src 'self' https://metrics.faststats.dev https://flags.faststats.dev "
            . "https://pagead2.googlesyndication.com https://googleads.g.doubleclick.net"
            . ($isArticle ? " https://api.kotlinlang.org" : "") . "; "
            . $frameSrc
            . $formAction . $frameAncestors . "base-uri 'self'"
        );
    }

    /** Restricted policy only for the opaque-origin Python runner frame. */
    public function sendCodeRunnerHeaders(string $runtimeBase, string $nonce): void
    {
        if (preg_match('~\Ahttps?://[a-zA-Z0-9.\[\]:-]+/templates/shared/assets/vendor/pyodide/0\.27\.7/\z~D', $runtimeBase) !== 1
            || preg_match('/^[a-f0-9]{32}$/D', $nonce) !== 1) {
            throw new RuntimeException('Nieprawidłowa konfiguracja środowiska Python.');
        }
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: no-referrer');
        header("Content-Security-Policy: default-src 'none'; script-src 'nonce-" . $nonce
            . "' 'wasm-unsafe-eval' " . $runtimeBase . "; connect-src " . $runtimeBase
            . "; worker-src blob:; frame-ancestors 'self'; form-action 'none'; base-uri 'none'");
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

    private function ensureSessionStarted(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            $this->startSession($this->secureRequest);
        }
    }

    private function shouldStartSession(Request $request): bool
    {
        if ($request->path() === '/articles/code-runner') {
            return false;
        }
        $sessionName = (string) ($this->config['name'] ?? 'MINIPORTALSESSID');
        $hasCookie = isset($_COOKIE[$sessionName])
            && is_string($_COOKIE[$sessionName])
            && $_COOKIE[$sessionName] !== '';
        if ($hasCookie || $this->rememberCookie() !== '') {
            return true;
        }

        $path = $request->path();
        if (
            str_starts_with($path, '/api/')
            || str_starts_with($path, '/metrics/')
            || str_starts_with($path, '/internal/authgatewayx/')
        ) {
            return false;
        }
        if (!in_array($request->method(), ['GET', 'HEAD'], true)) {
            return true;
        }

        $anonymousReadPaths = [
            '/', '/page', '/pages', '/article', '/articles', '/projects', '/builds', '/team', '/wiki',
            '/uptime', '/hosting', '/hosting/configure', '/robots.txt', '/sitemap.xml', '/ads.txt',
        ];
        if (in_array($path, $anonymousReadPaths, true)) {
            return false;
        }
        foreach (['/p/', '/article/', '/projects/', '/builds/', '/team/member/', '/wiki/'] as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return false;
            }
        }

        // Trasy prywatne, logowanie oraz publiczne formularze potrzebują sesji
        // jeszcze przed rozpoczęciem renderowania i wygenerowaniem tokenu CSRF.
        return true;
    }
}
