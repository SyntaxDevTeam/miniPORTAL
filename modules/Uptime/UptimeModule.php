<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\Uptime;

use SyntaxDevTeam\Cms\Core\AdminMenuRegistry;
use SyntaxDevTeam\Cms\Core\AdminSearchProviderInterface;
use SyntaxDevTeam\Cms\Core\AdminSearchRegistry;
use SyntaxDevTeam\Cms\Core\DashboardProviderInterface;
use SyntaxDevTeam\Cms\Core\DashboardRegistry;
use SyntaxDevTeam\Cms\Core\HookProviderInterface;
use SyntaxDevTeam\Cms\Core\HookRegistry;
use SyntaxDevTeam\Cms\Core\ModuleInterface;
use SyntaxDevTeam\Cms\Core\PublicNavigationProviderInterface;
use SyntaxDevTeam\Cms\Core\PublicNavigationRegistry;
use SyntaxDevTeam\Cms\Core\RateLimiter;
use SyntaxDevTeam\Cms\Core\Request;
use SyntaxDevTeam\Cms\Core\Router;
use SyntaxDevTeam\Cms\Core\Security;
use SyntaxDevTeam\Cms\Core\SeoIndex;
use SyntaxDevTeam\Cms\Core\SeoProviderInterface;
use SyntaxDevTeam\Cms\Core\TemplateCacheInterface;
use SyntaxDevTeam\Cms\Core\ThemeInterface;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuditLogService;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuthService;
use SyntaxDevTeam\Cms\Modules\CoreAuth\User;
use SyntaxDevTeam\Cms\Modules\Widgets\Widget;
use SyntaxDevTeam\Cms\Modules\Widgets\WidgetDefinition;

final class UptimeModule implements ModuleInterface, PublicNavigationProviderInterface, HookProviderInterface, AdminSearchProviderInterface, DashboardProviderInterface, SeoProviderInterface
{
    private const TYPES = [
        'heartbeat' => 'Heartbeat / event',
        'http' => 'HTTP/API',
        'manual' => 'Manualny',
    ];

    private const STATUSES = [
        'up' => 'Online',
        'warn' => 'Ostrzeżenie',
        'down' => 'Offline',
        'neutral' => 'Brak danych',
    ];

    private const NOTIFICATIONS = [
        'none' => 'Brak',
        'discord_webhook' => 'Discord webhook',
    ];

    private readonly UptimeMonitorChecker $checker;

    public function __construct(
        private readonly ThemeInterface $theme,
        private readonly AdminMenuRegistry $menu,
        private readonly UptimeRepository $uptime,
        private readonly AuthService $auth,
        private readonly Security $security,
        private readonly AuditLogService $audit,
        private readonly TemplateCacheInterface $cache,
        private readonly RateLimiter $rateLimiter,
        private readonly array $config = [],
        ?UptimeMonitorChecker $checker = null,
    ) {
        $this->checker = $checker ?? new UptimeMonitorChecker($this->uptime, $this->cache);
    }

    public function id(): string
    {
        return 'uptime';
    }

    public function version(): string
    {
        return '1.1.4';
    }

    public function dependencies(): array
    {
        return ['core_auth', 'core_pages', 'widgets'];
    }

    public function isProtected(): bool
    {
        return false;
    }

    public function requiredPermissions(): array
    {
        return ['uptime.manage'];
    }

    public function registerAdminMenu(AdminMenuRegistry $menu): void
    {
        $menu->add('Narzędzia', 'Uptime', '/admin/uptime', 'UP', 'uptime.manage', 35);
    }

    public function registerPublicNavigation(PublicNavigationRegistry $navigation): void
    {
        $navigation->add('uptime.index', 'Uptime', '/uptime', 'none', 80);
    }

