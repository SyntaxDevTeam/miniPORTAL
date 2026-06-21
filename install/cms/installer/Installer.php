<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Installer;

use PDO;
use RuntimeException;
use SyntaxDevTeam\Cms\Database\CrudApp;
use SyntaxDevTeam\Cms\Modules\CoreAuth\ExternalIdentity;
use SyntaxDevTeam\Cms\Modules\CoreAuth\FirstAdminBootstrapper;
use SyntaxDevTeam\Cms\Modules\CoreAuth\NativeHttpClient;
use Throwable;

final class Installer
{
    private const DEFERRED_CORE_MIGRATIONS = ['20260616_audit_archive.sql'];

    /** @var null|\Closure(string): ExternalIdentity */
    private readonly ?\Closure $identityResolver;

    public function __construct(
        private readonly string $root,
        ?callable $identityResolver = null,
    ) {
        $this->identityResolver = $identityResolver !== null
            ? \Closure::fromCallable($identityResolver)
            : null;
    }

    /** @return list<array{label: string, ok: bool, detail: string}> */
    public function preflight(): array
    {
        $checks = [[
            'label' => 'PHP 8.4 lub nowszy',
            'ok' => PHP_VERSION_ID >= 80400,
            'detail' => PHP_VERSION,
        ]];
        foreach (['pdo', 'pdo_mysql', 'json', 'openssl', 'session', 'fileinfo'] as $extension) {
            $checks[] = [
                'label' => 'Rozszerzenie ' . $extension,
                'ok' => extension_loaded($extension),
                'detail' => extension_loaded($extension) ? 'Dostępne' : 'Brak',
            ];
        }
        foreach (['config', 'cache'] as $directory) {
            $path = $this->root . '/' . $directory;
            $checks[] = [
                'label' => 'Zapis do ' . $directory . '/',
                'ok' => is_dir($path) && is_writable($path),
                'detail' => is_writable($path) ? 'Dostępny' : 'Brak uprawnień',
            ];
        }

        return $checks;
    }

    public function isInstalled(): bool
    {
        return is_file($this->lockFile());
    }

    /** @return list<array{id: string, name: string, required: bool, dependencies: list<string>}> */
    public function moduleOptions(): array
    {
        return array_values(array_map(
            static fn (array $module): array => [
                'id' => $module['id'],
                'name' => $module['name'],
                'required' => $module['protected'],
                'dependencies' => $module['dependencies'],
            ],
            $this->moduleCatalog()
        ));
    }

