<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

use JsonException;
use RuntimeException;

final class PlatformReleaseRepository
{
    public function __construct(
        private readonly string $releasesPath,
        private readonly string $catalogUrl = '',
        private readonly string $downloadPath = '',
        private readonly int $maxArchiveBytes = 52428800,
    ) {
    }

    /**
     * @return list<array{
     *     version:string,
     *     released_at:string,
     *     minimum_version:string,
     *     filename:string,
     *     checksum:string,
     *     changelog:list<string>
     * }>
     */
    public function all(): array
    {
        $catalog = trim($this->catalogUrl) !== ''
            ? $this->downloadString($this->catalogUrl, 1048576)
            : $this->localCatalog();

        try {
            $data = json_decode($catalog, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Katalog wydań miniPORTAL ma nieprawidłowy JSON.', 0, $exception);
        }
        if (!is_array($data) || !is_array($data['releases'] ?? null)) {
            throw new RuntimeException('Katalog wydań miniPORTAL ma nieprawidłową strukturę.');
        }

        $releases = [];
        foreach ($data['releases'] as $release) {
            if (!is_array($release)) {
                throw new RuntimeException('Katalog wydań zawiera nieprawidłowy wpis.');
            }
            $version = (string) ($release['version'] ?? '');
            $minimumVersion = (string) ($release['minimum_version'] ?? '0.0.0');
            $filename = (string) ($release['filename'] ?? '');
            $checksum = (string) ($release['checksum'] ?? '');
            $changelog = $release['changelog'] ?? [];
            if (
                preg_match('/^\d+\.\d+\.\d+(?:-[a-z0-9.-]+)?$/i', $version) !== 1
                || preg_match('/^\d+\.\d+\.\d+(?:-[a-z0-9.-]+)?$/i', $minimumVersion) !== 1
                || basename($filename) !== $filename
                || !str_ends_with(strtolower($filename), '.zip')
                || preg_match('/^[a-f0-9]{64}$/', $checksum) !== 1
                || !is_array($changelog)
            ) {
                throw new RuntimeException("Wpis wydania {$version} jest nieprawidłowy.");
            }
            $items = [];
            foreach ($changelog as $item) {
                if (!is_string($item) || trim($item) === '' || strlen($item) > 500) {
                    throw new RuntimeException("Lista zmian wydania {$version} jest nieprawidłowa.");
                }
                $items[] = trim($item);
            }
            $releases[] = [
                'version' => $version,
                'released_at' => (string) ($release['released_at'] ?? ''),
                'minimum_version' => $minimumVersion,
                'filename' => $filename,
                'checksum' => $checksum,
                'changelog' => $items,
            ];
        }
        usort($releases, static fn (array $left, array $right): int => version_compare(
            $right['version'],
            $left['version']
        ));

        return $releases;
    }

    public function latestFor(string $currentVersion): ?array
    {
        foreach ($this->all() as $release) {
            if (
                version_compare($release['version'], $currentVersion, '>')
                && version_compare($currentVersion, $release['minimum_version'], '>=')
            ) {
                return $release;
            }
        }

        return null;
    }

    public function find(string $version): ?array
    {
        foreach ($this->all() as $release) {
            if ($release['version'] === $version) {
                return $release;
            }
        }

        return null;
    }

    public function findByFilename(string $filename): ?array
    {
        if (basename($filename) !== $filename) {
            return null;
        }
        foreach ($this->all() as $release) {
            if ($release['filename'] === $filename) {
                return $release;
            }
        }

        return null;
    }

    public function usesRemoteCatalog(): bool
    {
        return trim($this->catalogUrl) !== '';
    }

    public function archivePath(array $release): string
    {
        $local = rtrim($this->releasesPath, '/') . '/' . $release['filename'];
        if (is_file($local)) {
            return $local;
        }
        if (trim($this->catalogUrl) === '' || $this->downloadPath === '') {
            return $local;
        }

        $directory = rtrim($this->downloadPath, '/');
        if (!is_dir($directory) && !mkdir($directory, 0770, true)) {
            throw new RuntimeException('Nie można utworzyć cache pobieranych wydań.');
        }
        $target = $directory . '/' . $release['filename'];
        if (is_file($target)) {
            $checksum = hash_file('sha256', $target);
            if (is_string($checksum) && hash_equals($release['checksum'], $checksum)) {
                return $target;
            }
            @unlink($target);
        }
        $baseUrl = substr($this->catalogUrl, 0, (int) strrpos($this->catalogUrl, '/') + 1);
        $url = $baseUrl . rawurlencode($release['filename']);
        $temporary = $target . '.download-' . bin2hex(random_bytes(4));
        try {
            $this->downloadFile($url, $temporary, $this->maxArchiveBytes);
            $checksum = hash_file('sha256', $temporary);
            if (!is_string($checksum) || !hash_equals($release['checksum'], $checksum)) {
                throw new RuntimeException('Pobrane wydanie ma nieprawidłową sumę SHA-256.');
            }
            if (!rename($temporary, $target)) {
                throw new RuntimeException('Nie można zatwierdzić pobranego wydania.');
            }
            @chmod($target, 0660);

            return $target;
        } finally {
            @unlink($temporary);
        }
    }

    private function localCatalog(): string
    {
        $catalog = rtrim($this->releasesPath, '/') . '/catalog.json';

        return is_file($catalog) ? (string) file_get_contents($catalog) : '{"releases":[]}';
    }

    private function downloadString(string $url, int $maxBytes): string
    {
        $temporary = tempnam(sys_get_temp_dir(), 'miniportal-catalog-');
        if (!is_string($temporary)) {
            throw new RuntimeException('Nie można przygotować pobierania katalogu wydań.');
        }
        try {
            $this->downloadFile($url, $temporary, $maxBytes);

            return (string) file_get_contents($temporary);
        } finally {
            @unlink($temporary);
        }
    }

    private function downloadFile(string $url, string $target, int $maxBytes): void
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false || parse_url($url, PHP_URL_SCHEME) !== 'https') {
            throw new RuntimeException('Katalog wydań musi korzystać z poprawnego adresu HTTPS.');
        }
        $context = stream_context_create([
            'http' => ['timeout' => 20, 'follow_location' => 0, 'ignore_errors' => false],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $input = @fopen($url, 'rb', false, $context);
        if (!is_resource($input)) {
            throw new RuntimeException('Nie można pobrać katalogu albo archiwum wydania.');
        }
        $output = fopen($target, 'wb');
        if (!is_resource($output)) {
            fclose($input);
            throw new RuntimeException('Nie można zapisać pobieranego wydania.');
        }
        $written = 0;
        try {
            while (!feof($input)) {
                $chunk = fread($input, 65536);
                if (!is_string($chunk)) {
                    throw new RuntimeException('Błąd odczytu pobieranego wydania.');
                }
                $written += strlen($chunk);
                if ($written > $maxBytes) {
                    throw new RuntimeException('Pobierane wydanie przekracza dozwolony rozmiar.');
                }
                if ($chunk !== '' && fwrite($output, $chunk) !== strlen($chunk)) {
                    throw new RuntimeException('Nie można zapisać całego pobieranego wydania.');
                }
            }
        } finally {
            fclose($input);
            fclose($output);
        }
    }
}
