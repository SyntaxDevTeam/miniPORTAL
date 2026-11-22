<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\SystemMonitor;

use PDO;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SyntaxDevTeam\Cms\Database\CrudApp;
use Throwable;

final class SystemMonitorInfrastructureProbe
{
    private const SYSTEM_DATABASES = [
        'information_schema',
        'mysql',
        'performance_schema',
        'sys',
    ];

    /** @var array<string, string> */
    private array $config;
    private string $cacheFile;

    public function __construct(
        private readonly ?CrudApp $database,
        private readonly string $applicationRoot,
    ) {
        $this->config = $this->loadConfig();
        $this->cacheFile = rtrim(sys_get_temp_dir(), '/') . '/miniportal-system-monitor-'
            . substr(hash('sha256', (realpath($applicationRoot) ?: $applicationRoot) . '|' . PHP_SAPI), 0, 16)
            . '.json';
    }

    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        $cached = $this->readCache();
        if ($cached !== null) {
            $cached['storage'] = (new SystemMonitorHost())->storage() ?? $cached['storage'];
            $cached['services'] = $this->sharedServices() ?? $cached['services'];
            return $cached;
        }

        $mysql = $this->mysqlSnapshot();
        $postgresql = $this->postgresSnapshot();
        $databases = [
            'mysql' => $mysql,
            'postgresql' => $postgresql,
        ];

        $snapshot = [
            'schema' => 3,
            'sampled_at' => time(),
            'cache_ttl_seconds' => $this->cacheTtl(),
            'services' => $this->sharedServices() ?? $this->servicesSnapshot(),
            'databases' => $databases,
            'storage' => (new SystemMonitorHost())->storage() ?? $this->storageSnapshot($databases),
        ];

        $this->writeCache($snapshot);