    public function registerHooks(HookRegistry $hooks): void
    {
        $hooks->addFilter('widgets.definitions', function (array $definitions): array {
            $definitions[] = new WidgetDefinition(
                'uptime.status_panel',
                'uptime',
                'Status monitorów',
                'Dynamiczny panel statusu z elementów zarządzanych w module Uptime.',
                'Monitoring',
                'UP',
                'uptime',
                [
                    'name' => 'Status monitorów Uptime',
                    'widget_key' => 'uptime-monitors',
                    'placement' => 'after_hero',
                    'title' => 'Service uptime',
                    'content' => "Statusy zostaną pobrane automatycznie z modułu Uptime.",
                    'content_help' => 'Treść publiczna jest generowana automatycznie z aktywnych monitorów Uptime. To pole jest tylko awaryjnym fallbackiem, gdy lista monitorów jest pusta.',
                    'sort_order' => 20,
                ],
                function (Widget $widget): array {
                    $this->evaluateStaleMonitors();
                    $content = $this->uptime->widgetContent();
                    if ($content === '') {
                        return [];
                    }

                    return [
                        'content' => $content,
                        'content_format' => 'html',
                        'title' => $widget->title !== '' ? $widget->title : 'Service uptime',
                    ];
                },
            );

            return $definitions;
        }, 90);
    }

    public function registerAdminSearch(AdminSearchRegistry $search): void
    {
        $search->add(
            'uptime.create',
            'Dodaj monitor Uptime',
            'Dodaj monitor bota, API albo usługi strony.',
            'index.php?route=/admin/uptime/create',
            ['uptime', 'monitoring', 'status', 'bot', 'api'],
            'uptime.manage',
            'Narzędzia',
            35,
        );
    }

    public function registerDashboard(DashboardRegistry $dashboard): void
    {
        $dashboard->addMetric(
            'uptime.active',
            'Monitory uptime',
            'Aktywne usługi obserwowane przez moduł Uptime.',
            'UP',
            function (): array {
                $stats = $this->uptime->stats();
                return ['value' => $stats['active'], 'detail' => $stats['down'] . ' offline / ' . $stats['all'] . ' wszystkich'];
            },
            'uptime.manage',
            132,
        );
    }

    public function registerRoutes(Router $router): void
    {
        $router->get('/uptime', fn () => $this->renderPublic());
        $router->post('/api/uptime/events', fn (Request $request) => $this->receiveEvent($request));
        $router->get('/admin/uptime', fn (Request $request) => $this->guard($request, fn () => $this->renderList()));
        $router->get('/admin/uptime/create', fn (Request $request) => $this->guard($request, fn () => $this->renderForm()));
        $router->post('/admin/uptime/create', fn (Request $request) => $this->guard($request, fn () => $this->save($request)));
        $router->get('/admin/uptime/edit', fn (Request $request) => $this->guard($request, fn () => $this->renderEdit($request)));
        $router->post('/admin/uptime/edit', fn (Request $request) => $this->guard($request, fn () => $this->update($request)));
        $router->post('/admin/uptime/delete', fn (Request $request) => $this->guard($request, fn () => $this->delete($request)));
        $router->post('/admin/uptime/check', fn (Request $request) => $this->guard($request, fn () => $this->checkNow($request)));
    }

    public function registerSeo(SeoIndex $seo): void
    {
        $items = $this->uptime->publicItems();
        if ($items === []) {
            return;
        }

        $lastModified = null;
        foreach ($items as $item) {
            if ($lastModified === null || $item->updatedAt > $lastModified) {
                $lastModified = $item->updatedAt;
            }
        }
        $seo->add(
            '/uptime',
            'Uptime - SyntaxDevTeam',
            'Current status of selected SyntaxDevTeam services, bots and APIs.',
            $lastModified,
            0.4,
            'daily',
            'WebPage'
        );
    }

    private function renderPublic(): void
    {
        $this->evaluateStaleMonitors();
        $items = $this->uptime->publicItems();
        $this->theme->start_page(
            'Uptime - SyntaxDevTeam',
            'Current status of selected SyntaxDevTeam services.',
            $items !== []
        );
        $this->theme->start_header('Uptime', 'Current status of bots, APIs and public services.', 'SyntaxDevTeam / Status');
        $this->theme->end_header();
        $this->theme->start_section();
        if ($items === []) {
            $this->theme->render_alert('No public uptime monitors are available yet.', 'info');
        } else {
            $this->theme->render_detail_card('Service status', 'Live overview', [], ['Service', 'Status', 'Last check'], array_map(
                fn (UptimeMonitor $monitor): array => [
                    $monitor->name,
                    $this->statusLabel($monitor),
                    $monitor->lastCheckedAt ?? 'No data',
                ],
                $items
            ));
        }
        $this->theme->end_section();
        $this->theme->end_page();
    }

