<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\BuildExplorer;

use SyntaxDevTeam\Cms\Core\AdminMenuRegistry;
use SyntaxDevTeam\Cms\Core\AdminSearchProviderInterface;
use SyntaxDevTeam\Cms\Core\AdminSearchRegistry;
use SyntaxDevTeam\Cms\Core\DashboardProviderInterface;
use SyntaxDevTeam\Cms\Core\DashboardRegistry;
use SyntaxDevTeam\Cms\Core\DownloadResponse;
use SyntaxDevTeam\Cms\Core\ModuleInterface;
use SyntaxDevTeam\Cms\Core\PublicNavigationProviderInterface;
use SyntaxDevTeam\Cms\Core\PublicNavigationRegistry;
use SyntaxDevTeam\Cms\Core\Request;
use SyntaxDevTeam\Cms\Core\Router;
use SyntaxDevTeam\Cms\Core\Security;
use SyntaxDevTeam\Cms\Core\SeoIndex;
use SyntaxDevTeam\Cms\Core\SeoProviderInterface;
use SyntaxDevTeam\Cms\Core\ThemeInterface;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuditLogService;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuthService;
use SyntaxDevTeam\Cms\Modules\CoreAuth\User;

final class BuildExplorerModule implements ModuleInterface, PublicNavigationProviderInterface, AdminSearchProviderInterface, DashboardProviderInterface, SeoProviderInterface
{
    use BuildRetentionAdminTrait;

    private const CHANNELS = [
        'release' => 'Release',
        'snapshot' => 'Snapshot',
        'dev' => 'Dev',
        'wip' => 'WIP',
    ];

    public function __construct(
        private readonly ThemeInterface $theme,
        private readonly AdminMenuRegistry $menu,
        private readonly BuildRepository $builds,
        private readonly AuthService $auth,
        private readonly Security $security,
        private readonly AuditLogService $audit,
        private readonly BuildArtifactStorage $storage,
        private readonly string $ciToken = '',
        private readonly ?BuildCiPolicy $ciPolicy = null,
    ) {}

    public function id(): string { return 'build_explorer'; }
    public function version(): string { return '2.5.0'; }
    public function dependencies(): array { return ['core_auth', 'projects']; }
    public function isProtected(): bool { return false; }
    public function requiredPermissions(): array { return ['builds.view', 'builds.manage']; }

    public function registerAdminMenu(AdminMenuRegistry $menu): void
    {
        $menu->add('Treść', 'Build Explorer', '/admin/builds', 'BL', 'builds.view', 38);
    }

    public function registerPublicNavigation(PublicNavigationRegistry $navigation): void
    {
        $navigation->add('build_explorer.index', 'Downloads', '/builds', 'main', 56);
    }

    public function registerAdminSearch(AdminSearchRegistry $search): void
    {
        $search->add('builds.create', 'Dodaj build', 'Dodaj plik lub metadane wydania projektu.', 'index.php?route=/admin/builds/create', ['build', 'wersja', 'jar', 'release', 'snapshot', 'dev', 'wip'], 'builds.manage', 'Treść', 38);
    }

    public function registerDashboard(DashboardRegistry $dashboard): void
    {
        $dashboard->addMetric('builds.published', 'Publiczne buildy', 'Buildy widoczne bez logowania.', 'BLD', function (): array {
            $statistics = $this->builds->statistics();
            return [
                'value' => $statistics['public_available'],
                'detail' => $statistics['authenticated_available'] . ' dostępnych po zalogowaniu; '
                    . $statistics['total'] . ' wszystkich buildów',
            ];
        }, 'builds.view', 115);
        $dashboard->addMetric('builds.storage', 'Magazyn buildów', 'Fizyczne miejsce zajęte przez lokalne artefakty.', 'DSK', function (): array {
            $storage = $this->storage->statistics();
            return [
                'value' => $this->fileSize($storage['bytes']),
                'detail' => $storage['files'] . ' plików; wolne ' . $this->fileSize($storage['disk_free_bytes']),
            ];
        }, 'builds.view', 116);
    }

    public function registerRoutes(Router $router): void
    {
        (new BuildExplorerV2Controller(
            $this->theme,
            $this->builds,
            $this->auth,
            $this->audit,
            $this->storage,
            $this->ciToken,
            $this->ciPolicy,
        ))->registerRoutes($router);
        $this->registerRetentionAdminRoutes($router);

        $router->get('/builds', fn () => $this->renderPublicProjects());
        $router->get('/builds/download', fn (Request $request) => $this->download($request));
        $router->get('/builds/project', fn (Request $request) => $this->renderPublicChannels($request->queryString('slug')));
        $router->post('/api/builds/ci/{project}', fn (Request $request) => $this->importCi($request, $request->routeString('project')));
        $router->get('/builds/{project}', fn (Request $request) => $this->renderPublicChannels($request->routeString('project')));
        $router->get('/builds/{project}/{channel}', fn (Request $request) => $this->renderPublicVersions(
            $request->routeString('project'),
            strtolower($request->routeString('channel'))
        ));
        $router->get('/builds/{project}/{channel}/{version}', fn (Request $request) => $this->renderPublicHistory(
            $request->routeString('project'),
            strtolower($request->routeString('channel')),
            $request->routeString('version')
        ));
        $router->get('/admin/builds', fn (Request $request) => $this->guard($request, 'builds.view', fn () => $this->renderAdmin($request)));
        $router->get('/admin/builds/create', fn (Request $request) => $this->guard($request, 'builds.manage', fn () => $this->renderForm()));
        $router->post('/admin/builds/create', fn (Request $request) => $this->guard($request, 'builds.manage', fn () => $this->save($request)));
        $router->get('/admin/builds/edit', fn (Request $request) => $this->guard($request, 'builds.manage', fn () => $this->renderEdit($request)));
        $router->post('/admin/builds/edit', fn (Request $request) => $this->guard($request, 'builds.manage', fn () => $this->save($request, $this->builds->find($request->postInt('id', 0) ?? 0))));
        $router->post('/admin/builds/delete', fn (Request $request) => $this->guard($request, 'builds.manage', fn () => $this->delete($request)));
        $router->post('/admin/builds/bulk-delete', fn (Request $request) => $this->guard($request, 'builds.manage', fn () => $this->bulkDelete($request)));
        $router->post('/admin/builds/project-visibility', fn (Request $request) => $this->guard($request, 'builds.manage', fn () => $this->updateProjectVisibility($request)));
    }

    public function registerSeo(SeoIndex $seo): void
    {
        unset($seo);
    }

    private function renderPublicProjects(): void
    {
        $projects = $this->builds->publicProjects($this->auth->actualUser() !== null);
        $this->theme->start_page('Build Explorer - SyntaxDevTeam', 'Downloadable SyntaxDevTeam project versions.', false);
        $this->theme->start_header('Build Explorer', 'Choose a project to download or explore its builds.', 'Build');
        $this->theme->end_header();
        $this->theme->start_section();
        $this->theme->render_breadcrumb($this->buildBreadcrumb());
        if ($projects === []) {
            $this->theme->render_alert('No projects have been published yet.', 'info');
        } else {
            $this->theme->start_grid();
            foreach ($projects as $project) {
                $this->theme->start_column(count($projects) === 1 ? '12' : (count($projects) === 2 || count($projects) === 4 ? 'lg-6' : 'lg-4'));
                $this->theme->start_card($project['name'], $project['build_count'] . ' available builds');
                $this->theme->render_link_list([
                    ['label' => 'Download', 'href' => '/builds/' . rawurlencode($project['slug']) . '/download', 'meta' => 'Choose platform / Minecraft / channel'],
                    ['label' => 'Explore', 'href' => '/builds/' . rawurlencode($project['slug']) . '/explore', 'meta' => 'Build history / artifacts'],
                ]);
                $this->theme->end_card();
                $this->theme->end_column();
            }
            $this->theme->end_grid();
        }
        $this->theme->end_section();
        $this->renderDownloadModal();
        $this->theme->end_page();
    }

