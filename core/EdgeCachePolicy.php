<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

final class EdgeCachePolicy
{
    /** @var list<string> */
    private const PUBLIC_PATHS = [
        '/',
        '/pages',
        '/articles',
        '/projects',
        '/builds',
        '/team',
        '/wiki',
        '/uptime',
        '/hosting',
    ];

    /** @var list<string> */
    private const PUBLIC_PREFIXES = [
        '/p/',
        '/article/',
        '/projects/',
        '/builds/',
        '/team/member/',
        '/wiki/',
    ];

    /** @var list<string> */
    private const PRIVATE_PREFIXES = [
        '/admin',
        '/api',
        '/metrics',
        '/account',
        '/econizer',
        '/translations',
        '/security-demo',
        '/hosting/configure',
    ];

    public function __construct(
        private readonly bool $enabled,
        private readonly string $sessionName,
        private readonly int $edgeTtl = 60,
        private readonly int $staleWhileRevalidate = 300,
        private readonly int $staleIfError = 86400,
    ) {
    }

    public function register(Request $request): void
    {
        if (!$this->enabled || headers_sent()) {
            return;
        }

        header_register_callback(function () use ($request): void {
            $decision = $this->evaluate(
                $request,
                http_response_code(),
                headers_list(),
                session_status() === PHP_SESSION_ACTIVE,
            );

            if ($decision === 'public') {
                header('Cache-Control: public, max-age=0, must-revalidate', true);
                header(sprintf(
                    'Cloudflare-CDN-Cache-Control: public, max-age=%d, stale-while-revalidate=%d, stale-if-error=%d',
                    max(1, $this->edgeTtl),
                    max(0, $this->staleWhileRevalidate),
                    max(0, $this->staleIfError),
                ), true);
                header('X-miniPORTAL-Cache-Policy: public-anonymous', true);
                return;
            }

            if ($decision === 'private') {
                header('Cache-Control: private, no-store', true);
                header('Cloudflare-CDN-Cache-Control: no-store', true);
                header('X-miniPORTAL-Cache-Policy: private', true);
            }
        });
    }

    /**
     * @param list<string> $responseHeaders
     * @return 'public'|'private'|'unchanged'
     */
    public function evaluate(
        Request $request,
        int|false $status,
        array $responseHeaders,
        bool $sessionActive,
    ): string {
        if (!$this->enabled) {
            return 'unchanged';
        }

        $path = $request->path();
        $publicPath = $this->isPublicPath($path);
        $privatePath = $this->isPrivatePath($path);
        if (!$publicPath && !$privatePath) {
            return 'unchanged';
        }
        if (!$this->isHtmlResponse($responseHeaders)) {
            return 'unchanged';
        }

        $hasSessionCookie = $this->requestHasSessionCookie($request)
            || $this->responseSetsSessionCookie($responseHeaders);
        if (
            $privatePath
            || $sessionActive
            || $hasSessionCookie
            || !in_array($request->method(), ['GET', 'HEAD'], true)
            || $request->header('Authorization') !== ''
        ) {
            return 'private';
        }

        $responseStatus = $status === false ? 200 : $status;
        if (
            $responseStatus !== 200
            || $request->hasQueryParameters(['route'])
            || $this->responseForbidsStorage($responseHeaders)
        ) {
            return 'private';
        }

        return 'public';
    }

    public static function cloudflarePublicPathExpression(): string
    {
        $exact = implode(' ', array_map(
            static fn (string $path): string => '"' . $path . '"',
            self::PUBLIC_PATHS,
        ));
        $prefixes = array_map(
            static fn (string $prefix): string => 'starts_with(http.request.uri.path, "' . $prefix . '")',
            self::PUBLIC_PREFIXES,
        );

        return '(http.request.uri.path in {' . $exact . '} or ' . implode(' or ', $prefixes) . ')';
    }

    /** @return list<string> */
    public static function cloudflarePrivatePrefixes(): array
    {
        return self::PRIVATE_PREFIXES;
    }

    private function isPublicPath(string $path): bool
    {
        if (in_array($path, self::PUBLIC_PATHS, true)) {
            return true;
        }
        foreach (self::PUBLIC_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function isPrivatePath(string $path): bool
    {
        foreach (self::PRIVATE_PREFIXES as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return true;
            }
        }

        return false;
    }

    /** @param list<string> $headers */
    private function isHtmlResponse(array $headers): bool
    {
        $contentType = $this->headerValue($headers, 'content-type');

        return $contentType === '' || str_starts_with(strtolower($contentType), 'text/html');
    }

    private function requestHasSessionCookie(Request $request): bool
    {
        if ($this->sessionName === '') {
            return false;
        }
        foreach (explode(';', $request->header('Cookie')) as $cookie) {
            [$name] = array_pad(explode('=', trim($cookie), 2), 2, '');
            if (in_array(trim($name), [$this->sessionName, 'REMEMBER_' . $this->sessionName], true)) {
                return true;
            }
        }

        return false;
    }

    /** @param list<string> $headers */
    private function responseSetsSessionCookie(array $headers): bool
    {
        if ($this->sessionName === '') {
            return false;
        }
        foreach ($headers as $header) {
            if (stripos($header, 'Set-Cookie:') !== 0) {
                continue;
            }
            $cookie = trim(substr($header, strlen('Set-Cookie:')));
            if (str_starts_with($cookie, $this->sessionName . '=')
                || str_starts_with($cookie, 'REMEMBER_' . $this->sessionName . '=')) {
                return true;
            }
        }

        return false;
    }

    /** @param list<string> $headers */
    private function responseForbidsStorage(array $headers): bool
    {
        $cacheControl = strtolower($this->headerValue($headers, 'cache-control'));

        return str_contains($cacheControl, 'no-store') || str_contains($cacheControl, 'private');
    }

    /** @param list<string> $headers */
    private function headerValue(array $headers, string $name): string
    {
        $prefix = strtolower($name) . ':';
        foreach (array_reverse($headers) as $header) {
            if (str_starts_with(strtolower($header), $prefix)) {
                return trim(substr($header, strlen($prefix)));
            }
        }

        return '';
    }
}