    private function renderList(string $message = '', string $variant = 'info'): void
    {
        $this->evaluateStaleMonitors();
        $this->startAdminPage('Uptime', 'Monitoruj boty, API i usługi strony oraz zasilaj publiczny widget statusu.', [[
            'label' => 'Dodaj monitor',
            'href' => 'index.php?route=/admin/uptime/create',
            'variant' => 'primary',
        ], [
            'label' => 'Sprawdź teraz',
            'href' => '#uptime-check-form',
            'variant' => 'outline-light',
        ], [
            'label' => 'Publiczny status',
            'href' => '/uptime',
            'variant' => 'outline-light',
        ]]);
        if ($message !== '') {
            $this->theme->render_alert($message, $variant);
        }
        $items = $this->uptime->all();
        $this->theme->start_admin_panel('Szybkie sprawdzenie', 'heartbeat');
        $this->theme->render_form(
            'index.php?route=/admin/uptime/check',
            [],
            'Sprawdź przeterminowane monitory',
            $this->security->csrfToken()
        );
        $this->theme->end_admin_panel();
        $this->renderCronPanel();
        $this->theme->start_admin_panel('Monitory', count($items) . ' pozycji');
        if ($items === []) {
            $this->theme->render_alert('Nie dodano jeszcze monitorów uptime.', 'info');
        } else {
            $this->theme->render_admin_action_table(
                ['Kolejność', 'Nazwa', 'UUID', 'Event', 'Status', 'Ostatni event', 'Widoczność'],
                array_map(fn (UptimeMonitor $monitor): array => [
                    'cells' => [
                        $monitor->sortOrder,
                        $monitor->name,
                        $monitor->uuid,
                        $monitor->expectedEvent,
                        $this->statusLabel($monitor),
                        $monitor->lastEventAt ?? $monitor->lastCheckedAt ?? 'Brak danych',
                        $monitor->visible && $monitor->active ? 'Publiczny' : 'Ukryty',
                    ],
                    'actions' => [[
                        'label' => 'Edytuj',
                        'href' => 'index.php?route=/admin/uptime/edit&id=' . $monitor->id,
                        'variant' => 'primary',
                    ], [
                        'label' => 'Usuń',
                        'action' => 'index.php?route=/admin/uptime/delete',
                        'variant' => 'danger',
                        'fields' => ['id' => $monitor->id],
                        'confirm' => 'Usunąć monitor „' . $monitor->name . '”?',
                    ]],
                ], $items),
                $this->security->csrfToken()
            );
        }
        $this->theme->end_admin_panel();
        $this->endAdminPage();
    }

