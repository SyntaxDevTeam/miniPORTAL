<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

use JsonException;
use RuntimeException;

final class ModuleUpdatePublisher
{
    public function __construct(
        private readonly ?ModuleManagerService $modules,
        private readonly string $repositoryPath,
    ) {
    }

    public function available(): bool
    {
        return $this->modules?->signsExportsAutomatically() === true;
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
    public function catalog(): array
    {
        if (!$this->available()) {
            throw new RuntimeException('Repozytorium aktualizacji modułów wymaga skonfigurowanego klucza wydawniczego.');
        }

        try {
            return $this->refresh();
        } catch (\Throwable $exception) {
            $existing = $this->readCatalog();
            if ($existing !== []) {
                return $existing;
            }
            throw $exception;
        }
    }

    public function findByFilename(string $filename): ?array
    {
        if (basename($filename) !== $filename) {
            return null;
        }
        foreach ($this->catalog() as $entry) {
            if ($entry['filename'] === $filename) {
                return $entry;
            }
        }

        return null;
    }

    public function archivePath(array $entry): string
    {
        $path = rtrim($this->repositoryPath, '/') . '/' . (string) ($entry['filename'] ?? '');
        if (!is_file($path) || is_link($path)) {
            throw new RuntimeException('Pakiet aktualizacji modułu jest niedostępny.');
        }
        $checksum = hash_file('sha256', $path);
        if (!is_string($checksum) || !hash_equals((string) ($entry['checksum'] ?? ''), $checksum)) {
            throw new RuntimeException('Pakiet aktualizacji modułu nie zgadza się z katalogiem.');
        }

        return $path;
    }

    private function refresh(): array
    {
        $this->ensureRepository();
        $lock = fopen(rtrim($this->repositoryPath, '/') . '/catalog.lock', 'c+');
        if (!is_resource($lock) || !flock($lock, LOCK_EX)) {
            if (is_resource($lock)) {
                fclose($lock);
            }
            throw new RuntimeException('Nie można zablokować katalogu aktualizacji modułów.');
        }

        try {
            $existing = [];
            foreach ($this->readCatalog() as $entry) {
                $existing[$entry['id']] = $entry;
            }

            $entries = [];
            foreach ($this->modules?->modules() ?? [] as $module) {
                $manifest = $module['manifest'];
                $state = $module['state'];
                if (
                    !$manifest instanceof ModuleManifest
                    || !$state instanceof ModuleState
                    || !$state->isInstalled()
                    || $module['error'] !== null
                ) {
                    continue;
                }

                $sourceChecksum = $this->modules?->sourceFingerprint($manifest->id);
                if (!is_string($sourceChecksum) || preg_match('/^[a-f0-9]{64}$/', $sourceChecksum) !== 1) {
                    throw new RuntimeException("Nie można obliczyć odcisku modułu {$manifest->id}.");
                }
                $filename = $manifest->id . '-' . $manifest->version . '.zip';
                $target = rtrim($this->repositoryPath, '/') . '/' . $filename;
                $previous = $existing[$manifest->id] ?? null;
                $unchanged = is_array($previous)
                    && $previous['version'] === $manifest->version
                    && $previous['source_checksum'] === $sourceChecksum
                    && $previous['filename'] === $filename
                    && is_file($target)
                    && hash_equals($previous['checksum'], (string) hash_file('sha256', $target));

                if (!$unchanged) {
                    $buildDirectory = rtrim($this->repositoryPath, '/') . '/.build-' . bin2hex(random_bytes(6));
                    try {
                        $export = $this->modules?->exportPackageTo($manifest->id, $buildDirectory);
                        $builtPath = is_array($export) ? (string) ($export['path'] ?? '') : '';
                        if (!is_file($builtPath) || basename($builtPath) !== $filename) {
                            throw new RuntimeException("Nie zbudowano pakietu modułu {$manifest->id}.");
                        }
                        if (!rename($builtPath, $target)) {
                            throw new RuntimeException("Nie można opublikować pakietu modułu {$manifest->id}.");
                        }
                        @chmod($target, 0660);
                    } finally {
                        $this->removeDirectory($buildDirectory);
                    }
                }

                $checksum = hash_file('sha256', $target);
                if (!is_string($checksum)) {
                    throw new RuntimeException("Nie można obliczyć SHA-256 pakietu {$manifest->id}.");
                }
                $entries[] = [
                    'id' => $manifest->id,
                    'name' => $manifest->name,
                    'version' => $manifest->version,
                    'type' => $manifest->type,
                    'protected' => $manifest->protected,
                    'miniportal_constraint' => $manifest->miniportalConstraint,
                    'required_modules' => $manifest->requiredModules,
                    'published_at' => $unchanged
                        ? (string) $previous['published_at']
                        : gmdate('Y-m-d\TH:i:s+00:00'),
                    'filename' => $filename,
                    'checksum' => $checksum,
                    'source_checksum' => $sourceChecksum,
                ];
            }
            usort($entries, static fn (array $left, array $right): int => strcmp($left['id'], $right['id']));
            $this->writeCatalog($entries);
            $this->removeUnreferencedArchives($entries);

            return $entries;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function ensureRepository(): void
    {
        if (!is_dir($this->repositoryPath) && !mkdir($this->repositoryPath, 0770, true)) {
            throw new RuntimeException('Nie można utworzyć repozytorium aktualizacji modułów.');
        }
        if (!is_writable($this->repositoryPath)) {
            throw new RuntimeException('Repozytorium aktualizacji modułów nie jest zapisywalne.');
        }
    }

    private function readCatalog(): array
    {
        $file = rtrim($this->repositoryPath, '/') . '/catalog.json';
        if (!is_file($file)) {
            return [];
        }
        try {
            $data = json_decode((string) file_get_contents($file), true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }
        if (!is_array($data) || !is_array($data['modules'] ?? null)) {
            return [];
        }
        $entries = [];
        foreach ($data['modules'] as $entry) {
            if (
                !is_array($entry)
                || preg_match('/^[a-z][a-z0-9_]{1,63}$/', (string) ($entry['id'] ?? '')) !== 1
                || !is_string($entry['name'] ?? null)
                || trim($entry['name']) === ''
                || preg_match('/^\d+\.\d+\.\d+(?:-[a-z0-9.-]+)?$/i', (string) ($entry['version'] ?? '')) !== 1
                || !in_array($entry['type'] ?? null, ['core', 'extension', 'system'], true)
                || !is_bool($entry['protected'] ?? null)
                || !is_string($entry['miniportal_constraint'] ?? null)
                || !is_array($entry['required_modules'] ?? null)
                || !is_string($entry['published_at'] ?? null)
                || !is_string($entry['filename'] ?? null)
                || basename($entry['filename']) !== $entry['filename']
                || preg_match('/^[a-f0-9]{64}$/', (string) ($entry['checksum'] ?? '')) !== 1
                || preg_match('/^[a-f0-9]{64}$/', (string) ($entry['source_checksum'] ?? '')) !== 1
            ) {
                return [];
            }
            $entries[] = $entry;
        }

        return $entries;
    }

    private function writeCatalog(array $entries): void
    {
        $file = rtrim($this->repositoryPath, '/') . '/catalog.json';
        $temporary = $file . '.tmp-' . bin2hex(random_bytes(5));
        $payload = json_encode(
            ['generated_at' => gmdate('Y-m-d\TH:i:s+00:00'), 'modules' => $entries],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        ) . PHP_EOL;
        if (file_put_contents($temporary, $payload, LOCK_EX) === false || !rename($temporary, $file)) {
            @unlink($temporary);
            throw new RuntimeException('Nie można atomowo zapisać katalogu aktualizacji modułów.');
        }
        @chmod($file, 0660);
    }

    private function removeUnreferencedArchives(array $entries): void
    {
        $referenced = array_fill_keys(array_column($entries, 'filename'), true);
        foreach (glob(rtrim($this->repositoryPath, '/') . '/*.zip') ?: [] as $archive) {
            if (!isset($referenced[basename($archive)]) && !is_link($archive)) {
                @unlink($archive);
            }
        }
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory) || is_link($directory)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($directory);
    }
}
