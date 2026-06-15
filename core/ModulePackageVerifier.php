<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

use JsonException;
use RuntimeException;

final class ModulePackageVerifier
{
    /**
     * @param array<string, array{name: string, public_key: string}> $trustedPublishers
     */
    public function __construct(
        private readonly array $trustedPublishers = [],
    ) {
    }

    /**
     * @return array{key_id: string, status: string}
     */
    public function verify(
        string $directory,
        string $signatureFile,
        string $moduleId,
        string $version,
        string $originType,
        string $originUrl,
    ): array {
        if (basename($signatureFile) !== $signatureFile || !str_ends_with($signatureFile, '.json')) {
            throw new RuntimeException('Nazwa pliku podpisu pakietu jest nieprawidłowa.');
        }
        $path = rtrim($directory, '/') . '/' . $signatureFile;
        if (!is_file($path)) {
            throw new RuntimeException("Nie znaleziono pliku podpisu {$signatureFile}.");
        }

        try {
            $data = json_decode((string) file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException("Plik podpisu {$signatureFile} ma nieprawidłowy JSON.", 0, $exception);
        }
        if (!is_array($data)) {
            throw new RuntimeException("Plik podpisu {$signatureFile} musi być obiektem JSON.");
        }
        $algorithm = (string) ($data['algorithm'] ?? '');
        $keyId = (string) ($data['key_id'] ?? '');
        $signature = (string) ($data['signature'] ?? '');
        $files = $data['files'] ?? null;
        if ($algorithm !== 'rsa-sha256' || preg_match('/^[a-z0-9][a-z0-9._-]{2,80}$/i', $keyId) !== 1) {
            throw new RuntimeException('Algorytm albo identyfikator klucza podpisu jest nieprawidłowy.');
        }
        if (!is_array($files) || $signature === '') {
            throw new RuntimeException('Plik podpisu nie zawiera mapy plików lub podpisu.');
        }

        $actualFiles = self::packageFiles($directory, $signatureFile);
        if ($actualFiles !== $files) {
            throw new RuntimeException('Mapa SHA-256 pakietu nie odpowiada jego aktualnej zawartości.');
        }
        $publisher = $this->trustedPublishers[$keyId] ?? null;
        if (!is_array($publisher) || trim((string) ($publisher['public_key'] ?? '')) === '') {
            return ['key_id' => $keyId, 'status' => 'untrusted'];
        }

        $decodedSignature = base64_decode($signature, true);
        if (!is_string($decodedSignature)) {
            throw new RuntimeException('Podpis pakietu nie jest poprawnym Base64.');
        }
        $payload = self::payload($moduleId, $version, $originType, $originUrl, $actualFiles);
        $verified = openssl_verify(
            $payload,
            $decodedSignature,
            (string) $publisher['public_key'],
            OPENSSL_ALGO_SHA256
        );
        if ($verified !== 1) {
            throw new RuntimeException('Podpis kryptograficzny pakietu jest nieprawidłowy.');
        }

        return ['key_id' => $keyId, 'status' => 'verified'];
    }

    /**
     * @return array<string, string>
     */
    public static function packageFiles(string $directory, string $signatureFile): array
    {
        $directory = rtrim($directory, '/');
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isLink()) {
                throw new RuntimeException('Pakiet nie może zawierać dowiązań symbolicznych.');
            }
            if (!$file->isFile()) {
                continue;
            }
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($directory) + 1));
            if ($relative === $signatureFile) {
                continue;
            }
            foreach (explode('/', $relative) as $segment) {
                if (str_starts_with($segment, '.')) {
                    throw new RuntimeException('Pakiet nie może zawierać ukrytych plików ani katalogów.');
                }
            }
            $checksum = hash_file('sha256', $file->getPathname());
            if (!is_string($checksum)) {
                throw new RuntimeException("Nie można obliczyć SHA-256 pliku {$relative}.");
            }
            $files[$relative] = $checksum;
        }
        ksort($files, SORT_STRING);

        return $files;
    }

    /**
     * @param array<string, string> $files
     */
    public static function payload(
        string $moduleId,
        string $version,
        string $originType,
        string $originUrl,
        array $files,
    ): string {
        return json_encode([
            'module_id' => $moduleId,
            'version' => $version,
            'origin' => ['type' => $originType, 'url' => $originUrl],
            'files' => $files,
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