    private function renderCronPanel(): void
    {
        $root = dirname(__DIR__, 2);
        $script = $root . '/bin/check-uptime.php';
        $log = $root . '/cache/uptime-cron.log';
        $cronLine = '* * * * * cd ' . $this->shellQuote($root) . ' && php bin/check-uptime.php >> cache/uptime-cron.log 2>&1';
        $installCommand = '(crontab -l 2>/dev/null | grep -Fv ' . $this->shellQuote('php bin/check-uptime.php') . '; printf ' . $this->shellQuote($cronLine . "\n") . ') | crontab -';
        $checkCommand = 'crontab -l | grep -F ' . $this->shellQuote('php bin/check-uptime.php');
        $testCommand = 'cd ' . $this->shellQuote($root) . ' && php bin/check-uptime.php';
        $logCommand = 'tail -n 20 ' . $this->shellQuote($log);
        $lastLine = $this->lastLogLine($log);

        $this->theme->start_admin_panel('Zadanie cron', is_file($script) ? 'gotowe do konfiguracji' : 'brak skryptu');
        $this->theme->render_admin_fact_grid([[
            'label' => 'Skrypt CLI',
            'value' => is_file($script) ? 'Dostępny' : 'Brak pliku',
            'detail' => $script,
        ], [
            'label' => 'Log crona',
            'value' => is_file($log) ? 'Istnieje' : 'Jeszcze nie utworzony',
                'detail' => $lastLine !== '' ? $lastLine : 'Pojawi się po pierwszym uruchomieniu zadania.',
        ]]);
        echo '<div class="admin-code-block">';
        echo '<label class="form-label" for="uptime-cron-install">Dodaj albo zaktualizuj cron jednym poleceniem</label>';
        echo '<pre id="uptime-cron-install"><code>' . self::e($installCommand) . '</code></pre>';
        echo '<label class="form-label" for="uptime-cron-check">Sprawdź, czy wpis istnieje</label>';
        echo '<pre id="uptime-cron-check"><code>' . self::e($checkCommand) . '</code></pre>';
        echo '<label class="form-label" for="uptime-cron-test">Uruchom ręcznie test monitoringu</label>';
        echo '<pre id="uptime-cron-test"><code>' . self::e($testCommand) . '</code></pre>';
        echo '<label class="form-label" for="uptime-cron-log">Podejrzyj ostatnie wyniki crona</label>';
        echo '<pre id="uptime-cron-log"><code>' . self::e($logCommand) . '</code></pre>';
        echo '<p class="text-muted">Wklej pierwszą komendę w terminalu serwera. Moduł nie zapisuje crona automatycznie, żeby proces webowy nie dostał uprawnień do crontaba systemu.</p>';
        echo '</div>';
        $this->theme->end_admin_panel();
    }

    private function renderForm(?UptimeMonitor $monitor = null, string $message = '', string $variant = 'info'): void
    {
        $editing = $monitor instanceof UptimeMonitor;
        $this->startAdminPage(
            $editing ? 'Edytuj monitor' : 'Dodaj monitor',
            'Bot albo usługa wysyła event z UUID do /api/uptime/events. Brak eventu w czasie interwału oznacza offline.',
            [['label' => 'Wróć do listy', 'href' => 'index.php?route=/admin/uptime', 'variant' => 'outline-light']]
        );
        if ($message !== '') {
            $this->theme->render_alert($message, $variant);
        }
        $this->theme->start_admin_panel('Monitor', $editing ? $monitor->key : 'Nowy element');
        if ($editing) {
            $this->theme->render_admin_fact_grid([[
                'label' => 'UUID dla bota',
                'value' => $monitor->uuid,
                'detail' => 'Umieść w konfiguracji klienta heartbeat.',
            ], [
                'label' => 'Endpoint',
                'value' => '/api/uptime/events',
                'detail' => 'POST JSON: uuid, event, message.',
            ], [
                'label' => 'Ostatni event',
                'value' => $monitor->lastEvent !== '' ? $monitor->lastEvent : 'Brak',
                'detail' => $monitor->lastEventAt ?? 'Nie odebrano jeszcze eventu.',
            ]]);
        }
        $this->theme->render_form(
            'index.php?route=' . ($editing ? '/admin/uptime/edit' : '/admin/uptime/create'),
            array_merge($editing ? [[
                'name' => 'id', 'label' => 'ID', 'type' => 'hidden', 'value' => (string) $monitor->id,
            ]] : [], [[
                'name' => 'name', 'label' => 'Nazwa', 'value' => $monitor?->name ?? '',
            ], [
                'name' => 'monitor_key', 'label' => 'Klucz', 'value' => $monitor?->key ?? '',
                'help' => 'Małe litery, cyfry, myślnik lub podkreślenie. Puste pole wygeneruje klucz z nazwy.',
            ], [
                'name' => 'monitor_type', 'label' => 'Typ', 'type' => 'select',
                'value' => $monitor?->type ?? 'heartbeat', 'options' => self::TYPES,
            ], [
                'name' => 'target_url', 'label' => 'Adres usługi', 'type' => 'url',
                'value' => $monitor?->targetUrl ?? '',
                'help' => 'Opcjonalny adres API, bota albo endpointu health-check.',
            ], [
                'name' => 'expected_event', 'label' => 'Oczekiwany event',
                'value' => $monitor?->expectedEvent ?? 'online',
                'help' => 'Np. online. Tylko ten event od klienta aktualizuje monitor.',
            ], [
                'name' => 'expected_status', 'label' => 'Oczekiwany kod HTTP', 'type' => 'number',
                'value' => (string) ($monitor?->expectedStatus ?? 200),
            ], [
                'name' => 'check_interval_minutes', 'label' => 'Maksymalna cisza w minutach', 'type' => 'number',
                'value' => (string) ($monitor?->checkIntervalMinutes ?? 5),
                'help' => 'Po tym czasie bez oczekiwanego eventu monitor przejdzie na Offline.',
            ], [
                'name' => 'notification_type', 'label' => 'Powiadomienie po braku eventu', 'type' => 'select',
                'value' => $monitor?->notificationType ?? 'none', 'options' => self::NOTIFICATIONS,
            ], [
                'name' => 'notification_webhook_url', 'label' => 'Discord webhook URL', 'type' => 'url',
                'value' => $monitor?->notificationWebhookUrl ?? '',
                'help' => 'Wymagany tylko dla powiadomień Discord. Dozwolony jest adres HTTPS discord.com/api/webhooks/...',
            ], [
                'name' => 'last_status', 'label' => 'Status', 'type' => 'select',
                'value' => $monitor?->lastStatus ?? 'neutral', 'options' => self::STATUSES,
            ], [
                'name' => 'last_message', 'label' => 'Komunikat statusu',
                'value' => $monitor?->lastMessage ?? '',
                'help' => 'Np. Online, HTTP 200, timeout albo degraded.',
            ], [
                'name' => 'sort_order', 'label' => 'Kolejność', 'type' => 'number',
                'value' => (string) ($monitor?->sortOrder ?? 100),
            ], [
                'name' => 'is_active', 'label' => 'Aktywny', 'type' => 'checkbox',
                'checked' => $monitor?->active ?? true,
            ], [
                'name' => 'is_visible', 'label' => 'Widoczny publicznie', 'type' => 'checkbox',
                'checked' => $monitor?->visible ?? true,
            ]]),
            $editing ? 'Zapisz monitor' : 'Dodaj monitor',
            $this->security->csrfToken()
        );
        $this->theme->end_admin_panel();
        $this->endAdminPage();
    }

