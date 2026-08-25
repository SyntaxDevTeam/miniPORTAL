<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\BuildExplorer;

use RuntimeException;

final class BuildArtifactStorage
{
    private const DEFAULT_RESERVE_BYTES = 5368709120;

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
        $this->assertFilesystemReserve((int) $file['size'], self::DEFAULT_RESERVE_BYTES);

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

    public function delete(?string $key): bool
    {
        if ($key === null || $key === '') { return false; }
        $path = $this->path($key);
        return $path !== null && @unlink($path);
    }

    public function sizeForKey(string $key): int
    {
        $path = $this->path($key);
        if ($path === null) { return 0; }
        $size = filesize($path);
        return $size === false ? 0 : max(0, (int) $size);
    }

    /** @return list<string> */
    public function storedKeys(): array
    {
        $keys = [];
        $iterator = new \FilesystemIterator($this->directory, \FilesystemIterator::SKIP_DOTS);
        foreach ($iterator as $entry) {
            if (!$entry->isFile() || $entry->isLink()) { continue; }
            $name = $entry->getFilename();
            if (preg_match('/^[a-f0-9]{48}\.jar$/', $name) === 1) {
                $keys[] = $name;
            }
        }
        sort($keys, SORT_STRING);
        return $keys;
    }

    /** @param list<string> $registeredKeys @return list<string> */
    public function orphanKeys(array $registeredKeys, int $minAgeSeconds = 86400): array
    {
        $registered = array_fill_keys($registeredKeys, true);
        $cutoff = time() - max(0, $minAgeSeconds);
        $orphans = [];
        foreach ($this->storedKeys() as $key) {
            if (isset($registered[$key])) { continue; }
            $path = $this->path($key);
            if ($path === null) { continue; }
            $modified = filemtime($path);
            if ($modified !== false && $modified <= $cutoff) {
                $orphans[] = $key;
            }
        }
        return $orphans;
    }

    public function assertCapacity(int $incomingBytes, int $hardLimitBytes, int $reserveBytes): void
    {
        $incomingBytes = max(0, $incomingBytes);
        $hardLimitBytes = max(1, $hardLimitBytes);
        $reserveBytes = max(0, $reserveBytes);
        $stats = $this->statistics();
        if ($stats['bytes'] + $incomingBytes > $hardLimitBytes) {
            throw new BuildStorageCapacityException(
                'Build storage hard limit would be exceeded.',
                [
                    'storage_bytes' => $stats['bytes'],
                    'incoming_bytes' => $incomingBytes,
                    'hard_limit_bytes' => $hardLimitBytes,
                    'disk_free_bytes' => $stats['disk_free_bytes'],
                    'reserve_bytes' => $reserveBytes,
                ]
            );
        }
        $this->assertFilesystemReserve($incomingBytes, $reserveBytes);
    }

    /** @return array{files: int, bytes: int, disk_free_bytes: int|null, disk_total_bytes: int|null} */
    public function statistics(): array
    {
        $files = 0;
        $bytes = 0;
        $iterator = new \FilesystemIterator($this->directory, \FilesystemIterator::SKIP_DOTS);
        foreach ($iterator as $entry) {
            if (!$entry->isFile() || $entry->isLink()) {
                continue;
            }
            $files++;
            $bytes += max(0, $entry->getSize());
        }

        $free = disk_free_space($this->directory);
        $total = disk_total_space($this->directory);

        return [
            'files' => $files,
            'bytes' => $bytes,
            'disk_free_bytes' => $free === false ? null : max(0, (int) $free),
            'disk_total_bytes' => $total === false ? null : max(0, (int) $total),
        ];
    }

    private function assertFilesystemReserve(int $incomingBytes, int $reserveBytes): void
    {
        $free = disk_free_space($this->directory);
        if ($free === false) { return; }
        $freeBytes = max(0, (int) $free);
        if ($freeBytes - max(0, $incomingBytes) < max(0, $reserveBytes)) {
            throw new BuildStorageCapacityException(
                'Build upload refused to preserve the filesystem free-space reserve.',
                [
                    'storage_bytes' => $this->statistics()['bytes'],
                    'incoming_bytes' => max(0, $incomingBytes),
                    'hard_limit_bytes' => null,
                    'disk_free_bytes' => $freeBytes,
                    'reserve_bytes' => max(0, $reserveBytes),
                ]
            );
        }
    }

    private static function segment(string $value): string
    {
        $value = trim($value);
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        $value = preg_replace('/\s+/', '-', $value) ?? '';
        return substr(preg_replace('/[^A-Za-z0-9._-]/', '', $value) ?? '', 0, 80);
    }
}
