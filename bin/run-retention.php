<?php

declare(strict_types=1);

use SyntaxDevTeam\Cms\Core\Autoloader;
use SyntaxDevTeam\Cms\Database\CrudApp;
use SyntaxDevTeam\Cms\Modules\System\SystemLogRepository;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

require_once dirname(__DIR__) . '/core/Autoloader.php';

Autoloader::register();

$args = array_slice($argv, 1);
if (in_array('-h', $args, true) || in_array('--help', $args, true)) {
    fwrite(STDOUT, "Użycie: php bin/run-retention.php [--dry-run]\n");
    fwrite(STDOUT, "Cron przykład: 17 3 * * * cd /var/www/syntaxdevteam.pl && php bin/run-retention.php >> cache/retention.log 2>&1\n");
    exit(0);
}
$dryRun = in_array('--dry-run', $args, true);

try {
    $config = require dirname(__DIR__) . '/config/config.php';
    $databaseConfig = $config['database'] ?? [];
    if (($databaseConfig['enabled'] ?? false) !== true) {
        throw new RuntimeException('Baza danych jest wyłączona.');
    }
    unset($databaseConfig['enabled']);

    $database = CrudApp::getInstance($databaseConfig);
    $pdo = $database->connection()->pdo;
    $modules = is_array($config['modules'] ?? null) ? $config['modules'] : [];
    $auth = is_array($config['auth'] ?? null) ? $config['auth'] : [];

    $summary = [];
    $summary[] = purgeTable(
        $pdo,
        'plugin_usage_events',
        'created_at',
        (int) ($modules['plugin_stats_retention_days'] ?? 180),
        $dryRun
    );
    $summary[] = purgeTable(
        $pdo,
        'licenses_check',
        'checked_at',
        (int) ($modules['licences_check_retention_days'] ?? 180),
        $dryRun
    );
    $summary[] = purgeRateLimitFiles(
        dirname(__DIR__) . '/cache/rate-limits',
        (int) ($modules['rate_limit_retention_days'] ?? 2),
        $dryRun
    );

    if ($dryRun) {
        $retentionDays = max(1, min(3650, (int) ($auth['audit_retention_days'] ?? 180)));
        $cutoff = gmdate('Y-m-d H:i:s', time() - ($retentionDays * 86400));
        $count = countOlderThan($pdo, 'auth_events', 'created_at', $cutoff);
        $summary[] = [
            'name' => 'auth_events',
            'cutoff' => $cutoff,
            'deleted' => 0,
            'archived' => 0,
            'matched' => $count,
            'dry_run' => true,
        ];
    } else {
        $archive = (new SystemLogRepository($database))->archiveOlderThan(
            (int) ($auth['audit_retention_days'] ?? 180),
            (int) ($auth['audit_archive_limit'] ?? 5000)
        );
        $summary[] = [
            'name' => 'auth_events',
            'cutoff' => $archive['cutoff'],
            'deleted' => $archive['deleted'],
            'archived' => $archive['archived'],
            'matched' => $archive['deleted'],
            'dry_run' => false,
        ];
    }

    foreach ($summary as $entry) {
        fwrite(STDOUT, sprintf(
            "[%s] retention %s cutoff=%s matched=%d deleted=%d archived=%d dry_run=%s\n",
            date('Y-m-d H:i:s'),
            $entry['name'],
            $entry['cutoff'],
            $entry['matched'],
            $entry['deleted'],
            $entry['archived'],
            $entry['dry_run'] ? 'yes' : 'no'
        ));
    }
} catch (Throwable $exception) {
    fwrite(STDERR, '[' . date('Y-m-d H:i:s') . '] retention failed: ' . $exception->getMessage() . "\n");
    exit(1);
}

/**
 * @return array{name:string, cutoff:string, matched:int, deleted:int, archived:int, dry_run:bool}
 */
function purgeTable(PDO $pdo, string $table, string $dateColumn, int $retentionDays, bool $dryRun): array
{
    $retentionDays = max(1, min(3650, $retentionDays));
    $cutoff = gmdate('Y-m-d H:i:s', time() - ($retentionDays * 86400));
    if (!tableExists($pdo, $table)) {
        return ['name' => $table, 'cutoff' => $cutoff, 'matched' => 0, 'deleted' => 0, 'archived' => 0, 'dry_run' => $dryRun];
    }

    $matched = countOlderThan($pdo, $table, $dateColumn, $cutoff);
    $deleted = 0;
    if (!$dryRun && $matched > 0) {
        $statement = $pdo->prepare("DELETE FROM {$table} WHERE {$dateColumn} < :cutoff");
        $statement->execute([':cutoff' => $cutoff]);
        $deleted = $statement->rowCount();
    }

    return ['name' => $table, 'cutoff' => $cutoff, 'matched' => $matched, 'deleted' => $deleted, 'archived' => 0, 'dry_run' => $dryRun];
}

function tableExists(PDO $pdo, string $table): bool
{
    $statement = $pdo->prepare('SHOW TABLES LIKE :table');
    $statement->execute([':table' => $table]);

    return $statement->fetchColumn() !== false;
}

function countOlderThan(PDO $pdo, string $table, string $dateColumn, string $cutoff): int
{
    if (!tableExists($pdo, $table)) {
        return 0;
    }
    $statement = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE {$dateColumn} < :cutoff");
    $statement->execute([':cutoff' => $cutoff]);

    return (int) $statement->fetchColumn();
}

/**
 * @return array{name:string, cutoff:string, matched:int, deleted:int, archived:int, dry_run:bool}
 */
function purgeRateLimitFiles(string $path, int $retentionDays, bool $dryRun): array
{
    $retentionDays = max(1, min(30, $retentionDays));
    $cutoffTimestamp = time() - ($retentionDays * 86400);
    $cutoff = gmdate('Y-m-d H:i:s', $cutoffTimestamp);
    $matched = 0;
    $deleted = 0;
    foreach (glob(rtrim($path, '/') . '/*.rate') ?: [] as $file) {
        if (!is_file($file) || is_link($file) || (int) filemtime($file) >= $cutoffTimestamp) {
            continue;
        }
        $matched++;
        if (!$dryRun && @unlink($file)) {
            $deleted++;
        }
    }

    return ['name' => 'rate_limit_files', 'cutoff' => $cutoff, 'matched' => $matched, 'deleted' => $deleted, 'archived' => 0, 'dry_run' => $dryRun];
}
