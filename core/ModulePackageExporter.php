<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

use RuntimeException;
use ZipArchive;

final class ModulePackageExporter
{
    /**
     * @return array{path: string, filename: string, mime: string}
     */
    public function exportZip(ModuleManifest $manifest, string $targetDirectory): array
    {
        $this->assertExportableDirectory($manifest->directory);
        if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0770, true)) {
            throw new RuntimeException('Nie można utworzyć katalogu eksportu modułów.');
        }

        $filename = $manifest->id . '-' . $manifest->version . '.zip';
        $target = rtrim($targetDirectory, '/') . '/' . $filename;
        @unlink($target);

        if (class_exists(ZipArchive::class)) {
            $this->exportWithZipArchive($manifest->directory, $target);
        } else {
            $this->exportWithCli($manifest->directory, $target);
        }
        @chmod($target, 0660);

        return [
            'path' => $target,
            'filename' => $filename,
            'mime' => 'application/zip',
        ];
    }

    private function exportWithZipArchive(string $directory, string $target): void
    {
        $zip = new ZipArchive();
        if ($zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Nie można utworzyć archiwum ZIP modułu.');
        }

        $baseDirectory = basename($directory);
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $relative = substr((string) $file->getPathName(), strlen(rtrim($directory, '/')) + 1);
            $archivePath = $baseDirectory . '/' . str_replace('\\', '/', $relative);
            if (!$zip->addFile((string) $file->getPathName(), $archivePath)) {
                $zip->close();
                throw new RuntimeException('Nie można dodać pliku do archiwum ZIP modułu.');
            }
        }

        if (!$zip->close()) {
            throw new RuntimeException('Nie można zamknąć archiwum ZIP modułu.');
        }
    }

    private function exportWithCli(string $directory, string $target): void
    {
        $zip = trim((string) shell_exec('command -v zip 2>/dev/null'));
        if ($zip === '') {
            throw new RuntimeException('Serwer PHP nie ma ZipArchive ani narzędzia zip.');
        }

        $parent = dirname($directory);
        $base = basename($directory);
        $command = 'cd ' . escapeshellarg($parent)
            . ' && ' . escapeshellarg($zip)
            . ' -qr ' . escapeshellarg($target)
            . ' ' . escapeshellarg($base)
            . ' 2>/dev/null';
        exec($command, $output, $code);
        if ($code !== 0 || !is_file($target)) {
            throw new RuntimeException('Nie można utworzyć archiwum ZIP modułu.');
        }
    }

    private function assertExportableDirectory(string $directory): void
    {
        if (!is_dir($directory) || is_link($directory) || !is_file($directory . '/info.json')) {
            throw new RuntimeException('Katalog modułu nie jest poprawnym pakietem do eksportu.');
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isLink()) {
                throw new RuntimeException('Eksport modułu z dowiązaniami symbolicznymi jest zablokowany.');
            }
            $relative = substr((string) $file->getPathName(), strlen(rtrim($directory, '/')) + 1);
            foreach (explode('/', str_replace('\\', '/', $relative)) as $segment) {
                if ($segment === '' || str_starts_with($segment, '.')) {
                    throw new RuntimeException('Eksport modułu z ukrytymi ścieżkami jest zablokowany.');
                }
            }
        }
    }
}