    private function renderEdit(Request $request): void
    {
        $monitor = $this->uptime->find($request->queryInt('id', 0) ?? 0);
        if (!$monitor instanceof UptimeMonitor) {
            $this->renderList('Nie znaleziono monitora uptime.', 'danger');
            return;
        }
        $this->renderForm($monitor);
    }

    private function update(Request $request): void
    {
        $monitor = $this->uptime->find($request->postInt('id', 0) ?? 0);
        if (!$monitor instanceof UptimeMonitor) {
            $this->renderList('Nie znaleziono monitora uptime.', 'danger');
            return;
        }
        $this->save($request, $monitor);
    }

    private function save(Request $request, ?UptimeMonitor $monitor = null): void
    {
        $actor = $this->auth->user();
        if (!$this->security->validateCsrfToken($request->postString('_token'))) {
            $this->audit->record($request, 'uptime_save', 'invalid_csrf', 'uptime', $actor?->id);
            $this->renderForm($monitor, 'Token CSRF jest nieprawidłowy lub wygasł.', 'danger');
            return;
        }

        $name = $this->bounded($request->postString('name'), 160);
        $key = $this->key($request->postString('monitor_key') !== '' ? $request->postString('monitor_key') : $name);
        $type = $request->postString('monitor_type');
        $status = $request->postString('last_status');
        $expectedEvent = $this->eventName($request->postString('expected_event', 'online'));
        $notificationType = $request->postString('notification_type', 'none');
        $webhookUrl = $this->bounded($request->postString('notification_webhook_url'), 500);
        $targetUrl = $this->bounded($request->postString('target_url'), 500);
        $expectedStatus = max(100, min(599, $request->postInt('expected_status', 200) ?? 200));
        $interval = max(1, min(1440, $request->postInt('check_interval_minutes', 5) ?? 5));
        if ($name === '' || $key === '' || !array_key_exists($type, self::TYPES) || !array_key_exists($status, self::STATUSES)) {
            $this->renderForm($monitor, 'Uzupełnij nazwę, klucz, typ i status.', 'warning');
            return;
        }
        if ($expectedEvent === '') {
            $this->renderForm($monitor, 'Podaj poprawny oczekiwany event, np. online.', 'warning');
            return;
        }
        if (!array_key_exists($notificationType, self::NOTIFICATIONS)) {
            $this->renderForm($monitor, 'Wybierz poprawny typ powiadomienia.', 'warning');
            return;
        }
        if ($notificationType === 'discord_webhook' && !$this->safeDiscordWebhook($webhookUrl)) {
            $this->renderForm($monitor, 'Webhook Discord musi być adresem HTTPS z discord.com/api/webhooks albo discordapp.com/api/webhooks.', 'warning');
            return;
        }
        if ($targetUrl !== '' && filter_var($targetUrl, FILTER_VALIDATE_URL) === false) {
            $this->renderForm($monitor, 'Adres usługi musi być poprawnym URL albo pozostać pusty.', 'warning');
            return;
        }
        if ($this->uptime->keyExists($key, $monitor?->id)) {
            $this->renderForm($monitor, 'Ten klucz monitora jest już używany.', 'warning');
            return;
        }

        $uuid = $monitor instanceof UptimeMonitor && $monitor->uuid !== '' ? $monitor->uuid : $this->uuid();
        $data = [
            'monitor_key' => $key,
            'monitor_uuid' => $uuid,
            'name' => $name,
            'target_url' => $targetUrl,
            'monitor_type' => $type,
            'expected_event' => $expectedEvent,
            'expected_status' => $expectedStatus,
            'check_interval_minutes' => $interval,
            'notification_type' => $notificationType,
            'notification_webhook_url' => $notificationType === 'discord_webhook' ? $webhookUrl : '',
            'last_status' => $status,
            'last_message' => $this->bounded($request->postString('last_message'), 220),
            'last_checked_at' => date('Y-m-d H:i:s'),
            'sort_order' => max(0, $request->postInt('sort_order', 100) ?? 100),
            'is_visible' => $request->postBool('is_visible') ? 1 : 0,
            'is_active' => $request->postBool('is_active') ? 1 : 0,
        ];

        try {
            $id = $monitor instanceof UptimeMonitor ? $monitor->id : $this->uptime->create($data);
            if ($monitor instanceof UptimeMonitor && !$this->uptime->update($monitor->id, $data)) {
                throw new \RuntimeException('Repozytorium nie zapisało monitora.');
            }
            $this->cache->invalidateTags(['homepage', 'widgets', 'uptime', 'theme']);
            $this->audit->record($request, $monitor instanceof UptimeMonitor ? 'uptime_update' : 'uptime_create', 'success', 'monitor:' . $id, $actor?->id);
            $this->renderList($monitor instanceof UptimeMonitor ? 'Monitor został zapisany.' : 'Monitor został dodany.', 'success');
        } catch (\Throwable $exception) {
            $this->audit->record($request, 'uptime_save', 'failed', 'uptime', $actor?->id);
            $this->renderForm($monitor, 'Nie udało się zapisać monitora: ' . $exception->getMessage(), 'danger');
        }
    }

