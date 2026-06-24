<?php

declare(strict_types=1);

use SyntaxDevTeam\Cms\Core\Autoloader;
use SyntaxDevTeam\Cms\Core\ModulePackageSigner;

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
try {
    $manifest = json_decode((string) file_get_contents($directory . '/info.json'), true, 32, JSON_THROW_ON_ERROR);
    (new ModulePackageSigner($privateKeyFile, $keyId))->sign($directory);
} catch (\Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}
echo "Podpisano {$manifest['id']} {$manifest['version']} kluczem {$keyId}.\n";
