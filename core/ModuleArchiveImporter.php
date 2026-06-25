<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

use PharData;
use RuntimeException;
use ZipArchive;

final class ModuleArchiveImporter
{
    public function __construct(
        private readonly string $quarantinePath,
        private readonly ModuleManifestValidator $validator,
        private readonly int $maxBytes = 10485760,
    ) {
    }

    /**
     * @param array{name: string, type: string, tmp_name: string, error: int, size: int} $file
     * @return array{directory: string, package: string, manifest: ?ModuleManifest, error: ?string}
     */
    public function importUploaded(array $file): array
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Nie odebrano poprawnego pliku archiwum.');
        }
        if ($file['size'] <= 0 || $file['size'] > $this->maxBytes) {
            throw new RuntimeException('Archiwum modułu jest puste albo przekracza dozwolony rozmiar.');
        }
        $source = $file['tmp_name'];
        if ($source === '' || !is_file($source)) {
            throw new RuntimeException('Plik tymczasowy archiwum nie jest dostępny.');
        }

        return $this->importFile($source, $file['name']);
    }

    /**
     * @return array{directory: string, package: string, manifest: ?ModuleManifest, error: ?string}
     */
    public function importFile(string $source, string $originalName): array
    {
        if (!is_file($source) || is_link($source)) {
            throw new RuntimeException('Nie znaleziono pliku archiwum.');
        }
        if (filesize($source) > $this->maxBytes) {
            throw new RuntimeException('Archiwum modułu przekracza dozwolony rozmiar.');
        }

        $extension = $this->archiveExtension($originalName);
        $importDirectory = rtrim($this->quarantinePath, '/') . '/import-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4));
        $extractDirectory = $importDirectory . '/source';
        if (!is_dir($extractDirectory) && !mkdir($extractDirectory, 0770, true)) {
            throw new RuntimeException('Nie można utworzyć katalogu kwarantanny.');
        }

        $archiveCopy = $importDirectory . '/archive.' . str_replace('.', '-', $extension);
        if (!copy($source, $archiveCopy)) {
            throw new RuntimeException('Nie można zapisać archiwum w kwarantannie.');
        }
        @chmod($archiveCopy, 0660);

        $this->extract($archiveCopy, $extractDirectory, $extension);
        $packageDirectory = $this->locatePackage($extractDirectory);
        $inspection = $this->validator->inspect($packageDirectory);

        return [
            'directory' => $importDirectory,
            'package' => basename($packageDirectory),
            'manifest' => $inspection['manifest'],
            'error' => $inspection['error'],
        ];
    }

    /**
     * @return list<array{directory: string, package: string, imported_at: string, manifest: ?ModuleManifest, error: ?string}>
     */
    public function imports(): array
    {
        $imports = [];
        foreach (glob(rtrim($this->quarantinePath, '/') . '/import-*') ?: [] as $directory) {
            if (!is_dir($directory) || is_link($directory)) {
                continue;
            }
            try {
                $packageDirectory = $this->locatePackage($directory . '/source');
                $inspection = $this->validator->inspect($packageDirectory);
                $imports[] = [
                    'directory' => basename($directory),
                    'package' => basename($packageDirectory),
                    'imported_at' => date('Y-m-d H:i:s', (int) filemtime($directory)),
                    'manifest' => $inspection['manifest'],
                    'error' => $inspection['error'],
                ];
            } catch (\Throwable $exception) {
                $imports[] = [
                    'directory' => basename($directory),
                    'package' => 'Nieznany',
                    'imported_at' => date('Y-m-d H:i:s', (int) filemtime($directory)),
                    'manifest' => null,
                    'error' => $exception->getMessage(),
                ];
            }
        }
        usort($imports, static fn (array $left, array $right): int => strcmp($right['directory'], $left['directory']));

        return $imports;
    }

    public function remove(string $importDirectory): void
    {
        $path = $this->importPath($importDirectory);
        if (!is_dir($path) || is_link($path)) {
            throw new RuntimeException('Nie znaleziono wskazanego importu w kwarantannie.');
        }
        $this->removeDirectory($path);
        if (is_dir($path)) {
            throw new RuntimeException('Nie można usunąć importu z kwarantanny.');
        }
    }

    /**
     * @return list<string>
     */
    public function removeOlderThan(int $days): array
    {
        $days = max(1, min(365, $days));
        $threshold = time() - ($days * 86400);
        $removed = [];
        foreach (glob(rtrim($this->quarantinePath, '/') . '/import-*') ?: [] as $path) {
            if (!is_dir($path) || is_link($path) || (int) filemtime($path) >= $threshold) {
                continue;
            }
            $directory = basename($path);
            $this->remove($directory);
            $removed[] = $directory;
        }

        return $removed;
    }

    /**
     * @param null|callable(ModuleManifest): void $updateInstalled
     * @return array{directory: string, manifest: ModuleManifest, operation: string}
     */
    public function approve(
        string $importDirectory,
        string $modulesPath,
        ?callable $updateInstalled = null,
    ): array
    {
        $importPath = $this->importPath($importDirectory);
        if (!is_dir($importPath) || is_link($importPath)) {
            throw new RuntimeException('Nie znaleziono wskazanego importu w kwarantannie.');
        }

        $packageDirectory = $this->locatePackage($importPath . '/source');
        $this->assertNoLinks($packageDirectory);
        $manifest = $this->validator->validate($packageDirectory);
        $packageName = basename($packageDirectory);
        if (preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,63}$/', $packageName) !== 1) {
            throw new RuntimeException('Nazwa katalogu pakietu jest nieprawidłowa.');
        }
        if (!is_dir($modulesPath) || !is_writable($modulesPath)) {
            throw new RuntimeException('Aktywny katalog modułów nie jest zapisywalny.');
        }

        $target = rtrim($modulesPath, '/') . '/' . $packageName;
        $existingManifest = null;
        foreach (glob(rtrim($modulesPath, '/') . '/*/info.json') ?: [] as $manifestFile) {
            $existing = $this->validator->inspect(dirname($manifestFile))['manifest'];
            if ($existing !== null && $existing->id === $manifest->id) {
                $existingManifest = $existing;
                break;
            }
        }

        if ($existingManifest === null) {
            if ($manifest->type !== 'extension' || $manifest->protected) {
                throw new RuntimeException('Nowy pakiet musi być niechronionym modułem typu rozszerzenie.');
            }
            $this->assertVerifiedSignature($manifest);
            if (file_exists($target) || is_link($target)) {
                throw new RuntimeException("Katalog modułu {$packageName} już istnieje.");
            }
            if (!rename($packageDirectory, $target)) {
                throw new RuntimeException('Nie można atomowo przenieść pakietu do katalogu modules/.');
            }
            @chmod($target, 0770);
            $approvedManifest = $this->validator->validate($target);
            $this->removeDirectory($importPath);

            return ['directory' => $target, 'manifest' => $approvedManifest, 'operation' => 'installed'];
        }

        $existingDirectory = $existingManifest->directory;
        if ($packageName !== basename($existingDirectory) || $target !== $existingDirectory) {
            throw new RuntimeException('Aktualizacja musi zachować katalog istniejącego modułu.');
        }
        if ($manifest->type !== $existingManifest->type || $manifest->protected !== $existingManifest->protected) {
            throw new RuntimeException('Aktualizacja nie może zmieniać typu ani ochrony modułu.');
        }
        if ($manifest->author !== $existingManifest->author) {
            throw new RuntimeException('Aktualizacja nie może zmieniać autora modułu.');
        }
        if (
            $manifest->originType !== $existingManifest->originType
            || $manifest->originUrl !== $existingManifest->originUrl
        ) {
            throw new RuntimeException('Aktualizacja nie może zmieniać pochodzenia modułu.');
        }
        if (version_compare($manifest->version, $existingManifest->version, '<=')) {
            throw new RuntimeException('Importowana aktualizacja musi mieć wyższą wersję niż bieżący kod modułu.');
        }
        if ($existingManifest->protected) {
            if ($manifest->originType !== 'bundled') {
                $this->assertVerifiedSignature($manifest);
            }
        } else {
            $this->assertVerifiedSignature($manifest);
        }
        if ($updateInstalled === null) {
            throw new RuntimeException('Podmiana zainstalowanego modułu wymaga kontrolowanej aktualizacji.');
        }

        $backup = $importPath . '/previous';
        if (file_exists($backup) || !rename($existingDirectory, $backup)) {
            throw new RuntimeException('Nie można utworzyć kopii bezpieczeństwa aktualizowanego modułu.');
        }
        try {
            if (!rename($packageDirectory, $target)) {
                throw new RuntimeException('Nie można atomowo umieścić aktualizacji w katalogu modules/.');
            }
            @chmod($target, 0770);
            $approvedManifest = $this->validator->validate($target);
            $updateInstalled($approvedManifest);
        } catch (\Throwable $exception) {
            $this->removeDirectory($target);
            if (!rename($backup, $existingDirectory)) {
                throw new RuntimeException(
                    'Aktualizacja nie powiodła się i nie można przywrócić poprzedniego kodu modułu.',
                    0,
                    $exception
                );
            }
            throw $exception;
        }

        $this->removeDirectory($backup);
        $this->removeDirectory($importPath);

        return ['directory' => $target, 'manifest' => $approvedManifest, 'operation' => 'updated'];
    }

    private function assertVerifiedSignature(ModuleManifest $manifest): void
    {
        if (!in_array($manifest->signatureStatus, ['verified', 'verified_retired'], true)) {
            throw new RuntimeException('Zatwierdzenie wymaga poprawnego podpisu zaufanego wydawcy.');
        }
    }

    private function importPath(string $importDirectory): string
    {
        if (preg_match('/^import-\d{8}-\d{6}-[a-f0-9]{8}$/', $importDirectory) !== 1) {
            throw new RuntimeException('Identyfikator importu z kwarantanny jest nieprawidłowy.');
        }

        return rtrim($this->quarantinePath, '/') . '/' . $importDirectory;
    }

    private function archiveExtension(string $name): string
    {
        $lower = strtolower($name);
        foreach (['tar.gz', 'tgz', 'tar', 'zip'] as $extension) {
            if (str_ends_with($lower, '.' . $extension)) {
                return $extension;
            }
        }

        throw new RuntimeException('Dozwolone archiwa modułów: .tar, .tar.gz, .tgz oraz .zip.');
    }

    private function extract(string $archive, string $target, string $extension): void
    {
        if ($extension === 'zip') {
            if (!class_exists(ZipArchive::class)) {
                $this->extractZipWithCli($archive, $target);
                $this->assertNoLinks($target);
                return;
            }
            $zip = new ZipArchive();
            if ($zip->open($archive) !== true) {
                throw new RuntimeException('Nie można otworzyć archiwum ZIP.');
            }
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $name = $zip->getNameIndex($index);
                if (!is_string($name)) {
                    continue;
                }
                $this->assertSafeArchivePath($name);
            }
            if (!$zip->extractTo($target)) {
                $zip->close();
                throw new RuntimeException('Nie można rozpakować archiwum ZIP.');
            }
            $zip->close();
        } else {
            $phar = new PharData($archive);
            $prefix = 'phar://' . $archive . '/';
            foreach (new \RecursiveIteratorIterator($phar) as $item) {
                $path = (string) $item->getPathName();
                $relative = str_starts_with($path, $prefix) ? substr($path, strlen($prefix)) : $path;
                $this->assertSafeArchivePath($relative);
            }
            $phar->extractTo($target, null, true);
        }
        $this->assertNoLinks($target);
    }

    private function extractZipWithCli(string $archive, string $target): void
    {
        $unzip = trim((string) shell_exec('command -v unzip 2>/dev/null'));
        if ($unzip === '') {
            throw new RuntimeException('Serwer PHP nie ma ZipArchive ani narzędzia unzip.');
        }
        $listCommand = escapeshellarg($unzip) . ' -Z1 ' . escapeshellarg($archive) . ' 2>/dev/null';
        $listing = shell_exec($listCommand);
        if (!is_string($listing) || trim($listing) === '') {
            throw new RuntimeException('Nie można odczytać listy plików archiwum ZIP.');
        }
        foreach (preg_split('/\R/', trim($listing)) ?: [] as $path) {
            $this->assertSafeArchivePath($path);
        }

        $extractCommand = escapeshellarg($unzip) . ' -qq ' . escapeshellarg($archive) . ' -d ' . escapeshellarg($target) . ' 2>/dev/null';
        exec($extractCommand, $output, $code);
        if ($code !== 0) {
            throw new RuntimeException('Nie można rozpakować archiwum ZIP.');
        }
    }

    private function locatePackage(string $sourceDirectory): string
    {
        if (is_file($sourceDirectory . '/info.json')) {
            return $sourceDirectory;
        }
        $candidates = [];
        foreach (glob(rtrim($sourceDirectory, '/') . '/*/info.json') ?: [] as $file) {
            $candidates[] = dirname($file);
        }
        if (count($candidates) !== 1) {
            throw new RuntimeException('Archiwum musi zawierać dokładnie jeden katalog modułu z info.json.');
        }

        return $candidates[0];
    }

    private function assertSafeArchivePath(string $path): void
    {
        $path = str_replace('\\', '/', $path);
        if ($path === '' || str_starts_with($path, '/') || str_contains($path, '../') || str_contains($path, '/..')) {
            throw new RuntimeException('Archiwum zawiera niebezpieczną ścieżkę.');
        }
        $segments = explode('/', trim($path, '/'));
        foreach ($segments as $index => $segment) {
            $isEnvExample = $index === count($segments) - 1 && $segment === '.env.example';
            if (
                $segment === ''
                || preg_match('/[\x00-\x1F\x7F]/', $segment) === 1
                || (str_starts_with($segment, '.') && !$isEnvExample)
            ) {
                throw new RuntimeException('Archiwum zawiera ukryty albo pusty segment ścieżki.');
            }
        }
    }

    private function assertNoLinks(string $directory): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isLink()) {
                throw new RuntimeException('Archiwum modułu nie może zawierać dowiązań symbolicznych.');
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
        foreach ($iterator as $file) {
            $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
        }
        @rmdir($directory);
    }
}