    private function delete(Request $request): void
    {
        $actor = $this->auth->user();
        if (!$this->security->validateCsrfToken($request->postString('_token'))) {
            $this->audit->record($request, 'uptime_delete', 'invalid_csrf', 'uptime', $actor?->id);
            $this->renderList('Token CSRF jest nieprawidłowy lub wygasł.', 'danger');
            return;
        }
        $id = $request->postInt('id', 0) ?? 0;
        if (!$this->uptime->delete($id)) {
            $this->audit->record($request, 'uptime_delete', 'failed', 'monitor:' . $id, $actor?->id);
            $this->renderList('Nie udało się usunąć monitora.', 'danger');
            return;
        }
        $this->cache->invalidateTags(['homepage', 'widgets', 'uptime', 'theme']);
        $this->audit->record($request, 'uptime_delete', 'success', 'monitor:' . $id, $actor?->id);
        $this->renderList('Monitor został usunięty.', 'success');
    }

    private function checkNow(Request $request): void
    {
        if (!$this->security->validateCsrfToken($request->postString('_token'))) {
            $this->audit->record($request, 'uptime_check', 'invalid_csrf', 'uptime', $this->auth->user()?->id);
            $this->renderList('Token CSRF jest nieprawidłowy lub wygasł.', 'danger');
            return;
        }
        $changed = $this->evaluateStaleMonitors();
        $this->audit->record($request, 'uptime_check', 'success', 'changed:' . $changed, $this->auth->user()?->id);
        $this->renderList('Sprawdzono monitory. Zmieniono status: ' . $changed . '.', 'success');
    }

