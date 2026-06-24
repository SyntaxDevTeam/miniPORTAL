<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\Econizer;

use RuntimeException;

final readonly class EconizerConfig
{
    public function __construct(
        public string $apiToken,
        public string $discordBotToken,
        public string $discordClientId,
        public string $discordClientSecret,
        public string $discordCallbackUrl,
        public int $discordBotPermissions,
        public string $environmentFile,
        public bool $environmentReadable,
    ) {
    }

    public static function load(string $moduleDirectory): self
    {
        $explicitFile = getenv('ECONIZER_ENV_FILE');
        $moduleDirectory = rtrim($moduleDirectory, '/');
        $managedFile = dirname(dirname($moduleDirectory)) . '/config/modules/econizer.env';
        $legacyFile = $moduleDirectory . '/.env';
        $environmentFile = is_string($explicitFile) && trim($explicitFile) !== ''
            ? trim($explicitFile)
            : (is_readable($managedFile) ? $managedFile : $legacyFile);
        $environment = [];

        if (is_readable($environmentFile)) {
            $parsed = parse_ini_file($environmentFile, false, INI_SCANNER_RAW);
            if ($parsed === false) {
                throw new RuntimeException('Nie można odczytać konfiguracji środowiska Econizer.');
            }
            $environment = $parsed;
        }

        $value = static function (string $name, string $default = '') use ($environment): string {
            if (array_key_exists($name, $environment)) {
                return trim((string) $environment[$name]);
            }
            $processValue = getenv($name);
            return $processValue === false ? $default : trim((string) $processValue);
        };
        $permissions = filter_var($value('ECONIZER_DISCORD_BOT_PERMISSIONS', '0'), FILTER_VALIDATE_INT);

        return new self(
            $value('ECONIZER_API_TOKEN'),
            $value('ECONIZER_DISCORD_BOT_TOKEN'),
            $value('ECONIZER_DISCORD_CLIENT_ID'),
            $value('ECONIZER_DISCORD_CLIENT_SECRET'),
            $value('ECONIZER_DISCORD_CALLBACK_URL'),
            $permissions === false ? 0 : max(0, $permissions),
            $environmentFile,
            is_readable($environmentFile),
        );
    }

    public function apiConfigured(): bool
    {
        return strlen($this->apiToken) >= 32;
    }

    public function discordApplicationConfigured(): bool
    {
        return $this->discordClientId !== ''
            && $this->discordClientSecret !== ''
            && filter_var($this->discordCallbackUrl, FILTER_VALIDATE_URL) !== false;
    }

    public function botTokenConfigured(): bool
    {
        return $this->discordBotToken !== '';
    }
}
