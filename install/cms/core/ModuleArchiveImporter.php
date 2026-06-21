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

    /**
     * @return array{directory: string, manifest: ModuleManifest}
     */
    public function approve(string $importDirectory, string $modulesPath): array
    {
        if (preg_match('/^import-\d{8}-\d{6}-[a-f0-9]{8}$/', $importDirectory) !== 1) {
            throw new RuntimeException('Identyfikator importu z kwarantanny jest nieprawidłowy.');
        }

        $quarantine = rtrim($this->quarantinePath, '/');
        $importPath = $quarantine . '/' . $importDirectory;
        if (!is_dir($importPath) || is_link($importPath)) {
            throw new RuntimeException('Nie znaleziono wskazanego importu w kwarantannie.');
        }

        $packageDirectory = $this->locatePackage($importPath . '/source');
        $this->assertNoLinks($packageDirectory);
        $manifest = $this->validator->validate($packageDirectory);
        if ($manifest->type !== 'extension' || $manifest->protected) {
            throw new RuntimeException('Z kwarantanny można zatwierdzać wyłącznie niechronione rozszerzenia.');
        }
        if (!in_array($manifest->signatureStatus, ['verified', 'verified_retired'], true)) {
            throw new RuntimeException('Zatwierdzenie wymaga poprawnego podpisu zaufanego wydawcy.');
        }

        $packageName = basename($packageDirectory);
        if (preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,63}$/', $packageName) !== 1) {
            throw new RuntimeException('Nazwa katalogu pakietu jest nieprawidłowa.');
        }
        if (!is_dir($modulesPath) || !is_writable($modulesPath)) {
            throw new RuntimeException('Aktywny katalog modułów nie jest zapisywalny.');
        }

        $target = rtrim($modulesPath, '/') . '/' . $packageName;
        if (file_exists($target) || is_link($target)) {
            throw new RuntimeException("Katalog modułu {$packageName} już istnieje.");
        }
        foreach (glob(rtrim($modulesPath, '/') . '/*/info.json') ?: [] as $manifestFile) {
            $existing = $this->validator->inspect(dirname($manifestFile))['manifest'];
            if ($existing !== null && $existing->id === $manifest->id) {
                throw new RuntimeException("Moduł o identyfikatorze {$manifest->id} już istnieje.");
            }
        }

        if (!rename($packageDirectory, $target)) {
            throw new RuntimeException('Nie można atomowo przenieść pakietu do katalogu modules/.');
        }
        @chmod($target, 0770);
        $approvedManifest = $this->validator->validate($target);
        $this->removeDirectory($importPath);

        return ['directory' => $target, 'manifest' => $approvedManifest];
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
        foreach (explode('/', trim($path, '/')) as $segment) {
            if ($segment === '' || str_starts_with($segment, '.')) {
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