        return $snapshot;
    }

    /** @return array<string, mixed> */
    public function processesSnapshot(): array
    {
        $shared = SystemMonitorAgent::snapshot()['processes'] ?? null;
        if (is_array($shared)) return $shared;
        $memory = SystemMonitorMemory::snapshot();
        $totalMemory = $memory['total'];
        $host = new SystemMonitorHost();
        $boot = trim((string)@file_get_contents('/proc/sys/kernel/random/boot_id'));
        $users = $this->systemUsers();
        $processes = [];
        $groups = [];
        $scanned = 0;
        $readable = 0;

        foreach (glob('/proc/[0-9]*', GLOB_ONLYDIR) ?: [] as $processDirectory) {
            $scanned++;
            $pid = (int) basename($processDirectory);
            if ($pid <= 0) {
                continue;
            }

            $comm = @file_get_contents($processDirectory . '/comm');
            $status = @file_get_contents($processDirectory . '/status');
            if (!is_string($comm) || !is_string($status)) {
                continue;
            }

            $name = trim($comm);
            if ($name === '') {
                continue;
            }

            $memoryBytes = $this->processMemoryFromStatus($status);
            $uid = $this->processUidFromStatus($status);
            $unit = $this->processUnit($processDirectory . '/cgroup');
            $user = $uid !== null ? ($users[$uid] ?? (string) $uid) : '—';
            $memoryPercent = $totalMemory > 0 ? ($memoryBytes / $totalMemory) * 100.0 : null;
            $readable++;

            $rawStat = (string)@file_get_contents($processDirectory . '/stat');
            $fields = preg_split('/\s+/', trim(substr($rawStat, (strrpos($rawStat, ')') ?: 0) + 1))) ?: [];
            $state = $fields[0] ?? '?'; $ppid = (int)($fields[1] ?? 0);
            $states = ['R'=>'Wykonuje pracę','S'=>'Uśpiony — oczekuje na pracę','D'=>'Oczekuje na I/O','T'=>'Zatrzymany','t'=>'Zatrzymany przez debugger','Z'=>'Zombie — oczekuje na rodzica','I'=>'Bezczynny wątek jądra'];
            $protected = $pid <= 1 || $uid === null || !($uid === 33 || ($uid >= 1000 && $uid < 60000))
                || ($unit !== null && str_ends_with($unit, '.service'))
                || in_array($name, ['systemd','sshd','sshd-session','bash','dash','sh','zsh','fish','sudo','su','login','screen','tmux','apache2','httpd','nginx','mariadbd','mysqld','postgres','cron','dockerd','containerd','python3','miniportal-node','Runner.Listener','Runner.Worker'], true)
                || !in_array($state, ['R','S','T','t'], true);
            $processes[] = [
                'pid' => $pid,
                'ppid' => $ppid, 'state' => $state, 'state_label' => $states[$state] ?? 'Nieznany',
                'orphaned' => $ppid === 1, 'start_ticks' => $fields[19] ?? '',
                'can_terminate' => !$protected && $host->available(),
                'protection' => $protected ? 'Proces systemowy, usługa lub sesja chroniona.' : ($host->available() ? '' : 'Brak usługi sterowania hostem.'),
                'name' => $name,
                'user' => $user,
                'unit' => $unit,
                'memory_bytes' => $memoryBytes,
                'memory_percent' => $memoryPercent,
            ];

            $groupKey = $unit !== null ? 'unit:' . $unit : 'name:' . $name;
            if (!isset($groups[$groupKey])) {
                $groups[$groupKey] = [
                    'label' => $unit ?? $name,
                    'name' => $name,
                    'unit' => $unit,
                    'processes' => 0,
                    'memory_bytes' => 0,
                    'memory_percent' => null,
                ];
            }
            $groups[$groupKey]['processes']++;
            $groups[$groupKey]['memory_bytes'] += $memoryBytes;
        }

        foreach ($groups as &$group) {
            $group['memory_percent'] = $totalMemory > 0
                ? (((int) $group['memory_bytes'] / $totalMemory) * 100.0)
                : null;
        }
        unset($group);

        usort($processes, static fn (array $a, array $b): int => ((int) $b['memory_bytes']) <=> ((int) $a['memory_bytes']));
        $groupList = array_values($groups);
        usort($groupList, static fn (array $a, array $b): int => ((int) $b['memory_bytes']) <=> ((int) $a['memory_bytes']));

        return [
            'sampled_at' => time(),
            'control_available' => $host->available(), 'boot_id' => $boot, 'memory' => $memory,
            'rss_all_bytes' => array_sum(array_column($processes, 'memory_bytes')),
            'rss_shown_bytes' => array_sum(array_column(array_slice($processes, 0, 30), 'memory_bytes')),
            'total_groups' => count($groupList),
            'total_memory_bytes' => $totalMemory,
            'scanned_processes' => $scanned,
            'readable_processes' => $readable,
            'groups' => array_slice($groupList, 0, 20),
            'processes' => array_slice($processes, 0, 30),
            'note' => 'RSS może zawierać współdzielone strony pamięci, dlatego sumy procesów są wskaźnikiem diagnostycznym, a nie dokładnym odpowiednikiem użycia RAM systemu.',
        ];
    }

    /** @return array<string, mixed> */
    private function mysqlSnapshot(): array
    {
        try {
            $pdo = $this->mysqlPdo();
            if (!$pdo instanceof PDO) {
                return $this->unavailableDatabase('MariaDB / MySQL', 'Brak dostępnego połączenia monitorującego.');
            }

            $namesStatement = $pdo->query('SHOW DATABASES');
            $names = $namesStatement !== false ? $namesStatement->fetchAll(PDO::FETCH_COLUMN) : [];
            if ($namesStatement !== false) {
                $namesStatement->closeCursor();
            }
            $names = array_values(array_filter(
                array_map('strval', is_array($names) ? $names : []),
                static fn (string $name): bool => $name !== '' && !in_array($name, self::SYSTEM_DATABASES, true)
            ));

            $sizeStatement = $pdo->query(
                "SELECT table_schema AS name, COALESCE(SUM(data_length + index_length), 0) AS bytes "
                . "FROM information_schema.tables "
                . "WHERE table_schema NOT IN ('information_schema','mysql','performance_schema','sys') "
                . 'GROUP BY table_schema ORDER BY bytes DESC'
            );
            $rows = $sizeStatement !== false ? $sizeStatement->fetchAll(PDO::FETCH_ASSOC) : [];
            if ($sizeStatement !== false) {
                $sizeStatement->closeCursor();
            }

            $sizes = [];
            foreach (is_array($rows) ? $rows : [] as $row) {
                $name = (string) ($row['name'] ?? '');
                if ($name !== '') {
                    $sizes[$name] = max(0, (int) ($row['bytes'] ?? 0));
                }
            }

            foreach (array_keys($sizes) as $name) {
                if (!in_array($name, $names, true)) {
                    $names[] = $name;
                }
            }
            sort($names, SORT_NATURAL | SORT_FLAG_CASE);

            $databases = [];
            foreach ($names as $name) {
                $databases[] = ['name' => $name, 'bytes' => $sizes[$name] ?? 0];
            }
            usort($databases, static fn (array $a, array $b): int => $b['bytes'] <=> $a['bytes']);

            return [
                'available' => true,
                'label' => 'MariaDB / MySQL',
                'count' => count($databases),
                'total_bytes' => array_sum(array_column($databases, 'bytes')),
                'connections' => $this->mysqlConnections($pdo),
                'databases' => $databases,
                'error' => null,
            ];
        } catch (Throwable $exception) {
            return $this->unavailableDatabase('MariaDB / MySQL', $exception->getMessage());
        }
    }

    /** @return array<string, mixed> */
    private function postgresSnapshot(): array
    {
        try {
            $pdo = $this->postgresPdo();
            if (!$pdo instanceof PDO) {
                return $this->unavailableDatabase(
                    'PostgreSQL',
                    'Brak konfiguracji POSTGRES_MONITOR_DSN w config/modules/system-monitor.env.'
                );
            }

            $statement = $pdo->query(
                'SELECT datname AS name, pg_database_size(datname) AS bytes '
                . 'FROM pg_database WHERE datistemplate = false ORDER BY bytes DESC'
            );
            $rows = $statement !== false ? $statement->fetchAll(PDO::FETCH_ASSOC) : [];
            if ($statement !== false) {
                $statement->closeCursor();
            }

            $databases = [];
            foreach (is_array($rows) ? $rows : [] as $row) {
                $name = (string) ($row['name'] ?? '');
                if ($name !== '') {
                    $databases[] = [
                        'name' => $name,
                        'bytes' => max(0, (int) ($row['bytes'] ?? 0)),
                    ];
                }
            }

            return [
                'available' => true,
                'label' => 'PostgreSQL',
                'count' => count($databases),
                'total_bytes' => array_sum(array_column($databases, 'bytes')),
                'connections' => $this->postgresConnections($pdo),
                'databases' => $databases,
                'error' => null,
            ];
        } catch (Throwable $exception) {
            return $this->unavailableDatabase('PostgreSQL', $exception->getMessage());
        }
    }

    private function mysqlPdo(): ?PDO
    {
        $dsn = trim($this->config['MYSQL_MONITOR_DSN'] ?? '');
        if ($dsn !== '') {
            return $this->newPdo(
                $dsn,
                $this->config['MYSQL_MONITOR_USER'] ?? '',
                $this->config['MYSQL_MONITOR_PASSWORD'] ?? ''
            );
        }

        $pdo = $this->database?->connection()->pdo;
        if (!$pdo instanceof PDO) {
            return null;
        }

        return strtolower((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME)) === 'mysql' ? $pdo : null;
    }

    private function postgresPdo(): ?PDO
    {
        $dsn = trim($this->config['POSTGRES_MONITOR_DSN'] ?? '');
        if ($dsn === '') {
            $pdo = $this->database?->connection()->pdo;
            if ($pdo instanceof PDO && strtolower((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME)) === 'pgsql') {
                return $pdo;
            }
            return null;
        }

        return $this->newPdo(
            $dsn,
            $this->config['POSTGRES_MONITOR_USER'] ?? '',
            $this->config['POSTGRES_MONITOR_PASSWORD'] ?? ''
        );
    }

    private function newPdo(string $dsn, string $user, string $password): PDO
    {
        return new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 3,
            PDO::ATTR_PERSISTENT => false,
        ]);
    }

    private function mysqlConnections(PDO $pdo): ?int
    {
        try {
            $statement = $pdo->query("SHOW STATUS LIKE 'Threads_connected'");
            $row = $statement !== false ? $statement->fetch(PDO::FETCH_NUM) : false;
            if ($statement !== false) {
                $statement->closeCursor();
            }
            return is_array($row) ? (int) ($row[1] ?? 0) : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function postgresConnections(PDO $pdo): ?int
    {
        try {
            $statement = $pdo->query('SELECT COUNT(*) FROM pg_stat_activity');
            $value = $statement !== false ? $statement->fetchColumn() : false;
            if ($statement !== false) {
                $statement->closeCursor();
            }
            return $value !== false ? (int) $value : null;
        } catch (Throwable) {
            return null;
        }
    }

    /** @return array<string, mixed> */
    private function unavailableDatabase(string $label, string $error): array
    {
        return [
            'available' => false,
            'label' => $label,
            'count' => 0,
            'total_bytes' => 0,
            'connections' => null,
            'databases' => [],
            'error' => $this->safeError($error),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function sharedServices(): ?array
    {
        $shared = SystemMonitorAgent::snapshot()['services'] ?? null;
        if (!is_array($shared)) return null;
        // Only PHP knows the SAPI actually serving this installation.
        $shared[] = ['id'=>'php','label'=>'PHP — środowisko WWW','present'=>true,'running'=>true,
            'version'=>PHP_VERSION . ' (' . PHP_SAPI . ')','processes'=>0,'memory_bytes'=>0];
        return $shared;
    }

    private function servicesSnapshot(): array
    {
        $definitions = [
            'apache2' => ['label' => 'Apache2', 'names' => ['apache2', 'httpd']],
            'mariadb' => ['label' => 'MariaDB / MySQL', 'names' => ['mariadbd', 'mysqld']],
            'postgresql' => ['label' => 'PostgreSQL', 'names' => ['postgres']],
            'php' => ['label' => 'PHP — środowisko WWW', 'prefixes' => ['php']],
            'ssh' => ['label' => 'OpenSSH', 'names' => ['sshd']],
            'systemd' => ['label' => 'systemd — usługi i harmonogramy', 'names' => ['systemd']],
            'agent' => ['label' => 'miniPORTAL Node Agent', 'prefixes' => ['miniportal-node']],
            'docker' => ['label' => 'Docker', 'names' => ['dockerd']],
        ];

        $counters = [];
        foreach ($definitions as $key => $definition) {
            $counters[$key] = ['processes' => 0, 'memory_bytes' => 0];
        }

        foreach (glob('/proc/[0-9]*', GLOB_ONLYDIR) ?: [] as $processDirectory) {
            $comm = @file_get_contents($processDirectory . '/comm');
            if (!is_string($comm)) {
                continue;
            }
            $comm = trim($comm);
            foreach ($definitions as $key => $definition) {
                if (!$this->matchesProcess($comm, $definition)) {
                    continue;
                }
                $counters[$key]['processes']++;
                $counters[$key]['memory_bytes'] += $this->processMemory($processDirectory . '/status');
                break;
            }
        }

        $packages = $this->packageVersions();
        $packageMap = ['apache2'=>'apache2','mariadb'=>'mariadb-server','postgresql'=>'postgresql','ssh'=>'openssh-server','systemd'=>'systemd','docker'=>'docker.io'];
        if (!isset($packages['postgresql'])) foreach ($packages as $name => $version) if (preg_match('/^postgresql-[0-9]+$/D', $name)) $packages['postgresql'] = $version;
        $result = [];
        foreach ($definitions as $key => $definition) {
            $result[] = [
                'id' => $key,
                'label' => $definition['label'],
                'running' => $key === 'php' || $counters[$key]['processes'] > 0,
                'version' => $key === 'php' ? PHP_VERSION . ' (' . PHP_SAPI . ')' : ($packages[$packageMap[$key] ?? ''] ?? null),
                'present' => $key === 'php' || isset($packages[$packageMap[$key] ?? '']) || $counters[$key]['processes'] > 0,
                'processes' => $counters[$key]['processes'],
                'memory_bytes' => $counters[$key]['memory_bytes'],
            ];
        }

        return $result;
    }

    private function packageVersions(): array
    {
        $contents = (string)@file_get_contents('/var/lib/dpkg/status'); $versions = [];
        foreach (preg_split('/\n\n/', $contents) ?: [] as $record) {
            if (!str_contains($record, 'Status: install ok installed')) continue;
            if (preg_match('/^Package: (.+)$/m', $record, $name) && preg_match('/^Version: (.+)$/m', $record, $version)) $versions[trim($name[1])] = trim($version[1]);
        }
        return $versions;
    }

    /** @param array<string, mixed> $definition */
    private function matchesProcess(string $comm, array $definition): bool
    {
        if (in_array($comm, $definition['names'] ?? [], true)) {
            return true;
        }
        foreach ($definition['prefixes'] ?? [] as $prefix) {
            if (str_starts_with($comm, (string) $prefix)) {
                return true;
            }
        }
        return false;
    }

    private function processMemory(string $statusFile): int
    {
        $status = @file_get_contents($statusFile);
        return is_string($status) ? $this->processMemoryFromStatus($status) : 0;
    }

    private function processMemoryFromStatus(string $status): int
    {
        if (preg_match('/^VmRSS:\s+(\d+)\s+kB$/m', $status, $matches) !== 1) {
            return 0;
        }
        return (int) $matches[1] * 1024;
    }

    private function processUidFromStatus(string $status): ?int
    {
        if (preg_match('/^Uid:\s+(\d+)\s+/m', $status, $matches) !== 1) {
            return null;
        }
        return (int) $matches[1];
    }

    private function processUnit(string $cgroupFile): ?string
    {
        $lines = @file($cgroupFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return null;
        }

        foreach ($lines as $line) {
            $parts = explode(':', $line, 3);
            if (count($parts) !== 3) {
                continue;
            }
            $segments = array_values(array_filter(explode('/', $parts[2]), static fn (string $value): bool => $value !== ''));
            for ($index = count($segments) - 1; $index >= 0; $index--) {
                $segment = $segments[$index];
                if (preg_match('/^[A-Za-z0-9_.@:-]+\.(?:service|scope)$/D', $segment) === 1) {
                    return $segment;
                }
            }
        }

        return null;
    }

    /** @return array<int, string> */
    private function systemUsers(): array
    {
        $lines = @file('/etc/passwd', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return [];
        }

        $users = [];
        foreach ($lines as $line) {
            $parts = explode(':', $line);
            if (count($parts) < 3 || !ctype_digit($parts[2])) {
                continue;
            }
            $users[(int) $parts[2]] = $parts[0];
        }
        return $users;
    }

    private function systemMemoryTotal(): int
    {
        $lines = @file('/proc/meminfo', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return 0;
        }
        foreach ($lines as $line) {
            if (preg_match('/^MemTotal:\s+(\d+)\s+kB$/D', trim($line), $matches) === 1) {
                return (int) $matches[1] * 1024;
            }
        }
        return 0;
    }

    /**
     * @param array<string, array<string, mixed>> $databases
     * @return array<string, mixed>
     */
    private function storageSnapshot(array $databases): array
    {
        $total = @disk_total_space('/');
        $free = @disk_free_space('/');
        $totalBytes = is_float($total) ? max(0, (int) $total) : 0;
        $freeBytes = is_float($free) ? max(0, (int) $free) : 0;
        $usedBytes = max(0, $totalBytes - $freeBytes);

        $paths = $this->storagePaths();
        $categories = [];
        $seen = [];
        $budgetPerPathMs = max(100, intdiv($this->scanBudgetMs(), max(1, count($paths))));

        foreach ($paths as $label => $path) {
            $real = realpath($path);
            if (!is_string($real) || !is_dir($real) || is_link($real)) {
                $categories[] = [
                    'label' => $label,
                    'path' => $path,
                    'bytes' => $this->databaseFallbackBytes($label, $databases),
                    'complete' => false,
                    'source' => $this->databaseFallbackBytes($label, $databases) > 0 ? 'database' : 'unavailable',
                ];
                continue;
            }

            if ($this->overlaps($real, $seen)) {
                continue;
            }
            $seen[] = rtrim($real, '/');

            $scan = $this->directorySize($real, $budgetPerPathMs);
            $categories[] = [
                'label' => $label,
                'path' => $real,
                'bytes' => $scan['bytes'],
                'complete' => $scan['complete'],
                'source' => $scan['readable'] ? 'filesystem' : 'unavailable',
            ];
        }

        $knownBytes = array_sum(array_map(
            static fn (array $category): int => max(0, (int) ($category['bytes'] ?? 0)),
            $categories
        ));
        $otherBytes = max(0, $usedBytes - min($knownBytes, $usedBytes));

        if ($otherBytes > 0) {
            $categories[] = [
                'label' => 'Niezmierzone / pozostałe (nie jest to rozmiar systemu)',
                'path' => '/',
                'bytes' => $otherBytes,
                'complete' => false,
                'source' => 'calculated',
            ];
        }

        usort($categories, static fn (array $a, array $b): int => ((int) $b['bytes']) <=> ((int) $a['bytes']));

        return [
            'total_bytes' => $totalBytes,
            'used_bytes' => $usedBytes,
            'free_bytes' => $freeBytes,
            'categories' => $categories,
        ];
    }

    /** @return array{bytes:int,complete:bool,readable:bool} */
    private function directorySize(string $path, int $budgetMs): array
    {
        if (!is_readable($path)) {
            return ['bytes' => 0, 'complete' => false, 'readable' => false];
        }

        $bytes = 0;
        $files = 0;
        $deadline = hrtime(true) + ($budgetMs * 1_000_000);
        $complete = true;

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY,
                RecursiveIteratorIterator::CATCH_GET_CHILD
            );
            foreach ($iterator as $file) {
                if (hrtime(true) >= $deadline || $files >= $this->scanMaxFiles()) {
                    $complete = false;
                    break;
                }
                $files++;
                if ($file->isLink() || !$file->isFile()) {
                    continue;
                }
                $size = $file->getSize();
                if (is_int($size) && $size > 0) {
                    $bytes += $size;
                }
            }
        } catch (Throwable) {
            $complete = false;
        }

        return ['bytes' => $bytes, 'complete' => $complete, 'readable' => true];
    }

    /** @return array<string, string> */
    private function storagePaths(): array
    {
        $configured = trim($this->config['SYSTEM_MONITOR_STORAGE_PATHS'] ?? '');
        if ($configured !== '') {
            $paths = [];
            foreach (explode(';', $configured) as $entry) {
                [$label, $path] = array_pad(explode('=', $entry, 2), 2, '');
                $label = trim($label);
                $path = trim($path);
                if ($label !== '' && str_starts_with($path, '/')) {
                    $paths[$label] = $path;
                }
            }
            if ($paths !== []) {
                return $paths;
            }
        }

        $paths = [
            'WWW' => '/var/www',
            'MariaDB' => '/var/lib/mysql',
            'PostgreSQL' => '/var/lib/postgresql',
            'Logi' => '/var/log',
            'Docker' => '/var/lib/docker',
        ];
        if (is_dir('/home/debian/server')) {
            $paths['Minecraft'] = '/home/debian/server';
        }
        return $paths;
    }

    /** @param list<string> $seen */
    private function overlaps(string $path, array $seen): bool
    {
        $path = rtrim($path, '/');
        foreach ($seen as $existing) {
            if ($path === $existing || str_starts_with($path . '/', $existing . '/') || str_starts_with($existing . '/', $path . '/')) {
                return true;
            }
        }
        return false;
    }

    /** @param array<string, array<string, mixed>> $databases */
    private function databaseFallbackBytes(string $label, array $databases): int
    {
        $normalized = strtolower($label);
        if (str_contains($normalized, 'maria') || str_contains($normalized, 'mysql')) {
            return max(0, (int) ($databases['mysql']['total_bytes'] ?? 0));
        }
        if (str_contains($normalized, 'postgres')) {
            return max(0, (int) ($databases['postgresql']['total_bytes'] ?? 0));
        }
        return 0;
    }

    /** @return array<string, string> */
    private function loadConfig(): array
    {
        $values = [];
        $path = $this->applicationRoot . '/config/modules/system-monitor.env';
        if (is_file($path) && is_readable($path)) {
            $parsed = parse_ini_file($path, false, INI_SCANNER_RAW);
            if (is_array($parsed)) {
                foreach ($parsed as $key => $value) {
                    if (is_string($key) && is_scalar($value)) {
                        $values[$key] = trim((string) $value);
                    }
                }
            }
        }

        foreach ([
            'SYSTEM_MONITOR_CACHE_TTL',
            'SYSTEM_MONITOR_SCAN_MAX_FILES',
            'SYSTEM_MONITOR_SCAN_BUDGET_MS',
            'SYSTEM_MONITOR_STORAGE_PATHS',
            'MYSQL_MONITOR_DSN',
            'MYSQL_MONITOR_USER',
            'MYSQL_MONITOR_PASSWORD',
            'POSTGRES_MONITOR_DSN',
            'POSTGRES_MONITOR_USER',
            'POSTGRES_MONITOR_PASSWORD',
        ] as $key) {
            $environment = getenv($key);
            if (is_string($environment) && $environment !== '') {
                $values[$key] = $environment;
            }
        }

        return $values;
    }

    private function cacheTtl(): int
    {
        return $this->boundedInt('SYSTEM_MONITOR_CACHE_TTL', 600, 30, 3600);
    }

    private function scanMaxFiles(): int
    {
        return $this->boundedInt('SYSTEM_MONITOR_SCAN_MAX_FILES', 75000, 1000, 500000);
    }

    private function scanBudgetMs(): int
    {
        return $this->boundedInt('SYSTEM_MONITOR_SCAN_BUDGET_MS', 2500, 250, 15000);
    }

    private function boundedInt(string $key, int $default, int $minimum, int $maximum): int
    {
        $value = filter_var($this->config[$key] ?? null, FILTER_VALIDATE_INT);
        if (!is_int($value)) {
            return $default;
        }
        return max($minimum, min($maximum, $value));
    }

    /** @return array<string, mixed>|null */
    private function readCache(): ?array
    {
        if (!is_file($this->cacheFile) || !is_readable($this->cacheFile)) {
            return null;
        }
        $mtime = @filemtime($this->cacheFile);
        if (!is_int($mtime) || (time() - $mtime) >= $this->cacheTtl()) {
            return null;
        }
        $json = @file_get_contents($this->cacheFile);
        $decoded = is_string($json) ? json_decode($json, true) : null;
        return is_array($decoded) && ($decoded['schema'] ?? 0) === 3 ? $decoded : null;
    }

    /** @param array<string, mixed> $snapshot */
    private function writeCache(array $snapshot): void
    {
        $encoded = json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if (!is_string($encoded)) {
            return;
        }
        $temporary = $this->cacheFile . '.' . getmypid() . '.tmp';
        if (@file_put_contents($temporary, $encoded, LOCK_EX) === false) {
            return;
        }
        @chmod($temporary, 0600);
        @rename($temporary, $this->cacheFile);
    }

    private function safeError(string $message): string
    {
        $message = preg_replace('/password\s*=\s*[^\s;]+/i', 'password=[ukryte]', $message) ?? $message;
        return mb_substr(trim($message), 0, 240);
    }
}
