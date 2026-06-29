<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Installer;

use PDO;
use RuntimeException;
use SyntaxDevTeam\Cms\Core\FilesystemPermissions;
use SyntaxDevTeam\Cms\Core\InstallationState;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuthProviderConfigStore;
use Throwable;

final class Installer
{
    public function __construct(
        private readonly string $root,
    ) {
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
        $publisherKey = $this->root . '/config/keys/syntaxdevteam-modules-2026-public.pem';
        $publisherKeyValid = is_readable($publisherKey)
            && openssl_pkey_get_public((string) file_get_contents($publisherKey)) !== false;
        $checks[] = [
            'label' => 'Zaufany klucz wydawcy modułów',
            'ok' => $publisherKeyValid,
            'detail' => $publisherKeyValid ? 'SyntaxDevTeam 2026' : 'Brak lub nieprawidłowy',
        ];
        foreach (FilesystemPermissions::installerDirectories() as $directory) {
            $path = $this->root . '/' . $directory;
            $checks[] = [
                'label' => 'Zapis do ' . $directory . '/',
                'ok' => is_dir($path) && is_writable($path),
                'detail' => is_writable($path) ? 'Dostępny' : 'Brak uprawnień',
            ];
        }
        $platformIssues = FilesystemPermissions::platformUpdateIssues($this->root);
        $checks[] = [
            'label' => 'Runtime gotowy do aktualizacji z panelu',
            'ok' => $platformIssues === [],
            'detail' => $platformIssues === []
                ? 'Dostępny'
                : 'Brak zapisu: ' . implode(', ', array_slice($platformIssues, 0, 5)),
        ];

        return $checks;
    }

