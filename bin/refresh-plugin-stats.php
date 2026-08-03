<?php

declare(strict_types=1);

use SyntaxDevTeam\Cms\Core\Autoloader;
use SyntaxDevTeam\Cms\Database\CrudApp;
use SyntaxDevTeam\Cms\Modules\PluginStats\PluginStatsDashboardCache;
use SyntaxDevTeam\Cms\Modules\PluginStats\PluginStatsRepository;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

require_once dirname(__DIR__) . '/core/Autoloader.php';
Autoloader::register();

$options = getopt('', ['ranges::', 'help']);
if (isset($options['help'])) {
    fwrite(STDOUT, "Użycie: php bin/refresh-plugin-stats.php [--ranges=7,30,90,180]\n");
    fwrite(STDOUT, "Cron: */15 * * * * cd /var/www/syntaxdevteam.pl && php bin/refresh-plugin-stats.php --ranges=7,30 >> cache/plugin-stats-refresh.log 2>&1\n");
    exit(0);
}

try {
    $config = require dirname(__DIR__) . '/config/config.php';
    $databaseConfig = is_array($config['database'] ?? null) ? $config['database'] : [];
    if (($databaseConfig['enabled'] ?? false) !== true) {
        throw new RuntimeException('Baza danych jest wyłączona.');
    }
    unset($databaseConfig['enabled']);
    $moduleConfig = is_array($config['modules'] ?? null) ? $config['modules'] : [];
    $rangesOption = is_string($options['ranges'] ?? null) ? $options['ranges'] : '7,30';
    $ranges = array_values(array_intersect(
        [7, 30, 90, 180],
        array_map('intval', explode(',', $rangesOption))
    ));
    if ($ranges === []) {
        throw new RuntimeException('Zakres musi zawierać co najmniej jedną wartość: 7, 30, 90 albo 180.');
    }

    $repository = new PluginStatsRepository(CrudApp::getInstance($databaseConfig));
    $cache = new PluginStatsDashboardCache(
        $repository,
        dirname(__DIR__) . '/cache/plugin-stats',
        (int) ($moduleConfig['plugin_stats_dashboard_ttl'] ?? 1800),
        (int) ($moduleConfig['plugin_stats_dashboard_stale_ttl'] ?? 86400)
    );
    $onlineMinutes = max(5, min(240, (int) ($moduleConfig['plugin_stats_online_window_minutes'] ?? 30)));
    foreach ($ranges as $range) {
        $startedAt = microtime(true);
        $data = $cache->refresh($range, $onlineMinutes, true);
        fwrite(STDOUT, sprintf(
            "[%s] plugin-stats range=%d generated_at=%d duration_ms=%.2f\n",
            date('Y-m-d H:i:s'),
            $range,
            (int) ($data['generated_at'] ?? time()),
            (microtime(true) - $startedAt) * 1000
        ));
    }
} catch (Throwable $exception) {
    fwrite(STDERR, '[' . date('Y-m-d H:i:s') . '] plugin-stats refresh failed: ' . $exception->getMessage() . "\n");
    exit(1);
}

