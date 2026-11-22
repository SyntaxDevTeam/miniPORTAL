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
    fwrite(STDOUT, "Użycie: php bin/run-retention.php [--dry-run] [--builds-only] [--batch-size=1000] [--max-rows=20000]\n");
    fwrite(STDOUT, "Cron przykład: 17 3 * * * cd /var/www/syntaxdevteam.pl && php bin/run-retention.php >> cache/retention.log 2>&1\n");
    exit(0);
}
$dryRun = in_array('--dry-run', $args, true);
$buildsOnly = in_array('--builds-only', $args, true);
$options = getopt('', ['dry-run', 'builds-only', 'batch-size::', 'max-rows::']);
$batchSize = max(100, min(5000, (int) ($options['batch-size'] ?? 1000)));
$maxRows = max($batchSize, min(100000, (int) ($options['max-rows'] ?? 20000)));

try {
    $config = require dirname(__DIR__) . '/config/config.php';
    $databaseConfig = $config['database'] ?? [];
    if (($databaseConfig['enabled'] ?? false) !== true) {
        throw new RuntimeException('Baza danych jest wyłączona.');
    }
    unset($databaseConfig['enabled']);

    $database = CrudApp::getInstance($databaseConfig);
    if ($buildsOnly) {
        $state = (new \SyntaxDevTeam\Cms\Core\ModuleStateRepository($database))->find('build_explorer');
        if ($state?->isActive() !== true) { fwrite(STDOUT, "{\"build_retention\":\"inactive\"}\n"); exit(0); }
        $service = new \SyntaxDevTeam\Cms\Modules\BuildExplorer\BuildRetentionService(
            new \SyntaxDevTeam\Cms\Modules\BuildExplorer\BuildRepository($database),
            new \SyntaxDevTeam\Cms\Modules\BuildExplorer\BuildArtifactStorage(dirname(__DIR__) . '/cache/build-artifacts'),
        );
        $result = $dryRun ? $service->preview() : $service->runDue();
        fwrite(STDOUT, json_encode(['build_retention' => $result, 'dry_run' => $dryRun], JSON_THROW_ON_ERROR) . "\n");
        exit(0);
    }
    $pdo = $database->connection()->pdo;
    $modules = is_array($config['modules'] ?? null) ? $config['modules'] : [];
    $auth = is_array($config['auth'] ?? null) ? $config['auth'] : [];

    $summary = [];
    $pluginStatsRetentionEnabled = ($modules['plugin_stats_raw_retention_enabled'] ?? false) === true;
    $summary[] = $pluginStatsRetentionEnabled
        ? purgePluginStats(
            $pdo,
            (int) ($modules['plugin_stats_retention_days'] ?? 180),
            $dryRun,
            $batchSize,
            $maxRows
        )
        : purgeTable(
            $pdo,
            'plugin_usage_events[retention-disabled]',
            'created_at',
            (int) ($modules['plugin_stats_retention_days'] ?? 180),
            true,
            $batchSize,
            $maxRows,
            'plugin_usage_events'
        );
    $summary[] = purgeTable(
        $pdo,
        'licenses_check',
        'checked_at',
        (int) ($modules['licences_check_retention_days'] ?? 180),
        $dryRun,
        $batchSize,
        $maxRows
    );
    $summary[] = purgeRateLimitFiles(
        dirname(__DIR__) . '/cache/rate-limits',
        (int) ($modules['rate_limit_retention_days'] ?? 2),
        $dryRun
    );
    $summary[] = purgeTable(
        $pdo,
        'auth_events_archive',
        'created_at',
        (int) ($auth['audit_archive_retention_days'] ?? 365),
        $dryRun,
        $batchSize,
        $maxRows
    );
    $summary[] = purgeCompletedErasureRequests(
        $pdo,
        (int) ($modules['account_erasure_request_retention_days'] ?? 365),
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
function purgeTable(
    PDO $pdo,
    string $table,
    string $dateColumn,
    int $retentionDays,
    bool $dryRun,
    int $batchSize,
    int $maxRows,
    ?string $physicalTable = null,
    ?string $fixedCutoff = null,
): array
{
    $retentionDays = max(1, min(3650, $retentionDays));
    $cutoff = $fixedCutoff ?? gmdate('Y-m-d H:i:s', time() - ($retentionDays * 86400));
    $physicalTable ??= $table;
    if (!tableExists($pdo, $physicalTable)) {
        return ['name' => $table, 'cutoff' => $cutoff, 'matched' => 0, 'deleted' => 0, 'archived' => 0, 'dry_run' => $dryRun];
    }

    $matched = countOlderThan($pdo, $physicalTable, $dateColumn, $cutoff);
    $deleted = 0;
    if (!$dryRun && $matched > 0) {
        while ($deleted < $maxRows) {
            $limit = min($batchSize, $maxRows - $deleted);
            $statement = $pdo->prepare(
                "DELETE FROM {$physicalTable} WHERE {$dateColumn} < :cutoff ORDER BY {$dateColumn} ASC LIMIT {$limit}"
            );
            $statement->execute([':cutoff' => $cutoff]);
            $affected = $statement->rowCount();
            $deleted += $affected;
            if ($affected < $limit) {
                break;
            }
        }
    }

    return ['name' => $table, 'cutoff' => $cutoff, 'matched' => $matched, 'deleted' => $deleted, 'archived' => 0, 'dry_run' => $dryRun];
}

/** @return array{name:string, cutoff:string, matched:int, deleted:int, archived:int, dry_run:bool} */
function purgePluginStats(
    PDO $pdo,
    int $retentionDays,
    bool $dryRun,
    int $batchSize,
    int $maxRows,
): array {
    $retentionDays = max(1, min(3650, $retentionDays));
    $cutoffDate = gmdate('Y-m-d', time() - ($retentionDays * 86400));
    $cutoff = $cutoffDate . ' 00:00:00';
    foreach (['plugin_usage_events', 'plugin_stats_daily_rollups', 'plugin_stats_monthly_rollups', 'plugin_stats_aggregation_state'] as $table) {
        if (!tableExists($pdo, $table)) {
            throw new RuntimeException("Retencja Plugin Stats wymaga tabeli {$table}.");
        }
    }
    $oldest = $pdo->prepare('SELECT MIN(DATE(created_at)) FROM plugin_usage_events WHERE created_at < :cutoff');
    $oldest->execute([':cutoff' => $cutoff]);
    $oldestDate = $oldest->fetchColumn();
    if (!is_string($oldestDate) || $oldestDate === '') {
        return ['name' => 'plugin_usage_events', 'cutoff' => $cutoff, 'matched' => 0, 'deleted' => 0, 'archived' => 0, 'dry_run' => $dryRun];
    }
    $states = $pdo->query(
        "SELECT aggregation_key, covered_from, covered_through FROM plugin_stats_aggregation_state "
        . "WHERE aggregation_key IN ('daily', 'monthly')"
    )->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);
    $oldestMonth = substr($oldestDate, 0, 7) . '-01';
    $cutoffMonthEnd = gmdate('Y-m-t', strtotime($cutoffDate . ' UTC'));
    if (
        !isset($states['daily'], $states['monthly'])
        || (string) ($states['daily']['covered_from'] ?? '') > $oldestDate
        || (string) ($states['daily']['covered_through'] ?? '') < $cutoffDate
        || (string) ($states['monthly']['covered_from'] ?? '') > $oldestMonth
        || (string) ($states['monthly']['covered_through'] ?? '') < $cutoffMonthEnd
    ) {
        throw new RuntimeException('Retencja Plugin Stats zablokowana: agregaty nie pokrywają całego usuwanego zakresu.');
    }
    $coverage = $pdo->prepare(
        'SELECT COUNT(*) FROM ('
        . 'SELECT DATE(created_at) AS event_date, COUNT(*) AS raw_events FROM plugin_usage_events '
        . 'WHERE created_at < :cutoff GROUP BY DATE(created_at)'
        . ') raw LEFT JOIN ('
        . 'SELECT bucket_date, SUM(event_count) AS aggregate_events FROM plugin_stats_daily_rollups '
        . 'WHERE bucket_date < :cutoff_date GROUP BY bucket_date'
        . ') rollups ON rollups.bucket_date = raw.event_date '
        . 'WHERE COALESCE(rollups.aggregate_events, 0) < raw.raw_events'
    );
    $coverage->execute([':cutoff' => $cutoff, ':cutoff_date' => $cutoffDate]);
    if ((int) $coverage->fetchColumn() !== 0) {
        throw new RuntimeException('Retencja Plugin Stats zablokowana: co najmniej jeden dzień nie ma kompletnego agregatu.');
    }

    return purgeTable(
        $pdo,
        'plugin_usage_events',
        'created_at',
        $retentionDays,
        $dryRun,
        $batchSize,
        $maxRows,
        null,
        $cutoff
    );
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

/** @return array{name:string, cutoff:string, matched:int, deleted:int, archived:int, dry_run:bool} */
function purgeCompletedErasureRequests(PDO $pdo, int $retentionDays, bool $dryRun): array
{
    $retentionDays = max(30, min(3650, $retentionDays));
    $cutoff = gmdate('Y-m-d H:i:s', time() - ($retentionDays * 86400));
    if (!tableExists($pdo, 'account_erasure_requests')) {
        return ['name' => 'account_erasure_requests', 'cutoff' => $cutoff, 'matched' => 0, 'deleted' => 0, 'archived' => 0, 'dry_run' => $dryRun];
    }
    $count = $pdo->prepare("SELECT COUNT(*) FROM account_erasure_requests WHERE status = 'completed' AND completed_at < :cutoff");
    $count->execute([':cutoff' => $cutoff]);
    $matched = (int) $count->fetchColumn();
    $deleted = 0;
    if (!$dryRun && $matched > 0) {
        $delete = $pdo->prepare("DELETE FROM account_erasure_requests WHERE status = 'completed' AND completed_at < :cutoff");
        $delete->execute([':cutoff' => $cutoff]);
        $deleted = $delete->rowCount();
    }
    return ['name' => 'account_erasure_requests', 'cutoff' => $cutoff, 'matched' => $matched, 'deleted' => $deleted, 'archived' => 0, 'dry_run' => $dryRun];
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
