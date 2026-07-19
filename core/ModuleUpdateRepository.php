<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

use JsonException;
use RuntimeException;

final class ModuleUpdateRepository
{
    public function __construct(
        private readonly string $catalogUrl,
        private readonly string $cachePath,
        private readonly int $maxArchiveBytes = 10485760,
        private readonly int $catalogTtl = 900,
    ) {
    }

    public function configured(): bool
    {
        return trim($this->catalogUrl) !== '';
    }

    /**
     * @return list<array{
     *     id:string,
     *     name:string,
     *     version:string,
     *     type:string,
     *     protected:bool,
     *     miniportal_constraint:string,
     *     required_modules:list<string>,
     *     published_at:string,
     *     filename:string,
     *     checksum:string,
     *     source_checksum:string
     * }>
     */
    public function all(bool $forceRefresh = false): array
    {
        if (!$this->configured()) {
            return [];
        }
        $this->ensureCache();
        $catalogFile = rtrim($this->cachePath, '/') . '/catalog.json';
        if (
            $forceRefresh
            || !is_file($catalogFile)
            || (int) filemtime($catalogFile) < time() - max(1, $this->catalogTtl)
        ) {
            $temporary = $catalogFile . '.download-' . bin2hex(random_bytes(5));
            try {
                $this->downloadFile($this->catalogUrl, $temporary, 1048576);
                $this->decodeCatalog((string) file_get_contents($temporary));
                if (!rename($temporary, $catalogFile)) {
                    throw new RuntimeException('Nie można zatwierdzić katalogu aktualizacji modułów.');
                }
                @chmod($catalogFile, 0660);
            } catch (\Throwable $exception) {
                @unlink($temporary);
                if ($forceRefresh || !is_file($catalogFile)) {
                    throw $exception;
                }
            }
        }

        return $this->decodeCatalog((string) file_get_contents($catalogFile));
    }

    public function latestFor(string $moduleId, string $currentVersion, bool $forceRefresh = false): ?array
    {
        foreach ($this->all($forceRefresh) as $entry) {
            if ($entry['id'] === $moduleId && version_compare($entry['version'], $currentVersion, '>')) {
                return $entry;
            }
        }

        return null;
    }

    public function find(string $moduleId, string $version, bool $forceRefresh = false): ?array
    {
        foreach ($this->all($forceRefresh) as $entry) {
            if ($entry['id'] === $moduleId && $entry['version'] === $version) {
                return $entry;
            }
        }

        return null;
    }

    public function compatibleWith(string $constraint, string $platformVersion): bool
    {
        if (trim($constraint) === '') {
            return true;
        }
        if (preg_match('/^(>=|>|=|<=|<)\s*(\d+\.\d+(?:\.\d+)?)$/', $constraint, $matches) !== 1) {
            return false;
        }

        return version_compare($platformVersion, $matches[2], $matches[1]);
    }

    public function archivePath(array $entry): string
    {
        $filename = (string) ($entry['filename'] ?? '');
        $checksum = (string) ($entry['checksum'] ?? '');
        if (basename($filename) !== $filename || preg_match('/^[a-f0-9]{64}$/', $checksum) !== 1) {
            throw new RuntimeException('Wpis aktualizacji modułu jest nieprawidłowy.');
        }
        $this->ensureCache();
        $downloads = rtrim($this->cachePath, '/') . '/downloads';
        if (!is_dir($downloads) && !mkdir($downloads, 0770, true)) {
            throw new RuntimeException('Nie można utworzyć cache pakietów modułów.');
        }
        $target = $downloads . '/' . $filename;
        if (is_file($target)) {
            $localChecksum = hash_file('sha256', $target);
            if (is_string($localChecksum) && hash_equals($checksum, $localChecksum)) {
                return $target;
            }
            @unlink($target);
        }

        $baseUrl = substr($this->catalogUrl, 0, (int) strrpos($this->catalogUrl, '/') + 1);
        $temporary = $target . '.download-' . bin2hex(random_bytes(5));
        try {
            $this->downloadFile($baseUrl . rawurlencode($filename), $temporary, $this->maxArchiveBytes);
            $downloadedChecksum = hash_file('sha256', $temporary);
            if (!is_string($downloadedChecksum) || !hash_equals($checksum, $downloadedChecksum)) {
                throw new RuntimeException('Pobrany pakiet modułu ma nieprawidłową sumę SHA-256.');
            }
            if (!rename($temporary, $target)) {
                throw new RuntimeException('Nie można zatwierdzić pobranego pakietu modułu.');
            }
            @chmod($target, 0660);

            return $target;
        } finally {
            @unlink($temporary);
        }
    }

