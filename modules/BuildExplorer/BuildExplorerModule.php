<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\BuildExplorer;

use SyntaxDevTeam\Cms\Core\AdminMenuRegistry;
use SyntaxDevTeam\Cms\Core\AdminSearchProviderInterface;
use SyntaxDevTeam\Cms\Core\AdminSearchRegistry;
use SyntaxDevTeam\Cms\Core\DashboardProviderInterface;
use SyntaxDevTeam\Cms\Core\DashboardRegistry;
use SyntaxDevTeam\Cms\Core\ModuleInterface;
use SyntaxDevTeam\Cms\Core\PublicNavigationProviderInterface;
use SyntaxDevTeam\Cms\Core\PublicNavigationRegistry;
use SyntaxDevTeam\Cms\Core\Request;
use SyntaxDevTeam\Cms\Core\Router;
use SyntaxDevTeam\Cms\Core\Security;
use SyntaxDevTeam\Cms\Core\ThemeInterface;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuditLogService;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuthService;
use SyntaxDevTeam\Cms\Modules\CoreAuth\User;

final class BuildExplorerModule implements ModuleInterface, PublicNavigationProviderInterface, AdminSearchProviderInterface, DashboardProviderInterface
{
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
    ) {}

    public function id(): string { return 'build_explorer'; }
    public function version(): string { return '1.4.1'; }
    public function dependencies(): array { return ['core_auth', 'projects']; }
    public function isProtected(): bool { return false; }
    public function requiredPermissions(): array { return ['builds.view', 'builds.manage']; }

    public function registerAdminMenu(AdminMenuRegistry $menu): void
    {
        $menu->add('Treść', 'Build Explorer', '/admin/builds', 'BL', 'builds.view', 38);
    }

    public function registerPublicNavigation(PublicNavigationRegistry $navigation): void
    {
        $navigation->add('build_explorer.index', 'Pliki do pobrania', '/builds', 'main', 56);
    }

    public function registerAdminSearch(AdminSearchRegistry $search): void
    {
        $search->add('builds.create', 'Dodaj build', 'Dodaj plik lub metadane wydania projektu.', 'index.php?route=/admin/builds/create', ['build', 'wersja', 'jar', 'release', 'snapshot', 'dev', 'wip'], 'builds.manage', 'Treść', 38);
    }

    public function registerDashboard(DashboardRegistry $dashboard): void
    {
        $dashboard->addMetric('builds.published', 'Opublikowane buildy', 'Buildy widoczne w publicznym Build Explorerze.', 'BLD', function (): array {
            $all = $this->builds->all();
            $published = count(array_filter($all, static fn (ProjectBuild $build): bool => $build->published));
            return ['value' => $published, 'detail' => count($all) . ' wszystkich buildów'];
        }, 'builds.view', 115);
    }

    public function registerRoutes(Router $router): void
    {
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
        $router->get('/admin/builds', fn (Request $request) => $this->guard($request, 'builds.view', fn () => $this->renderAdmin()));
        $router->get('/admin/builds/create', fn (Request $request) => $this->guard($request, 'builds.manage', fn () => $this->renderForm()));
        $router->post('/admin/builds/create', fn (Request $request) => $this->guard($request, 'builds.manage', fn () => $this->save($request)));
        $router->get('/admin/builds/edit', fn (Request $request) => $this->guard($request, 'builds.manage', fn () => $this->renderEdit($request)));
        $router->post('/admin/builds/edit', fn (Request $request) => $this->guard($request, 'builds.manage', fn () => $this->save($request, $this->builds->find($request->postInt('id', 0) ?? 0))));
        $router->post('/admin/builds/delete', fn (Request $request) => $this->guard($request, 'builds.manage', fn () => $this->delete($request)));
    }

    private function renderPublicProjects(): void
    {
        $projects = $this->builds->publicProjects();
        $this->theme->start_page('Build Explorer - SyntaxDevTeam', 'Wersje projektów SyntaxDevTeam do pobrania.');
        $this->theme->start_header('Build Explorer', 'Wybierz projekt, aby przejść do jego kanałów wydań.', 'Build');
        $this->theme->end_header();
        $this->theme->start_section();
        $this->theme->render_breadcrumb($this->buildBreadcrumb());
        if ($projects === []) {
            $this->theme->render_alert('Nie opublikowano jeszcze żadnych projektów.', 'info');
        } else {
            $this->theme->start_grid();
            foreach ($projects as $project) {
                $this->theme->start_column(count($projects) === 1 ? '12' : (count($projects) === 2 || count($projects) === 4 ? 'lg-6' : 'lg-4'));
                $this->theme->start_card($project['name'], $project['build_count'] . ' publicznych buildów');
                $this->theme->render_link_list([['label' => 'Kanały wydań', 'href' => '/builds/' . rawurlencode($project['slug']), 'meta' => 'Release / Snapshot / Dev / WIP']]);
                $this->theme->end_card();
                $this->theme->end_column();
            }
            $this->theme->end_grid();
        }
        $this->theme->end_section();
        $this->theme->end_page();
    }

    private function renderPublicChannels(string $slug): void
    {
        $project = $this->builds->projectBySlug($slug);
        $builds = $this->builds->all(true, $slug);
        if ($project === null || $builds === []) { $this->publicNotFound(); return; }
        $available = [];
        foreach ($builds as $build) { $available[$build->channel] = ($available[$build->channel] ?? 0) + 1; }
        $this->theme->start_page($project['name'] . ' - Build Explorer', 'Kanały wydań projektu.');
        $this->theme->start_header($project['name'], 'Wybierz kanał wydań.', 'Build / ' . $project['name']); $this->theme->end_header();
        $this->theme->start_section(); $this->theme->render_breadcrumb($this->buildBreadcrumb($project['name'], $slug)); $this->theme->start_grid();
        foreach (self::CHANNELS as $channel => $label) {
            if (!isset($available[$channel])) { continue; }
            $this->theme->start_column('lg-6'); $this->theme->start_card($label, $available[$channel] . ' buildów');
            $this->theme->render_link_list([['label' => 'Pokaż wersje', 'href' => '/builds/' . rawurlencode($slug) . '/' . $channel, 'meta' => $label]]);
            $this->theme->end_card(); $this->theme->end_column();
        }
        $this->theme->end_grid(); $this->theme->end_section(); $this->theme->end_page();
    }

    private function renderPublicVersions(string $slug, string $channel): void
    {
        if (!isset(self::CHANNELS[$channel])) { $this->publicNotFound(); return; }
        $builds = array_values(array_filter($this->builds->all(true, $slug), static fn (ProjectBuild $build): bool => $build->channel === $channel));
        if ($builds === []) { $this->publicNotFound(); return; }
        $latest = [];
        foreach ($builds as $build) { $latest[$build->versionLabel . "\0" . strtolower($build->serverType)] ??= $build; }
        $this->theme->start_page($builds[0]->projectName . ' ' . self::CHANNELS[$channel], 'Dostępne wersje projektu.');
        $this->theme->start_header(self::CHANNELS[$channel] . ': ' . $builds[0]->projectName, 'Każdy wiersz wskazuje najnowszy build danej wersji.', 'Build / ' . $builds[0]->projectName . ' / ' . self::CHANNELS[$channel]); $this->theme->end_header();
        $rows = [];
        foreach ($latest as $build) {
            $rows[] = ['cells' => [$build->versionLabel, $build->serverType, $this->fileSize($build->fileSizeBytes), $build->publishedAt ?? 'Brak daty'], 'actions' => [
                ['label' => 'Pobierz najnowszy', 'href' => $this->downloadHref($build), 'variant' => 'primary'],
                ['label' => 'Historia buildów', 'href' => '/builds/' . rawurlencode($slug) . '/' . $channel . '/' . rawurlencode($build->versionLabel), 'variant' => 'outline-light'],
            ]];
        }
        $this->theme->start_section(); $this->theme->render_breadcrumb($this->buildBreadcrumb($builds[0]->projectName, $slug, $channel)); $this->theme->render_action_table(['Wersja', 'Platforma', 'Rozmiar', 'Data'], $rows); $this->theme->end_section(); $this->theme->end_page();
    }

    private function renderPublicHistory(string $slug, string $channel, string $version): void
    {
        $builds = array_values(array_filter($this->builds->all(true, $slug), static fn (ProjectBuild $build): bool => $build->channel === $channel && $build->versionLabel === $version));
        if ($builds === []) { $this->publicNotFound(); return; }
        $this->theme->start_page($builds[0]->projectName . ' ' . $version, 'Historia buildów i commitów.');
        $this->theme->start_header($builds[0]->projectName . ' ' . $version, 'Historia buildów ' . self::CHANNELS[$channel] . '.', 'Build / ' . $builds[0]->projectName . ' / ' . self::CHANNELS[$channel] . ' / Historia buildów ' . self::CHANNELS[$channel]); $this->theme->end_header();
        $this->theme->start_section();
        $this->theme->render_breadcrumb($this->buildBreadcrumb($builds[0]->projectName, $slug, $channel, 'Historia buildów ' . self::CHANNELS[$channel]));
        foreach ($builds as $build) {
            $this->theme->render_detail_card(
                'Build ' . ($build->buildNumber !== '' ? $build->buildNumber : $build->filename),
                $build->serverType,
                [
                    ['label' => 'Plik', 'value' => $build->filename],
                    ['label' => 'Czas CI', 'value' => $build->ciBuildTime ?? $build->publishedAt ?? 'Brak'],
                    ['label' => 'SHA-256', 'value' => $build->checksumSha256 ?: 'Brak'],
                    ['label' => 'Rozmiar', 'value' => $this->fileSize($build->fileSizeBytes)],
                ],
                $build->commits !== [] ? ['Commit', 'Czas', 'Wiadomość'] : [],
                array_map(static fn (array $commit): array => [substr($commit['sha'], 0, 12), $commit['time'], trim($commit['message'])], $build->commits),
                [['label' => 'Pobierz', 'href' => $this->downloadHref($build), 'variant' => 'primary']]
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
                $ids[] = $this->builds->upsertCi([
                    'project_id' => $project['id'], 'server_type' => ucfirst($server), 'version_label' => $version,
                    'channel' => $channel, 'build_number' => (string) $buildId, 'filename' => $filename,
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
        $buildNumber = $this->bounded((string) ($payload['build_number'] ?? $buildId), 80);
        $filename = basename(trim((string) ($payload['filename'] ?? '')));
        $sourceFilename = basename($artifact['name']);
        if ($server === '') { throw new \RuntimeException('CI artifact server/platform is required.'); }
        if ($version === '') { $version = $this->versionFromFilename($project['name'], $server, $filename !== '' ? $filename : $sourceFilename, $channel); }
        if ($version === '') { throw new \RuntimeException('CI artifact version is required.'); }
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

        $serverType = ucfirst($server);
        $previous = $this->builds->findCi((int) $project['id'], $channel, $serverType, $buildId);
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
        $this->theme->render_public_error(404, 'Nie znaleziono buildów', 'Wybrany projekt, kanał albo wersja nie ma publicznych buildów.', 'Wróć do Build Explorera', '/builds');
    }

    private function downloadHref(ProjectBuild $build): string
    {
        return $build->storageKey !== '' ? 'index.php?route=/builds/download&id=' . $build->id : $build->downloadUrl;
    }

    private function renderAdmin(string $message = '', string $variant = 'info'): void
    {
        $this->startPage('Build Explorer', 'Metadane plików i bezpieczne zewnętrzne adresy pobierania.', [[
            'label' => 'Dodaj build', 'href' => 'index.php?route=/admin/builds/create', 'variant' => 'primary',
        ], ['label' => 'Publiczna lista', 'href' => '/builds', 'variant' => 'outline-light']]);
        if ($message !== '') { $this->theme->render_alert($message, $variant); }
        $builds = $this->builds->all();
        $this->theme->start_admin_panel('Buildy projektów', count($builds) . ' plików');
        if ($builds === []) {
            $this->theme->render_alert('Nie dodano jeszcze żadnych buildów.', 'info');
        } else {
            $this->theme->render_admin_action_table(['Projekt', 'Wersja', 'Kanał', 'Plik', 'Publikacja'], array_map(
                static fn (ProjectBuild $build): array => [
                    'cells' => [$build->projectName, $build->versionLabel, self::CHANNELS[$build->channel], $build->filename, $build->published ? 'Publiczny' : 'Ukryty'],
                    'actions' => [[
                        'label' => 'Edytuj', 'href' => 'index.php?route=/admin/builds/edit&id=' . $build->id, 'variant' => 'primary',
                    ], [
                        'label' => 'Usuń', 'action' => 'index.php?route=/admin/builds/delete', 'fields' => ['id' => $build->id], 'variant' => 'danger', 'confirm' => 'Usunąć build z katalogu?',
                    ]],
                ], $builds
            ), $this->security->csrfToken());
        }
        $this->theme->end_admin_panel();
        $this->endPage();
    }

    private function renderEdit(Request $request): void
    {
        $build = $this->builds->find($request->queryInt('id', 0) ?? 0);
        $build instanceof ProjectBuild ? $this->renderForm($build) : $this->renderAdmin('Nie znaleziono buildu.', 'danger');
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
        if ($build === null && $request->postInt('id', 0)) { $this->renderAdmin('Nie znaleziono buildu.', 'danger'); return; }
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
        $this->renderAdmin('Build został zapisany.', 'success');
    }

    private function delete(Request $request): void
    {
        $actor = $this->auth->user();
        if (!$this->security->validateCsrfToken($request->postString('_token'))) {
            $this->audit->record($request, 'build_delete', 'invalid_csrf', 'builds', $actor?->id);
            $this->renderAdmin('Token CSRF jest nieprawidłowy lub wygasł.', 'danger'); return;
        }
        $id = $request->postInt('id', 0) ?? 0; $build = $this->builds->find($id); $ok = $this->builds->delete($id);
        if ($ok) { $this->storage->delete($build?->storageKey); }
        $this->audit->record($request, 'build_delete', $ok ? 'success' : 'failed', 'build:' . $id, $actor?->id);
        $this->renderAdmin($ok ? 'Build został usunięty.' : 'Nie udało się usunąć buildu.', $ok ? 'success' : 'danger');
    }

    private function download(Request $request): void
    {
        $build = $this->builds->findPublic($request->queryInt('id', 0) ?? 0);
        $path = $build instanceof ProjectBuild ? $this->storage->path($build->storageKey) : null;
        if (!$build instanceof ProjectBuild || $path === null) {
            http_response_code(404);
            $this->theme->render_public_error(404, 'Plik niedostępny', 'Nie znaleziono opublikowanego pliku JAR.', 'Wróć do plików', '/builds'); return;
        }
        header('Content-Type: application/java-archive');
        header('Content-Disposition: attachment; filename="' . $build->filename . '"');
        header('Content-Length: ' . (string) filesize($path));
        header('X-Content-Type-Options: nosniff');
        readfile($path);
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
