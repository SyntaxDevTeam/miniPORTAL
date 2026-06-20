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
    ) {
    }

    public static function fromGlobals(): self
    {
        $body = file_get_contents('php://input');

        return self::fromArrays($_GET, $_POST, $_SERVER, $_FILES, $body === false ? '' : $body);
    }

    public static function fromArrays(array $query, array $post, array $server, array $files = [], string $rawBody = ''): self
    {
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

        return $https === 'on' || $https === '1' || $this->stringValue($this->server, 'SERVER_PORT') === '443';
    }

    public function clientIp(): string
    {
        return $this->stringValue($this->server, 'REMOTE_ADDR');
    }

    public function userAgent(): string
    {
        return substr($this->stringValue($this->server, 'HTTP_USER_AGENT'), 0, 512);
    }

    public function header(string $name, string $default = ''): string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        if (strtolower($name) === 'content-type') {
            $key = 'CONTENT_TYPE';
        }

        return $this->stringValue($this->server, $key, $default);
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
}
