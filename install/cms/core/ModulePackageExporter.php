<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

use RuntimeException;
use ZipArchive;

final class ModulePackageExporter
{
    public function __construct(
        private readonly ?ModulePackageSigner $signer = null,
    ) {
    }

    /**
     * @return array{path: string, filename: string, mime: string}
     */
    public function exportZip(ModuleManifest $manifest, string $targetDirectory): array
    {
        $this->ensureExportDirectory($targetDirectory);
        $filename = $manifest->id . '-' . $manifest->version . '.zip';
        $target = rtrim($targetDirectory, '/') . '/' . $filename;
        @unlink($target);

        $sourceDirectory = $manifest->directory;
        $stagingDirectory = null;
        try {
            if ($this->signer !== null) {
                $stagingDirectory = rtrim($targetDirectory, '/') . '/.signing-' . bin2hex(random_bytes(8));
                $sourceDirectory = $stagingDirectory . '/' . basename($manifest->directory);
                $this->copyPackage($manifest->directory, $sourceDirectory);
                $this->signer->sign($sourceDirectory);
            }

            $files = $this->exportableFiles($sourceDirectory);
            if (class_exists(ZipArchive::class)) {
                $this->exportWithZipArchive($sourceDirectory, $target, $files);
            } else {
                $this->exportWithCli($sourceDirectory, $target, $files);
            }
        } finally {
            if ($stagingDirectory !== null) {
                $this->removeDirectory($stagingDirectory);
            }
        }
        @chmod($target, 0660);

        return [
            'path' => $target,
            'filename' => $filename,
            'mime' => 'application/zip',
        ];
    }

    private function copyPackage(string $source, string $target): void
    {
        $files = $this->exportableFiles($source);
        if (!mkdir($target, 0770, true) && !is_dir($target)) {
            throw new RuntimeException('Nie można utworzyć kopii roboczej podpisywanego modułu.');
        }
        foreach ($files as $relative) {
            $destination = $target . '/' . $relative;
            $parent = dirname($destination);
            if (!is_dir($parent) && !mkdir($parent, 0770, true) && !is_dir($parent)) {
                throw new RuntimeException('Nie można przygotować struktury podpisywanego modułu.');
            }
            if (!copy(rtrim($source, '/') . '/' . $relative, $destination)) {
                throw new RuntimeException("Nie można skopiować pliku {$relative} do podpisania.");
            }
        }
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
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

    private function ensureExportDirectory(string $targetDirectory): void
    {
        if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0770, true)) {
            throw new RuntimeException('Nie można utworzyć katalogu eksportu modułów.');
        }

        clearstatcache(true, $targetDirectory);
        if (!is_dir($targetDirectory) || !is_writable($targetDirectory)) {
            throw new RuntimeException('Katalog eksportu modułów nie jest zapisywalny.');
        }
    }

    /**
     * @param list<string> $files
     */
    private function exportWithZipArchive(string $directory, string $target, array $files): void
    {
        $zip = new ZipArchive();
        if ($zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Nie można utworzyć archiwum ZIP modułu.');
        }

        $baseDirectory = basename($directory);
        foreach ($files as $relative) {
            $archivePath = $baseDirectory . '/' . str_replace('\\', '/', $relative);
            if (!$zip->addFile(rtrim($directory, '/') . '/' . $relative, $archivePath)) {
                $zip->close();
                throw new RuntimeException('Nie można dodać pliku do archiwum ZIP modułu.');
            }
        }

        if (!$zip->close()) {
            throw new RuntimeException('Nie można zamknąć archiwum ZIP modułu.');
        }
    }

    /**
     * @param list<string> $files
     */
    private function exportWithCli(string $directory, string $target, array $files): void
    {
        $zip = trim((string) shell_exec('command -v zip 2>/dev/null'));
        if ($zip === '') {
            throw new RuntimeException('Serwer PHP nie ma ZipArchive ani narzędzia zip.');
        }

        $parent = dirname(rtrim($directory, '/'));
        $base = basename(rtrim($directory, '/'));
        $command = 'cd ' . escapeshellarg($parent)
            . ' && ' . escapeshellarg($zip)
            . ' -q ' . escapeshellarg($target)
            . ' -@ 2>/dev/null';
        $process = proc_open($command, [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException('Nie można uruchomić narzędzia zip.');
        }
        foreach ($files as $relative) {
            fwrite($pipes[0], $base . '/' . str_replace('\\', '/', $relative) . "\n");
        }
        fclose($pipes[0]);
        stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $code = proc_close($process);
        if ($code !== 0 || !is_file($target)) {
            throw new RuntimeException('Nie można utworzyć archiwum ZIP modułu.');
        }
    }

    /**
     * @return list<string>
     */
    private function exportableFiles(string $directory): array
    {
        if (!is_dir($directory) || is_link($directory) || !is_file($directory . '/info.json')) {
            throw new RuntimeException('Katalog modułu nie jest poprawnym pakietem do eksportu.');
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isLink()) {
                throw new RuntimeException('Eksport modułu z dowiązaniami symbolicznymi jest zablokowany.');
            }
            if (!$file->isFile()) {
                continue;
            }
            $relative = substr((string) $file->getPathName(), strlen(rtrim($directory, '/')) + 1);
            $segments = explode('/', str_replace('\\', '/', $relative));
            foreach ($segments as $index => $segment) {
                if ($segment === '' || preg_match('/[\x00-\x1F\x7F]/', $segment) === 1) {
                    throw new RuntimeException('Eksport modułu z ukrytymi ścieżkami jest zablokowany.');
                }
                if (str_starts_with($segment, '.')) {
                    $isExample = $index === count($segments) - 1 && $segment === '.env.example';
                    if (!$isExample) {
                        if ($index === count($segments) - 1 && $segment === '.env') {
                            continue 2;
                        }
                        throw new RuntimeException('Eksport modułu z ukrytymi ścieżkami jest zablokowany.');
                    }
                }
            }
            $files[] = $relative;
        }

        sort($files, SORT_STRING);

        return $files;
    }
}