    /**
     * @param array<string, mixed> $input
     * @return array{owner: string, login_url: string, installed_modules: int}
     */
    public function install(array $input): array
    {
        if ($this->isInstalled()) {
            throw new RuntimeException('miniPORTAL jest już zainstalowany.');
        }
        foreach ($this->preflight() as $check) {
            if (!$check['ok']) {
                throw new RuntimeException('Wymagania środowiska nie są spełnione: ' . $check['label'] . '.');
            }
        }

        $data = $this->validate($input);
        $identity = $this->resolveGitHubIdentity($data['github_login']);
        $environment = $this->environmentContent($data);
        if (!is_array(parse_ini_string($environment, false, INI_SCANNER_RAW))) {
            throw new RuntimeException('Wygenerowana konfiguracja środowiska jest nieprawidłowa.');
        }

        $pdo = null;
        $databaseInitiallyEmpty = false;
        try {
            $pdo = $this->connectDatabase($data);
            $databaseInitiallyEmpty = $this->databaseTableCount($pdo, $data['db_name']) === 0;
            if (!$databaseInitiallyEmpty) {
                throw new RuntimeException('Wybrana baza nie jest pusta. Instalator nie nadpisuje istniejących tabel.');
            }

            $this->installSchema($pdo, $data['selected_modules']);
            $database = CrudApp::make([
                'database_type' => 'mysql',
                'server' => $data['db_host'],
                'database_name' => $data['db_name'],
                'username' => $data['db_user'],
                'password' => $data['db_pass'],
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_general_ci',
                'port' => $data['db_port'],
                'logging' => false,
            ]);
            $owner = (new FirstAdminBootstrapper($database))->bootstrap($identity, $identity->login);
            $this->saveSiteSettings($pdo, $owner->id, $data);
            $this->writeEnvironment($environment);
            $this->writeLock();

            return [
                'owner' => $owner->displayName,
                'login_url' => $data['site_url'] . '/admin/login',
                'installed_modules' => count($data['selected_modules']),
            ];
        } catch (Throwable $exception) {
            if ($pdo instanceof PDO && $databaseInitiallyEmpty) {
                $this->clearDatabase($pdo);
            }
            throw new RuntimeException('Instalacja nie została ukończona: ' . $exception->getMessage(), 0, $exception);
        }
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    private function validate(array $input): array
    {
        $siteUrl = rtrim(trim((string) ($input['site_url'] ?? '')), '/');
        if (filter_var($siteUrl, FILTER_VALIDATE_URL) === false
            || !in_array((string) parse_url($siteUrl, PHP_URL_SCHEME), ['http', 'https'], true)
            || !in_array((string) parse_url($siteUrl, PHP_URL_PATH), ['', '/'], true)) {
            throw new RuntimeException('Podaj bazowy adres strony bez dodatkowej ścieżki.');
        }
        $siteName = trim((string) ($input['site_name'] ?? ''));
        if ($siteName === '' || strlen($siteName) > 80) {
            throw new RuntimeException('Nazwa strony jest wymagana i może mieć maksymalnie 80 znaków.');
        }
        $timezone = trim((string) ($input['timezone'] ?? 'Europe/Warsaw'));
        if (!in_array($timezone, timezone_identifiers_list(), true)) {
            throw new RuntimeException('Wybrana strefa czasowa jest nieprawidłowa.');
        }
        $locale = trim((string) ($input['locale'] ?? 'pl_PL'));
        if (preg_match('/^[a-z]{2}_[A-Z]{2}$/', $locale) !== 1) {
            throw new RuntimeException('Locale musi mieć format pl_PL.');
        }
        $theme = trim((string) ($input['theme'] ?? 'default'));
        if (!is_file($this->root . '/templates/' . $theme . '/theme.php')) {
            throw new RuntimeException('Wybrany motyw nie istnieje.');
        }

        $dbHost = trim((string) ($input['db_host'] ?? '127.0.0.1'));
        $dbPort = filter_var($input['db_port'] ?? 3306, FILTER_VALIDATE_INT);
        $dbName = trim((string) ($input['db_name'] ?? ''));
        $dbUser = trim((string) ($input['db_user'] ?? ''));
        $dbPass = (string) ($input['db_pass'] ?? '');
        if ($dbHost === '' || $dbPort === false || $dbPort < 1 || $dbPort > 65535
            || preg_match('/^[A-Za-z0-9_]{1,64}$/', $dbName) !== 1 || $dbUser === '' || $dbPass === '') {
            throw new RuntimeException('Uzupełnij poprawne dane połączenia z bazą MySQL.');
        }
        $githubLogin = trim((string) ($input['github_login'] ?? ''));
        $githubClientId = trim((string) ($input['github_client_id'] ?? ''));
        $githubClientSecret = trim((string) ($input['github_client_secret'] ?? ''));
        if (preg_match('/^[A-Za-z0-9-]{1,39}$/', $githubLogin) !== 1
            || $githubClientId === '' || $githubClientSecret === '') {
            throw new RuntimeException('GitHub login, Client ID i Client Secret są wymagane.');
        }

        $catalog = $this->moduleCatalog();
        $selected = array_values(array_unique(array_filter(
            array_map('strval', is_array($input['modules'] ?? null) ? $input['modules'] : []),
            static fn (string $id): bool => isset($catalog[$id])
        )));
        foreach ($catalog as $id => $module) {
            if ($module['protected']) {
                $selected[] = $id;
            }
        }
        $selected = $this->expandDependencies(array_values(array_unique($selected)), $catalog);

        return [
            'site_url' => $siteUrl,
            'site_name' => $siteName,
            'timezone' => $timezone,
            'locale' => $locale,
            'theme' => $theme,
            'db_host' => $dbHost,
            'db_port' => (int) $dbPort,
            'db_name' => $dbName,
            'db_user' => $dbUser,
            'db_pass' => $dbPass,
            'create_database' => filter_var($input['create_database'] ?? false, FILTER_VALIDATE_BOOL),
            'github_login' => $githubLogin,
            'github_client_id' => $githubClientId,
            'github_client_secret' => $githubClientSecret,
            'discord_client_id' => trim((string) ($input['discord_client_id'] ?? '')),
            'discord_client_secret' => trim((string) ($input['discord_client_secret'] ?? '')),
            'google_client_id' => trim((string) ($input['google_client_id'] ?? '')),
            'google_client_secret' => trim((string) ($input['google_client_secret'] ?? '')),
            'selected_modules' => $selected,
        ];
    }

    private function resolveGitHubIdentity(string $login): ExternalIdentity
    {
        if ($this->identityResolver !== null) {
            $identity = ($this->identityResolver)($login);
            if (!$identity instanceof ExternalIdentity || $identity->provider !== 'github') {
                throw new RuntimeException('Resolver pierwszego Ownera zwrócił nieprawidłową tożsamość.');
            }

            return $identity;
        }

        $response = (new NativeHttpClient())->request(
            'GET',
            'https://api.github.com/users/' . rawurlencode($login),
            [
                'Accept' => 'application/vnd.github+json',
                'X-GitHub-Api-Version' => '2022-11-28',
                'User-Agent' => 'miniPORTAL Installer',
            ]
        );
        if ($response->status !== 200) {
            throw new RuntimeException('Nie można potwierdzić wskazanego konta GitHub.');
        }
        $profile = $response->json();
        $subject = $profile['id'] ?? null;
        $resolvedLogin = $profile['login'] ?? null;
        if ((!is_int($subject) && !is_string($subject)) || !is_string($resolvedLogin)) {
            throw new RuntimeException('Odpowiedź GitHub nie zawiera identyfikatora konta.');
        }

        return new ExternalIdentity(
            'github',
            (string) $subject,
            $resolvedLogin,
            is_string($profile['email'] ?? null) ? $profile['email'] : null,
            false,
            is_string($profile['avatar_url'] ?? null) ? $profile['avatar_url'] : null,
        );
    }

    /** @param array<string, mixed> $data */
    private function connectDatabase(array $data): PDO
    {
        $baseDsn = sprintf(
            'mysql:host=%s;port=%d;charset=utf8mb4',
            $data['db_host'],
            $data['db_port']
        );
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        if ($data['create_database']) {
            $server = new PDO($baseDsn, $data['db_user'], $data['db_pass'], $options);
            $server->exec(
                'CREATE DATABASE IF NOT EXISTS `' . $data['db_name']
                . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci'
            );
        }

        return new PDO(
            $baseDsn . ';dbname=' . $data['db_name'],
            $data['db_user'],
            $data['db_pass'],
            $options
        );
    }

    private function databaseTableCount(PDO $pdo, string $database): int
    {
        $statement = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = :database'
        );
        $statement->execute([':database' => $database]);

        return (int) $statement->fetchColumn();
    }

