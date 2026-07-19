<?php

declare(strict_types=1);

if ($argc < 2 || $argc > 3) {
    fwrite(STDERR, "Użycie: php bin/setup-module-signing.php KATALOG_KLUCZY [KEY_ID]\n");
    exit(1);
}

$directory = rtrim($argv[1], '/');
$keyId = $argv[2] ?? 'syntaxdevteam-modules-' . gmdate('Y');
if (preg_match('/^[a-z0-9][a-z0-9._-]{2,80}$/i', $keyId) !== 1) {
    fwrite(STDERR, "Identyfikator klucza jest nieprawidłowy.\n");
    exit(1);
}
if (!is_dir($directory) && !mkdir($directory, 0700, true)) {
    fwrite(STDERR, "Nie można utworzyć katalogu kluczy.\n");
    exit(1);
}

$privateFile = $directory . '/' . $keyId . '-private.pem';
$publicFile = $directory . '/' . $keyId . '-public.pem';
if (file_exists($privateFile) || file_exists($publicFile)) {
    fwrite(STDERR, "Pliki tego klucza już istnieją; przerwano bez nadpisywania.\n");
    exit(1);
}

$key = openssl_pkey_new([
    'private_key_bits' => 4096,
    'private_key_type' => OPENSSL_KEYTYPE_RSA,
]);
$details = $key !== false ? openssl_pkey_get_details($key) : false;
if (
    $key === false
    || !openssl_pkey_export($key, $privatePem)
    || !is_array($details)
    || !is_string($details['key'] ?? null)
) {
    fwrite(STDERR, "Nie można wygenerować pary kluczy RSA.\n");
    exit(1);
}
if (
    file_put_contents($privateFile, $privatePem, LOCK_EX) === false
    || file_put_contents($publicFile, $details['key'], LOCK_EX) === false
) {
    @unlink($privateFile);
    @unlink($publicFile);
    fwrite(STDERR, "Nie można zapisać wygenerowanych kluczy.\n");
    exit(1);
}
chmod($privateFile, 0640);
chmod($publicFile, 0644);

echo "Wygenerowano lokalnego wydawcę modułów.\n\n";
echo "Dodaj do pliku środowiskowego miniPORTAL:\n";
echo 'MODULE_SIGNING_KEY_ID="' . $keyId . "\"\n";
echo 'MODULE_SIGNING_PRIVATE_KEY_FILE="' . $privateFile . "\"\n";
echo 'MODULE_SIGNING_PUBLIC_KEY_FILE="' . $publicFile . "\"\n\n";
echo "Proces PHP musi móc odczytać klucz prywatny podczas eksportu.\n";
echo "Na instalacjach odbierających paczki wystarczą KEY_ID oraz PUBLIC_KEY_FILE.\n";
