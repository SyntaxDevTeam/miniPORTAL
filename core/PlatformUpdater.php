<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

use JsonException;
use RuntimeException;
use ZipArchive;

final class PlatformUpdater
{
    private const EXACT_FILES = ['index.php', '.htaccess', '.user.ini'];
    private const ROOTS = ['bin/', 'config/', 'core/', 'modules/', 'templates/', 'tools/'];
    private const REMOVABLE_SOURCE_TOOLS = [
        'bin/build-cms-distribution.php',
        'bin/build-platform-release.php',
        'bin/publish-cms-distribution.php',
        'bin/set-platform-version.php',
    ];

    public function __construct(
        private readonly string $applicationRoot,
        private readonly string $workPath,
        private readonly int $maxFiles = 10000,
        private readonly int $maxFileBytes = 52428800,
        private readonly int $maxUnpackedBytes = 209715200,
    ) {
    }

    /**
     * @param array{version:string,minimum_version:string,filename:string,checksum:string} $release
     * @param callable(): void $afterFiles
     * @return array{version:string,files:int,backup:string,preserved:list<string>}
     */
    public function apply(array $release, string $archive, string $currentVersion, callable $afterFiles): array
    {
        if (!is_file($archive) || is_link($archive)) {
            throw new RuntimeException('Archiwum wydania miniPORTAL nie jest dostępne.');
        }
        $checksum = hash_file('sha256', $archive);
        if (!is_string($checksum) || !hash_equals($release['checksum'], $checksum)) {
            throw new RuntimeException('Suma SHA-256 archiwum wydania jest nieprawidłowa.');
        }
        if (
            version_compare($release['version'], $currentVersion, '<=')
            || version_compare($currentVersion, $release['minimum_version'], '<')
        ) {
            throw new RuntimeException('Wydanie nie jest nowsze albo wymaga pośredniej wersji miniPORTAL.');
        }

        $run = rtrim($this->workPath, '/') . '/run-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4));
        $stage = $run . '/stage';
        $backup = rtrim($this->workPath, '/') . '/backups/'
            . $release['version'] . '-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(3));
        $backupRoot = dirname($backup);
        if ((!is_dir($backupRoot) && !mkdir($backupRoot, 0770, true)) || !is_writable($backupRoot)) {
            throw new RuntimeException('Katalog kopii aktualizacji nie jest zapisywalny. Sprawdź prawa cache/platform-updates/backups.');
        }
        if (!mkdir($stage, 0770, true)) {
            throw new RuntimeException('Nie można utworzyć katalogu stagingu w cache/platform-updates.');
        }
        if (!mkdir($backup, 0770)) {
            @rmdir($stage);
            @rmdir($run);
            throw new RuntimeException('Nie można utworzyć kopii rollbacku w cache/platform-updates/backups.');
        }

        try {
            $this->extract($archive, $stage);
            $manifest = $this->manifest($stage);
            if ($manifest['version'] !== $release['version']) {
                throw new RuntimeException('Wersja manifestu pakietu nie odpowiada katalogowi wydań.');
            }
            $payload = $stage . '/payload';
            $files = $manifest['files'];
            $remove = $manifest['remove'];
            $this->validatePayload($payload, $files);
            $this->validateRemovePaths($remove);
            // Use installed metadata, never the incoming module manifests.
            $protected = LocalRuntime::protectedPaths($this->applicationRoot);
            $preserved = array_values(array_filter(array_values(array_unique([...array_keys($files), ...$remove])), static fn (string $path): bool =>
                LocalRuntime::isProtected($path, $protected)));
            $files = array_diff_key($files, array_fill_keys($preserved, true));
            $remove = array_values(array_filter($remove, static fn (string $path): bool =>
                !LocalRuntime::isProtected($path, $protected)));
            $this->assertWritableTargets(array_values(array_unique([...array_keys($files), ...$remove])));
            $changed = $this->replaceFiles($payload, array_keys($files), $remove, $backup);
            try {
                $afterFiles();
            } catch (\Throwable $exception) {
                $this->restoreFiles($backup, $changed);
                throw $exception;
            }
            file_put_contents(
                $backup . '/applied.json',
                json_encode([
                    'version' => $release['version'],
                    'applied_at' => gmdate(\DateTimeInterface::ATOM),
                    'files' => array_keys($changed),
                    'preserved' => $preserved,
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL
            );
            $this->removeDirectory($run);

            return ['version' => $release['version'], 'files' => count($changed), 'backup' => $backup, 'preserved' => $preserved];
        } catch (\Throwable $exception) {
            $this->removeDirectory($run);
            throw $exception;
        }
    }

    private function manifest(string $stage): array
    {
        $file = $stage . '/release.json';
        if (!is_file($file)) {
            throw new RuntimeException('Pakiet nie zawiera release.json.');
        }
        try {
            $data = json_decode((string) file_get_contents($file), true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Manifest wydania ma nieprawidłowy JSON.', 0, $exception);
        }
        if (
            !is_array($data)
            || preg_match('/^\d+\.\d+\.\d+(?:-[a-z0-9.-]+)?$/i', (string) ($data['version'] ?? '')) !== 1
            || !is_array($data['files'] ?? null)
        ) {
            throw new RuntimeException('Manifest wydania ma nieprawidłową strukturę.');
        }

        $remove = $data['remove'] ?? [];
        if (!is_array($remove)) {
            throw new RuntimeException('Manifest wydania ma nieprawidłową listę usuwanych plików.');
        }

        return [
            'version' => (string) $data['version'],
            'files' => $data['files'],
            'remove' => array_values($remove),
        ];
    }

    private function validatePayload(string $payload, array $files): void
    {
        foreach ($files as $relative => $checksum) {
            if (!is_string($relative) || !is_string($checksum) || preg_match('/^[a-f0-9]{64}$/', $checksum) !== 1) {
                throw new RuntimeException('Manifest wydania zawiera nieprawidłową mapę plików.');
            }
            $this->assertManagedPath($relative);
            $file = $payload . '/' . $relative;
            $actual = is_file($file) && !is_link($file) ? hash_file('sha256', $file) : false;
            if (!is_string($actual) || !hash_equals($checksum, $actual)) {
                throw new RuntimeException("Plik {$relative} nie odpowiada manifestowi wydania.");
            }
        }
    }

    /** @param list<mixed> $paths */
    private function validateRemovePaths(array $paths): void
    {
        foreach ($paths as $path) {
            if (!is_string($path) || !in_array($path, self::REMOVABLE_SOURCE_TOOLS, true)) {
                throw new RuntimeException('Manifest wydania próbuje usunąć plik spoza listy narzędzi macierzystych.');
            }
        }
    }

    private function assertManagedPath(string $relative): void
    {
        $relative = str_replace('\\', '/', $relative);
        if (
            $relative === ''
            || str_starts_with($relative, '/')
            || str_contains($relative, '../')
            || preg_match('/[\x00-\x1F\x7F]/', $relative) === 1
        ) {
            throw new RuntimeException('Pakiet wydania zawiera niebezpieczną ścieżkę.');
        }
        if (in_array($relative, self::EXACT_FILES, true)) {
            return;
        }
        foreach (self::ROOTS as $root) {
            if (str_starts_with($relative, $root)) {
                if (
                    str_starts_with($relative, 'config/installed.')
                    || str_starts_with($relative, 'config/modules/')
                    || $relative === 'config/storage'
                    || str_starts_with($relative, 'config/storage/')
                    || str_ends_with($relative, '/.env')
                ) {
                    break;
                }
                return;
            }
        }

        throw new RuntimeException("Plik {$relative} nie należy do aktualizowalnego runtime.");
    }

    private function assertWritableTargets(array $files): void
    {
        foreach ($files as $relative) {
            $target = rtrim($this->applicationRoot, '/') . '/' . $relative;
            $parent = is_file($target) ? dirname($target) : $this->nearestExistingParent(dirname($target));
            if (!is_writable($parent) || !is_executable($parent)) {
                throw new RuntimeException("Brak dostępu do zapisu w katalogu potrzebnym do podmiany {$relative}.");
            }
            if (is_file($target) && !is_readable($target)) {
                throw new RuntimeException("Brak prawa odczytu pliku {$relative} potrzebnego do kopii rollbacku.");
            }
        }
    }

    private function nearestExistingParent(string $directory): string
    {
        while (!is_dir($directory) && dirname($directory) !== $directory) {
            $directory = dirname($directory);
        }

        return $directory;
    }

    /**
     * @return array<string, int|null> Original mode, or null for a newly created file.
     */
    private function replaceFiles(string $payload, array $files, array $remove, string $backup): array
    {
        $changed = [];
        try {
            foreach ($files as $relative) {
                $source = $payload . '/' . $relative;
                $target = rtrim($this->applicationRoot, '/') . '/' . $relative;
                $backupFile = $backup . '/files/' . $relative;
                $originalMode = is_file($target) ? fileperms($target) & 0777 : null;
                if ($originalMode !== null) {
                    $this->ensureDirectory(dirname($backupFile));
                    if (!copy($target, $backupFile)) {
                        throw new RuntimeException("Nie można zabezpieczyć pliku {$relative}.");
                    }
                } else {
                    $this->ensureDirectory(dirname($backup . '/missing/' . $relative));
                    touch($backup . '/missing/' . $relative);
                }
                $this->ensureDirectory(dirname($target));
                $mode = $originalMode ?? (fileperms($source) & 0777);
                $this->replaceAtomically($source, $target, $mode, "Nie można zaktualizować pliku {$relative}.");
                if (function_exists('opcache_invalidate')) {
                    @opcache_invalidate($target, true);
                }
                $changed[$relative] = $originalMode;
            }
            foreach ($remove as $relative) {
                $target = rtrim($this->applicationRoot, '/') . '/' . $relative;
                if (!is_file($target) || is_link($target)) {
                    continue;
                }
                $backupFile = $backup . '/files/' . $relative;
                $this->ensureDirectory(dirname($backupFile));
                $originalMode = fileperms($target) & 0777;
                if (!copy($target, $backupFile) || !unlink($target)) {
                    throw new RuntimeException("Nie można usunąć narzędzia macierzystego {$relative}.");
                }
                if (function_exists('opcache_invalidate')) {
                    @opcache_invalidate($target, true);
                }
                $changed[$relative] = $originalMode;
            }
        } catch (\Throwable $exception) {
            $this->restoreFiles($backup, $changed);
            throw $exception;
        }

        return $changed;
    }

    /** @param array<string, int|null> $files */
    private function restoreFiles(string $backup, array $files): void
    {
        foreach (array_reverse($files, true) as $relative => $originalMode) {
            $target = rtrim($this->applicationRoot, '/') . '/' . $relative;
            $backupFile = $backup . '/files/' . $relative;
            if ($originalMode !== null) {
                $this->ensureDirectory(dirname($target));
                $this->replaceAtomically(
                    $backupFile,
                    $target,
                    $originalMode,
                    "Nie można przywrócić pliku {$relative} z kopii aktualizacji."
                );
            } elseif (is_file($target) && !unlink($target)) {
                throw new RuntimeException("Nie można usunąć nowego pliku {$relative} podczas cofania aktualizacji.");
            }
            if (function_exists('opcache_invalidate')) {
                @opcache_invalidate($target, true);
            }
        }
    }

    private function replaceAtomically(string $source, string $target, int $mode, string $error): void
    {
        $temporary = $target . '.update-' . bin2hex(random_bytes(6));
        try {
            if (!copy($source, $temporary) || !chmod($temporary, $mode) || !rename($temporary, $target)) {
                throw new RuntimeException($error);
            }
        } finally {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }

    private function extract(string $archive, string $target): void
    {
        if (class_exists(ZipArchive::class)) {
            $zip = new ZipArchive();
            if ($zip->open($archive) !== true) {
                throw new RuntimeException('Nie można otworzyć archiwum wydania.');
            }
            $this->assertArchiveEntryCount($zip->numFiles);
            $unpackedBytes = 0;
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $name = $zip->getNameIndex($index);
                if (!is_string($name)) {
                    continue;
                }
                $this->assertArchivePath($name);
                $stat = $zip->statIndex($index);
                $size = is_array($stat) ? (int) ($stat['size'] ?? 0) : 0;
                $this->assertArchiveFileSize($size);
                $unpackedBytes += max(0, $size);
                $this->assertArchiveTotalSize($unpackedBytes);
            }
            if (!$zip->extractTo($target)) {
                $zip->close();
                throw new RuntimeException('Nie można rozpakować wydania.');
            }
            $zip->close();
            $this->assertExtractedBudget($target);
            return;
        }
        $unzip = trim((string) shell_exec('command -v unzip 2>/dev/null'));
        if ($unzip === '') {
            throw new RuntimeException('Serwer nie ma ZipArchive ani narzędzia unzip.');
        }
        $listing = shell_exec(escapeshellarg($unzip) . ' -Z1 ' . escapeshellarg($archive) . ' 2>/dev/null');
        if (!is_string($listing)) {
            throw new RuntimeException('Nie można odczytać archiwum wydania.');
        }
        foreach (preg_split('/\R/', trim($listing)) ?: [] as $name) {
            $this->assertArchivePath($name);
        }
        $this->assertArchiveEntryCount(count(preg_split('/\R/', trim($listing)) ?: []));
        exec(
            escapeshellarg($unzip) . ' -qq ' . escapeshellarg($archive)
            . ' -d ' . escapeshellarg($target) . ' 2>/dev/null',
            $output,
            $code
        );
        if ($code !== 0) {
            throw new RuntimeException('Nie można rozpakować wydania.');
        }
        $this->assertExtractedBudget($target);
    }

    private function assertArchivePath(string $path): void
    {
        $path = str_replace('\\', '/', $path);
        if ($path === '' || str_starts_with($path, '/') || str_contains($path, '../')) {
            throw new RuntimeException('Archiwum wydania zawiera niebezpieczną ścieżkę.');
        }
    }

    private function assertArchiveEntryCount(int $count): void
    {
        if ($count > $this->maxFiles) {
            throw new RuntimeException('Archiwum wydania zawiera zbyt wiele plików.');
        }
    }

    private function assertArchiveFileSize(int $bytes): void
    {
        if ($bytes > $this->maxFileBytes) {
            throw new RuntimeException('Archiwum wydania zawiera zbyt duży plik.');
        }
    }

    private function assertArchiveTotalSize(int $bytes): void
    {
        if ($bytes > $this->maxUnpackedBytes) {
            throw new RuntimeException('Archiwum wydania przekracza dozwolony rozmiar po rozpakowaniu.');
        }
    }

    private function assertExtractedBudget(string $directory): void
    {
        $files = 0;
        $bytes = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isDir()) {
                continue;
            }
            $files++;
            $this->assertArchiveEntryCount($files);
            $size = (int) $file->getSize();
            $this->assertArchiveFileSize($size);
            $bytes += max(0, $size);
            $this->assertArchiveTotalSize($bytes);
        }
    }

    private function ensureDirectory(string $directory): void
    {
        if (!is_dir($directory) && !mkdir($directory, 0770, true)) {
            throw new RuntimeException("Nie można utworzyć katalogu {$directory}.");
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
        foreach ($iterator as $file) {
            $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
        }
        @rmdir($directory);
    }
}
