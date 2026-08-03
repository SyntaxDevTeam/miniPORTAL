<?php

declare(strict_types=1);

use SyntaxDevTeam\Cms\Core\Autoloader;
use SyntaxDevTeam\Cms\Core\ModuleInstaller;
use SyntaxDevTeam\Cms\Core\ModuleManagerService;
use SyntaxDevTeam\Cms\Core\ModuleManifestValidator;
use SyntaxDevTeam\Cms\Core\ModulePackageSigner;
use SyntaxDevTeam\Cms\Core\ModuleStateRepository;
use SyntaxDevTeam\Cms\Core\ModuleUpdatePublisher;
use SyntaxDevTeam\Cms\Core\ModuleUpdateRefreshQueue;
use SyntaxDevTeam\Cms\Database\CrudApp;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

require_once dirname(__DIR__) . '/core/Autoloader.php';
Autoloader::register();

$root = dirname(__DIR__);
$repositoryPath = $root . '/cache/module-repository';
$queue = new ModuleUpdateRefreshQueue($repositoryPath);
$job = $queue->claim();
if ($job === null) {
    exit(0);
}

$jobId = (string) ($job['job_id'] ?? '');
$startedAt = hrtime(true);
try {
    $config = require $root . '/config/config.php';
    $databaseConfig = is_array($config['database'] ?? null) ? $config['database'] : [];
    if (($databaseConfig['enabled'] ?? false) !== true) {
        throw new RuntimeException('Baza danych jest wyłączona.');
    }
    unset($databaseConfig['enabled']);

    $moduleDefinitions = require $root . '/config/modules.php';
    $trustedPublishers = require $root . '/config/module_publishers.php';
    $modulesConfig = is_array($config['modules'] ?? null) ? $config['modules'] : [];
    $keyId = trim((string) ($modulesConfig['signing_key_id'] ?? ''));
    $privateKeyFile = trim((string) ($modulesConfig['signing_private_key_file'] ?? ''));
    $publicKeyFile = trim((string) ($modulesConfig['signing_public_key_file'] ?? ''));
    if ($keyId !== '' && $publicKeyFile !== '' && is_readable($publicKeyFile)) {
        $trustedPublishers[$keyId] = [
            'name' => 'Lokalny wydawca miniPORTAL',
            'public_key' => (string) file_get_contents($publicKeyFile),
            'status' => 'active',
            'valid_from' => null,
            'valid_until' => null,
            'replacement_key_id' => null,
        ];
    }
    $signer = $keyId !== '' && $privateKeyFile !== ''
        ? new ModulePackageSigner($privateKeyFile, $keyId)
        : null;
    $database = CrudApp::getInstance($databaseConfig);
    $states = new ModuleStateRepository($database);
    $installer = new ModuleInstaller($database, $states);
    $manager = new ModuleManagerService(
        $root . '/modules',
        new ModuleManifestValidator((string) ($config['app']['version'] ?? '0.1.0'), $trustedPublishers),
        $states,
        $installer,
        array_values(array_map(
            static fn (array $definition): string => (string) ($definition['directory'] ?? ''),
            $moduleDefinitions
        )),
        $signer
    );
    $catalog = (new ModuleUpdatePublisher($manager, $repositoryPath))->refreshCatalog();
    $durationMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);
    $queue->complete($jobId, count($catalog), $durationMs);
    fwrite(STDOUT, sprintf(
        "[%s] module-catalog completed modules=%d duration_ms=%d\n",
        gmdate('Y-m-d H:i:s'),
        count($catalog),
        $durationMs
    ));
} catch (Throwable $exception) {
    $durationMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);
    try {
        $queue->fail($jobId, $durationMs, $exception->getMessage());
    } catch (Throwable $queueException) {
        fwrite(STDERR, '[' . gmdate('Y-m-d H:i:s') . '] module-catalog state failed: '
            . $queueException->getMessage() . "\n");
    }
    fwrite(STDERR, '[' . gmdate('Y-m-d H:i:s') . '] module-catalog failed: '
        . $exception->getMessage() . "\n");
    exit(1);
}
