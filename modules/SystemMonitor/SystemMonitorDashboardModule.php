<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\SystemMonitor;

use SyntaxDevTeam\Cms\Core\AdminMenuRegistry;
use SyntaxDevTeam\Cms\Core\DashboardProviderInterface;
use SyntaxDevTeam\Cms\Core\DashboardRegistry;
use SyntaxDevTeam\Cms\Core\ModuleInterface;
use SyntaxDevTeam\Cms\Core\Request;
use SyntaxDevTeam\Cms\Core\Router;
use SyntaxDevTeam\Cms\Core\Security;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AdminAccessGate;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuditLogService;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuthService;
use Throwable;

final class SystemMonitorDashboardModule implements ModuleInterface, DashboardProviderInterface
{
    public function __construct(
        private readonly SystemMonitorModule $module,
        private readonly SystemMonitorInfrastructureProbe $infrastructure,
        private readonly SystemMonitorLogCleaner $logCleaner,
        private readonly AdminAccessGate $access,
        private readonly AuthService $auth,
        private readonly AuditLogService $audit,
        private readonly Security $security,
    ) {
    }

    public function id(): string
    {
        return $this->module->id();
    }

    public function version(): string
    {
        return $this->module->version();
    }

    public function dependencies(): array
    {
        return $this->module->dependencies();
    }

    public function isProtected(): bool
    {
        return $this->module->isProtected();
    }

    public function requiredPermissions(): array
    {
        return $this->module->requiredPermissions();
    }

    public function registerAdminMenu(AdminMenuRegistry $menu): void
    {
        $this->module->registerAdminMenu($menu);
    }

    public function registerRoutes(Router $router): void
    {
        $this->module->registerRoutes($router);
        $router->get('/admin/system-monitor/infrastructure', fn (Request $request) => $this->serveInfrastructure($request));
        $router->get('/admin/system-monitor/actions', fn (Request $request) => $this->serveActions($request));
        $router->post('/admin/system-monitor/processes/terminate', fn (Request $request) => $this->terminateProcess($request));
        $router->get('/admin/system-monitor/processes', fn (Request $request) => $this->serveProcesses($request));
        $router->get('/admin/system-monitor/log-cleaner', fn (Request $request) => $this->serveLogCleanerPreview($request));
        $router->get('/admin/system-monitor/log-cleaner/job', fn (Request $request) => $this->serveLogCleanerJob($request));
        $router->post('/admin/system-monitor/log-cleaner', fn (Request $request) => $this->serveLogCleanerRun($request));
    }

    public function registerDashboard(DashboardRegistry $dashboard): void
    {
        $dashboard->addMetric(
            'system_monitor.ram',
            'RAM VPS',
            'Bieżące wykorzystanie pamięci operacyjnej VPS.',
            'RAM',
            function (): array {
                $memory = $this->memorySnapshot();

                return $this->resourceMetric(
                    $memory['used'],
                    $memory['total'],
                    $memory['available']
                );
            },
            'settings.manage',
            10,
            true
        );

        $dashboard->addMetric(
            'system_monitor.disk',
            'Dysk VPS',
            'Bieżące wykorzystanie głównego systemu plików VPS (/).',
            'DSK',
            function (): array {
                $disk = $this->diskSnapshot('/');

                return $this->resourceMetric(
                    $disk['used'],
                    $disk['total'],
                    $disk['free']
                );
            },
            'settings.manage',
            11,
            true
        );
    }

