<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\SystemMonitor;

use SyntaxDevTeam\Cms\Core\AdminMenuRegistry;
use SyntaxDevTeam\Cms\Core\ModuleInterface;
use SyntaxDevTeam\Cms\Core\Request;
use SyntaxDevTeam\Cms\Core\Router;
use SyntaxDevTeam\Cms\Core\Security;
use SyntaxDevTeam\Cms\Core\ThemeInterface;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AdminAccessGate;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuditLogService;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuthService;

final class SystemMonitorModule implements ModuleInterface
{
    private const POLL_INTERVAL_MS = 2000;

    public function __construct(
        private readonly ThemeInterface $theme,
        private readonly AdminMenuRegistry $menu,
        private readonly AuthService $auth,
        private readonly AdminAccessGate $access,
        private readonly Security $security,
        private readonly AuditLogService $audit,
    ) {
    }

    public function id(): string
    {
        return 'system_monitor';
    }

    public function version(): string
    {
        return '0.1.1';
    }

    public function dependencies(): array
    {
        return ['core_auth'];
    }

    public function isProtected(): bool
    {
        return false;
    }

    public function requiredPermissions(): array
    {
        return ['settings.manage'];
    }

    public function registerAdminMenu(AdminMenuRegistry $menu): void
    {
        $menu->add('System', 'Monitor VPS', '/admin/system-monitor', 'SM', 'settings.manage', 58);
    }

    public function registerRoutes(Router $router): void
    {
        $router->get('/admin/system-monitor', fn (Request $request) => $this->guard(
            $request,
            'settings.manage',
            fn () => $this->renderDashboard()
        ));
        $router->get('/admin/system-monitor/metrics', fn (Request $request) => $this->guard(
            $request,
            'settings.manage',
            fn () => $this->serveMetrics()
        ));
    }

    private function renderDashboard(): void
    {
        $user = $this->auth->user();
        if ($user === null) {
            return;
        }

        $this->theme->start_admin_page(
            'Monitor VPS',
            $this->menu->visibleFor($user->permissions),
            '/admin/system-monitor',
            [
                'name' => $user->displayName,
                'role' => ucfirst($user->primaryRole()),
                'initials' => $user->initials(),
                'avatar_url' => $user->avatarUrl ?? '',
                'logout_action' => 'index.php?route=/admin/logout',
                'logout_token' => $this->security->csrfToken(),
            ]
        );
        $this->theme->start_admin_content(
            'Monitor VPS',
            'Bieżące wykorzystanie zasobów systemu, na którym działa miniPORTAL.',
            [
                ['label' => 'Panel', 'href' => 'index.php?route=/admin'],
                ['label' => 'Monitor VPS', 'href' => ''],
            ]
        );

        echo '<div data-system-monitor data-endpoint="index.php?route=/admin/system-monitor/metrics" '
            . 'data-interval="' . self::POLL_INTERVAL_MS . '">';
        echo '<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">';
        echo '<div class="d-flex flex-wrap align-items-center gap-2">';
        echo '<span class="badge text-bg-secondary" data-monitor-status>Łączenie…</span>';
        echo '<small class="text-secondary" data-monitor-updated>Oczekiwanie na pierwszy pomiar.</small>';
        echo '</div><small class="text-secondary">Odświeżanie co 2 s, tylko gdy karta jest aktywna.</small></div>';

        echo '<div class="metric-grid mb-4">';
        $this->metric('CPU', '—', 'CPU', 'Oczekiwanie na drugi pomiar…', 'cpu');
        $this->metric('RAM', '—', 'RAM', 'Pamięć dostępna: —', 'memory');
        $this->metric('Dysk /', '—', 'DSK', 'Wolne miejsce: —', 'disk');
        $this->metric('Swap', '—', 'SWP', 'Wolny swap: —', 'swap');
        echo '</div>';

        echo '<div class="admin-panel-grid admin-panel-grid-balanced">';
        echo '<div class="admin-panel-column">';
        $this->theme->start_admin_panel('Stan systemu', 'LIVE');
        $this->theme->render_admin_fact_grid([
            ['label' => 'Host', 'value' => '—', 'detail' => 'Nazwa hosta', 'variant' => 'success'],
            ['label' => 'Uptime', 'value' => '—', 'detail' => 'Czas od startu systemu'],
            ['label' => 'Rdzenie CPU', 'value' => '—', 'detail' => 'Wykryte logiczne CPU'],
            ['label' => 'Load average', 'value' => '—', 'detail' => '1 / 5 / 15 min'],
        ]);
        echo '<div class="visually-hidden" aria-hidden="true" '
            . 'data-monitor-fact-map="host,uptime,cpu-cores,load-average"></div>';
        $this->theme->end_admin_panel();

        $this->theme->start_admin_panel('Wykorzystanie zasobów');
        $this->resourceProgress('CPU', 'cpu');
        $this->resourceProgress('RAM', 'memory');
        $this->resourceProgress('Dysk /', 'disk');
        $this->resourceProgress('Swap', 'swap');
        $this->theme->end_admin_panel();
        echo '</div>';

        echo '<div class="admin-panel-column">';
        $this->theme->start_admin_panel('Sieć', '/proc/net/dev');
        $this->theme->render_admin_fact_grid([
            ['label' => 'Pobieranie', 'value' => '—', 'detail' => 'Bieżąca prędkość RX'],
            ['label' => 'Wysyłanie', 'value' => '—', 'detail' => 'Bieżąca prędkość TX'],
            ['label' => 'Odebrano', 'value' => '—', 'detail' => 'Od startu systemu'],
            ['label' => 'Wysłano', 'value' => '—', 'detail' => 'Od startu systemu'],
        ]);
        echo '<div class="visually-hidden" aria-hidden="true" '
            . 'data-monitor-fact-map="network-rx-rate,network-tx-rate,network-rx-total,network-tx-total"></div>';
        $this->theme->end_admin_panel();
        echo '</div></div></div>';

        $this->theme->end_admin_content();
        $this->renderScript();
        $this->theme->end_admin_page();
    }