    private function receiveEvent(Request $request): void
    {
        $data = $request->json() ?? [];
        $uuid = $this->bounded((string) ($data['uuid'] ?? $request->postString('uuid') ?: $request->header('X-Uptime-UUID')), 64);
        $event = $this->eventName((string) ($data['event'] ?? $request->postString('event')));
        $message = $this->bounded((string) ($data['message'] ?? $request->postString('message')), 220);
        $limit = $this->rateLimiter->hit(
            'uptime',
            $request->clientIp() . ':' . ($uuid !== '' ? $uuid : 'missing'),
            (int) ($this->config['uptime_rate_limit'] ?? 120),
            (int) ($this->config['uptime_rate_window'] ?? 120)
        );
        if ($limit['limited']) {
            $this->json(429, ['ok' => false, 'error' => 'rate_limited', 'retry_after' => $limit['retry_after']]);
            return;
        }
        if ($uuid === '' || $event === '') {
            $this->json(422, ['ok' => false, 'error' => 'invalid_payload']);
            return;
        }

        $monitor = $this->uptime->findByUuid($uuid);
        if (!$monitor instanceof UptimeMonitor || !$monitor->active) {
            $this->json(404, ['ok' => false, 'error' => 'unknown_monitor']);
            return;
        }
        if (!hash_equals($monitor->expectedEvent, $event)) {
            $this->json(202, ['ok' => true, 'accepted' => false, 'expected_event' => $monitor->expectedEvent]);
            return;
        }

        $status = $this->statusFromEvent($event);
        if ($message === '') {
            $message = self::STATUSES[$status] ?? 'OK';
        }
        $shouldNotifyRecovered = $monitor->lastStatus === 'down' && $status === 'up';
        $ok = $this->uptime->recordEvent($monitor, $event, $status, $message);
        if ($ok) {
            if ($shouldNotifyRecovered) {
                $this->notifyRecovered($monitor);
            }
            $this->cache->invalidateTags(['homepage', 'widgets', 'uptime', 'theme']);
        }

        $this->json($ok ? 200 : 500, [
            'ok' => $ok,
            'accepted' => true,
            'monitor' => $monitor->key,
            'status' => $status,
        ]);
    }

    private function statusLabel(UptimeMonitor $monitor): string
    {
        $label = self::STATUSES[$monitor->lastStatus] ?? $monitor->lastStatus;
        return $monitor->lastMessage !== '' ? $label . ' - ' . $monitor->lastMessage : $label;
    }

    private function evaluateStaleMonitors(): int
    {
        return $this->checker->check()['changed'];
    }

    private function statusFromEvent(string $event): string
    {
        return match ($event) {
            'offline', 'down', 'stopped' => 'down',
            'warn', 'warning', 'degraded' => 'warn',
            'neutral', 'unknown' => 'neutral',
            default => 'up',
        };
    }

    private function eventName(string $value): string
    {
        $value = strtolower(trim($value));

        return preg_match('/^[a-z][a-z0-9_.-]{1,31}$/', $value) === 1 ? $value : '';
    }