    private function renderDownloadModal(): void
    {
        echo <<<'HTML'
<style>
.build-download-dialog{width:min(34rem,calc(100vw - 2rem));max-width:100%;padding:0;border:1px solid var(--bs-border-color,#495057);background:var(--bs-body-bg,#11151b);color:var(--bs-body-color,#f8f9fa);box-shadow:0 1.5rem 4rem rgba(0,0,0,.45)}
.build-download-dialog::backdrop{background:rgba(0,0,0,.72);backdrop-filter:blur(3px)}
.build-download-dialog__body{padding:1.25rem;display:grid;gap:1rem}
.build-download-dialog__header,.build-download-dialog__actions{display:flex;align-items:center;justify-content:space-between;gap:.75rem}
.build-download-dialog__header h2{margin:0;font-size:1.25rem}
.build-download-dialog__field{display:grid;gap:.4rem}
.build-download-dialog__field label{font-weight:600}
.build-download-dialog__field select{width:100%;padding:.65rem .75rem;border:1px solid var(--bs-border-color,#495057);background:var(--bs-secondary-bg,#1d232b);color:inherit}
.build-download-dialog__filename{margin:0;padding:.8rem 0;font-weight:700;overflow-wrap:anywhere}
.build-download-dialog__status{min-height:1.25rem;margin:0;font-size:.9rem;color:var(--bs-secondary-color,#adb5bd)}
.build-download-dialog__actions{justify-content:flex-end}
.build-download-dialog [data-build-download][aria-disabled="true"]{pointer-events:none;opacity:.5}
</style>
<dialog id="build-download-dialog" class="build-download-dialog" aria-labelledby="build-download-title">
    <div class="build-download-dialog__body">
        <div class="build-download-dialog__header">
            <h2 id="build-download-title">Download</h2>
            <button type="button" class="btn btn-sm btn-outline-light" data-build-download-close aria-label="Close">Close</button>
        </div>
        <div class="build-download-dialog__field">
            <label for="build-download-platform">Platform</label>
            <select id="build-download-platform" disabled><option value="">Choose</option></select>
        </div>
        <div class="build-download-dialog__field" data-build-download-game-wrap>
            <label for="build-download-game">Minecraft version</label>
            <select id="build-download-game" disabled><option value="">Choose</option></select>
        </div>
        <div class="build-download-dialog__field">
            <label for="build-download-channel">Release channel</label>
            <select id="build-download-channel" disabled><option value="">Choose</option></select>
        </div>
        <p class="build-download-dialog__status" data-build-download-status aria-live="polite"></p>
        <p class="build-download-dialog__filename" data-build-download-filename hidden></p>
        <div class="build-download-dialog__actions">
            <button type="button" class="btn btn-outline-light" data-build-download-close>Close</button>
            <a class="btn btn-primary" href="#" data-build-download aria-disabled="true" tabindex="-1">Download</a>
        </div>
    </div>
</dialog>
<script>
(() => {
    const dialog = document.getElementById('build-download-dialog');
    if (!(dialog instanceof HTMLDialogElement)) return;

    const platform = document.getElementById('build-download-platform');
    const game = document.getElementById('build-download-game');
    const channel = document.getElementById('build-download-channel');
    const gameWrap = dialog.querySelector('[data-build-download-game-wrap]');
    const status = dialog.querySelector('[data-build-download-status]');
    const filename = dialog.querySelector('[data-build-download-filename]');
    const download = dialog.querySelector('[data-build-download]');
    const closeButtons = dialog.querySelectorAll('[data-build-download-close]');
    let project = '';
    let gameRequired = false;
    let resolveSequence = 0;

    const resetResult = () => {
        resolveSequence += 1;
        filename.hidden = true;
        filename.textContent = '';
        status.textContent = '';
        download.href = '#';
        download.setAttribute('aria-disabled', 'true');
        download.setAttribute('tabindex', '-1');
    };

    const setOptions = (select, values, labels = {}) => {
        select.replaceChildren(new Option('Choose', ''));
        values.forEach((value) => select.add(new Option(labels[value] || value, value)));
        select.disabled = false;
    };

    const channelLabels = {release: 'Release', snapshot: 'Snapshot', dev: 'Dev', wip: 'WIP'};
    const platformLabel = (value) => value.charAt(0).toUpperCase() + value.slice(1);

    const resolveArtifact = async () => {
        resetResult();
        const selectedPlatform = platform.value;
        const selectedChannel = channel.value;
        const selectedGame = gameRequired ? game.value : '';
        if (!project || !selectedPlatform || !selectedChannel || (gameRequired && !selectedGame)) return;

        const sequence = resolveSequence;
        status.textContent = 'Finding build…';
        const params = new URLSearchParams({platform: selectedPlatform, channel: selectedChannel});
        if (selectedGame) params.set('game_version', selectedGame);

        try {
            const response = await fetch(`/api/v2/builds/${encodeURIComponent(project)}/resolve?${params.toString()}`, {
                headers: {'Accept': 'application/json'},
                credentials: 'same-origin'
            });
            if (sequence !== resolveSequence) return;
            if (!response.ok) {
                status.textContent = response.status === 404 ? 'No matching build.' : 'Unable to resolve this build.';
                return;
            }
            const data = await response.json();
            if (sequence !== resolveSequence || !data.filename || !data.download) return;
            status.textContent = '';
            filename.textContent = data.filename;
            filename.hidden = false;
            download.href = data.download;
            download.removeAttribute('aria-disabled');
            download.removeAttribute('tabindex');
        } catch (_) {
            if (sequence === resolveSequence) status.textContent = 'Unable to resolve this build.';
        }
    };

    const openFor = async (href) => {
        const match = href.match(/^\/builds\/([^/]+)\/download$/);
        if (!match) return;
        project = decodeURIComponent(match[1]);
        resetResult();
        setOptions(platform, []);
        setOptions(channel, []);
        setOptions(game, []);
        platform.disabled = true;
        channel.disabled = true;
        game.disabled = true;
        gameRequired = false;
        gameWrap.hidden = true;
        status.textContent = 'Loading…';
        dialog.showModal();

        try {
            const response = await fetch(`/api/v2/builds/${encodeURIComponent(project)}/options`, {
                headers: {'Accept': 'application/json'},
                credentials: 'same-origin'
            });
            if (!response.ok) throw new Error('options');
            const options = await response.json();
            setOptions(platform, Array.isArray(options.platforms) ? options.platforms : [], Object.fromEntries((options.platforms || []).map((value) => [value, platformLabel(value)])));
            setOptions(channel, Array.isArray(options.channels) ? options.channels : [], channelLabels);
            const versions = Array.isArray(options.game_versions) ? options.game_versions : [];
            gameRequired = versions.length > 0;
            gameWrap.hidden = !gameRequired;
            if (gameRequired) setOptions(game, versions);
            status.textContent = '';
        } catch (_) {
            status.textContent = 'Unable to load download options.';
        }
    };

    document.querySelectorAll('a[href^="/builds/"][href$="/download"]').forEach((link) => {
        link.addEventListener('click', (event) => {
            const href = link.getAttribute('href') || '';
            if (!/^\/builds\/[^/]+\/download$/.test(href)) return;
            event.preventDefault();
            openFor(href);
        });
    });

    [platform, game, channel].forEach((select) => select.addEventListener('change', resolveArtifact));
    closeButtons.forEach((button) => button.addEventListener('click', () => dialog.close()));
    dialog.addEventListener('close', () => {
        project = '';
        resetResult();
    });
})();
</script>
HTML;
    }

    private function renderPublicChannels(string $slug): void
    {
        $project = $this->accessiblePublicProject($slug, '/builds/' . rawurlencode($slug));
        if ($project === null) { return; }
        $builds = $this->builds->all(true, $slug);
        if ($builds === []) { $this->publicNotFound(); return; }
        $available = [];
        foreach ($builds as $build) { $available[$build->channel] = ($available[$build->channel] ?? 0) + 1; }
        $this->theme->start_page($project['name'] . ' - Build Explorer', 'Project release channels.', false);
        $this->theme->start_header($project['name'], 'Choose a release channel.', 'Build / ' . $project['name']); $this->theme->end_header();
        $this->theme->start_section(); $this->theme->render_breadcrumb($this->buildBreadcrumb($project['name'], $slug)); $this->theme->start_grid();
        foreach (self::CHANNELS as $channel => $label) {
            if (!isset($available[$channel])) { continue; }
            $this->theme->start_column('lg-6'); $this->theme->start_card($label, $available[$channel] . ' builds');
            $this->theme->render_link_list([['label' => 'Show versions', 'href' => '/builds/' . rawurlencode($slug) . '/' . $channel, 'meta' => $label]]);
            $this->theme->end_card(); $this->theme->end_column();
        }
        $this->theme->end_grid(); $this->theme->end_section(); $this->theme->end_page();
    }

    private function renderPublicVersions(string $slug, string $channel): void
    {
        if (!isset(self::CHANNELS[$channel])) { $this->publicNotFound(); return; }
        $project = $this->accessiblePublicProject(
            $slug,
            '/builds/' . rawurlencode($slug) . '/' . rawurlencode($channel)
        );
        if ($project === null) { return; }
        $builds = array_values(array_filter($this->builds->all(true, $slug), static fn (ProjectBuild $build): bool => $build->channel === $channel));
        if ($builds === []) { $this->publicNotFound(); return; }
        $latest = [];
        foreach ($builds as $build) { $latest[$build->versionLabel . "\0" . strtolower($build->serverType)] ??= $build; }
        $this->theme->start_page($builds[0]->projectName . ' ' . self::CHANNELS[$channel], 'Available project versions.', false);
        $this->theme->start_header(self::CHANNELS[$channel] . ': ' . $builds[0]->projectName, 'Each row points to the newest build for that version.', 'Build / ' . $builds[0]->projectName . ' / ' . self::CHANNELS[$channel]); $this->theme->end_header();
        $rows = [];
        foreach ($latest as $build) {
            $rows[] = ['cells' => [$build->versionLabel, $build->serverType, $this->fileSize($build->fileSizeBytes), $build->publishedAt ?? 'No date'], 'actions' => [
                ['label' => 'Download latest', 'href' => $this->downloadHref($build), 'variant' => 'primary'],
                ['label' => 'Build history', 'href' => '/builds/' . rawurlencode($slug) . '/' . $channel . '/' . rawurlencode($build->versionLabel), 'variant' => 'outline-light'],
            ]];
        }
        $this->theme->start_section(); $this->theme->render_breadcrumb($this->buildBreadcrumb($builds[0]->projectName, $slug, $channel)); $this->theme->render_action_table(['Version', 'Platform', 'Size', 'Date'], $rows); $this->theme->end_section(); $this->theme->end_page();
    }

    private function renderPublicHistory(string $slug, string $channel, string $version): void
    {
        if (!isset(self::CHANNELS[$channel])) { $this->publicNotFound(); return; }
        $project = $this->accessiblePublicProject(
            $slug,
            '/builds/' . rawurlencode($slug) . '/' . rawurlencode($channel) . '/' . rawurlencode($version)
        );
        if ($project === null) { return; }
        $builds = array_values(array_filter($this->builds->all(true, $slug), static fn (ProjectBuild $build): bool => $build->channel === $channel && $build->versionLabel === $version));
        if ($builds === []) { $this->publicNotFound(); return; }
        $this->theme->start_page($builds[0]->projectName . ' ' . $version, 'Build and commit history.', false);
        $this->theme->start_header($builds[0]->projectName . ' ' . $version, self::CHANNELS[$channel] . ' build history.', 'Build / ' . $builds[0]->projectName . ' / ' . self::CHANNELS[$channel] . ' / ' . self::CHANNELS[$channel] . ' build history'); $this->theme->end_header();
        $this->theme->start_section();
        $this->theme->render_breadcrumb($this->buildBreadcrumb($builds[0]->projectName, $slug, $channel, self::CHANNELS[$channel] . ' build history'));
        foreach ($builds as $build) {
            $this->theme->render_detail_card(
                'Build ' . ($build->buildNumber !== '' ? $build->buildNumber : $build->filename),
                $build->serverType,
                [
                    ['label' => 'File', 'value' => $build->filename],
                    ['label' => 'Published', 'value' => $build->publishedAt ?? 'No date'],
                    ['label' => 'CI time', 'value' => $build->ciBuildTime ?? $build->publishedAt ?? 'None'],
                    ['label' => 'SHA-256', 'value' => $build->checksumSha256 ?: 'None'],
                    ['label' => 'Size', 'value' => $this->fileSize($build->fileSizeBytes)],
                ],
                $build->commits !== [] ? ['Commit', 'Time', 'Message'] : [],
                array_map(static fn (array $commit): array => [substr($commit['sha'], 0, 12), $commit['time'], trim($commit['message'])], $build->commits),
                [['label' => 'Download', 'href' => $this->downloadHref($build), 'variant' => 'primary']]
            );
        }
        $this->theme->end_section(); $this->theme->end_page();
    }

    /** @return list<array{label: string, href?: string}> */
    private function buildBreadcrumb(string $projectName = '', string $projectSlug = '', string $channel = '', string $current = ''): array
    {
        $items = [['label' => 'Build', 'href' => '/builds']];
        if ($projectName !== '' && $projectSlug !== '') {
            $items[] = ['label' => $projectName, 'href' => '/builds/' . rawurlencode($projectSlug)];
        }
        if ($channel !== '' && isset(self::CHANNELS[$channel]) && $projectSlug !== '') {
            $items[] = ['label' => self::CHANNELS[$channel], 'href' => '/builds/' . rawurlencode($projectSlug) . '/' . $channel];
        }
        if ($current !== '') {
            $items[] = ['label' => $current];
        }
        return $items;
    }

    private function importCi(Request $request, string $projectSlug): void
    {
        if (strlen($this->ciToken) < 32) {
            $this->jsonResponse(503, ['error' => 'CI endpoint is not configured.']); return;
        }
        $provided = $request->header('X-Build-Token');
        $authorization = $request->header('Authorization');
        if ($provided === '' && preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches) === 1) { $provided = trim($matches[1]); }
        if ($provided === '' || !hash_equals($this->ciToken, $provided)) {
            $this->audit->record($request, 'build_ci_import', 'denied', 'project:' . $projectSlug, null);
            $this->jsonResponse(401, ['error' => 'Invalid build token.']); return;
        }
        $project = $this->builds->projectBySlug($projectSlug);
        $payload = $this->ciPayload($request);
        if ($project === null) {
            $this->jsonResponse(404, ['error' => 'BuildExplorer project slug is not published or does not exist.', 'project' => $projectSlug]); return;
        }
        if ($payload === null) {
            $this->jsonResponse(400, ['error' => 'CI metadata payload is invalid or missing.', 'expected' => 'multipart field metadata with JSON or JSON request body']); return;
        }
        try {
            $buildId = filter_var($payload['id'] ?? null, FILTER_VALIDATE_INT);
            $channel = strtolower(trim((string) ($payload['channel'] ?? '')));
            $time = $this->parseTime((string) ($payload['time'] ?? ''));
            if ($buildId === false || $buildId < 1 || !in_array($channel, ['dev', 'wip'], true)) {
                throw new \RuntimeException('CI build id and DEV/WIP channel are required.');
            }
            $commits = $this->validateCommits($payload['commits'] ?? []);
            $artifact = $request->file('artifact');
            if ($artifact !== null && $artifact['error'] !== UPLOAD_ERR_NO_FILE) {
                $id = $this->importCiArtifact($project, (int) $buildId, $channel, $time, $commits, $payload, $artifact);
                $this->audit->record($request, 'build_ci_import', 'success', 'project:' . $projectSlug . ':build:' . $buildId, null);
                $this->jsonResponse(200, ['status' => 'ok', 'build_id' => (int) $buildId, 'records' => [$id], 'mode' => 'artifact']);
                return;
            }
            $downloads = $payload['downloads'] ?? null;
            if (!is_array($downloads) || $downloads === [] || array_is_list($downloads)) {
                throw new \RuntimeException('At least one named download is required.');
            }
            $ids = [];
            $nextBuildNumbers = [];
            foreach ($downloads as $key => $download) {
                if (!is_string($key) || !is_array($download)) { throw new \RuntimeException('Invalid download entry.'); }
                $server = str_contains($key, ':') ? substr($key, strpos($key, ':') + 1) : $key;
                $server = $this->bounded($server, 80);
                $filename = basename(trim((string) ($download['name'] ?? '')));
                $checksum = strtolower(trim((string) (($download['checksums']['sha256'] ?? ''))));
                $size = filter_var($download['size'] ?? null, FILTER_VALIDATE_INT);
                $url = $this->normalizeHttpsUrl((string) ($download['url'] ?? ''));
                $version = $this->versionFromFilename($project['name'], $server, $filename, $channel);
                if ($server === '' || preg_match('/^[A-Za-z0-9._-]{1,251}\.jar$/i', $filename) !== 1
                    || preg_match('/^[a-f0-9]{64}$/', $checksum) !== 1 || $size === false || $size < 1 || $url === '' || $version === '') {
                    throw new \RuntimeException('Download name, version, SHA-256, size or HTTPS URL is invalid.');
                }
                $serverType = ucfirst($server);
                $previous = $this->builds->findCi((int) $project['id'], $channel, $serverType, (int) $buildId);
                if ($previous instanceof ProjectBuild) {
                    $buildNumber = $previous->buildNumber;
                } else {
                    $sequenceKey = $channel . "\0" . $version;
                    $buildNumber = $nextBuildNumbers[$sequenceKey]
                        ??= $this->builds->nextCiBuildNumber((int) $project['id'], $channel, $version);
                }
                $ids[] = $this->builds->upsertCi([
                    'project_id' => $project['id'], 'server_type' => $serverType, 'version_label' => $version,
                    'channel' => $channel, 'build_number' => $buildNumber, 'filename' => $filename,
                    'storage_key' => null, 'download_url' => $url, 'checksum_sha256' => $checksum,
                    'file_size_bytes' => (int) $size, 'changelog' => implode("\n", array_column($commits, 'message')),
                    'is_published' => 1, 'published_at' => $time->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
                    'ci_build_id' => (int) $buildId, 'ci_build_time' => $time->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
                    'commits_json' => json_encode($commits, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                ]);
            }
            $this->audit->record($request, 'build_ci_import', 'success', 'project:' . $projectSlug . ':build:' . $buildId, null);
            $this->jsonResponse(200, ['status' => 'ok', 'build_id' => (int) $buildId, 'records' => $ids]);
        } catch (\Throwable $exception) {
            $this->audit->record($request, 'build_ci_import', 'failed', 'project:' . $projectSlug, null);
            $this->jsonResponse(422, ['error' => $exception->getMessage()]);
        }
    }

    /**
     * @param array{id: int, name: string, slug: string} $project
     * @param list<array{sha: string, time: string, message: string}> $commits
     * @param array<string, mixed> $payload
     * @param array{name: string, type: string, tmp_name: string, error: int, size: int} $artifact
     */
    private function importCiArtifact(
        array $project,
        int $buildId,
        string $channel,
        \DateTimeImmutable $time,
        array $commits,
        array $payload,
        array $artifact,
    ): int {
        $server = $this->bounded((string) ($payload['server'] ?? $payload['server_type'] ?? ''), 80);
        $version = $this->bounded((string) ($payload['version'] ?? $payload['version_label'] ?? ''), 120);
        $filename = basename(trim((string) ($payload['filename'] ?? '')));
        $sourceFilename = basename($artifact['name']);
        if ($server === '') { throw new \RuntimeException('CI artifact server/platform is required.'); }
        if ($version === '') { $version = $this->versionFromFilename($project['name'], $server, $filename !== '' ? $filename : $sourceFilename, $channel); }
        if ($version === '') { throw new \RuntimeException('CI artifact version is required.'); }
        $serverType = ucfirst($server);
        $previous = $this->builds->findCi((int) $project['id'], $channel, $serverType, $buildId);
        $buildNumber = $previous instanceof ProjectBuild
            ? $previous->buildNumber
            : $this->builds->nextCiBuildNumber((int) $project['id'], $channel, $version);
        if ($filename === '') {
            $filename = BuildArtifactStorage::filename($project['name'], $server, $version, $channel, $buildNumber);
        }
        if (basename($filename) !== $filename || preg_match('/^[A-Za-z0-9._-]{1,251}\.jar$/i', $filename) !== 1) {
            throw new \RuntimeException('CI artifact filename must be a safe .jar basename.');
        }

        $stored = $this->storage->store($artifact);
        $expectedChecksum = strtolower(trim((string) ($payload['sha256'] ?? $payload['checksum_sha256'] ?? '')));
        $hasExpectedSize = array_key_exists('size', $payload) || array_key_exists('file_size_bytes', $payload);
        $expectedSize = filter_var($payload['size'] ?? $payload['file_size_bytes'] ?? null, FILTER_VALIDATE_INT);
        if ($expectedChecksum !== '' && (!preg_match('/^[a-f0-9]{64}$/', $expectedChecksum) || !hash_equals($expectedChecksum, $stored['checksum']))) {
            $this->storage->delete($stored['storage_key']);
            throw new \RuntimeException('CI artifact SHA-256 does not match uploaded file.');
        }
        if ($hasExpectedSize && ($expectedSize === false || (int) $expectedSize < 1)) {
            $this->storage->delete($stored['storage_key']);
            throw new \RuntimeException('CI artifact size is invalid.');
        }
        if ($hasExpectedSize && (int) $expectedSize !== $stored['size']) {
            $this->storage->delete($stored['storage_key']);
            throw new \RuntimeException('CI artifact size does not match uploaded file.');
        }

        try {
            $id = $this->builds->upsertCi([
                'project_id' => $project['id'], 'server_type' => $serverType, 'version_label' => $version,
                'channel' => $channel, 'build_number' => $buildNumber, 'filename' => $filename,
                'storage_key' => $stored['storage_key'], 'download_url' => null, 'checksum_sha256' => $stored['checksum'],
                'file_size_bytes' => $stored['size'], 'changelog' => implode("\n", array_column($commits, 'message')),
                'is_published' => 1, 'published_at' => $time->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
                'ci_build_id' => $buildId, 'ci_build_time' => $time->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
                'commits_json' => json_encode($commits, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            ]);
        } catch (\Throwable $exception) {
            $this->storage->delete($stored['storage_key']);
            throw $exception;
        }
        if ($previous instanceof ProjectBuild && $previous->storageKey !== $stored['storage_key']) {
            $this->storage->delete($previous->storageKey);
        }
        return $id;
    }

    /** @return array<string, mixed>|null */
    private function ciPayload(Request $request): ?array
    {
        $payload = $request->json();
        if ($payload !== null) { return $payload; }
        $metadata = $request->postString('metadata');
        if ($metadata === '') {
            $metadataFile = $request->file('metadata');
            if ($metadataFile !== null && $metadataFile['error'] === UPLOAD_ERR_OK && $metadataFile['size'] > 0 && $metadataFile['size'] <= 1048576 && is_file($metadataFile['tmp_name'])) {
                $contents = file_get_contents($metadataFile['tmp_name']);
                $metadata = is_string($contents) ? $contents : '';
            }
        }
        if ($metadata === '' || strlen($metadata) > 1048576) { return null; }
        try {
            $decoded = json_decode($metadata, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
        return is_array($decoded) && !array_is_list($decoded) ? $decoded : null;
    }

    /** @return list<array{sha: string, time: string, message: string}> */
    private function validateCommits(mixed $value): array
    {
        if (!is_array($value) || count($value) > 100) { throw new \RuntimeException('Invalid commits list.'); }
        $result = [];
        foreach ($value as $commit) {
            if (!is_array($commit)) { throw new \RuntimeException('Invalid commit entry.'); }
            $sha = strtolower(trim((string) ($commit['sha'] ?? '')));
            $message = $this->bounded((string) ($commit['message'] ?? ''), 2000);
            $time = $this->parseTime((string) ($commit['time'] ?? ''));
            if (preg_match('/^[a-f0-9]{40}$/', $sha) !== 1 || $message === '') { throw new \RuntimeException('Invalid commit SHA or message.'); }
            $result[] = ['sha' => $sha, 'time' => $time->setTimezone(new \DateTimeZone('UTC'))->format(DATE_ATOM), 'message' => $message];
        }
        return $result;
    }

    private function versionFromFilename(string $project, string $server, string $filename, string $channel): string
    {
        $base = preg_replace('/\.jar$/i', '', $filename) ?? '';
        $base = preg_replace('/^' . preg_quote($project, '/') . '-/i', '', $base) ?? $base;
        $base = preg_replace('/^' . preg_quote($server, '/') . '-/i', '', $base) ?? $base;
        $base = preg_replace('/-' . preg_quote(strtoupper($channel), '/') . '(?:-[A-Za-z0-9._]+)?$/i', '', $base) ?? $base;
        return $this->bounded($base, 120);
    }

    private function parseTime(string $value): \DateTimeImmutable
    {
        $value = trim($value);
        if ($value === '' || preg_match('/^\d{4}-\d{2}-\d{2}T/', $value) !== 1) {
            throw new \RuntimeException('CI timestamps must use ISO-8601.');
        }
        return new \DateTimeImmutable($value);
    }

    private function normalizeHttpsUrl(string $url): string
    {
        $url = trim($url);
        if (preg_match('/^\[[^]]+\]\((https:\/\/[^)]+)\)$/i', $url, $matches) === 1) { $url = $matches[1]; }
        return filter_var($url, FILTER_VALIDATE_URL) !== false && str_starts_with(strtolower($url), 'https://') ? $url : '';
    }

    /** @param array<string, mixed> $payload */
    private function jsonResponse(int $status, array $payload): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function publicNotFound(): void
    {
        http_response_code(404);
        $this->theme->render_public_error(404, 'Builds not found', 'The selected project, channel or version has no public builds.', 'Back to Build Explorer', '/builds');
    }

    /** @return array{id: int, name: string, slug: string, requires_auth: bool}|null */
    private function accessiblePublicProject(string $slug, string $returnPath): ?array
    {
        $project = $this->builds->publicProjectBySlug($slug);
        if ($project === null) {
            $this->publicNotFound();
            return null;
        }
        if ($project['requires_auth'] && $this->auth->actualUser() === null) {
            $this->publicLoginRequired($returnPath);
            return null;
        }

        return $project;
    }

    private function publicLoginRequired(string $returnPath): void
    {
        http_response_code(401);
        $this->theme->render_public_error(
            401,
            'Login required',
            'This Build Explorer project is available only to signed-in users.',
            'Sign in',
            'index.php?' . http_build_query(['route' => '/admin/login', 'next' => $returnPath])
        );
    }

    private function downloadHref(ProjectBuild $build): string
    {
        return 'index.php?route=/builds/download&id=' . $build->id;
    }

    /** @param array{project_id:int,channel:string,published:string,search:string} $filters */
    private function renderV2AdminPanel(array $filters): void
    {
        $releases = $this->builds->v2AdminRecent($filters, 100);
        $this->theme->start_admin_panel(
            'Build Explorer v2',
            'Logiczne buildy z build_releases i ich artefakty. Legacy project_builds pozostaje poniżej do zgodności wstecznej.'
        );
        if ($releases === []) {
            $this->theme->render_alert('Brak buildów v2 pasujących do bieżących filtrów.', 'info');
            $this->theme->end_admin_panel();
            return;
        }

        $rows = [];
        foreach ($releases as $release) {
            $platforms = array_values(array_filter(explode(',', (string) ($release['platforms'] ?? ''))));
            $actions = [];
            if ((bool) $release['is_published']) {
                $actions[] = [
                    'label' => 'Explore',
                    'href' => 'index.php?' . http_build_query([
                        'route' => '/builds/v2/release',
                        'project' => (string) $release['project_slug'],
                        'release' => (int) $release['id'],
                    ]),
                    'variant' => 'outline-light',
                ];
            }
            $rows[] = [
                'cells' => [
                    (string) $release['project_name'],
                    (string) $release['version_label'],
                    self::CHANNELS[(string) $release['channel']] ?? ucfirst((string) $release['channel']),
                    $platforms !== [] ? implode(', ', array_map('ucfirst', $platforms)) : 'Brak',
                    (string) $release['artifact_count'],
                    $this->fileSize((int) $release['file_size_bytes']),
                    (string) ($release['build_number'] !== '' ? $release['build_number'] : '—'),
                    (string) ($release['published_at'] ?? $release['ci_build_time'] ?? 'Brak daty'),
                    (bool) $release['is_published'] ? 'Tak' : 'Nie',
                ],
                'actions' => $actions,
            ];
        }

        $this->theme->render_admin_action_table(
            ['Projekt', 'Wersja', 'Kanał', 'Platformy', 'Artefakty', 'Rozmiar', 'Build', 'Data', 'Publikacja'],
            $rows,
            $this->security->csrfToken()
        );
        $this->theme->end_admin_panel();
    }

    private function renderAdmin(Request $request, string $message = '', string $variant = 'info'): void
    {
        $this->startPage('Build Explorer', 'Metadane plików i bezpieczne zewnętrzne adresy pobierania.', [[
            'label' => 'Dodaj build', 'href' => 'index.php?route=/admin/builds/create', 'variant' => 'primary',
        ], ['label' => 'Publiczna lista', 'href' => '/builds', 'variant' => 'outline-light']]);
        if ($message !== '') { $this->theme->render_alert($message, $variant); }
        $projectOptions = ['' => 'Wszystkie projekty'] + $this->stringOptions($this->builds->projectOptions());
        $filters = [
            'project_id' => max(0, $request->queryInt('project_id', 0) ?? 0),
            'channel' => strtolower($request->queryString('channel')),
            'published' => $request->queryString('published'),
            'search' => $this->bounded($request->queryString('search'), 120),
        ];
        $sort = in_array($request->queryString('sort'), ['date', 'project', 'version', 'size', 'channel', 'filename', 'published'], true)
            ? $request->queryString('sort')
            : 'date';
        $direction = strtolower($request->queryString('direction')) === 'asc' ? 'asc' : 'desc';
        $perPage = max(10, min(100, $request->queryInt('per_page', 25) ?? 25));
        $total = $this->builds->adminCount($filters);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $request->queryInt('page', 1) ?? 1), $pages);
        $builds = $this->builds->adminPage($page, $perPage, $filters, $sort, $direction);
        $storage = $this->storage->statistics();
        $statistics = $this->builds->statistics();
        $freePercent = $storage['disk_free_bytes'] !== null
            && $storage['disk_total_bytes'] !== null
            && $storage['disk_total_bytes'] > 0
                ? number_format(($storage['disk_free_bytes'] / $storage['disk_total_bytes']) * 100, 1, ',', ' ') . '%'
                : 'brak danych';
        $this->theme->start_admin_panel('Statystyki buildów', 'Stan magazynu i katalogu');
        $this->theme->render_admin_fact_grid([
            ['label' => 'Wszystkie buildy', 'value' => (string) $statistics['total'], 'detail' => $statistics['legacy_builds'] . ' legacy; ' . $statistics['v2_releases'] . ' v2 releases'],
            [
                'label' => 'Publicznie dostępne',
                'value' => (string) $statistics['public_available'],
                'detail' => $statistics['authenticated_available'] . ' dostępnych po zalogowaniu',
            ],
            [
                'label' => 'Publikacja buildu włączona',
                'value' => (string) $statistics['published'],
                'detail' => ($statistics['total'] - $statistics['published']) . ' buildów z wyłączoną publikacją',
            ],
            ['label' => 'Lokalne artefakty', 'value' => (string) $statistics['local'], 'detail' => 'Legacy + V2; fizyczne artefakty zapisane lokalnie'],
            ['label' => 'Rozmiar wg bazy', 'value' => $this->fileSize($statistics['registered_bytes']), 'detail' => 'Suma lokalnych artefaktów przypisanych do buildów'],
            ['label' => 'Pliki na dysku', 'value' => (string) $storage['files'], 'detail' => 'Faktyczna zawartość magazynu buildów'],
            ['label' => 'Zajęte miejsce na dysku', 'value' => $this->fileSize($storage['bytes']), 'detail' => 'Fizyczny rozmiar wszystkich plików magazynu'],
            ['label' => 'Wolne miejsce na dysku', 'value' => $this->fileSize($storage['disk_free_bytes']), 'detail' => $freePercent . ' z ' . $this->fileSize($storage['disk_total_bytes']) . ' pojemności systemu plików'],
            ...array_map(
                fn (string $channel): array => [
                    'label' => 'Kanał ' . self::CHANNELS[$channel],
                    'value' => (string) $statistics[$channel],
                    'detail' => 'Liczba buildów w kanale',
                ],
                array_keys(self::CHANNELS)
            ),
        ]);
        $this->theme->end_admin_panel();
        $this->renderRetentionPanel();
        $this->renderV2AdminPanel($filters);
        $projectAccess = $this->builds->projectAccessSettings();
        $this->theme->start_admin_panel(
            'Widoczność projektów',
            'Dostęp Build Explorera działa dopiero po opublikowaniu projektu w module Projekty. '
                . 'Domyślnie opublikowany projekt i jego opublikowane buildy są publiczne.'
        );
        if ($projectAccess === []) {
            $this->theme->render_alert('Brak projektów do skonfigurowania.', 'info');
        } else {
            $accessRows = [];
            foreach ($projectAccess as $project) {
                $actions = [];
                if ($this->hasPermission('builds.manage')) {
                    $actions[] = [
                        'label' => $project['requires_auth'] ? 'Ustaw publiczny' : 'Tylko zalogowani',
                        'action' => 'index.php?route=/admin/builds/project-visibility',
                        'fields' => [
                            'project_id' => $project['id'],
                            'requires_auth' => $project['requires_auth'] ? '0' : '1',
                        ],
                        'variant' => $project['requires_auth'] ? 'outline-light' : 'primary',
                    ];
                }
                if ($this->hasPermission('projects.manage')) {
                    $actions[] = [
                        'label' => 'Edytuj projekt',
                        'href' => 'index.php?route=/admin/projects/edit&id=' . $project['id'],
                        'variant' => 'outline-light',
                    ];
                }
                $accessRows[] = [
                    'cells' => [
                        $project['name'],
                        $project['slug'],
                        (string) $project['build_count'],
                        $project['published'] ? 'Opublikowany' : 'Nieopublikowany',
                        $project['requires_auth'] ? 'Tylko zalogowani' : 'Publiczny',
                    ],
                    'actions' => $actions,
                ];
            }
            $this->theme->render_admin_action_table(
                ['Projekt', 'Slug', 'Buildy', 'Katalog projektów', 'Dostęp w Build Explorerze'],
                $accessRows,
                $this->security->csrfToken()
            );
        }
        $this->theme->end_admin_panel();
        $filterFields = [
            ['name' => 'route', 'label' => 'Trasa', 'type' => 'hidden', 'value' => '/admin/builds'],
            ['name' => 'search', 'label' => 'Szukaj', 'value' => $filters['search'], 'placeholder' => 'Projekt, wersja, plik lub platforma'],
            ['name' => 'project_id', 'label' => 'Projekt', 'type' => 'select', 'value' => (string) $filters['project_id'], 'options' => $projectOptions],
            ['name' => 'channel', 'label' => 'Kanał', 'type' => 'select', 'value' => $filters['channel'], 'options' => ['' => 'Wszystkie kanały'] + self::CHANNELS],
            [
                'name' => 'published',
                'label' => 'Publikacja buildu',
                'type' => 'select',
                'value' => $filters['published'],
                'options' => ['' => 'Wszystkie', '1' => 'Włączona', '0' => 'Wyłączona'],
            ],
            ['name' => 'per_page', 'label' => 'Na stronę', 'type' => 'select', 'value' => (string) $perPage, 'options' => ['10' => '10', '25' => '25', '50' => '50', '100' => '100']],
            ['name' => 'sort', 'label' => 'Sortowanie', 'type' => 'hidden', 'value' => $sort],
            ['name' => 'direction', 'label' => 'Kierunek', 'type' => 'hidden', 'value' => $direction],
        ];

        $queryState = [
            'project_id' => $filters['project_id'] ?: null, 'channel' => $filters['channel'] ?: null,
            'published' => $filters['published'] !== '' ? $filters['published'] : null,
            'search' => $filters['search'] ?: null, 'per_page' => $perPage,
        ];
        $pageQuery = ['route' => '/admin/builds', ...$queryState, 'sort' => $sort, 'direction' => $direction];
        $this->theme->start_admin_panel('Buildy projektów', "{$total} wpisów, strona {$page}/{$pages}");
        $this->theme->render_admin_filter_modal(
            'builds',
            'Filtruj buildy projektów',
            'index.php',
            $filterFields,
            'index.php?route=/admin/builds'
        );
        if ($builds === []) {
            $this->theme->render_alert('Nie znaleziono buildów spełniających wybrane filtry.', 'info');
        } else {
            $sortableHeader = function (string $label, string $sortKey) use ($sort, $direction, $queryState): array {
                $nextDirection = $sort === $sortKey && $direction === 'desc' ? 'asc' : 'desc';
                return [
                    'label' => $label,
                    'href' => 'index.php?' . http_build_query([
                        'route' => '/admin/builds', ...$queryState, 'sort' => $sortKey, 'direction' => $nextDirection,
                    ]),
                    'direction' => $sort === $sortKey ? $direction : '',
                ];
            };
            $rows = [];
            foreach ($builds as $index => $build) {
                $rows[] = [
                    'selection_value' => $build->id,
                    'cells' => [
                        (string) (($page - 1) * $perPage + $index + 1),
                        $build->projectName,
                        $build->versionLabel,
                        self::CHANNELS[$build->channel],
                        $build->filename,
                        $this->fileSize($build->fileSizeBytes),
                        $this->effectiveVisibilityLabel($build),
                    ],
                    'actions' => [[
                        'label' => 'Edytuj', 'href' => 'index.php?route=/admin/builds/edit&id=' . $build->id, 'variant' => 'primary',
                    ], [
                        'label' => 'Usuń', 'action' => 'index.php?route=/admin/builds/delete', 'fields' => ['id' => $build->id], 'variant' => 'danger', 'confirm' => 'Usunąć build z katalogu?',
                    ]],
                ];
            }
            $this->theme->render_admin_action_table([
                [
                    'label' => 'Lp.',
                    'href' => 'index.php?' . http_build_query(['route' => '/admin/builds', ...$queryState]),
                    'reset' => true,
                ],
                $sortableHeader('Projekt', 'project'),
                $sortableHeader('Wersja', 'version'),
                $sortableHeader('Kanał', 'channel'),
                $sortableHeader('Plik', 'filename'),
                $sortableHeader('Rozmiar', 'size'),
                $sortableHeader('Widoczność', 'published'),
            ], $rows, $this->security->csrfToken(), $this->hasPermission('builds.manage') ? [
                'action' => 'index.php?route=/admin/builds/bulk-delete',
                'field' => 'ids[]',
                'label' => 'Usuń zaznaczone',
                'confirm' => 'Usunąć wszystkie zaznaczone buildy z katalogu? Lokalnych artefaktów nie będzie można odzyskać.',
            ] : null);
        }
        if ($page > 1) {
            $this->theme->render_button('Poprzednia strona', 'index.php?' . http_build_query([...$pageQuery, 'page' => $page - 1]), 'outline-light');
        }
        if ($page < $pages) {
            $this->theme->render_button('Następna strona', 'index.php?' . http_build_query([...$pageQuery, 'page' => $page + 1]), 'outline-light');
        }
        $this->theme->end_admin_panel();
        $this->endPage();
    }

    private function renderEdit(Request $request): void
    {
        $build = $this->builds->find($request->queryInt('id', 0) ?? 0);
        $build instanceof ProjectBuild ? $this->renderForm($build) : $this->renderAdmin($request, 'Nie znaleziono buildu.', 'danger');
    }

    private function renderForm(?ProjectBuild $build = null, string $message = '', string $variant = 'info'): void
    {
        $projects = $this->builds->projectOptions();
        $this->startPage($build === null ? 'Dodaj build' : 'Edytuj build', 'Plik JAR zostanie zapisany poza publicznym katalogiem WWW.', [[
            'label' => 'Wróć do buildów', 'href' => 'index.php?route=/admin/builds', 'variant' => 'outline-light',
        ]]);
        if ($message !== '') { $this->theme->render_alert($message, $variant); }
        if ($projects === []) {
            $this->theme->render_alert('Najpierw dodaj projekt w module Projekty.', 'warning'); $this->endPage(); return;
        }
        if ($build !== null) {
            $this->theme->render_admin_fact_grid([
                ['label' => 'Aktualny plik', 'value' => $build->filename, 'detail' => $build->storageKey !== '' ? 'Plik lokalny' : 'Link zewnętrzny'],
                ['label' => 'Rozmiar', 'value' => $this->fileSize($build->fileSizeBytes), 'detail' => 'Obliczony po uploadzie'],
                ['label' => 'SHA-256', 'value' => $build->checksumSha256 ?: 'Brak', 'detail' => 'Obliczony po uploadzie'],
            ]);
        }
        $fields = $build !== null ? [['name' => 'id', 'label' => 'ID', 'type' => 'hidden', 'value' => (string) $build->id]] : [];
        $fields = [...$fields,
            ['name' => 'project_id', 'label' => 'Projekt', 'type' => 'select', 'value' => (string) ($build?->projectId ?? array_key_first($projects)), 'options' => $this->stringOptions($projects)],
            ['name' => 'server_type', 'label' => 'Serwer / platforma', 'value' => $build?->serverType ?? '', 'help' => 'Np. Paper, Spigot, Folia.'],
            ['name' => 'version_label', 'label' => 'Wersja', 'value' => $build?->versionLabel ?? ''],
            ['name' => 'channel', 'label' => 'Kanał', 'type' => 'select', 'value' => $build?->channel ?? 'release', 'options' => self::CHANNELS],
            ['name' => 'build_number', 'label' => 'Numer buildu', 'value' => $build?->buildNumber ?? '', 'help' => 'Wymagany dla DEV/WIP; Release i Snapshot mogą pozostać bez numeru.'],
            ['name' => 'filename', 'label' => 'Nazwa pliku', 'value' => $build?->filename ?? '', 'help' => 'Puste pole wygeneruje nazwę z projektu, serwera, wersji, kanału i opcjonalnego numeru buildu.'],
            ['name' => 'artifact', 'label' => $build === null ? 'Plik JAR' : 'Nowy plik JAR (opcjonalnie)', 'type' => 'file', 'accept' => '.jar,application/java-archive'],
            ['name' => 'changelog', 'label' => 'Opis zmian', 'type' => 'textarea', 'rows' => 7, 'value' => $build?->changelog ?? ''],
            ['name' => 'is_published', 'label' => 'Widoczny publicznie', 'type' => 'checkbox', 'checked' => $build?->published ?? false],
        ];
        $this->theme->start_admin_panel('Dane buildu', 'Build Explorer');
        $this->theme->render_form('index.php?route=' . ($build === null ? '/admin/builds/create' : '/admin/builds/edit'), $fields, $build === null ? 'Dodaj build' : 'Zapisz build', $this->security->csrfToken());
        $this->theme->end_admin_panel();
        $this->endPage();
    }

    private function save(Request $request, ?ProjectBuild $build = null): void
    {
        $actor = $this->auth->user();
        if ($build === null && $request->postInt('id', 0)) { $this->renderAdmin($request, 'Nie znaleziono buildu.', 'danger'); return; }
        if (!$this->security->validateCsrfToken($request->postString('_token'))) {
            $this->audit->record($request, 'build_save', 'invalid_csrf', 'builds', $actor?->id);
            $this->renderForm($build, 'Token CSRF jest nieprawidłowy lub wygasł.', 'danger'); return;
        }
        $projectId = $request->postInt('project_id', 0) ?? 0;
        $projects = $this->builds->projectOptions();
        $serverType = $this->bounded($request->postString('server_type'), 80);
        $version = $this->bounded($request->postString('version_label'), 120);
        $channel = $request->postString('channel');
        $buildNumber = $this->bounded($request->postString('build_number'), 80);
        $filename = trim($request->postString('filename'));
        $changelog = $this->bounded($request->postString('changelog'), 10000);
        $published = $request->postBool('is_published');
        if (!isset($projects[$projectId]) || $serverType === '' || $version === '' || !isset(self::CHANNELS[$channel])
            || (in_array($channel, ['dev', 'wip'], true) && $buildNumber === '')) {
            $this->renderForm($build, 'Uzupełnij projekt, serwer, wersję i kanał; DEV/WIP wymagają numeru buildu.', 'warning'); return;
        }
        if ($filename === '') {
            try {
                $filename = BuildArtifactStorage::filename($projects[$projectId], $serverType, $version, $channel, $buildNumber);
            } catch (\Throwable $exception) {
                $this->renderForm($build, $exception->getMessage(), 'warning'); return;
            }
        }
        if (basename($filename) !== $filename || preg_match('/^[A-Za-z0-9._-]{1,251}\.jar$/i', $filename) !== 1) {
            $this->renderForm($build, 'Nazwa musi być bezpiecznym plikiem .jar bez ścieżki.', 'warning'); return;
        }
        $file = $request->file('artifact');
        $hasUpload = $file !== null && $file['error'] !== UPLOAD_ERR_NO_FILE;
        if ($build === null && !$hasUpload) {
            $this->renderForm(null, 'Wybierz plik JAR do zapisania.', 'warning'); return;
        }
        $stored = null;
        if ($hasUpload) {
            try { $stored = $this->storage->store($file); }
            catch (\Throwable $exception) { $this->renderForm($build, $exception->getMessage(), 'danger'); return; }
        }
        $data = [
            'project_id' => $projectId, 'server_type' => $serverType, 'version_label' => $version,
            'channel' => $channel, 'build_number' => $buildNumber, 'filename' => $filename,
            'storage_key' => $stored['storage_key'] ?? $build?->storageKey,
            'download_url' => $stored !== null ? null : ($build?->downloadUrl ?: null),
            'checksum_sha256' => $stored['checksum'] ?? ($build?->checksumSha256 ?: null),
            'file_size_bytes' => $stored['size'] ?? $build?->fileSizeBytes,
            'changelog' => $changelog, 'is_published' => $published ? 1 : 0,
            'published_at' => $published ? ($build?->publishedAt ?? date('Y-m-d H:i:s')) : null,
        ];
        try {
            if ($build === null) {
                $id = $this->builds->create($data); $event = 'build_create';
                if ($id <= 0) { throw new \RuntimeException('Baza nie zwróciła identyfikatora buildu.'); }
            } else {
                $id = $build->id;
                if (!$this->builds->update($id, $data)) { throw new \RuntimeException('Nie udało się zaktualizować buildu.'); }
                $event = 'build_update';
            }
        } catch (\Throwable $exception) {
            if ($stored !== null) { $this->storage->delete($stored['storage_key']); }
            $this->audit->record($request, 'build_save', 'failed', 'builds', $actor?->id);
            $this->renderForm($build, 'Nie udało się zapisać buildu: ' . $exception->getMessage(), 'danger');
            return;
        }
        $this->audit->record($request, $event, 'success', 'build:' . $id, $actor?->id);
        if ($stored !== null) { $this->storage->delete($build?->storageKey); }
        $this->renderAdmin($request, 'Build został zapisany.', 'success');
    }

    private function delete(Request $request): void
    {
        $actor = $this->auth->user();
        if (!$this->security->validateCsrfToken($request->postString('_token'))) {
            $this->audit->record($request, 'build_delete', 'invalid_csrf', 'builds', $actor?->id);
            $this->renderAdmin($request, 'Token CSRF jest nieprawidłowy lub wygasł.', 'danger'); return;
        }
        $id = $request->postInt('id', 0) ?? 0; $build = $this->builds->find($id); $ok = $this->builds->delete($id);
        if ($ok) { $this->storage->delete($build?->storageKey); }
        $this->audit->record($request, 'build_delete', $ok ? 'success' : 'failed', 'build:' . $id, $actor?->id);
        $this->renderAdmin($request, $ok ? 'Build został usunięty.' : 'Nie udało się usunąć buildu.', $ok ? 'success' : 'danger');
    }

    private function bulkDelete(Request $request): void
    {
        $actor = $this->auth->user();
        if (!$this->security->validateCsrfToken($request->postString('_token'))) {
            $this->audit->record($request, 'build_bulk_delete', 'invalid_csrf', 'builds', $actor?->id);
            $this->renderAdmin($request, 'Token CSRF jest nieprawidłowy lub wygasł.', 'danger');
            return;
        }

        $ids = $this->normalizeBuildIds($request->postStringList('ids'));
        if ($ids === []) {
            $this->audit->record($request, 'build_bulk_delete', 'failed', 'reason=no_selection', $actor?->id);
            $this->renderAdmin($request, 'Zaznacz co najmniej jeden build do usunięcia.', 'warning');
            return;
        }

        $deleted = 0;
        $failed = [];
        foreach ($ids as $id) {
            $build = $this->builds->find($id);
            if (!$build instanceof ProjectBuild || !$this->builds->delete($id)) {
                $failed[] = $id;
                continue;
            }
            $this->storage->delete($build->storageKey);
            $deleted++;
        }

        $status = $failed === [] ? 'success' : ($deleted > 0 ? 'partial' : 'failed');
        $details = 'selected=' . count($ids) . '; deleted=' . $deleted . '; failed=' . count($failed)
            . ($failed !== [] ? '; failed_ids=' . implode(',', $failed) : '');
        $this->audit->record($request, 'build_bulk_delete', $status, $details, $actor?->id);

        if ($failed === []) {
            $this->renderAdmin($request, 'Usunięto zaznaczone buildy: ' . $deleted . '.', 'success');
            return;
        }
        $this->renderAdmin(
            $request,
            'Usunięto ' . $deleted . ' z ' . count($ids) . ' zaznaczonych buildów. Nie usunięto: ' . count($failed) . '.',
            $deleted > 0 ? 'warning' : 'danger'
        );
    }

    /** @param list<string|int> $values @return list<int> */
    private function normalizeBuildIds(array $values): array
    {
        $ids = [];
        foreach (array_slice($values, 0, 100) as $value) {
            $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id !== false) {
                $ids[(int) $id] = (int) $id;
            }
        }

        return array_values($ids);
    }

    private function updateProjectVisibility(Request $request): void
    {
        $actor = $this->auth->actualUser();
        if (!$this->security->validateCsrfToken($request->postString('_token'))) {
            $this->audit->record($request, 'build_project_visibility_update', 'invalid_csrf', 'builds', $actor?->id);
            $this->renderAdmin($request, 'Token CSRF jest nieprawidłowy lub wygasł.', 'danger');
            return;
        }
        $projectId = $request->postInt('project_id', 0) ?? 0;
        $access = $request->postString('requires_auth');
        $projects = $this->builds->projectOptions();
        if (!isset($projects[$projectId]) || !in_array($access, ['0', '1'], true)) {
            $this->audit->record($request, 'build_project_visibility_update', 'invalid_input', 'project:' . $projectId, $actor?->id);
            $this->renderAdmin($request, 'Nieprawidłowy projekt lub poziom dostępu.', 'warning');
            return;
        }

        $requiresAuth = $access === '1';
        $ok = $this->builds->setProjectRequiresAuth($projectId, $requiresAuth);
        $this->audit->record(
            $request,
            'build_project_visibility_update',
            $ok ? 'success' : 'failed',
            'project:' . $projectId,
            $actor?->id
        );
        $this->renderAdmin(
            $request,
            $ok
                ? 'Widoczność projektu „' . $projects[$projectId] . '” została zmieniona na: '
                    . ($requiresAuth ? 'tylko zalogowani.' : 'publiczny.')
                : 'Nie udało się zmienić widoczności projektu.',
            $ok ? 'success' : 'danger'
        );
    }

    private function download(Request $request): void
    {
        $build = $this->builds->findPublic($request->queryInt('id', 0) ?? 0);
        if (!$build instanceof ProjectBuild) {
            http_response_code(404);
            $this->theme->render_public_error(404, 'File unavailable', 'No published JAR file was found.', 'Back to downloads', '/builds'); return;
        }
        if ($build->projectRequiresAuth && $this->auth->actualUser() === null) {
            $this->publicLoginRequired('index.php?route=/builds/download&id=' . $build->id);
            return;
        }
        $path = $this->storage->path($build->storageKey);
        if ($path === null) {
            if (filter_var($build->downloadUrl, FILTER_VALIDATE_URL) !== false
                && str_starts_with(strtolower($build->downloadUrl), 'https://')
            ) {
                header('Location: ' . $build->downloadUrl, true, 302);
                return;
            }
            http_response_code(404);
            $this->theme->render_public_error(404, 'File unavailable', 'No published JAR file was found.', 'Back to downloads', '/builds');
            return;
        }
        DownloadResponse::send($path, $build->filename, 'application/java-archive', false);
    }

    private function guard(Request $request, string $permission, callable $handler): void
    {
        $user = $this->auth->user();
        if (!$user instanceof User) { http_response_code(401); $this->theme->render_admin_access_state(401, 'Wymagane logowanie', 'Build Explorer wymaga aktywnej sesji.', 'index.php?route=/admin/login', 'Przejdź do logowania'); return; }
        if (!in_array('*', $user->permissions, true) && !in_array($permission, $user->permissions, true)) {
            $this->audit->record($request, 'admin_access', 'denied', $permission, $user->id);
            http_response_code(403); $this->theme->render_admin_access_state(403, 'Brak uprawnień', 'Twoje konto nie ma uprawnienia: ' . $permission, 'index.php?route=/admin', 'Wróć do panelu'); return;
        }
        $handler();
    }

    private function startPage(string $title, string $lead, ?array $actions = null): void
    {
        $user = $this->auth->user();
        $this->theme->start_admin_page($title, $this->menu->visibleFor($user?->permissions ?? []), '/admin/builds', [
            'name' => $user?->displayName ?? 'Gość', 'role' => $user?->primaryRole() ?? 'Gość', 'initials' => $user?->initials() ?? 'G',
            'avatar_url' => $user?->avatarUrl ?? '', 'logout_action' => 'index.php?route=/admin/logout', 'logout_token' => $this->security->csrfToken(),
        ]);
        $this->theme->start_admin_content($title, $lead, [['label' => 'Panel', 'href' => 'index.php?route=/admin'], ['label' => 'Build Explorer', 'href' => 'index.php?route=/admin/builds']], $actions);
    }
    private function endPage(): void { $this->theme->end_admin_content(); $this->theme->end_admin_page(); }
    private function bounded(string $value, int $max): string { return function_exists('mb_substr') ? mb_substr(trim($value), 0, $max) : substr(trim($value), 0, $max); }
    private function hasPermission(string $permission): bool
    {
        $permissions = $this->auth->user()?->permissions ?? [];
        return in_array('*', $permissions, true) || in_array($permission, $permissions, true);
    }
    private function effectiveVisibilityLabel(ProjectBuild $build): string
    {
        if (!$build->published) {
            return 'Build ukryty';
        }
        if (!$build->projectPublished) {
            return 'Projekt nieopublikowany';
        }

        return $build->projectRequiresAuth ? 'Tylko zalogowani' : 'Publiczny';
    }
    /** @param array<int, string> $options @return array<string, string> */
    private function stringOptions(array $options): array { $result = []; foreach ($options as $id => $label) { $result[(string) $id] = $label; } return $result; }
    private function fileSize(?int $bytes): string
    {
        if ($bytes === null) { return 'Nie podano'; }
        if ($bytes >= 1048576) { return number_format($bytes / 1048576, 2, ',', ' ') . ' MB'; }
        if ($bytes >= 1024) { return number_format($bytes / 1024, 1, ',', ' ') . ' KB'; }
        return $bytes . ' B';
    }
}
