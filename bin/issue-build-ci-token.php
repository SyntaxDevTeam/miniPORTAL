<?php

declare(strict_types=1);

use SyntaxDevTeam\Cms\Core\Autoloader;
use SyntaxDevTeam\Cms\Database\CrudApp;
use SyntaxDevTeam\Cms\Modules\BuildExplorer\BuildCiPolicy;
use SyntaxDevTeam\Cms\Modules\BuildExplorer\BuildRepository;

if (PHP_SAPI !== 'cli') { http_response_code(404); exit(1); }
require dirname(__DIR__) . '/core/Autoloader.php';
Autoloader::register();
$project = $argv[1] ?? '';
$secretFile = $argv[2] ?? '';
if (preg_match('/^[a-z0-9][a-z0-9-]{0,190}$/D', $project) !== 1 || $secretFile === '' || file_exists($secretFile)) {
    fwrite(STDERR, "Usage: php bin/issue-build-ci-token.php PROJECT NEW_SECRET_FILE [--rotate]\n"); exit(2);
}
try {
    $config = require dirname(__DIR__) . '/config/config.php';
    $databaseConfig = $config['database']; unset($databaseConfig['enabled']);
    $database = CrudApp::getInstance($databaseConfig);
    if ((new BuildRepository($database))->projectBySlug($project) === null) {
        throw new RuntimeException('Create the project in Projects before issuing CI credentials.');
    }
    $directory = dirname(__DIR__) . '/config/modules';
    if (!is_dir($directory) && !mkdir($directory, 0770, true)) { throw new RuntimeException('Cannot create configuration directory.'); }
    $lock = fopen($directory . '/build-ci-projects.lock', 'c');
    if ($lock === false || !flock($lock, LOCK_EX)) { throw new RuntimeException('Cannot lock CI policy configuration.'); }
    try {
        $path = $directory . '/build-ci-projects.json';
        $policies = is_file($path) ? json_decode((string) file_get_contents($path), true, 16, JSON_THROW_ON_ERROR) : [];
        if (!is_array($policies)) { throw new RuntimeException('Invalid policy configuration.'); }
        if (isset($policies[$project]) && !in_array('--rotate', $argv, true)) {
            throw new RuntimeException('Credential exists; explicit --rotate is required.');
        }
        $token = bin2hex(random_bytes(32));
        $secret = fopen($secretFile, 'x');
        if ($secret === false) { throw new RuntimeException('Cannot create secret transfer file.'); }
        chmod($secretFile, 0600);
        fwrite($secret, $token); fclose($secret);
        $policies[$project] = ['token_sha256' => hash('sha256', $token),
            'extensions' => BuildCiPolicy::NATIVE_EXTENSIONS, 'max_bytes' => BuildCiPolicy::NATIVE_MAX_BYTES];
        $temporary = tempnam($directory, '.build-ci-');
        if ($temporary === false) { throw new RuntimeException('Cannot stage CI configuration.'); }
        try {
            file_put_contents($temporary, json_encode($policies, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
            chmod($temporary, 0640);
            if (!rename($temporary, $path)) { throw new RuntimeException('Cannot publish CI configuration.'); }
        } finally { if (is_file($temporary)) { unlink($temporary); } }
    } finally { flock($lock, LOCK_UN); fclose($lock); }
    echo "Project-scoped application credential created. Transfer the secret to CI and remove its transfer file.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n"); exit(1);
}