    private function safeDiscordWebhook(string $value): bool
    {
        if (!str_starts_with($value, 'https://') || filter_var($value, FILTER_VALIDATE_URL) === false) {
            return false;
        }
        $host = strtolower((string) parse_url($value, PHP_URL_HOST));
        $path = (string) parse_url($value, PHP_URL_PATH);

        return in_array($host, ['discord.com', 'discordapp.com'], true)
            && str_starts_with($path, '/api/webhooks/');
    }

    private function notifyRecovered(UptimeMonitor $monitor): bool
    {
        if ($monitor->notificationType !== 'discord_webhook') {
            return false;
        }
        if (!$this->safeDiscordWebhook($monitor->notificationWebhookUrl)) {
            return false;
        }

        $payload = json_encode([
            'content' => ':green_circle: **' . $monitor->name . '** is online again.',
        ], JSON_UNESCAPED_SLASHES);
        if (!is_string($payload)) {
            return false;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $payload,
                'timeout' => 5,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);
        $body = @file_get_contents($monitor->notificationWebhookUrl, false, $context);
        if ($body === false) {
            return false;
        }
        foreach ($http_response_header ?? [] as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $matches) === 1) {
                $status = (int) $matches[1];
                return $status >= 200 && $status < 300;
            }
        }

        return true;
    }

    private function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4)
            . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
    }

    /** @param array<string, mixed> $payload */
    private function json(int $status, array $payload): void
    {
        http_response_code($status);
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=UTF-8');
            header('Cache-Control: no-store');
            if ($status === 429 && isset($payload['retry_after'])) {
                header('Retry-After: ' . max(1, (int) $payload['retry_after']));
            }
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** @param callable(): void $handler */
    private function guard(Request $request, callable $handler): void
    {
        $user = $this->auth->user();
        if (!$user instanceof User) {
            $this->theme->render_admin_access_state(401, 'Wymagane logowanie', 'Zarządzanie uptime wymaga aktywnej sesji.', 'index.php?route=/admin/login', 'Przejdź do logowania');
            return;
        }
        if (!in_array('*', $user->permissions, true) && !in_array('uptime.manage', $user->permissions, true)) {
            $this->audit->record($request, 'admin_access', 'denied', 'uptime.manage', $user->id);
            $this->theme->render_admin_access_state(403, 'Brak uprawnień', 'Twoje konto nie ma uprawnienia uptime.manage.', 'index.php?route=/admin', 'Wróć do panelu');
            return;
        }

        $handler();
    }

    /**
     * @param array{label:string,href:string,variant?:string}|list<array{label:string,href:string,variant?:string}> $actions
     */
    private function startAdminPage(string $title, string $lead, array $actions): void
    {
        $user = $this->auth->user();
        $this->theme->start_admin_page($title, $this->menu->visibleFor($user?->permissions ?? []), '/admin/uptime', [
            'name' => $user?->displayName ?? 'Gość',
            'role' => $user?->primaryRole() ?? 'Gość',
            'initials' => $user?->initials() ?? 'G',
            'avatar_url' => $user?->avatarUrl ?? '',
            'logout_action' => 'index.php?route=/admin/logout',
            'logout_token' => $this->security->csrfToken(),
        ]);
        $this->theme->start_admin_content(
            $title,
            $lead,
            [['label' => 'Panel', 'href' => 'index.php?route=/admin'], ['label' => 'Uptime', 'href' => 'index.php?route=/admin/uptime']],
            $actions
        );
    }

    private function endAdminPage(): void
    {
        $this->theme->end_admin_content();
        $this->theme->end_admin_page();
    }

    private function key(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9_-]+/', '-', $value) ?? '';

        return trim(substr($value, 0, 64), '-_');
    }

    private function bounded(string $value, int $max): string
    {
        $value = trim($value);
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $max);
        }

        return substr($value, 0, $max);
    }

    private function lastLogLine(string $file): string
    {
        if (!is_file($file) || !is_readable($file)) {
            return '';
        }
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false || $lines === []) {
            return '';
        }

        return $this->bounded((string) end($lines), 220);
    }

    private function shellQuote(string $value): string
    {
        return "'" . str_replace("'", "'\\''", $value) . "'";
    }

    private static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
