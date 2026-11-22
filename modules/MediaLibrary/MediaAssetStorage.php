<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\MediaLibrary;

final class MediaAssetStorage
{
    private const MIME_EXTENSIONS = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'image/svg+xml' => 'svg',
    ];

    public function __construct(
        private readonly string $directory,
        private readonly int $maxBytes = 5242880,
        private readonly ?TinifyImageOptimizer $optimizer = null,
    ) {
    }

    /**
     * @param array{name:string,type:string,tmp_name:string,error:int,size:int} $file
     * @return array{original_name:string,stored_name:string,public_path:string,mime_type:string,file_size:int,width:?int,height:?int}
     */
    public function store(array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Nie wybrano poprawnego pliku grafiki.');
        }
        if ($file['size'] <= 0 || $file['size'] > $this->maxBytes) {
            throw new \RuntimeException('Plik grafiki jest pusty albo przekracza limit 5 MB.');
        }
        $actualSize = is_file($file['tmp_name']) ? filesize($file['tmp_name']) : false;
        if ($actualSize === false || $actualSize <= 0 || $actualSize > $this->maxBytes) {
            throw new \RuntimeException('Plik grafiki jest pusty albo przekracza limit 5 MB.');
        }
        if (!is_dir($this->directory) && !mkdir($this->directory, 0770, true) && !is_dir($this->directory)) {
            throw new \RuntimeException('Nie można utworzyć katalogu biblioteki grafik.');
        }
        if (!is_writable($this->directory)) {
            throw new \RuntimeException('Katalog biblioteki grafik nie jest zapisywalny.');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $svg = null;
        if ($mime === 'image/svg+xml' || (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) === 'svg' && in_array($mime, ['text/plain', 'text/xml', 'application/xml', 'application/octet-stream'], true))) {
            $source = file_get_contents($file['tmp_name']);
            $svg = (new SvgSanitizer())->sanitize(is_string($source) ? $source : '');
            if (strlen($svg['xml']) > $this->maxBytes) {
                throw new \RuntimeException('Plik grafiki jest pusty albo przekracza limit 5 MB.');
            }
            $mime = 'image/svg+xml';
        }
        if (!is_string($mime) || !isset(self::MIME_EXTENSIONS[$mime])) {
            throw new \RuntimeException('Dozwolone są tylko PNG, JPG, WEBP, GIF i SVG.');
        }
        if ($svg === null) {
            $image = @getimagesize($file['tmp_name']);
            if (!is_array($image) || ($image['mime'] ?? '') !== $mime) {
                throw new \RuntimeException('Plik nie zawiera poprawnego obrazu.');
            }
        }

        $extension = self::MIME_EXTENSIONS[$mime];
        $storedName = date('YmdHis') . '-' . bin2hex(random_bytes(8)) . '.' . $extension;
        $target = rtrim($this->directory, '/') . '/' . $storedName;
        $moved = $svg !== null
            ? file_put_contents($target, $svg['xml'], LOCK_EX) === strlen($svg['xml'])
            : (is_uploaded_file($file['tmp_name']) ? move_uploaded_file($file['tmp_name'], $target) : rename($file['tmp_name'], $target));
        if (!$moved) {
            if (is_file($target)) unlink($target);
            throw new \RuntimeException('Nie udało się zapisać pliku grafiki.');
        }
        // Public images must remain readable by the Git operator outside the PHP group.
        chmod($target, 0664);
        try {
            if ($svg === null) $this->optimizer?->optimize($target);
        } catch (\Throwable $exception) {
            unlink($target);
            throw $exception;
        }

        [$width, $height] = $svg !== null ? [$svg['width'], $svg['height']] : $this->dimensions($target, $mime);

        return [
            'original_name' => $file['name'],
            'stored_name' => $storedName,
            'public_path' => '/uploads/media/' . rawurlencode($storedName),
            'mime_type' => $mime,
            'file_size' => (int) filesize($target),
            'width' => $width,
            'height' => $height,
        ];
    }

    public function delete(string $storedName): void
    {
        $storedName = basename($storedName);
        if ($storedName === '') {
            return;
        }
        $path = rtrim($this->directory, '/') . '/' . $storedName;
        if (is_file($path)) {
            unlink($path);
        }
    }

    /** @return array{0:?int,1:?int} */
    private function dimensions(string $path, string $mime): array
    {
        $size = @getimagesize($path);
        if (!is_array($size)) {
            return [null, null];
        }

        return [(int) ($size[0] ?? 0) ?: null, (int) ($size[1] ?? 0) ?: null];
    }
}
