<?php

declare(strict_types=1);

use SyntaxDevTeam\Cms\Core\Autoloader;
use SyntaxDevTeam\Cms\Core\FileTemplateCache;
use SyntaxDevTeam\Cms\Database\CrudApp;
use SyntaxDevTeam\Cms\Modules\Uptime\UptimeMonitorChecker;
use SyntaxDevTeam\Cms\Modules\Uptime\UptimeRepository;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

require_once dirname(__DIR__) . '/core/Autoloader.php';

Autoloader::register();

if (in_array($argv[1] ?? '', ['-h', '--help'], true)) {
    fwrite(STDOUT, "Użycie: php bin/check-uptime.php\n");
    fwrite(STDOUT, "Cron przykład: * * * * * cd /var/www/syntaxdevteam.pl && php bin/check-uptime.php >> cache/uptime-cron.log 2>&1\n");
    exit(0);
}

try {
    $config = require dirname(__DIR__) . '/config/config.php';
    $databaseConfig = $config['database'] ?? [];
    if (($databaseConfig['enabled'] ?? false) !== true) {
        throw new RuntimeException('Baza danych jest wyłączona.');
    }
    unset($databaseConfig['enabled']);

    $cacheConfig = is_array($config['cache'] ?? null) ? $config['cache'] : [];
    $cache = new FileTemplateCache(
        dirname(__DIR__) . '/cache/templates',
        ($cacheConfig['enabled'] ?? true) === true,
        (int) ($cacheConfig['ttl'] ?? 300),
    );

    $result = (new UptimeMonitorChecker(
        new UptimeRepository(CrudApp::getInstance($databaseConfig)),
        $cache,
    ))->check();

    fwrite(
        STDOUT,
        sprintf(
            "[%s] uptime check: changed=%d notified=%d\n",
            date('Y-m-d H:i:s'),
            $result['changed'],
            $result['notified'],
        )
    );
} catch (Throwable $exception) {
    fwrite(STDERR, '[' . date('Y-m-d H:i:s') . '] uptime check failed: ' . $exception->getMessage() . "\n");
    exit(1);
}
