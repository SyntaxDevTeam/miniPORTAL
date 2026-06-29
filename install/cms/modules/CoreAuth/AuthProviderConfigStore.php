<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\CoreAuth;

use RuntimeException;

final class AuthProviderConfigStore
{
    /** @var array<string, string> */
    private const PREFIXES = [
        'github' => 'GITHUB',
        'discord' => 'DISCORD',
        'google' => 'GOOGLE',
        'microsoft' => 'MICROSOFT',
    ];

    public function __construct(
        private readonly string $file,
    ) {
    }

    /** @return array<string, array{enabled: bool, client_id: string, client_secret: string}> */
    public function read(): array
    {
        $values = is_readable($this->file)
            ? parse_ini_file($this->file, false, INI_SCANNER_RAW)
            : [];
        $values = is_array($values) ? $values : [];
        $providers = [];
        foreach (self::PREFIXES as $provider => $prefix) {
            $providers[$provider] = [
                'enabled' => filter_var($values[$prefix . '_ENABLED'] ?? false, FILTER_VALIDATE_BOOL),
                'client_id' => trim((string) ($values[$prefix . '_CLIENT_ID'] ?? '')),
                'client_secret' => (string) ($values[$prefix . '_CLIENT_SECRET'] ?? ''),
            ];
        }

        return $providers;
    }

    /**
     * @param array<string, array{enabled?: bool, client_id?: string, client_secret?: string}> $providers
     */
    public function save(array $providers, array $fallback = []): void
    {
        $current = $this->read();
        $lines = [];
        $configured = 0;
        foreach (self::PREFIXES as $provider => $prefix) {
            $enabled = (bool) ($providers[$provider]['enabled'] ?? false);
            $clientId = trim((string) ($providers[$provider]['client_id'] ?? ''));
            $clientSecret = (string) ($providers[$provider]['client_secret'] ?? '');
            if ($clientSecret === '') {
                $clientSecret = (string) ($current[$provider]['client_secret'] ?? '');
            }
            if ($clientSecret === '') {
                $clientSecret = (string) ($fallback[$provider]['client_secret'] ?? '');
            }
            if ($enabled && ($clientId === '' || $clientSecret === '')) {
                throw new RuntimeException("Włączony dostawca {$provider} wymaga Client ID i Client Secret.");
            }
            if (strlen($clientId) > 255 || strlen($clientSecret) > 2048) {
                throw new RuntimeException("Konfiguracja dostawcy {$provider} przekracza dozwolony limit.");
            }
            $configured += $enabled ? 1 : 0;
            $lines[] = $prefix . '_ENABLED=' . self::encode($enabled ? 'true' : 'false');
            $lines[] = $prefix . '_CLIENT_ID=' . self::encode($clientId);
            $lines[] = $prefix . '_CLIENT_SECRET=' . self::encode($clientSecret);
        }
        if ($configured === 0) {
            throw new RuntimeException('Co najmniej jeden dostawca logowania musi pozostać włączony.');
        }

        $directory = dirname($this->file);
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new RuntimeException('Nie można utworzyć katalogu chronionej konfiguracji OAuth.');
        }
        $temporary = tempnam($directory, '.auth-providers-');
        if ($temporary === false) {
            throw new RuntimeException('Nie można przygotować atomowego zapisu konfiguracji OAuth.');
        }
        try {
            if (file_put_contents($temporary, implode(PHP_EOL, $lines) . PHP_EOL, LOCK_EX) === false) {
                throw new RuntimeException('Nie można zapisać konfiguracji OAuth.');
            }
            chmod($temporary, 0600);
            if (!rename($temporary, $this->file)) {
                throw new RuntimeException('Nie można aktywować konfiguracji OAuth.');
            }
            chmod($this->file, 0600);
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }

    private static function encode(string $value): string
    {
        return '"' . addcslashes($value, "\\\"\r\n") . '"';
    }
}
