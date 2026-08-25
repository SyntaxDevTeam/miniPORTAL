<?php

declare(strict_types=1);

use SyntaxDevTeam\Cms\Core\{Autoloader, EventBus};
use SyntaxDevTeam\Cms\Database\CrudApp;
use SyntaxDevTeam\Cms\Modules\Payments\{
    DatabasePaymentWebhookPaymentUpdater,
    DatabasePaymentWebhookStore,
    PaymentWebhookDispatcher,
    PaymentWebhookEventHandler
};

require_once dirname(__DIR__) . '/core/Autoloader.php';
Autoloader::register();

$config = require dirname(__DIR__) . '/config/config.php';
$databaseConfig = is_array($config['database'] ?? null) ? $config['database'] : [];
if (($databaseConfig['enabled'] ?? false) !== true) {
    fwrite(STDERR, "Baza danych nie jest skonfigurowana.\n");
    exit(1);
}
$database = CrudApp::make($databaseConfig);
$store = new DatabasePaymentWebhookStore($database);
$dispatcher = new PaymentWebhookDispatcher(
    $store,
    new PaymentWebhookEventHandler(new DatabasePaymentWebhookPaymentUpdater($database), new EventBus()),
);
$processed = $dispatcher->dispatch(50);
$modulesConfig = is_array($config['modules'] ?? null) ? $config['modules'] : [];
$retentionDays = max(1, (int) ($modulesConfig['payments_webhook_payload_retention_days'] ?? 30));
$purged = $store->purgePayloads(new DateTimeImmutable("-{$retentionDays} days", new DateTimeZone('UTC')), 500);
$stats = $store->stats();
echo json_encode(['processed' => $processed, 'payloads_purged' => $purged, 'queue' => $stats], JSON_THROW_ON_ERROR) . PHP_EOL;
