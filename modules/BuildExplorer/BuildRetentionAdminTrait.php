<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\BuildExplorer;

use SyntaxDevTeam\Cms\Core\Request;
use SyntaxDevTeam\Cms\Core\Router;

trait BuildRetentionAdminTrait
{
    private function registerRetentionAdminRoutes(Router $router): void
    {
        $router->post('/admin/builds/retention/preview', fn (Request $request) => $this->guard(
            $request,
            'builds.manage',
            fn () => $this->previewRetention($request)
        ));
        $router->post('/admin/builds/retention/cleanup', fn (Request $request) => $this->guard(
            $request,
            'builds.manage',
            fn () => $this->cleanupRetention($request)
        ));
        $router->post('/admin/builds/retention/lock', fn (Request $request) => $this->guard(
            $request,
            'builds.manage',
            fn () => $this->changeRetentionLock($request)
        ));
    }

    private function renderRetentionPanel(): void
    {
        $service = new BuildRetentionService($this->builds, $this->storage);
        try {
            $status = $service->status(true);
            $candidates = $service->candidates();
            $locked = $service->lockedReleases();
        } catch (\Throwable $exception) {
            $this->theme->start_admin_panel('Storage & Retention', 'Automatyczna ochrona magazynu buildów');
            $this->theme->render_alert(
                'Retencja nie jest jeszcze gotowa: ' . $exception->getMessage(),
                'warning'
            );
            $this->theme->end_admin_panel();
            return;
        }

        $policy = $status['policy'];
        $storage = $status['storage'];
        $preview = $status['preview'];
        $lastCleanup = $status['last_cleanup'];
        $softExceeded = (bool) $status['soft_limit_exceeded'];
        $hardExceeded = (bool) $status['hard_limit_exceeded'];
        $reserveAtRisk = (bool) $status['reserve_at_risk'];
        $state = $hardExceeded || $reserveAtRisk ? 'CRITICAL' : ($softExceeded ? 'WARNING' : 'SAFE');
        $stateVariant = $state === 'CRITICAL' ? 'danger' : ($state === 'WARNING' ? 'warning' : 'success');
        $lastAt = is_string($lastCleanup['at']) && $lastCleanup['at'] !== '' ? $lastCleanup['at'] . ' UTC' : 'Jeszcze nie wykonano';
        $nextAt = $this->nextRetentionCleanupAt(
            is_string($lastCleanup['at']) ? $lastCleanup['at'] : null,
            (int) $policy['cleanup_interval_hours']
        );

        $this->theme->start_admin_panel('Storage & Retention', 'Limity, polityka retencji i kontrolowane czyszczenie');
        $this->theme->render_admin_fact_grid([
            [
                'label' => 'Stan magazynu',
                'value' => $state,
                'detail' => $state === 'SAFE'
                    ? 'Limity i rezerwa systemu plików są bezpieczne.'
                    : ($state === 'WARNING' ? 'Przekroczono soft limit; cleanup ma pierwszeństwo.' : 'Hard limit lub rezerwa dysku wymagają uwagi.'),
                'variant' => $stateVariant,
            ],
            [
                'label' => 'Zajęte przez buildy',
                'value' => $this->fileSize((int) $storage['bytes']),
                'detail' => (int) $storage['files'] . ' plików w magazynie',
            ],
            [
                'label' => 'Soft limit',
                'value' => $this->fileSize((int) $policy['soft_limit_bytes']),
                'detail' => 'Po przekroczeniu cleanup uruchamia się przed kolejnym uploadem.',
                'variant' => $softExceeded ? 'warning' : 'default',
            ],
            [
                'label' => 'Hard limit',
                'value' => $this->fileSize((int) $policy['hard_limit_bytes']),
                'detail' => 'Upload jest odrzucany HTTP 507, jeśli po cleanupie nadal przekracza limit.',
                'variant' => $hardExceeded ? 'danger' : 'default',
            ],
            [
                'label' => 'Wolne miejsce filesystemu',
                'value' => $this->fileSize($storage['disk_free_bytes'] !== null ? (int) $storage['disk_free_bytes'] : null),
                'detail' => 'Cały filesystem hostujący magazyn Build Explorera',
                'variant' => $reserveAtRisk ? 'danger' : 'default',
            ],
            [
                'label' => 'Chroniona rezerwa',
                'value' => $this->fileSize((int) $policy['reserve_bytes']),
                'detail' => 'Build Explorer nie powinien zejść poniżej tej ilości wolnego miejsca.',
            ],
            ['label' => 'Release', 'value' => 'Unlimited', 'detail' => 'Nigdy nieusuwany automatycznie'],
            ['label' => 'Snapshot', 'value' => 'Keep ' . (int) $policy['snapshot_keep'], 'detail' => 'Na projekt + wersję pluginu'],
            ['label' => 'DEV', 'value' => 'Keep ' . (int) $policy['dev_keep'], 'detail' => 'Na projekt + wersję pluginu'],
            ['label' => 'WIP', 'value' => 'Keep ' . (int) $policy['wip_keep'], 'detail' => 'Na projekt + wersję pluginu'],
            ['label' => 'Ostatni cleanup', 'value' => $lastAt, 'detail' => $this->retentionCleanupSummary($lastCleanup)],
            ['label' => 'Następny cleanup', 'value' => $nextAt, 'detail' => 'Lazy schedule: pierwszy upload CI po upływie interwału'],
            [
                'label' => 'Dry-run: release',
                'value' => (string) (int) $preview['releases'],
                'detail' => (int) $preview['artifacts'] . ' artefaktów kwalifikuje się do usunięcia',
                'variant' => (int) $preview['releases'] > 0 ? 'warning' : 'default',
            ],
            [
                'label' => 'Dry-run: do odzyskania',
                'value' => $this->fileSize((int) $preview['total_bytes']),
                'detail' => (int) $preview['orphans'] . ' orphanów / ' . $this->fileSize((int) $preview['orphan_bytes']),
            ],
        ]);

        if ($this->hasPermission('builds.manage')) {
            $this->theme->render_admin_action_table(
                ['Operacja', 'Opis'],
                [[
                    'cells' => ['Preview cleanup', 'Przelicza aktualny dry-run i kandydatów bez usuwania plików.'],
                    'actions' => [[
                        'label' => 'Preview cleanup',
                        'action' => 'index.php?route=/admin/builds/retention/preview',
                        'fields' => [],
                        'variant' => 'outline-light',
                    ]],
                ], [
                    'cells' => ['Run cleanup', 'Usuwa tylko niechronione Snapshot/DEV/WIP ponad limity retencji oraz stare orphan files. Release pozostaje nietykalny.'],
                    'actions' => [[
                        'label' => 'Run cleanup',
                        'action' => 'index.php?route=/admin/builds/retention/cleanup',
                        'fields' => [],
                        'variant' => 'danger',
                        'confirm' => 'Uruchomić cleanup Build Explorera? Operacja usunie zakwalifikowane pliki i metadane.',
                    ]],
                ]],
                $this->security->csrfToken()
            );
        }

        $this->theme->end_admin_panel();

        $this->theme->start_admin_panel(
            'Retention candidates',
            (int) $preview['releases'] . ' logicznych buildów / około ' . $this->fileSize((int) $preview['release_bytes'])
        );
        if ($candidates === []) {
            $this->theme->render_alert('Brak buildów przekraczających aktualne limity retencji.', 'success');
        } else {
            $rows = [];
            foreach ($candidates as $candidate) {
                $actions = [];
                if ($this->hasPermission('builds.manage')) {
                    $actions[] = [
                        'label' => 'Lock',
                        'action' => 'index.php?route=/admin/builds/retention/lock',
                        'fields' => ['release_id' => (int) $candidate['id'], 'locked' => '1'],
                        'variant' => 'outline-light',
                        'confirm' => 'Chronić ten logical release przed automatycznym cleanupem?',
                    ];
                }
                $rows[] = [
                    'cells' => [
                        (string) $candidate['project_name'],
                        (string) $candidate['version_label'],
                        strtoupper((string) $candidate['channel']),
                        (string) ($candidate['build_number'] !== '' ? $candidate['build_number'] : '—'),
                        (string) (int) $candidate['artifact_count'],
                        $this->fileSize((int) $candidate['file_size_bytes']),
                        (string) ($candidate['release_time'] ?? '—'),
                    ],
                    'actions' => $actions,
                ];
            }
            $this->theme->render_admin_action_table(
                ['Projekt', 'Wersja', 'Kanał', 'Build', 'Artefakty', 'Rozmiar', 'Data'],
                $rows,
                $this->security->csrfToken()
            );
        }
        $this->theme->end_admin_panel();

        $this->theme->start_admin_panel('Retention locks', count($locked) . ' chronionych logical releases');
        if ($locked === []) {
            $this->theme->render_alert('Żaden niestabilny logical release nie ma ręcznej blokady retencji.', 'info');
        } else {
            $rows = [];
            foreach ($locked as $release) {
                $actions = [];
                if ($this->hasPermission('builds.manage')) {
                    $actions[] = [
                        'label' => 'Unlock',
                        'action' => 'index.php?route=/admin/builds/retention/lock',
                        'fields' => ['release_id' => (int) $release['id'], 'locked' => '0'],
                        'variant' => 'danger',
                        'confirm' => 'Zdjąć ochronę retencji z tego logical release?',
                    ];
                }
                $rows[] = [
                    'cells' => [
                        (string) $release['project_name'],
                        (string) $release['version_label'],
                        strtoupper((string) $release['channel']),
                        (string) ($release['build_number'] !== '' ? $release['build_number'] : '—'),
                        (string) (int) $release['artifact_count'],
                        $this->fileSize((int) $release['file_size_bytes']),
                        (string) ($release['release_time'] ?? '—'),
                    ],
                    'actions' => $actions,
                ];
            }
            $this->theme->render_admin_action_table(
                ['Projekt', 'Wersja', 'Kanał', 'Build', 'Artefakty', 'Rozmiar', 'Data'],
                $rows,
                $this->security->csrfToken()
            );
        }
        $this->theme->end_admin_panel();
    }

