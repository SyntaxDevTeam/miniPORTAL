<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

final class Request
{
    private function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly array $query,
        private readonly array $post,
        private readonly array $server,
        private readonly array $files,
        private readonly string $rawBody,
        private readonly array $trustedProxies = [],
        private readonly array $routeParameters = [],
    ) {
    }

    /** @param list<string> $trustedProxies */
    public static function fromGlobals(int $maxRawBodyBytes = 1048576, array $trustedProxies = []): self
    {
        self::assertAllowedContentLength($_SERVER, $maxRawBodyBytes);
        $body = file_get_contents('php://input');

        return self::fromArrays($_GET, $_POST, $_SERVER, $_FILES, $body === false ? '' : $body, $trustedProxies, $maxRawBodyBytes);
    }

    /** @param list<string> $trustedProxies */
    public static function fromArrays(
        array $query,
        array $post,
        array $server,
        array $files = [],
        string $rawBody = '',
        array $trustedProxies = [],
        int $maxRawBodyBytes = 1048576,
    ): self
    {
        self::assertAllowedContentLength($server, $maxRawBodyBytes);
        if ($maxRawBodyBytes > 0 && strlen($rawBody) > $maxRawBodyBytes && !self::isMultipartRequest($server)) {
            throw new PayloadTooLargeException(strlen($rawBody), $maxRawBodyBytes);
        }

        $method = strtoupper((string) ($server['REQUEST_METHOD'] ?? 'GET'));
        $path = self::resolvePath($query, $server);

        return new self(
            preg_match('/^[A-Z]+$/', $method) === 1 ? $method : 'GET',
            $path,
            self::normalizeArray($query),
            self::normalizeArray($post),
            self::normalizeArray($server),
            self::normalizeArray($files),
            $rawBody,
            array_values(array_filter(array_map(
                static fn (mixed $proxy): string => trim((string) $proxy),
                $trustedProxies
            ), static fn (string $proxy): bool => $proxy !== '')),
            [],
        );
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    /** @param array<string, string> $parameters */
    public function withRouteParameters(array $parameters): self
    {
        return new self(
            $this->method,
            $this->path,
            $this->query,
            $this->post,
            $this->server,
            $this->files,
            $this->rawBody,
            $this->trustedProxies,
            $parameters,
        );
    }

    public function routeString(string $key, string $default = ''): string
    {
        $value = $this->routeParameters[$key] ?? null;

        return is_string($value) ? $value : $default;
    }

    public function queryString(string $key, string $default = ''): string
    {
        return $this->stringValue($this->query, $key, $default);
    }

    public function queryInt(string $key, ?int $default = null): ?int
    {
        $value = $this->query[$key] ?? null;

        if (!is_scalar($value)) {
            return $default;
        }

        $filtered = filter_var($value, FILTER_VALIDATE_INT);

        return $filtered === false ? $default : $filtered;
    }

    public function postString(string $key, string $default = ''): string
    {
        return $this->stringValue($this->post, $key, $default);
    }

    public function postBool(string $key): bool
    {
        return filter_var($this->post[$key] ?? false, FILTER_VALIDATE_BOOL);
    }

    public function postInt(string $key, ?int $default = null): ?int
    {
        $value = $this->post[$key] ?? null;

        if (!is_scalar($value)) {
            return $default;
        }

        $filtered = filter_var($value, FILTER_VALIDATE_INT);

        return $filtered === false ? $default : $filtered;
    }

    /**
     * @return list<string>
     */
    public function postStringList(string $key): array
    {
        $value = $this->post[$key] ?? [];
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map(
                static fn (mixed $item): string => is_scalar($item) ? (string) $item : '',
                $value
            ),
            static fn (string $item): bool => $item !== ''
        )));
    }

    /**
     * @return array<string, mixed>
     */
    public function postArray(string $key): array
    {
        $value = $this->post[$key] ?? [];

        return is_array($value) ? $value : [];
    }

    public function isSecure(): bool
    {
        $https = strtolower($this->stringValue($this->server, 'HTTPS'));
        if ($https === 'on' || $https === '1' || $this->stringValue($this->server, 'SERVER_PORT') === '443') {
            return true;
        }

        if ($this->isFromTrustedProxy()) {
            $proto = strtolower(trim(explode(',', $this->header('X-Forwarded-Proto'))[0] ?? ''));

            return $proto === 'https';
        }

        return false;
    }

    public function clientIp(): string
    {
        if ($this->isFromTrustedProxy()) {
            foreach (explode(',', $this->header('X-Forwarded-For')) as $candidate) {
                $candidate = trim($candidate);
                if (filter_var($candidate, FILTER_VALIDATE_IP) !== false) {
                    return $candidate;
                }
            }
        }

        return $this->stringValue($this->server, 'REMOTE_ADDR');
    }

    public function userAgent(): string
    {
        return substr($this->stringValue($this->server, 'HTTP_USER_AGENT'), 0, 512);
    }

    public function header(string $name, string $default = ''): string
    {
        $normalized = strtolower($name);
        $keys = ['HTTP_' . strtoupper(str_replace('-', '_', $name))];
        if ($normalized === 'content-type') {
            $keys = ['CONTENT_TYPE', ...$keys];
        }
        if ($normalized === 'content-length') {
            $keys = ['CONTENT_LENGTH', ...$keys];
        }
        if ($normalized === 'authorization') {
            $keys[] = 'REDIRECT_HTTP_AUTHORIZATION';
        }

        foreach ($keys as $key) {
            $value = $this->stringValue($this->server, $key);
            if ($value !== '') {
                return $value;
            }
        }

        return $default;
    }

    public function body(): string
    {
        return $this->rawBody;
    }

    /** @return array<string, mixed>|null */
    public function json(): ?array
    {
        if ($this->rawBody === '' || strlen($this->rawBody) > 1048576) {
            return null;
        }

        try {
            $decoded = json_decode($this->rawBody, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($decoded) && !array_is_list($decoded) ? $decoded : null;
    }

    /**
     * @return array{name: string, type: string, tmp_name: string, error: int, size: int}|null
     */
    public function file(string $key): ?array
    {
        $file = $this->files[$key] ?? null;
        if (!is_array($file)) {
            return null;
        }

        $name = $file['name'] ?? '';
        $tmpName = $file['tmp_name'] ?? '';
        $type = $file['type'] ?? '';
        $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        $size = $file['size'] ?? 0;
        if (!is_scalar($name) || !is_scalar($tmpName) || !is_scalar($type) || !is_scalar($error) || !is_scalar($size)) {
            return null;
        }

        return [
            'name' => basename(self::normalizeString((string) $name)),
            'type' => substr(self::normalizeString((string) $type), 0, 120),
            'tmp_name' => self::normalizeString((string) $tmpName),
            'error' => (int) $error,
            'size' => (int) $size,
        ];
    }

    private static function resolvePath(array $query, array $server): string
    {
        $route = $query['route'] ?? null;

        if (is_scalar($route) && trim((string) $route) !== '') {
            return self::normalizePath((string) $route);
        }

        $uri = (string) ($server['REQUEST_URI'] ?? '/');
        $path = (string) (parse_url($uri, PHP_URL_PATH) ?? '/');

        if ($path === '/index.php' || str_ends_with($path, '/index.php')) {
            return '/';
        }

        return self::normalizePath($path);
    }

    private static function normalizePath(string $path): string
    {
        $path = '/' . ltrim(trim($path), '/');
        $path = preg_replace('#/+#', '/', $path) ?? '/';

        return $path !== '/' ? rtrim($path, '/') : '/';
    }

    private static function assertAllowedContentLength(array $server, int $maxRawBodyBytes): void
    {
        if ($maxRawBodyBytes <= 0 || self::isMultipartRequest($server)) {
            return;
        }
        $contentLength = $server['CONTENT_LENGTH'] ?? null;
        if (!is_scalar($contentLength)) {
            return;
        }
        $length = filter_var($contentLength, FILTER_VALIDATE_INT);
        if ($length !== false && $length > $maxRawBodyBytes) {
            throw new PayloadTooLargeException((int) $length, $maxRawBodyBytes);
        }
    }

    private static function isMultipartRequest(array $server): bool
    {
        $contentType = strtolower((string) ($server['CONTENT_TYPE'] ?? $server['HTTP_CONTENT_TYPE'] ?? ''));

        return str_starts_with($contentType, 'multipart/form-data');
    }

    private static function normalizeArray(array $values): array
    {
        $normalized = [];

        foreach ($values as $key => $value) {
            $normalizedKey = is_int($key) ? $key : self::normalizeString((string) $key);

            if (is_array($value)) {
                $normalized[$normalizedKey] = self::normalizeArray($value);
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $normalized[$normalizedKey] = is_string($value)
                    ? self::normalizeString($value)
                    : $value;
            }
        }

        return $normalized;
    }

    private static function normalizeString(string $value): string
    {
        return trim(str_replace("\0", '', $value));
    }

    private function stringValue(array $source, string $key, string $default = ''): string
    {
        $value = $source[$key] ?? $default;

        return is_scalar($value) ? (string) $value : $default;
    }

    private function isFromTrustedProxy(): bool
    {
        $remoteAddress = $this->stringValue($this->server, 'REMOTE_ADDR');
        if ($remoteAddress === '') {
            return false;
        }
        foreach ($this->trustedProxies as $proxy) {
            if ($this->ipMatches($remoteAddress, $proxy)) {
                return true;
            }
        }

        return false;
    }

    private function ipMatches(string $address, string $proxy): bool
    {
        if ($proxy === '*' || hash_equals($proxy, $address)) {
            return true;
        }
        if (!str_contains($proxy, '/')) {
            return false;
        }
        [$subnet, $bits] = explode('/', $proxy, 2);
        $bits = filter_var($bits, FILTER_VALIDATE_INT);
        $addressPacked = @inet_pton($address);
        $subnetPacked = @inet_pton($subnet);
        if ($bits === false || $addressPacked === false || $subnetPacked === false || strlen($addressPacked) !== strlen($subnetPacked)) {
            return false;
        }
        $maxBits = strlen($addressPacked) * 8;
        if ($bits < 0 || $bits > $maxBits) {
            return false;
        }
        $fullBytes = intdiv($bits, 8);
        $remainingBits = $bits % 8;
        if ($fullBytes > 0 && substr($addressPacked, 0, $fullBytes) !== substr($subnetPacked, 0, $fullBytes)) {
            return false;
        }
        if ($remainingBits === 0) {
            return true;
        }
        $mask = (0xFF << (8 - $remainingBits)) & 0xFF;

        return (ord($addressPacked[$fullBytes]) & $mask) === (ord($subnetPacked[$fullBytes]) & $mask);
    }
}
