<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\BuildExplorer;

use RuntimeException;

final class BuildArtifactStorage
{
    public function __construct(
        private readonly string $directory,
        private readonly int $maxBytes = 20971520,
    ) {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0770, true)) {
            throw new RuntimeException('Nie można utworzyć magazynu buildów.');
        }
        @chmod($this->directory, 02770);
        if (!is_writable($this->directory)) {
            throw new RuntimeException('Magazyn buildów nie jest zapisywalny.');
        }
    }

    public static function filename(
        string $project,
        string $server,
        string $version,
        string $channel,
        string $buildNumber,
    ): string {
        $segments = array_map(self::segment(...), [$project, $server, $version, strtoupper($channel)]);
        if (in_array('', $segments, true)) {
            throw new RuntimeException('Nie można utworzyć nazwy pliku z podanych danych.');
        }
        $buildNumber = self::segment($buildNumber);
        if ($buildNumber !== '') { $segments[] = $buildNumber; }
        return implode('-', $segments) . '.jar';
    }

    /**
     * @param array{name: string, type: string, tmp_name: string, error: int, size: int} $file
     * @return array{storage_key: string, size: int, checksum: string}
     */
    public function store(array $file): array
    {
        if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] <= 0 || $file['size'] > $this->maxBytes) {
            throw new RuntimeException('Plik JAR jest pusty, uszkodzony albo przekracza limit uploadu.');
        }
        if (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'jar' || !is_file($file['tmp_name'])) {
            throw new RuntimeException('Dozwolone są wyłącznie pliki .jar.');
        }
        $handle = fopen($file['tmp_name'], 'rb');
        $signature = is_resource($handle) ? fread($handle, 4) : false;
        if (is_resource($handle)) { fclose($handle); }
        if (!is_string($signature) || !str_starts_with($signature, "PK")) {
            throw new RuntimeException('Plik JAR nie ma prawidłowej struktury ZIP.');
        }

        $key = bin2hex(random_bytes(24)) . '.jar';
        $temporary = $this->directory . '/upload-' . bin2hex(random_bytes(8));
        $moved = is_uploaded_file($file['tmp_name'])
            ? move_uploaded_file($file['tmp_name'], $temporary)
            : (PHP_SAPI === 'cli' && rename($file['tmp_name'], $temporary));
        if (!$moved || !rename($temporary, $this->directory . '/' . $key)) {
            @unlink($temporary);
            throw new RuntimeException('Nie można bezpiecznie zapisać pliku JAR.');
        }
        @chmod($this->directory . '/' . $key, 0660);
        $path = $this->directory . '/' . $key;
        $checksum = hash_file('sha256', $path);
        if (!is_string($checksum)) { @unlink($path); throw new RuntimeException('Nie można obliczyć SHA-256 pliku.'); }
        return ['storage_key' => $key, 'size' => (int) filesize($path), 'checksum' => $checksum];
    }

    public function path(string $key): ?string
    {
        if (preg_match('/^[a-f0-9]{48}\.jar$/', $key) !== 1) { return null; }
        $path = $this->directory . '/' . $key;
        return is_file($path) && !is_link($path) ? $path : null;
    }

    public function delete(?string $key): void
    {
        if ($key === null || $key === '') { return; }
        $path = $this->path($key);
        if ($path !== null) { @unlink($path); }
    }

    private static function segment(string $value): string
    {
        $value = trim($value);
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        $value = preg_replace('/\s+/', '-', $value) ?? '';
        return substr(preg_replace('/[^A-Za-z0-9._-]/', '', $value) ?? '', 0, 80);
    }
}