    public function isInstalled(): bool
    {
        return InstallationState::isInstalled($this->root);
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
            $this->saveSiteSettings($pdo, null, $data);
            $this->writeEnvironment($environment);
            (new AuthProviderConfigStore(
                $this->root . '/config/modules/auth-providers.env'
            ))->save($data['providers']);
            if (in_array('econizer', $data['selected_modules'], true)) {
                $this->writeEconizerEnvironment($this->econizerEnvironmentContent($data));
            }
            $this->writeLock();

            return [
                'owner' => 'Pierwszy użytkownik wybranego logowania',
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
        $providers = [];
        foreach (['github', 'discord', 'google', 'microsoft'] as $provider) {
            $providers[$provider] = [
                'enabled' => filter_var($input[$provider . '_enabled'] ?? false, FILTER_VALIDATE_BOOL),
                'client_id' => trim((string) ($input[$provider . '_client_id'] ?? '')),
                'client_secret' => (string) ($input[$provider . '_client_secret'] ?? ''),
            ];
        }
        $enabledProviders = array_filter(
            $providers,
            static fn (array $provider): bool => $provider['enabled']
        );
        if ($enabledProviders === []) {
            throw new RuntimeException('Wybierz i skonfiguruj co najmniej jednego dostawcę logowania.');
        }
        foreach ($enabledProviders as $name => $provider) {
            if ($provider['client_id'] === '' || $provider['client_secret'] === '') {
                throw new RuntimeException("Dostawca {$name} wymaga Client ID i Client Secret.");
            }
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
            'providers' => $providers,
            'selected_modules' => $selected,
        ];
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
        $this->executeSqlFile($pdo, $this->root . '/core/install.sql');
        $baseline = $this->migrationBaseline();

        $catalog = $this->moduleCatalog();
        foreach ($catalog as $id => $module) {
            if (!in_array($id, $selectedModules, true) || $module['install'] === null) {
                continue;
            }
            $this->executeSqlFile($pdo, $module['directory'] . '/' . $module['install']);
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
            foreach ($baseline['modules'][$id] ?? [] as $migration => $checksum) {
                $record = $pdo->prepare(
                    'INSERT INTO module_migrations (module_id, migration, checksum) '
                    . 'VALUES (:module_id, :migration, :checksum)'
                );
                $record->execute([
                    ':module_id' => $id,
                    ':migration' => $migration,
                    ':checksum' => $checksum,
                ]);
            }
        }
        $record = $pdo->prepare(
            'INSERT INTO core_migrations (migration, checksum) VALUES (:migration, :checksum)'
        );
        foreach ($baseline['core'] as $migration => $checksum) {
            $record->execute([':migration' => $migration, ':checksum' => $checksum]);
        }
    }

    private function executeSqlFile(PDO $pdo, string $file): void
    {
        $sql = trim((string) file_get_contents($file));
        if ($sql !== '') {
            $pdo->exec($sql);
        }
    }

    /**
     * @return array{
     *     core: array<string, string>,
     *     modules: array<string, array<string, string>>
     * }
     */
    private function migrationBaseline(): array
    {
        $file = $this->root . '/installer/migration-baseline.php';
        if (!is_file($file)) {
            throw new RuntimeException('Pakiet instalacyjny nie zawiera manifestu stanu migracji.');
        }
        $baseline = require $file;
        if (!is_array($baseline)
            || !is_array($baseline['core'] ?? null)
            || !is_array($baseline['modules'] ?? null)) {
            throw new RuntimeException('Manifest stanu migracji jest nieprawidłowy.');
        }

        return $baseline;
    }

    /** @param array<string, mixed> $data */
    private function saveSiteSettings(PDO $pdo, ?int $ownerId, array $data): void
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
            'GITHUB_CALLBACK_URL' => $callback . 'github/callback',
            'DISCORD_CALLBACK_URL' => $callback . 'discord/callback',
            'GOOGLE_CALLBACK_URL' => $callback . 'google/callback',
            'MICROSOFT_CALLBACK_URL' => $callback . 'microsoft/callback',
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
            'PLATFORM_RELEASE_CATALOG_URL' => 'https://new.syntaxdevteam.pl/api/platform-releases/catalog',
            'PLATFORM_RELEASE_MAX_BYTES' => '52428800',
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

    /** @param array<string, mixed> $data */
    private function econizerEnvironmentContent(array $data): string
    {
        $values = [
            'ECONIZER_API_TOKEN' => bin2hex(random_bytes(32)),
            'ECONIZER_DISCORD_CLIENT_ID' => '',
            'ECONIZER_DISCORD_CLIENT_SECRET' => '',
            'ECONIZER_DISCORD_BOT_TOKEN' => '',
            'ECONIZER_DISCORD_CALLBACK_URL' => $data['site_url'] . '/index.php?route=/econizer/discord/callback',
            'ECONIZER_DISCORD_BOT_PERMISSIONS' => '0',
        ];
        $lines = ['# Wygenerowano dla modułu Econizer. Plik nie należy do konfiguracji miniPORTAL.'];
        foreach ($values as $key => $value) {
            $lines[] = $key . '=' . $this->quoteEnvironmentValue((string) $value);
        }
        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    private function writeEconizerEnvironment(string $content): void
    {
        $file = $this->root . '/config/modules/econizer.env';
        $temporary = $file . '.tmp-' . bin2hex(random_bytes(4));
        if (!is_dir(dirname($file)) || file_put_contents($temporary, $content, LOCK_EX) === false) {
            throw new RuntimeException('Nie można zapisać konfiguracji środowiska Econizer.');
        }
        chmod($temporary, 0600);
        if (!is_array(parse_ini_file($temporary, false, INI_SCANNER_RAW)) || !rename($temporary, $file)) {
            @unlink($temporary);
            throw new RuntimeException('Nie można zatwierdzić konfiguracji środowiska Econizer.');
        }
    }

    private function writeLock(): void
    {
        $content = json_encode([
            'installed_at' => gmdate(DATE_ATOM),
            'version' => '0.2.4',
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
            $modules[(string) $data['id']] = [
                'id' => (string) $data['id'],
                'name' => (string) $data['name'],
                'version' => (string) $data['version'],
                'protected' => (bool) ($data['protected'] ?? false),
                'dependencies' => array_values(array_map('strval', $data['requires']['modules'] ?? [])),
                'install' => is_string($data['install'] ?? null) ? $data['install'] : null,
                'directory' => $directory,
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
