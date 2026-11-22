<?php

declare(strict_types=1);

use SyntaxDevTeam\Cms\Core\{Autoloader,ModuleInstaller,ModuleManifestValidator,ModuleStateRepository,Request};
use SyntaxDevTeam\Cms\Database\CrudApp;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuditLogService;
use SyntaxDevTeam\Cms\Modules\MinecraftAccounts\LinkedMinecraftProfileRepository;

// Explicit --root keeps one reviewed implementation usable for parent and derivative installations.
$root = rtrim((string) (getopt('', ['root:'])['root'] ?? dirname(__DIR__)), '/');
if (PHP_SAPI !== 'cli' || !is_file($root . '/core/Autoloader.php')) { fwrite(STDERR, "Wymagana instalacja CLI.\n"); exit(1); }
require $root . '/core/Autoloader.php'; Autoloader::register();
try {
    $environment = \SyntaxDevTeam\Cms\Core\InstallationState::environmentFile($root);
    if (!is_readable($environment)) throw new RuntimeException('Konfiguracja instalacji jest niedostępna dla tego konta systemowego.');
    $config = require $root . '/config/config.php';
    $settings = $config['database'] ?? [];
    if (($settings['enabled'] ?? false) !== true) throw new RuntimeException('Baza instalacji nie jest skonfigurowana.');
    unset($settings['enabled']);
    $db = CrudApp::make($settings); $states = new ModuleStateRepository($db); $installer = new ModuleInstaller($db,$states);
    $validator = new ModuleManifestValidator((string) ($config['app']['version'] ?? '0.1.0'));
    // Core first: it supplies the shared profile components and session-generation migration.
    foreach (['CoreAuth','MinecraftAccounts','PlayerProfile'] as $directory) {
        if ($directory === 'PlayerProfile' && !is_file($root . '/modules/' . $directory . '/info.json')) continue;
        $manifest = $validator->validate($root . '/modules/' . $directory, false);
        $states->registerDiscovered($manifest);
        $state = $states->find($manifest->id);
        if ($state === null || !$state->isInstalled()) {
            if ($directory !== 'MinecraftAccounts') continue;
            $installer->install($manifest); $states->setActive($manifest->id, true);
        } elseif (version_compare($manifest->version,$state->version,'>')) $installer->update($manifest);
    }
    $states->setActive('minecraft_accounts', true);
    $imported=0;
    if (is_file($root.'/modules/AuthGatewayX/LegacyMinecraftProfileExport.php')) {
        $rows=(new \SyntaxDevTeam\Cms\Modules\AuthGatewayX\LegacyMinecraftProfileExport($db))->rows();
        $audit=new AuditLogService($db, (string) ($config['auth']['audit_hash_key'] ?? ''));
        $imported=(new LinkedMinecraftProfileRepository($db))->importVerifiedProfiles($rows,
            fn(int $count)=>$audit->record(Request::fromArrays([],[],['REQUEST_METHOD'=>'CLI']),'minecraft_account_import','success','count:'.$count));
        $manifest=$validator->validate($root.'/modules/AuthGatewayX',false);
        $state=$states->find($manifest->id);
        if($state?->isInstalled() && version_compare($manifest->version,$state->version,'>')) $installer->update($manifest);
    }
    echo 'Zaktualizowano moduły. Zaimportowano potwierdzeń Minecraft: '.$imported.PHP_EOL;
} catch (Throwable $exception) {
    // No credentials, SQL statements, database names or exception traces in deployment output.
    fwrite(STDERR, $exception instanceof RuntimeException && !$exception instanceof PDOException
        ? $exception->getMessage().PHP_EOL : "Aktualizacja wymaga sprawdzenia konfiguracji lub zgodności bazy.\n");
    exit(1);
}