    private function previewRetention(Request $request): void
    {
        $actor = $this->auth->user();
        if (!$this->security->validateCsrfToken($request->postString('_token'))) {
            $this->audit->record($request, 'build_retention_preview', 'invalid_csrf', 'retention', $actor?->id);
            $this->renderAdmin($request, 'Token CSRF jest nieprawidłowy lub wygasł.', 'danger');
            return;
        }
        try {
            $preview = (new BuildRetentionService($this->builds, $this->storage))->preview();
            $this->audit->record($request, 'build_retention_preview', 'success', 'retention', $actor?->id);
            $this->renderAdmin(
                $request,
                'Dry-run: ' . (int) $preview['releases'] . ' release, ' . (int) $preview['artifacts']
                    . ' artefaktów i ' . (int) $preview['orphans'] . ' orphanów; potencjalnie '
                    . $this->fileSize((int) $preview['total_bytes']) . ' do odzyskania. Nic nie zostało usunięte.',
                'info'
            );
        } catch (\Throwable $exception) {
            $this->audit->record($request, 'build_retention_preview', 'failed', 'retention', $actor?->id);
            $this->renderAdmin($request, 'Nie udało się wykonać dry-run: ' . $exception->getMessage(), 'danger');
        }
    }

    private function cleanupRetention(Request $request): void
    {
        $actor = $this->auth->user();
        if (!$this->security->validateCsrfToken($request->postString('_token'))) {
            $this->audit->record($request, 'build_retention_cleanup_admin', 'invalid_csrf', 'retention', $actor?->id);
            $this->renderAdmin($request, 'Token CSRF jest nieprawidłowy lub wygasł.', 'danger');
            return;
        }
        try {
            $result = (new BuildRetentionService($this->builds, $this->storage))->cleanup('admin');
            $this->audit->record($request, 'build_retention_cleanup_admin', 'success', 'retention', $actor?->id);
            $this->renderAdmin(
                $request,
                'Cleanup zakończony: usunięto ' . (int) $result['releases'] . ' logical releases, '
                    . (int) $result['artifacts'] . ' artefaktów i ' . (int) $result['orphans']
                    . ' orphanów; odzyskano ' . $this->fileSize((int) $result['bytes']) . '.',
                'success'
            );
        } catch (\Throwable $exception) {
            $this->audit->record($request, 'build_retention_cleanup_admin', 'failed', 'retention', $actor?->id);
            $this->renderAdmin($request, 'Cleanup nie powiódł się: ' . $exception->getMessage(), 'danger');
        }
    }