    private function metric(string $label, string $value, string $symbol, string $detail, string $key): void
    {
        echo '<article class="metric-card" data-symbol="' . self::e($symbol) . '" data-monitor-metric="' . self::e($key) . '">';
        echo '<span class="metric-label">' . self::e($label) . '</span>';
        echo '<strong class="metric-value" data-monitor-value>' . self::e($value) . '</strong>';
        echo '<span class="metric-change" data-monitor-detail>' . self::e($detail) . '</span></article>';
    }

    private function resourceProgress(string $label, string $key): void
    {
        echo '<div class="mb-3" data-monitor-resource="' . self::e($key) . '">';
        echo '<div class="d-flex justify-content-between gap-3 mb-1"><span>' . self::e($label) . '</span>';
        echo '<strong data-monitor-resource-label>—</strong></div>';
        echo '<progress class="w-100" max="100" value="0" data-monitor-progress>0%</progress>';
        echo '</div>';
    }

    private function renderScript(): void
    {
        $file = dirname(__DIR__, 2) . '/templates/shared/assets/js/system-monitor.js';
        $version = is_file($file) ? (string) filemtime($file) : '1';
        echo '<script src="/templates/shared/assets/js/system-monitor.js?v=' . self::e($version) . '"></script>';

        $processFile = dirname(__DIR__, 2) . '/templates/shared/assets/js/system-monitor-processes.js';
        $processVersion = is_file($processFile) ? (string) filemtime($processFile) : '1';
        echo '<script src="/templates/shared/assets/js/system-monitor-processes.js?v=' . self::e($processVersion) . '"></script>';
    }