    private function decodeCatalog(string $payload): array
    {
        try {
            $data = json_decode($payload, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Katalog aktualizacji modułów ma nieprawidłowy JSON.', 0, $exception);
        }
        if (!is_array($data) || !is_array($data['modules'] ?? null)) {
            throw new RuntimeException('Katalog aktualizacji modułów ma nieprawidłową strukturę.');
        }

        $entries = [];
        foreach ($data['modules'] as $entry) {
            if (!is_array($entry)) {
                throw new RuntimeException('Katalog aktualizacji modułów zawiera nieprawidłowy wpis.');
            }
            $id = (string) ($entry['id'] ?? '');
            $name = trim((string) ($entry['name'] ?? ''));
            $version = (string) ($entry['version'] ?? '');
            $type = (string) ($entry['type'] ?? '');
            $constraint = trim((string) ($entry['miniportal_constraint'] ?? ''));
            $filename = (string) ($entry['filename'] ?? '');
            $checksum = (string) ($entry['checksum'] ?? '');
            $sourceChecksum = (string) ($entry['source_checksum'] ?? '');
            $dependencies = $entry['required_modules'] ?? [];
            if (
                preg_match('/^[a-z][a-z0-9_]{1,63}$/', $id) !== 1
                || $name === '' || strlen($name) > 160
                || preg_match('/^\d+\.\d+\.\d+(?:-[a-z0-9.-]+)?$/i', $version) !== 1
                || !in_array($type, ['core', 'extension', 'system'], true)
                || !is_bool($entry['protected'] ?? null)
                || ($constraint !== '' && preg_match('/^(>=|>|=|<=|<)\s*\d+\.\d+(?:\.\d+)?$/', $constraint) !== 1)
                || basename($filename) !== $filename || !str_ends_with(strtolower($filename), '.zip')
                || preg_match('/^[a-f0-9]{64}$/', $checksum) !== 1
                || preg_match('/^[a-f0-9]{64}$/', $sourceChecksum) !== 1
                || !is_array($dependencies)
            ) {
                throw new RuntimeException("Wpis aktualizacji modułu {$id} jest nieprawidłowy.");
            }
            $requiredModules = [];
            foreach ($dependencies as $dependency) {
                if (!is_string($dependency) || preg_match('/^[a-z][a-z0-9_]{1,63}$/', $dependency) !== 1) {
                    throw new RuntimeException("Zależności aktualizacji modułu {$id} są nieprawidłowe.");
                }
                $requiredModules[] = $dependency;
            }
            $entries[] = [
                'id' => $id,
                'name' => $name,
                'version' => $version,
                'type' => $type,
                'protected' => $entry['protected'],
                'miniportal_constraint' => $constraint,
                'required_modules' => array_values(array_unique($requiredModules)),
                'published_at' => (string) ($entry['published_at'] ?? ''),
                'filename' => $filename,
                'checksum' => $checksum,
                'source_checksum' => $sourceChecksum,
            ];
        }
        usort($entries, static fn (array $left, array $right): int => version_compare(
            $right['version'],
            $left['version']
        ));

        return $entries;
    }

    private function ensureCache(): void
    {
        if (!is_dir($this->cachePath) && !mkdir($this->cachePath, 0770, true)) {
            throw new RuntimeException('Nie można utworzyć cache aktualizacji modułów.');
        }
        if (!is_writable($this->cachePath)) {
            throw new RuntimeException('Cache aktualizacji modułów nie jest zapisywalny.');
        }
    }

    private function downloadFile(string $url, string $target, int $maxBytes): void
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false || parse_url($url, PHP_URL_SCHEME) !== 'https') {
            throw new RuntimeException('Katalog aktualizacji modułów musi korzystać z poprawnego adresu HTTPS.');
        }
        $context = stream_context_create([
            'http' => ['timeout' => 20, 'follow_location' => 0, 'ignore_errors' => false],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $input = @fopen($url, 'rb', false, $context);
        if (!is_resource($input)) {
            throw new RuntimeException('Nie można pobrać katalogu albo pakietu aktualizacji modułu.');
        }
        $output = fopen($target, 'wb');
        if (!is_resource($output)) {
            fclose($input);
            throw new RuntimeException('Nie można zapisać pobieranej aktualizacji modułu.');
        }
        $written = 0;
        try {
            while (!feof($input)) {
                $chunk = fread($input, 65536);
                if (!is_string($chunk)) {
                    throw new RuntimeException('Błąd odczytu pobieranej aktualizacji modułu.');
                }
                $written += strlen($chunk);
                if ($written > $maxBytes) {
                    throw new RuntimeException('Pobierana aktualizacja modułu przekracza dozwolony rozmiar.');
                }
                if ($chunk !== '' && fwrite($output, $chunk) !== strlen($chunk)) {
                    throw new RuntimeException('Nie można zapisać całej aktualizacji modułu.');
                }
            }
        } finally {
            fclose($input);
            fclose($output);
        }
    }
}
