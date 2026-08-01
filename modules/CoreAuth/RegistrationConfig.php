<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\CoreAuth;

final class RegistrationConfig
{
    public const SELF_SERVICE = 'self_service';
    public const APPROVAL = 'approval';

    public function __construct(
        public readonly string $mode,
        public readonly string $termsVersion,
        public readonly string $termsUrl,
        public readonly string $privacyUrl,
        private readonly string $hashKey = '',
    ) {
    }

    /** @param array<string, mixed> $config */
    public static function fromArray(array $config): self
    {
        $mode = strtolower(trim((string) ($config['registration_mode'] ?? self::SELF_SERVICE)));
        if (!in_array($mode, [self::SELF_SERVICE, self::APPROVAL], true)) {
            $mode = self::SELF_SERVICE;
        }

        return new self(
            $mode,
            self::bounded((string) ($config['terms_version'] ?? '1'), 64, '1'),
            self::safeUrl(
                (string) ($config['terms_url'] ?? '/p/miniportal-warunki-uzytkowania'),
                '/p/miniportal-warunki-uzytkowania'
            ),
            self::safeUrl(
                (string) ($config['privacy_url'] ?? '/p/miniportal-prywatnosc'),
                '/p/miniportal-prywatnosc'
            ),
            (string) ($config['audit_hash_key'] ?? ''),
        );
    }

    public function isSelfService(): bool
    {
        return $this->mode === self::SELF_SERVICE;
    }

    public function ipHash(string $ip): ?string
    {
        return $ip !== '' && $this->hashKey !== ''
            ? hash_hmac('sha256', $ip, $this->hashKey)
            : null;
    }

    private static function safeUrl(string $url, string $fallback): string
    {
        $url = trim($url);
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            return substr($url, 0, 2048);
        }
        if (filter_var($url, FILTER_VALIDATE_URL) !== false && str_starts_with(strtolower($url), 'https://')) {
            return substr($url, 0, 2048);
        }

        return $fallback;
    }

    private static function bounded(string $value, int $limit, string $fallback): string
    {
        $value = trim($value);
        return $value !== '' ? substr($value, 0, $limit) : $fallback;
    }
}