    /** @param list<string> $selectedModules */
    private function installSchema(PDO $pdo, array $selectedModules): void
    {
        $pdo->exec(
            "CREATE TABLE core_migrations (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(191) NOT NULL,
                checksum CHAR(64) NOT NULL,
                executed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_core_migrations_name (migration)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
        );

        $deferred = [];
        $coreFiles = glob($this->root . '/core/migrations/*.sql') ?: [];
        sort($coreFiles, SORT_STRING);
        foreach ($coreFiles as $file) {
            if (in_array(basename($file), self::DEFERRED_CORE_MIGRATIONS, true)) {
                $deferred[] = $file;
                continue;
            }
            $this->executeSqlFile($pdo, $file);
            $this->recordCoreMigration($pdo, $file);
        }

        $catalog = $this->moduleCatalog();
        foreach ($catalog as $id => $module) {
            if (!in_array($id, $selectedModules, true) || $module['install'] === null) {
                continue;
            }
            $this->executeSqlFile($pdo, $module['directory'] . '/' . $module['install']);
        }
        foreach ($deferred as $file) {
            if (!$this->tableExists($pdo, 'auth_events_archive')) {
                $this->executeSqlFile($pdo, $file);
            }
            $this->recordCoreMigration($pdo, $file);
        }

        $state = $pdo->prepare(
            'INSERT INTO modules_config '
            . '(module_id, version, status, is_protected, data_preserved, installed_at) '
            . 'VALUES (:id, :version, :status, :protected, 0, :installed_at) '
            . 'ON DUPLICATE KEY UPDATE version = VALUES(version), status = VALUES(status), '
            . 'is_protected = VALUES(is_protected), data_preserved = 0, installed_at = VALUES(installed_at)'
        );
        foreach ($catalog as $id => $module) {
            $active = in_array($id, $selectedModules, true);
            $state->execute([
                ':id' => $id,
                ':version' => $module['version'],
                ':status' => $active ? 'active' : 'discovered',
                ':protected' => $module['protected'] ? 1 : 0,
                ':installed_at' => $active ? date('Y-m-d H:i:s') : null,
            ]);
            if (!$active) {
                continue;
            }
            foreach ($module['migrations'] as $migration) {
                $checksum = hash_file('sha256', $migration);
                if (!is_string($checksum)) {
                    throw new RuntimeException('Nie można policzyć sumy migracji ' . basename($migration) . '.');
                }
                $record = $pdo->prepare(
                    'INSERT INTO module_migrations (module_id, migration, checksum) '
                    . 'VALUES (:module_id, :migration, :checksum)'
                );
                $record->execute([
                    ':module_id' => $id,
                    ':migration' => basename($migration),
                    ':checksum' => $checksum,
                ]);
            }
        }
    }

