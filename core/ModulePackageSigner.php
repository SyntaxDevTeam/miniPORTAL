<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

use JsonException;
use RuntimeException;

final class ModulePackageSigner
{
    public function __construct(
        private readonly string $privateKeyFile,
        private readonly string $keyId,
    ) {
    }

    public function sign(string $directory): string
    {
        if (!is_readable($this->privateKeyFile)) {
            throw new RuntimeException('Klucz prywatny wydawcy modułów nie jest dostępny.');
        }
        if (preg_match('/^[a-z0-9][a-z0-9._-]{2,80}$/i', $this->keyId) !== 1) {
            throw new RuntimeException('Identyfikator klucza wydawcy modułów jest nieprawidłowy.');
        }

        $manifestFile = rtrim($directory, '/') . '/info.json';
        try {
            $manifest = json_decode((string) file_get_contents($manifestFile), true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Nie można odczytać manifestu podpisywanego modułu.', 0, $exception);
        }
        if (!is_array($manifest)) {
            throw new RuntimeException('Manifest podpisywanego modułu musi być obiektem JSON.');
        }

        $origin = is_array($manifest['origin'] ?? null) ? $manifest['origin'] : [];
        $originType = trim((string) ($origin['type'] ?? 'unspecified'));
        $originUrl = trim((string) ($origin['url'] ?? ''));
        if ($originType === 'unspecified' || $originUrl === '') {
            throw new RuntimeException('Automatyczny podpis wymaga jawnego pochodzenia i URL w info.json.');
        }

        $signatureFile = (string) ($manifest['signature'] ?? 'signature.json');
        if (basename($signatureFile) !== $signatureFile || !str_ends_with($signatureFile, '.json')) {
            throw new RuntimeException('Nazwa pliku podpisu modułu jest nieprawidłowa.');
        }
        $manifest['signature'] = $signatureFile;
        $encodedManifest = json_encode(
            $manifest,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        ) . PHP_EOL;
        if (file_put_contents($manifestFile, $encodedManifest, LOCK_EX) === false) {
            throw new RuntimeException('Nie można przygotować manifestu do podpisania.');
        }

        $files = ModulePackageVerifier::packageFiles($directory, $signatureFile);
        $signedAt = gmdate('Y-m-d\TH:i:s+00:00');
        $payload = ModulePackageVerifier::payload(
            (string) ($manifest['id'] ?? ''),
            (string) ($manifest['version'] ?? ''),
            $originType,
            $originUrl,
            $signedAt,
            $files
        );
        $privateKey = openssl_pkey_get_private((string) file_get_contents($this->privateKeyFile));
        if ($privateKey === false || !openssl_sign($payload, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Nie można podpisać eksportowanego pakietu modułu.');
        }

        $document = json_encode([
            'algorithm' => 'rsa-sha256',
            'key_id' => $this->keyId,
            'signed_at' => $signedAt,
            'files' => $files,
            'signature' => base64_encode($signature),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
        if (file_put_contents(rtrim($directory, '/') . '/' . $signatureFile, $document, LOCK_EX) === false) {
            throw new RuntimeException('Nie można zapisać podpisu eksportowanego modułu.');
        }

        return $signatureFile;
    }
}