    private function serveInfrastructure(Request $request): void
    {
        if (!$this->allowJson($request, 'system_monitor_infrastructure_access')) {
            return;
        }

        $payload = json_encode([
            'ok' => true,
            'infrastructure' => $this->infrastructure->snapshot(),
        ], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if (!is_string($payload)) {
            http_response_code(500);
            echo '{"ok":false,"status":500}';
            return;
        }

        echo $payload;
    }

    private function serveActions(Request $request): void
    {
        if (!$this->allowJson($request, 'system_monitor_actions_access')) {
            return;
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        $this->json(['ok' => true, 'actions' => SystemMonitorActions::snapshot()]);
    }

    private function serveProcesses(Request $request): void
    {
        if (!$this->allowJson($request, 'system_monitor_processes_access')) {
            return;
        }

        $this->json([
            'ok' => true,
            'processes' => $this->infrastructure->processesSnapshot(),
            'may_terminate' => $this->mayTerminate(),
            'csrf_token' => $this->mayTerminate() ? $this->security->csrfToken() : '',
        ]);
    }

    private function mayTerminate(): bool
    {
        $user = $this->auth->user();
        return $user !== null && $user->isActive() && $this->auth->rolePreview() === null
            && array_intersect($user->roles, ['owner','administrator']) !== [];
    }

    private function terminateProcess(Request $request): void
    {
        if (!$this->allowJson($request, 'system_monitor_process_terminate')) return;
        if (!$this->mayTerminate() || $this->auth->rolePreviewBlocks($request) || !$this->security->validateCsrfToken($request->postString('_token'))) {
            http_response_code(403); $this->json(['ok'=>false,'message'=>'Brak uprawnień lub nieprawidłowy token formularza.']); return;
        }
        $pid = $request->postInt('pid', 0) ?? 0;
        $actor = $this->auth->user()->id;
        if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
        try {
            $result = (new SystemMonitorHost())->terminate($pid, $request->postString('start_ticks'), $request->postString('boot_id'));
            if (!$result['ok']) http_response_code(409);
            $this->audit->record($request, 'system_monitor_process_terminate', $result['ok'] ? 'sigterm' : 'denied', 'pid:' . $pid, $actor);
            $this->json($result);
        } catch (\Throwable) {
            http_response_code(409);
            $this->audit->record($request, 'system_monitor_process_terminate', 'failed', 'pid:' . $pid, $actor);
            $this->json(['ok'=>false,'message'=>'Nie można zakończyć procesu. Odśwież listę i sprawdź dostępność usługi hosta.']);
        }
    }

    private function serveLogCleanerPreview(Request $request): void
    {
        if (!$this->allowJson($request, 'system_monitor_log_cleaner_preview')) {
            return;
        }

        $retentionDays = $request->queryInt('retention_days');
        $payload = json_encode([
            'ok' => true,
            'cleaner' => $this->logCleaner->preview($retentionDays),
            'csrf_token' => $this->security->csrfToken(),
        ], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if (!is_string($payload)) {
            http_response_code(500);
            echo '{"ok":false,"status":500}';
            return;
        }

        echo $payload;
    }

    private function serveLogCleanerRun(Request $request): void
    {
        if (!$this->allowJson($request, 'system_monitor_log_cleaner_run')) {
            return;
        }
        if (!$this->security->validateCsrfToken($request->postString('_token'))) {
            $this->audit->record(
                $request,
                'system_monitor_log_clean',
                'invalid_csrf',
                null,
                $this->auth->user()?->id
            );
            http_response_code(403);
            echo '{"ok":false,"status":403,"message":"Nieprawidłowy lub wygasły token CSRF."}';
            return;
        }

        try {
            $job = $this->logCleaner->clean(
                $request->postInt('retention_days'),
                $request->postBool('include_journal')
            );
            $this->audit->record(
                $request,
                'system_monitor_log_clean',
                'queued',
                'job_id=' . (string) ($job['job_id'] ?? ''),
                $this->auth->user()?->id
            );

            $this->json([
                'ok' => true,
                'job' => $job,
            ]);
        } catch (Throwable $exception) {
            $this->audit->record(
                $request,
                'system_monitor_log_clean',
                'failed',
                null,
                $this->auth->user()?->id
            );
            http_response_code(502);
            $this->json([
                'ok' => false,
                'status' => 502,
                'message' => trim($exception->getMessage()) !== ''
                    ? $exception->getMessage()
                    : 'Node Agent odrzucił zadanie czyszczenia logów.',
            ]);
        }
    }

    private function serveLogCleanerJob(Request $request): void
    {
        if (!$this->allowJson($request, 'system_monitor_log_cleaner_job')) {
            return;
        }

        $jobId = $request->queryString('job_id');
        try {
            $job = $this->logCleaner->job($jobId);
            $status = (string) ($job['status'] ?? '');
            if ($status === 'succeeded' || $status === 'failed') {
                $this->audit->record(
                    $request,
                    'system_monitor_log_clean',
                    $status,
                    'job_id=' . $jobId,
                    $this->auth->user()?->id
                );
            }

            $this->json([
                'ok' => true,
                'job' => $job,
            ]);
        } catch (Throwable $exception) {
            http_response_code(502);
            $this->json([
                'ok' => false,
                'status' => 502,
                'message' => trim($exception->getMessage()) !== ''
                    ? $exception->getMessage()
                    : 'Nie można pobrać stanu zadania agenta.',
            ]);
        }
    }

    private function allowJson(Request $request, string $event): bool
    {
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store, max-age=0');
        header('Pragma: no-cache');
        header('X-Content-Type-Options: nosniff');

        $decision = $this->access->check('settings.manage');
        if ($decision === AdminAccessGate::ALLOWED) {
            return true;
        }

        $status = $decision === AdminAccessGate::UNAUTHENTICATED ? 401 : 403;
        $this->audit->record(
            $request,
            $event,
            $status === 401 ? 'unauthenticated' : 'forbidden',
            null,
            $this->auth->user()?->id
        );
        http_response_code($status);
        $this->json(['ok' => false, 'status' => $status]);

        return false;
    }

    /** @param array<string, mixed> $payload */
    private function json(array $payload): void
    {
        $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        echo is_string($encoded) ? $encoded : '{"ok":false,"status":500}';
    }

    /** @return array{value:string, detail:string} */
    private function resourceMetric(?int $used, ?int $total, ?int $free): array
    {
        if ($used === null || $total === null || $free === null || $total <= 0) {
            return [
                'value' => '—',
                'detail' => 'Dane chwilowo niedostępne',
            ];
        }

        $percent = max(0.0, min(100.0, ($used / $total) * 100.0));

        return [
            'value' => number_format($percent, 1, ',', ' ') . '%',
            'detail' => self::formatBytes($used)
                . ' / ' . self::formatBytes($total)
                . ' • wolne ' . self::formatBytes($free),
        ];
    }

    /** @return array{total:?int, used:?int, available:?int} */
    private function memorySnapshot(): array
    {
        $shared = SystemMonitorAgent::snapshot()['metrics']['memory'] ?? null;
        if (is_array($shared)) return $shared;
        $lines = @file('/proc/meminfo', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return ['total' => null, 'used' => null, 'available' => null];
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
            $available = ($values['MemFree'] ?? 0)
                + ($values['Buffers'] ?? 0)
                + ($values['Cached'] ?? 0);
        }
        $used = $total !== null && $available !== null
            ? max(0, $total - $available)
            : null;

        return [
            'total' => $total,
            'used' => $used,
            'available' => $available,
        ];
    }

    /** @return array{total:?int, used:?int, free:?int} */
    private function diskSnapshot(string $path): array
    {
        $shared = SystemMonitorAgent::snapshot()['metrics']['disk'] ?? null;
        if ($path === '/' && is_array($shared)) return $shared;
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

    private static function formatBytes(int $bytes): string
    {
        $value = max(0, $bytes);
        $units = ['B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB'];
        $unit = 0;
        $display = (float) $value;

        while ($display >= 1024.0 && $unit < count($units) - 1) {
            $display /= 1024.0;
            $unit++;
        }

        $decimals = $unit === 0 ? 0 : ($display >= 10.0 ? 1 : 2);

        return number_format($display, $decimals, ',', ' ') . ' ' . $units[$unit];
    }
}
