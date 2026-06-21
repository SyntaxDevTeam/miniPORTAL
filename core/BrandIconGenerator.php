<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

use RuntimeException;

final class BrandIconGenerator
{
    private const OUTPUT_FILES = [
        'favicon-16x16.png', 'favicon-32x32.png', 'favicon-48x48.png',
        'favicon-64x64.png', 'favicon-96x96.png', 'favicon-128x128.png',
        'favicon-256x256.png', 'favicon.ico', 'apple-touch-icon.png',
        'icon-192.png', 'icon-512.png', 'icon-maskable-512.png', 'site.webmanifest',
    ];

    public function __construct(
        private readonly string $root,
        private readonly string $outputDirectory,
        private readonly int $maxBytes = 8_388_608,
    ) {
    }

    /** @param array{name:string,type:string,tmp_name:string,error:int,size:int} $file */
    public function generate(array $file, string $appName = 'miniPORTAL', string $themeColor = '#080c12'): void
    {
        if ($file['error'] !== UPLOAD_ERR_OK || !is_file($file['tmp_name']) || $file['size'] < 1 || $file['size'] > $this->maxBytes) {
            throw new RuntimeException('Wgraj poprawny plik PNG o rozmiarze do 8 MiB.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $size = @getimagesize($file['tmp_name']);
        if ($mime !== 'image/png' || !is_array($size) || ($size[2] ?? null) !== IMAGETYPE_PNG) {
            throw new RuntimeException('Generator przyjmuje wyłącznie prawidłowy obraz PNG.');
        }
        $width = (int) ($size[0] ?? 0);
        $height = (int) ($size[1] ?? 0);
        if ($width < 512 || $height < 512 || $width > 4096 || $height > 4096) {
            throw new RuntimeException('Ikona źródłowa musi mieć od 512×512 do 4096×4096 px.');
        }
        if (!function_exists('proc_open')) {
            throw new RuntimeException('Serwer nie udostępnia procesu wymaganego przez generator ikon.');
        }

        $parent = dirname($this->outputDirectory);
        if (!is_dir($parent) && !mkdir($parent, 02770, true) && !is_dir($parent)) {
            throw new RuntimeException('Nie można utworzyć katalogu brandingu.');
        }
        $temporary = $parent . '/.favicon-' . bin2hex(random_bytes(6));
        if (!mkdir($temporary, 02770, true)) {
            throw new RuntimeException('Nie można przygotować generatora ikon.');
        }
        try {
            $command = [
                'node',
                $this->root . '/tools/generate-brand-assets.mjs',
                '--favicon-source',
                $file['tmp_name'],
                '--output-dir',
                $temporary,
                '--app-name',
                trim($appName) !== '' ? substr(trim($appName), 0, 80) : 'miniPORTAL',
                '--theme-color',
                preg_match('/^#[0-9a-fA-F]{6}$/', $themeColor) === 1 ? strtolower($themeColor) : '#080c12',
            ];
            $pipes = [];
            $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $this->root);
            if (!is_resource($process)) {
                throw new RuntimeException('Nie można uruchomić generatora ikon.');
            }
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exit = proc_close($process);
            if ($exit !== 0) {
                throw new RuntimeException('Generator odrzucił PNG: ' . trim((string) ($stderr ?: $stdout)));
            }
            foreach (self::OUTPUT_FILES as $name) {
                if (!is_file($temporary . '/' . $name) || filesize($temporary . '/' . $name) < 1) {
                    throw new RuntimeException('Generator nie utworzył kompletnego zestawu ikon.');
                }
            }
            if (!is_dir($this->outputDirectory) && !mkdir($this->outputDirectory, 02770, true) && !is_dir($this->outputDirectory)) {
                throw new RuntimeException('Nie można utworzyć docelowego katalogu ikon.');
            }
            foreach (self::OUTPUT_FILES as $name) {
                if (!rename($temporary . '/' . $name, $this->outputDirectory . '/' . $name)) {
                    throw new RuntimeException('Nie można zatwierdzić wygenerowanej ikony.');
                }
                @chmod($this->outputDirectory . '/' . $name, 0660);
            }
        } finally {
            foreach (glob($temporary . '/*') ?: [] as $filePath) @unlink($filePath);
            @rmdir($temporary);
        }
    }
}
