<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

use RuntimeException;

final class DistributionAccessCredentialStore
{
    public function __construct(
        private readonly string $file,
        private readonly string $catalogCacheFile,
    ) {
    }

    /** @return array{configured:bool,installation_id:string,key_last4:string} */
    public function status(): array
    {
        $credentials = $this->credentials();

        return [
            'configured' => $credentials !== null,
            'installation_id' => $credentials['installation_id'] ?? '',
            'key_last4' => $credentials !== null ? substr($credentials['access_key'], -4) : '',
        ];
    }

    /** @return array{installation_id:string,access_key:string}|null */
    public function credentials(): ?array
    {
        if (!is_file($this->file)) {
            return null;
        }
        if (is_link($this->file) || !is_readable($this->file) || filesize($this->file) > 4096) {
            throw new RuntimeException('Plik klucza dostępu do dystrybucji jest nieprawidłowy.');
        }
        $values = parse_ini_file($this->file, false, INI_SCANNER_RAW);
        if (!is_array($values)) {
            throw new RuntimeException('Nie można odczytać klucza dostępu do dystrybucji.');
        }
        $key = trim((string) ($values['DISTRIBUTION_ACCESS_KEY'] ?? ''));
        if ($key === '') {
            return null;
        }

        return [
            'installation_id' => self::installationIdFromKey($key),
            'access_key' => $key,
        ];
    }

    public function save(string $key): void
    {
        $key = trim($key);
        self::installationIdFromKey($key);
        $directory = dirname($this->file);
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new RuntimeException('Nie można utworzyć katalogu konfiguracji dostępu do dystrybucji.');
        }
        $temporary = tempnam($directory, '.distribution-access-');
        if ($temporary === false) {
            throw new RuntimeException('Nie można przygotować atomowego zapisu klucza dystrybucji.');
        }
        try {
            $payload = 'DISTRIBUTION_ACCESS_KEY="' . addcslashes($key, "\\\"\r\n") . '"' . PHP_EOL;
            if (file_put_contents($temporary, $payload, LOCK_EX) === false) {
                throw new RuntimeException('Nie można zapisać klucza dostępu do dystrybucji.');
            }
            chmod($temporary, 0600);
            if (!rename($temporary, $this->file)) {
                throw new RuntimeException('Nie można aktywować klucza dostępu do dystrybucji.');
            }
            chmod($this->file, 0600);
            $this->clearCatalogCache();
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }

    public function remove(): void
    {
        if (is_file($this->file) && !unlink($this->file)) {
            throw new RuntimeException('Nie można usunąć klucza dostępu do dystrybucji.');
        }
        $this->clearCatalogCache();
    }

    private function clearCatalogCache(): void
    {
        if (is_file($this->catalogCacheFile)) {
            @unlink($this->catalogCacheFile);
        }
    }

    public static function installationIdFromKey(string $key): string
    {
        if (preg_match('/^mpda_([a-f0-9]{32})_([A-Za-z0-9_-]{43})$/D', $key, $match) !== 1) {
            throw new RuntimeException('Klucz dostępu ma nieprawidłowy format.');
        }
        $hex = $match[1];

        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4)
            . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
    }
}
