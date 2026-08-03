<?php

declare(strict_types=1);

use SyntaxDevTeam\Cms\Core\Autoloader;
use SyntaxDevTeam\Cms\Database\CrudApp;
use SyntaxDevTeam\Cms\Modules\PluginStats\PluginStatsAggregationRepository;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

require_once dirname(__DIR__) . '/core/Autoloader.php';
Autoloader::register();

$options = getopt('', ['from::', 'through::', 'recent-days::', 'status', 'help']);
if (isset($options['help'])) {
    fwrite(STDOUT, "Użycie: php bin/aggregate-plugin-stats.php [--from=YYYY-MM-DD] [--through=YYYY-MM-DD] [--recent-days=2] [--status]\n");
    fwrite(STDOUT, "Bez zakresu pierwszy start wykonuje pełny backfill, a kolejne odświeżają dwa ostatnie dni.\n");
    exit(0);
}

try {
    $config = require dirname(__DIR__) . '/config/config.php';
    $databaseConfig = is_array($config['database'] ?? null) ? $config['database'] : [];
    if (($databaseConfig['enabled'] ?? false) !== true) {
        throw new RuntimeException('Baza danych jest wyłączona.');
    }
    unset($databaseConfig['enabled']);
    $repository = new PluginStatsAggregationRepository(CrudApp::getInstance($databaseConfig));

    if (isset($options['status'])) {
        $status = $repository->status();
        if ($status === []) {
            fwrite(STDOUT, "Brak wykonanych agregacji.\n");
        }
        foreach ($status as $row) {
            fwrite(STDOUT, sprintf(
                "%s from=%s through=%s source_events=%d rows=%d last_event_id=%s refreshed_at=%s\n",
                (string) ($row['aggregation_key'] ?? ''),
                (string) ($row['covered_from'] ?? '—'),
                (string) ($row['covered_through'] ?? '—'),
                (int) ($row['source_event_count'] ?? 0),
                (int) ($row['rollup_row_count'] ?? 0),
                (string) ($row['last_event_id'] ?? '—'),
                (string) ($row['refreshed_at'] ?? '—')
            ));
        }
        exit(0);
    }

    $source = $repository->sourceRange();
    if ($source['from'] === null || $source['through'] === null) {
        fwrite(STDOUT, "Brak surowych zdarzeń do agregacji.\n");
        exit(0);
    }

    $from = is_string($options['from'] ?? null) ? $options['from'] : null;
    $through = is_string($options['through'] ?? null) ? $options['through'] : null;
    $recentDays = max(1, min(31, (int) ($options['recent-days'] ?? 2)));
    if ($from === null) {
        $state = $repository->status();
        $dailyState = null;
        foreach ($state as $row) {
            if (($row['aggregation_key'] ?? null) === 'daily') {
                $dailyState = $row;
                break;
            }
        }
        $from = $dailyState === null
            ? $source['from']
            : gmdate('Y-m-d', strtotime((string) $source['through'] . ' 00:00:00 UTC') - (($recentDays - 1) * 86400));
    }
    $through ??= $source['through'];
    if ($from < $source['from']) {
        $from = $source['from'];
    }
    if ($through > $source['through']) {
        $through = $source['through'];
    }

    $startedAt = microtime(true);
    fwrite(STDOUT, sprintf(
        "[%s] plugin-stats aggregation start from=%s through=%s source_events=%d\n",
        gmdate('Y-m-d H:i:s'),
        $from,
        $through,
        $source['events']
    ));
    $result = $repository->rebuild(
        $from,
        $through,
        static function (string $type, array $entry): void {
            fwrite(STDOUT, sprintf(
                "[%s] aggregate type=%s bucket=%s rows=%d\n",
                gmdate('Y-m-d H:i:s'),
                $type,
                (string) ($entry['date'] ?? ''),
                (int) ($entry['rows'] ?? 0)
            ));
        }
    );
    fwrite(STDOUT, sprintf(
        "[%s] plugin-stats aggregation complete days=%d months=%d daily_rows=%d monthly_rows=%d latest_rows=%d duration_ms=%.2f\n",
        gmdate('Y-m-d H:i:s'),
        $result['days'],
        $result['months'],
        $result['daily_rows'],
        $result['monthly_rows'],
        $result['latest_rows'],
        (microtime(true) - $startedAt) * 1000
    ));
} catch (Throwable $exception) {
    fwrite(STDERR, '[' . gmdate('Y-m-d H:i:s') . '] plugin-stats aggregation failed: ' . $exception->getMessage() . "\n");
    exit(1);
}
