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
        if (!is_dir($this->directory) && !mkdir($this->directory, 0770, true) && !is_dir($this->directory)) {
            throw new \RuntimeException('Nie można utworzyć katalogu biblioteki grafik.');
        }
        if (!is_writable($this->directory)) {
            throw new \RuntimeException('Katalog biblioteki grafik nie jest zapisywalny.');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!is_string($mime) || !isset(self::MIME_EXTENSIONS[$mime])) {
            throw new \RuntimeException('Dozwolone są tylko PNG, JPG, WEBP i GIF.');
        }

        $extension = self::MIME_EXTENSIONS[$mime];
        $storedName = date('YmdHis') . '-' . bin2hex(random_bytes(8)) . '.' . $extension;
        $target = rtrim($this->directory, '/') . '/' . $storedName;
        $moved = is_uploaded_file($file['tmp_name'])
            ? move_uploaded_file($file['tmp_name'], $target)
            : rename($file['tmp_name'], $target);
        if (!$moved) {
            throw new \RuntimeException('Nie udało się zapisać pliku grafiki.');
        }
        chmod($target, 0660);
        $this->optimizer?->optimize($target);

        [$width, $height] = $this->dimensions($target, $mime);

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
