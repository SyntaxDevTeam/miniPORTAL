<?php

declare(strict_types=1);

use SyntaxDevTeam\Cms\Core\Autoloader;
use SyntaxDevTeam\Cms\Database\CrudApp;
use SyntaxDevTeam\Cms\Installer\Installer;
use SyntaxDevTeam\Cms\Modules\CoreAuth\ExternalIdentity;
use SyntaxDevTeam\Cms\Modules\CoreAuth\FirstAdminBootstrapper;

$root = (string) getenv('TEST_INSTALL_ROOT');
if ($root === '' || !is_file($root . '/installer/Installer.php')) {
    throw new RuntimeException('TEST_INSTALL_ROOT nie wskazuje zbudowanej dystrybucji.');
}

require_once $root . '/core/Autoloader.php';
require_once $root . '/installer/Installer.php';
Autoloader::register();

$installer = new Installer($root);
$modules = array_column($installer->moduleOptions(), 'id');
$result = $installer->install([
    'site_url' => 'https://portal.example.test',
    'site_name' => 'Test miniPORTAL',
    'timezone' => 'Europe/Warsaw',
    'locale' => 'pl_PL',
    'theme' => 'default',
    'db_host' => (string) getenv('TEST_DB_HOST'),
    'db_port' => (string) getenv('TEST_DB_PORT'),
    'db_name' => (string) getenv('TEST_DB_NAME'),
    'db_user' => (string) getenv('TEST_DB_USER'),
    'db_pass' => (string) getenv('TEST_DB_PASS'),
    'create_database' => false,
    'github_enabled' => true,
    'github_client_id' => 'test-client-id',
    'github_client_secret' => 'test-client-secret',
    'modules' => $modules,
]);

$pdo = new PDO(
    sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        getenv('TEST_DB_HOST'),
        (int) getenv('TEST_DB_PORT'),
        getenv('TEST_DB_NAME')
    ),
    (string) getenv('TEST_DB_USER'),
    (string) getenv('TEST_DB_PASS'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$ownerCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM users u "
    . "JOIN user_roles ur ON ur.user_id = u.id "
    . "JOIN roles r ON r.id = ur.role_id WHERE r.name = 'owner' AND u.status = 'active'"
)->fetchColumn();
$activeModules = (int) $pdo->query(
    "SELECT COUNT(*) FROM modules_config WHERE status = 'active'"
)->fetchColumn();
$econizerEnvironment = $root . '/config/modules/econizer.env';
$econizerValues = is_file($econizerEnvironment)
    ? parse_ini_file($econizerEnvironment, false, INI_SCANNER_RAW)
    : false;
$installedValues = is_file($root . '/config/installed.env')
    ? parse_ini_file($root . '/config/installed.env', false, INI_SCANNER_RAW)
    : false;
$authProviderEnvironment = $root . '/config/modules/auth-providers.env';
$authProviderValues = is_file($authProviderEnvironment)
    ? parse_ini_file($authProviderEnvironment, false, INI_SCANNER_RAW)
    : false;

$database = CrudApp::make([
    'database_type' => 'mysql',
    'server' => (string) getenv('TEST_DB_HOST'),
    'database_name' => (string) getenv('TEST_DB_NAME'),
    'username' => (string) getenv('TEST_DB_USER'),
    'password' => (string) getenv('TEST_DB_PASS'),
    'charset' => 'utf8mb4',
    'port' => (int) getenv('TEST_DB_PORT'),
    'logging' => false,
]);
(new FirstAdminBootstrapper($database))->bootstrap(
    new ExternalIdentity('github', '1000001', 'installer-owner', 'owner@example.test'),
    'installer-owner'
);
$ownerCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM users u "
    . "JOIN user_roles ur ON ur.user_id = u.id "
    . "JOIN roles r ON r.id = ur.role_id WHERE r.name = 'owner' AND u.status = 'active'"
)->fetchColumn();

if ($ownerCount !== 1
    || $activeModules !== count($modules)
    || $result['installed_modules'] !== count($modules)
    || !is_file($root . '/config/installed.env')
    || !is_file($root . '/config/installed.lock')
    || !is_array($installedValues)
    || isset($installedValues['GITHUB_CLIENT_SECRET'])
    || !is_array($authProviderValues)
    || ($authProviderValues['GITHUB_ENABLED'] ?? '') !== 'true'
    || ($authProviderValues['GITHUB_CLIENT_SECRET'] ?? '') !== 'test-client-secret'
    || ((fileperms($authProviderEnvironment) ?: 0) & 0777) !== 0600
    || ($installedValues['PLATFORM_RELEASE_CATALOG_URL'] ?? '') !== 'https://new.syntaxdevteam.pl/api/platform-releases/catalog'
    || !is_array($econizerValues)
    || strlen((string) ($econizerValues['ECONIZER_API_TOKEN'] ?? '')) < 32
    || ((fileperms($econizerEnvironment) ?: 0) & 0777) !== 0600) {
    throw new RuntimeException('Integracyjna instalacja nie utworzyła kompletnego stanu.');
}

echo "Installer integration passed: {$activeModules} modules, 1 Owner.\n";
