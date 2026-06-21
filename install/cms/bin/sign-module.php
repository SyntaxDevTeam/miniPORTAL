<?php

declare(strict_types=1);

use SyntaxDevTeam\Cms\Core\Autoloader;
use SyntaxDevTeam\Cms\Core\ModulePackageVerifier;

require_once dirname(__DIR__) . '/core/Autoloader.php';

Autoloader::register();

if ($argc !== 4) {
    fwrite(STDERR, "Użycie: php bin/sign-module.php KATALOG KLUCZ_PRYWATNY KEY_ID\n");
    exit(1);
}

$directory = realpath($argv[1]);
$privateKeyFile = $argv[2];
$keyId = $argv[3];
if ($directory === false || !is_file($directory . '/info.json') || !is_readable($privateKeyFile)) {
    fwrite(STDERR, "Katalog modułu albo klucz prywatny nie jest dostępny.\n");
    exit(1);
}
$manifest = json_decode((string) file_get_contents($directory . '/info.json'), true, 32, JSON_THROW_ON_ERROR);
$signatureFile = (string) ($manifest['signature'] ?? 'signature.json');
$origin = is_array($manifest['origin'] ?? null) ? $manifest['origin'] : [];
if (preg_match('/^[a-z0-9][a-z0-9._-]{2,80}$/i', $keyId) !== 1) {
    fwrite(STDERR, "Identyfikator klucza jest nieprawidłowy.\n");
    exit(1);
}
$files = ModulePackageVerifier::packageFiles($directory, $signatureFile);
$signedAt = gmdate('Y-m-d\TH:i:s+00:00');
$payload = ModulePackageVerifier::payload(
    (string) ($manifest['id'] ?? ''),
    (string) ($manifest['version'] ?? ''),
    (string) ($origin['type'] ?? 'unspecified'),
    (string) ($origin['url'] ?? ''),
    $signedAt,
    $files
);
$privateKey = openssl_pkey_get_private((string) file_get_contents($privateKeyFile));
if ($privateKey === false || !openssl_sign($payload, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
    fwrite(STDERR, "Nie można podpisać pakietu.\n");
    exit(1);
}
$document = json_encode([
    'algorithm' => 'rsa-sha256',
    'key_id' => $keyId,
    'signed_at' => $signedAt,
    'files' => $files,
    'signature' => base64_encode($signature),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
file_put_contents($directory . '/' . $signatureFile, $document);
echo "Podpisano {$manifest['id']} {$manifest['version']} kluczem {$keyId}.\n";