    private function serveMetrics(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store, max-age=0');
        header('Pragma: no-cache');
        header('X-Content-Type-Options: nosniff');

        $cpu = $this->cpuSnapshot();
        $memory = $this->memorySnapshot();
        $network = $this->networkSnapshot();
        $load = function_exists('sys_getloadavg') ? sys_getloadavg() : false;
        $rootDisk = $this->diskSnapshot('/');

        echo json_encode([
            'ok' => true,
            'sampled_at' => microtime(true),
            'platform' => [
                'family' => PHP_OS_FAMILY,
                'procfs' => is_readable('/proc/stat') && is_readable('/proc/meminfo'),
            ],
            'host' => [
                'name' => gethostname() ?: php_uname('n'),
                'uptime_seconds' => $this->uptimeSeconds(),
            ],
            'cpu' => [
                'total_ticks' => $cpu['total'],
                'idle_ticks' => $cpu['idle'],
                'cores' => $cpu['cores'],
                'load' => is_array($load) ? array_map('floatval', array_slice($load, 0, 3)) : [],
            ],
            'memory' => $memory,
            'disk' => $rootDisk,
            'network' => $network,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    /** @return array{total: ?int, idle: ?int, cores: ?int} */
    private function cpuSnapshot(): array
    {
        $lines = @file('/proc/stat', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return ['total' => null, 'idle' => null, 'cores' => null];
        }

        $total = null;
        $idle = null;
        $cores = 0;
        foreach ($lines as $line) {
            if (str_starts_with($line, 'cpu ')) {
                $parts = preg_split('/\s+/', trim(substr($line, 4))) ?: [];
                $values = array_map(
                    static fn (string $value): int => ctype_digit($value) ? (int) $value : 0,
                    $parts
                );
                $total = array_sum(array_slice($values, 0, 8));
                $idle = ($values[3] ?? 0) + ($values[4] ?? 0);
                continue;
            }
            if (preg_match('/^cpu\d+\s/', $line) === 1) {
                $cores++;
            }
        }

        return [
            'total' => $total,
            'idle' => $idle,
            'cores' => $cores > 0 ? $cores : null,
        ];
    }

    /**
     * @return array{
     *     total: ?int, used: ?int, available: ?int,
     *     swap_total: ?int, swap_used: ?int, swap_free: ?int
     * }
     */
    private function memorySnapshot(): array
    {
        $lines = @file('/proc/meminfo', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return [
                'total' => null,
                'used' => null,
                'available' => null,
                'swap_total' => null,
                'swap_used' => null,
                'swap_free' => null,
            ];
        }

        $values = [];
        foreach ($lines as $line) {
            if (preg_match('/^([A-Za-z0-9_()]+):\s+(\d+)\s+kB$/D', trim($line), $matches) !== 1) {
                continue;
            }
            $values[$matches[1]] = (int) $matches[2] * 1024;
        }

        $total = $values['MemTotal'] ?? null;
        $available = $values['MemAvailable'] ?? null;
        if ($available === null && $total !== null) {
            $available = ($values['MemFree'] ?? 0) + ($values['Buffers'] ?? 0) + ($values['Cached'] ?? 0);
        }
        $used = $total !== null && $available !== null ? max(0, $total - $available) : null;
        $swapTotal = $values['SwapTotal'] ?? null;
        $swapFree = $values['SwapFree'] ?? null;
        $swapUsed = $swapTotal !== null && $swapFree !== null ? max(0, $swapTotal - $swapFree) : null;

        return [
            'total' => $total,
            'used' => $used,
            'available' => $available,
            'swap_total' => $swapTotal,
            'swap_used' => $swapUsed,
            'swap_free' => $swapFree,
        ];
    }

    /** @return array{total: ?int, used: ?int, free: ?int} */
    private function diskSnapshot(string $path): array
    {
        $total = @disk_total_space($path);
        $free = @disk_free_space($path);
        if (!is_float($total) || !is_float($free)) {
            return ['total' => null, 'used' => null, 'free' => null];
        }

        $totalBytes = max(0, (int) $total);
        $freeBytes = max(0, (int) $free);

        return [
            'total' => $totalBytes,
            'used' => max(0, $totalBytes - $freeBytes),
            'free' => $freeBytes,
        ];
    }

    /** @return array{rx_bytes: ?int, tx_bytes: ?int} */
    private function networkSnapshot(): array
    {
        $lines = @file('/proc/net/dev', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return ['rx_bytes' => null, 'tx_bytes' => null];
        }

        $rx = 0;
        $tx = 0;
        $interfaces = 0;
        foreach ($lines as $line) {
            if (!str_contains($line, ':')) {
                continue;
            }
            [$interface, $payload] = explode(':', $line, 2);
            $interface = trim($interface);
            if ($interface === '' || $interface === 'lo') {
                continue;
            }
            $fields = preg_split('/\s+/', trim($payload)) ?: [];
            if (count($fields) < 16 || !ctype_digit($fields[0]) || !ctype_digit($fields[8])) {
                continue;
            }
            $rx += (int) $fields[0];
            $tx += (int) $fields[8];
            $interfaces++;
        }

        return $interfaces > 0
            ? ['rx_bytes' => $rx, 'tx_bytes' => $tx]
            : ['rx_bytes' => null, 'tx_bytes' => null];
    }

    private function uptimeSeconds(): ?float
    {
        $contents = @file_get_contents('/proc/uptime');
        if (!is_string($contents)) {
            return null;
        }
        $first = strtok(trim($contents), ' ');

        return is_string($first) && is_numeric($first) ? (float) $first : null;
    }

    private function guard(Request $request, string $permission, callable $handler): void
    {
        $decision = $this->access->check($permission);
        if ($decision === AdminAccessGate::ALLOWED) {
            $handler();
            return;
        }

        $status = $decision === AdminAccessGate::UNAUTHENTICATED ? 401 : 403;
        $this->audit->record(
            $request,
            'system_monitor_access',
            $status === 401 ? 'unauthenticated' : 'forbidden',
            null,
            $this->auth->user()?->id
        );
        http_response_code($status);
        $this->theme->render_admin_access_state(
            $status,
            $status === 401 ? 'Wymagane logowanie' : 'Brak uprawnienia',
            $status === 401 ? 'Ta trasa wymaga aktywnej sesji.' : "Twoje konto nie posiada uprawnienia {$permission}.",
            $status === 401 ? 'index.php?route=/admin/login' : 'index.php?route=/admin',
            $status === 401 ? 'Przejdź do logowania' : 'Wróć do dashboardu'
        );
    }

    private static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