    private function recordCoreMigration(PDO $pdo, string $file): void
    {
        $checksum = hash_file('sha256', $file);
        if (!is_string($checksum)) {
            throw new RuntimeException('Nie można policzyć sumy migracji Core.');
        }
        $statement = $pdo->prepare(
            'INSERT INTO core_migrations (migration, checksum) VALUES (:migration, :checksum)'
        );
        $statement->execute([':migration' => basename($file), ':checksum' => $checksum]);
    }

    private function executeSqlFile(PDO $pdo, string $file): void
    {
        $sql = trim((string) file_get_contents($file));
        if ($sql !== '') {
            $pdo->exec($sql);
        }
    }

    private function tableExists(PDO $pdo, string $table): bool
    {
        $statement = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.tables '
            . 'WHERE table_schema = DATABASE() AND table_name = :table'
        );
        $statement->execute([':table' => $table]);

        return (int) $statement->fetchColumn() === 1;
    }

    /** @param array<string, mixed> $data */
    private function saveSiteSettings(PDO $pdo, int $ownerId, array $data): void
    {
        $values = [
            'theme' => $data['theme'],
            'public_url' => $data['site_url'],
            'public_name' => $data['site_name'],
            'public_default_title' => $data['site_name'],
            'public_eyebrow' => 'Software dla społeczności',
            'public_meta_description' => $data['site_name'],
            'public_meta_keywords' => '',
            'public_meta_author' => $data['site_name'],
            'public_meta_robots' => 'index, follow, max-image-preview:large',
            'public_locale' => $data['locale'],
            'public_social_image_url' => '',
            'public_social_image_alt' => 'Logo ' . $data['site_name'],
            'public_twitter_site' => '',
            'public_theme_color' => '#080c12',
            'public_google_site_verification' => '',
            'public_bing_site_verification' => '',
            'public_footer_text' => 'Projektowane modułowo. Rozwijane świadomie.',
        ];
        $statement = $pdo->prepare(
            'INSERT INTO system_settings (setting_key, setting_value, updated_by) '
            . 'VALUES (:key, :value, :owner) '
            . 'ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)'
        );
        foreach ($values as $key => $value) {
            $statement->execute([':key' => $key, ':value' => $value, ':owner' => $ownerId]);
        }
    }

    /** @param array<string, mixed> $data */
    private function environmentContent(array $data): string
    {
        $callback = $data['site_url'] . '/index.php?route=/admin/auth/';
        $values = [
            'APP_NAME' => 'miniPORTAL',
            'APP_DEBUG' => 'false',
            'APP_TIMEZONE' => $data['timezone'],
            'APP_THEME' => $data['theme'],
            'SITE_URL' => $data['site_url'],
            'SITE_NAME' => $data['site_name'],
            'SITE_LOCALE' => $data['locale'],
            'SESSION_NAME' => 'MINIPORTALSESSID',
            'SESSION_SAME_SITE' => 'Lax',
            'TEMPLATE_CACHE_ENABLED' => 'true',
            'TEMPLATE_CACHE_TTL' => '300',
            'AUTH_STORAGE' => 'database',
            'AUTH_DEMO_ENABLED' => 'false',
            'AUTH_AUDIT_HASH_KEY' => bin2hex(random_bytes(32)),
            'AUTH_OAUTH_WINDOW_SECONDS' => '600',
            'AUTH_OAUTH_START_LIMIT' => '10',
            'AUTH_OAUTH_CALLBACK_LIMIT' => '20',
            'GITHUB_CLIENT_ID' => $data['github_client_id'],
            'GITHUB_CLIENT_SECRET' => $data['github_client_secret'],
            'GITHUB_CALLBACK_URL' => $callback . 'github/callback',
            'DISCORD_CLIENT_ID' => $data['discord_client_id'],
            'DISCORD_CLIENT_SECRET' => $data['discord_client_secret'],
            'DISCORD_CALLBACK_URL' => $callback . 'discord/callback',
            'GOOGLE_CLIENT_ID' => $data['google_client_id'],
            'GOOGLE_CLIENT_SECRET' => $data['google_client_secret'],
            'GOOGLE_CALLBACK_URL' => $callback . 'google/callback',
            'DB_ENABLED' => 'true',
            'DB_DRIVER' => 'mysql',
            'DB_HOST' => $data['db_host'],
            'DB_PORT' => (string) $data['db_port'],
            'DB_NAME' => $data['db_name'],
            'DB_USER' => $data['db_user'],
            'DB_PASS' => $data['db_pass'],
            'DB_CHARSET' => 'utf8mb4',
            'DB_COLLATION' => 'utf8mb4_general_ci',
            'DB_LOGGING' => 'false',
            'BUILD_UPLOAD_MAX_BYTES' => '20971520',
            'BUILD_CI_TOKEN' => bin2hex(random_bytes(32)),
        ];
        $lines = ['# Wygenerowano przez kreator miniPORTAL.'];
        foreach ($values as $key => $value) {
            $lines[] = $key . '=' . $this->quoteEnvironmentValue((string) $value);
        }

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    private function quoteEnvironmentValue(string $value): string
    {
        $value = str_replace(["\r", "\n"], ' ', $value);
        $value = str_replace(['\\', '"'], ['\\\\', '\\"'], $value);

        return '"' . $value . '"';
    }

    private function writeEnvironment(string $content): void
    {
        $file = $this->root . '/config/installed.env';
        $temporary = $file . '.tmp-' . bin2hex(random_bytes(4));
        if (file_put_contents($temporary, $content, LOCK_EX) === false) {
            throw new RuntimeException('Nie można zapisać konfiguracji środowiska.');
        }
        chmod($temporary, 0600);
        if (!is_array(parse_ini_file($temporary, false, INI_SCANNER_RAW)) || !rename($temporary, $file)) {
            @unlink($temporary);
            throw new RuntimeException('Nie można zatwierdzić konfiguracji środowiska.');
        }
    }

    private function writeLock(): void
    {
        $content = json_encode([
            'installed_at' => gmdate(DATE_ATOM),
            'version' => '0.1.0',
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        if (file_put_contents($this->lockFile(), $content . PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException('Nie można zablokować instalatora po instalacji.');
        }
        chmod($this->lockFile(), 0600);
    }

    private function lockFile(): string
    {
        return $this->root . '/config/installed.lock';
    }

    private function clearDatabase(PDO $pdo): void
    {
        try {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
            $tables = $pdo->query('SHOW TABLES')?->fetchAll(PDO::FETCH_COLUMN) ?: [];
            foreach ($tables as $table) {
                if (is_string($table) && preg_match('/^[A-Za-z0-9_]+$/', $table) === 1) {
                    $pdo->exec('DROP TABLE `' . $table . '`');
                }
            }
        } finally {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        }
    }

    /** @return array<string, array<string, mixed>> */
    private function moduleCatalog(): array
    {
        $modules = [];
        foreach (glob($this->root . '/modules/*/info.json') ?: [] as $file) {
            $data = json_decode((string) file_get_contents($file), true, 16, JSON_THROW_ON_ERROR);
            $directory = dirname($file);
            $migrations = glob($directory . '/migrations/*.sql') ?: [];
            sort($migrations, SORT_STRING);
            $modules[(string) $data['id']] = [
                'id' => (string) $data['id'],
                'name' => (string) $data['name'],
                'version' => (string) $data['version'],
                'protected' => (bool) ($data['protected'] ?? false),
                'dependencies' => array_values(array_map('strval', $data['requires']['modules'] ?? [])),
                'install' => is_string($data['install'] ?? null) ? $data['install'] : null,
                'directory' => $directory,
                'migrations' => array_values($migrations),
            ];
        }

        $sorted = [];
        $visiting = [];
        $visit = function (string $id) use (&$visit, &$sorted, &$visiting, $modules): void {
            if (isset($sorted[$id])) {
                return;
            }
            if (isset($visiting[$id]) || !isset($modules[$id])) {
                throw new RuntimeException('Nieprawidłowe zależności modułów instalacyjnych.');
            }
            $visiting[$id] = true;
            foreach ($modules[$id]['dependencies'] as $dependency) {
                $visit($dependency);
            }
            unset($visiting[$id]);
            $sorted[$id] = $modules[$id];
        };
        foreach (array_keys($modules) as $id) {
            $visit($id);
        }

        return $sorted;
    }

    /**
     * @param list<string> $selected
     * @param array<string, array<string, mixed>> $catalog
     * @return list<string>
     */
    private function expandDependencies(array $selected, array $catalog): array
    {
        $expanded = array_fill_keys($selected, true);
        $add = function (string $id) use (&$add, &$expanded, $catalog): void {
            if (!isset($catalog[$id])) {
                throw new RuntimeException('Brak zależnego modułu: ' . $id . '.');
            }
            $expanded[$id] = true;
            foreach ($catalog[$id]['dependencies'] as $dependency) {
                $add($dependency);
            }
        };
        foreach ($selected as $id) {
            $add($id);
        }

        return array_values(array_filter(
            array_keys($catalog),
            static fn (string $id): bool => isset($expanded[$id])
        ));
    }
}