    private function changeRetentionLock(Request $request): void
    {
        $actor = $this->auth->user();
        if (!$this->security->validateCsrfToken($request->postString('_token'))) {
            $this->audit->record($request, 'build_retention_lock_admin', 'invalid_csrf', 'retention', $actor?->id);
            $this->renderAdmin($request, 'Token CSRF jest nieprawidłowy lub wygasł.', 'danger');
            return;
        }
        $releaseId = $request->postInt('release_id', 0) ?? 0;
        $lockedRaw = $request->postString('locked');
        if ($releaseId < 1 || !in_array($lockedRaw, ['0', '1'], true)) {
            $this->renderAdmin($request, 'Nieprawidłowy identyfikator release lub stan blokady.', 'warning');
            return;
        }
        $locked = $lockedRaw === '1';
        try {
            $ok = (new BuildRetentionService($this->builds, $this->storage))->setRetentionLock($releaseId, $locked);
            $this->audit->record(
                $request,
                'build_retention_lock_admin',
                $ok ? 'success' : 'not_found',
                'release:' . $releaseId . ':locked:' . ($locked ? '1' : '0'),
                $actor?->id
            );
            $this->renderAdmin(
                $request,
                $ok
                    ? ($locked ? 'Logical release został zabezpieczony przed cleanupem.' : 'Blokada retencji została zdjęta.')
                    : 'Nie znaleziono niestabilnego logical release do zmiany blokady.',
                $ok ? 'success' : 'warning'
            );
        } catch (\Throwable $exception) {
            $this->audit->record($request, 'build_retention_lock_admin', 'failed', 'release:' . $releaseId, $actor?->id);
            $this->renderAdmin($request, 'Nie udało się zmienić blokady retencji: ' . $exception->getMessage(), 'danger');
        }
    }

    /** @param array<string, mixed> $lastCleanup */
    private function retentionCleanupSummary(array $lastCleanup): string
    {
        if (!is_string($lastCleanup['at'] ?? null) || $lastCleanup['at'] === '') {
            return 'Brak wcześniejszego cleanupu.';
        }
        return (int) $lastCleanup['releases'] . ' release / '
            . (int) $lastCleanup['artifacts'] . ' artefaktów / '
            . (int) $lastCleanup['orphans'] . ' orphanów / '
            . $this->fileSize((int) $lastCleanup['bytes']) . ' odzyskane; powód: '
            . ((string) ($lastCleanup['reason'] ?? '') !== '' ? (string) $lastCleanup['reason'] : '—');
    }

    private function nextRetentionCleanupAt(?string $lastCleanupAt, int $intervalHours): string
    {
        if ($lastCleanupAt === null || trim($lastCleanupAt) === '') {
            return 'Przy następnym uploadzie CI';
        }
        try {
            $last = new \DateTimeImmutable($lastCleanupAt, new \DateTimeZone('UTC'));
            $next = $last->modify('+' . max(1, $intervalHours) . ' hours');
            return $next->format('Y-m-d H:i') . ' UTC';
        } catch (\Throwable) {
            return 'Po upływie ' . max(1, $intervalHours) . ' h od ostatniego cleanupu';
        }
    }
}
