<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\BuildExplorer;

use SyntaxDevTeam\Cms\Core\DownloadResponse;
use SyntaxDevTeam\Cms\Core\Request;
use SyntaxDevTeam\Cms\Core\Router;
use SyntaxDevTeam\Cms\Core\ThemeInterface;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuditLogService;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuthService;

final class BuildExplorerV2Controller
{
    private const CHANNELS = [
        'release' => 'Release',
        'snapshot' => 'Snapshot',
        'dev' => 'Dev',
        'wip' => 'WIP',
    ];

    public function __construct(
        private readonly ThemeInterface $theme,
        private readonly BuildRepository $builds,
        private readonly AuthService $auth,
        private readonly AuditLogService $audit,
        private readonly BuildArtifactStorage $storage,
        private readonly string $ciToken,
    ) {}

    public function registerRoutes(Router $router): void
    {
        $router->get('/api/v2/builds/retention/status', fn (Request $request) => $this->retentionStatus($request));
        $router->post('/api/v2/builds/retention/cleanup', fn (Request $request) => $this->retentionCleanup($request));
        $router->post('/api/v2/builds/retention/{release}/lock', fn (Request $request) => $this->retentionLock(
            $request,
            (int) $request->routeString('release')
        ));
        $router->post('/api/v2/builds/ci/{project}', fn (Request $request) => $this->importCi(
            $request,
            $request->routeString('project')
        ));
        $router->get('/api/v2/builds/{project}/options', fn (Request $request) => $this->options(
            $request->routeString('project')
        ));
        $router->get('/api/v2/builds/{project}/resolve', fn (Request $request) => $this->resolve(
            $request,
            $request->routeString('project')
        ));
        $router->get('/builds/{project}/explore', fn (Request $request) => $this->renderExplore(
            $request->routeString('project')
        ));
        $router->get('/builds/v2/release', fn (Request $request) => $this->renderRelease(
            $request->queryString('project'),
            $request->queryInt('release', 0) ?? 0
        ));
        $router->get('/builds/{project}/download', fn (Request $request) => $this->renderDownloadChooser(
            $request,
            $request->routeString('project')
        ));
        $router->get('/builds/download/v2', fn (Request $request) => $this->downloadArtifact($request));
    }

    private function importCi(Request $request, string $projectSlug): void
    {
        if (!$this->authorized($request)) {
            $this->audit->record($request, 'build_v2_ci_import', 'denied', 'project:' . $projectSlug, null);
            $this->jsonResponse(401, ['error' => 'Invalid build token.']);
            return;
        }
        $project = $this->builds->projectBySlug($projectSlug);
        if ($project === null) {
            $this->jsonResponse(404, [
                'error' => 'BuildExplorer project slug does not exist.',
                'project' => $projectSlug,
            ]);
            return;
        }
        $payload = $this->payload($request);
        $artifact = $request->file('artifact');
        if ($payload === null || $artifact === null || $artifact['error'] === UPLOAD_ERR_NO_FILE) {
            $this->jsonResponse(400, [
                'error' => 'V2 requires multipart metadata JSON and one artifact file.',
            ]);
            return;
        }

        $stored = null;
        try {
            $ciBuildId = filter_var($payload['id'] ?? null, FILTER_VALIDATE_INT);
            $channel = strtolower(trim((string) ($payload['channel'] ?? '')));
            $platform = strtolower($this->bounded((string) ($payload['platform'] ?? $payload['server'] ?? ''), 80));
            $platformFamily = strtolower($this->bounded((string) ($payload['platform_family'] ?? 'server'), 20));
            $version = $this->bounded((string) ($payload['version'] ?? $payload['version_label'] ?? ''), 120);
            $buildNumber = $this->bounded((string) ($payload['build_number'] ?? ''), 80);
            $time = $this->parseTime((string) ($payload['time'] ?? ''));
            $filename = basename(trim((string) ($payload['filename'] ?? $artifact['name'] ?? '')));
            $branch = $this->bounded((string) ($payload['branch'] ?? ''), 255);
            $commits = $this->validateCommits($payload['commits'] ?? []);
            $commitSha = strtolower(trim((string) ($payload['commit_sha'] ?? ($commits[0]['sha'] ?? ''))));
            $javaVersionRaw = $payload['java_version'] ?? null;
            $javaVersion = $javaVersionRaw === null || $javaVersionRaw === ''
                ? null
                : filter_var($javaVersionRaw, FILTER_VALIDATE_INT);
            $gameVersions = $this->validateGameVersions($payload['game_versions'] ?? []);

            if ($ciBuildId === false || $ciBuildId < 1) {
                throw new \RuntimeException('CI build id is required.');
            }
            if (!isset(self::CHANNELS[$channel])) {
                throw new \RuntimeException('Channel must be release, snapshot, dev or wip.');
            }
            if ($platform === '' || preg_match('/^[a-z0-9._-]{1,80}$/', $platform) !== 1) {
                throw new \RuntimeException('Platform is invalid.');
            }
            $compatiblePlatforms = $this->validateCompatiblePlatforms(
                $payload['compatible_platforms'] ?? [],
                $platform
            );
            if (!in_array($platformFamily, ['server', 'proxy', 'other'], true)) {
                throw new \RuntimeException('Platform family must be server, proxy or other.');
            }
            if ($version === '') {
                throw new \RuntimeException('Version is required.');
            }
            if (in_array($channel, ['dev', 'wip'], true) && $buildNumber === '') {
                throw new \RuntimeException('DEV/WIP builds require build_number.');
            }
            if (preg_match('/^[A-Za-z0-9._-]{1,251}\.jar$/i', $filename) !== 1) {
                throw new \RuntimeException('Artifact filename must be a safe .jar basename.');
            }
            if ($commitSha !== '' && preg_match('/^[a-f0-9]{40}$/', $commitSha) !== 1) {
                throw new \RuntimeException('commit_sha must be a full 40-character Git SHA.');
            }
            if ($javaVersion !== null && ($javaVersion === false || $javaVersion < 8 || $javaVersion > 99)) {
                throw new \RuntimeException('java_version is invalid.');
            }

            (new BuildRetentionService($this->builds, $this->storage))->prepareForUpload((int) $artifact['size']);

            $stored = $this->storage->store($artifact);
            $expectedChecksum = strtolower(trim((string) ($payload['sha256'] ?? $payload['checksum_sha256'] ?? '')));
            if ($expectedChecksum !== '' && (
                preg_match('/^[a-f0-9]{64}$/', $expectedChecksum) !== 1
                || !hash_equals($expectedChecksum, $stored['checksum'])
            )) {
                throw new \RuntimeException('CI artifact SHA-256 does not match uploaded file.');
            }
            if (array_key_exists('size', $payload)) {
                $expectedSize = filter_var($payload['size'], FILTER_VALIDATE_INT);
                if ($expectedSize === false || (int) $expectedSize !== $stored['size']) {
                    throw new \RuntimeException('CI artifact size does not match uploaded file.');
                }
            }

            $utc = $time->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
            $releaseId = $this->builds->upsertV2Release([
                'project_id' => (int) $project['id'],
                'version_label' => $version,
                'channel' => $channel,
                'build_number' => $buildNumber,
                'changelog' => implode("\n", array_column($commits, 'message')),
                'is_published' => 1,
                'published_at' => $utc,
                'ci_build_id' => (int) $ciBuildId,
                'ci_build_time' => $utc,
                'commit_sha' => $commitSha !== '' ? $commitSha : null,
                'branch' => $branch !== '' ? $branch : null,
                'commits_json' => json_encode($commits, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            ]);

            $previous = $this->builds->v2ArtifactForPlatform($releaseId, $platform);
            $artifactId = $this->builds->upsertV2Artifact([
                'release_id' => $releaseId,
                'platform' => $platform,
                'platform_family' => $platformFamily,
                'filename' => $filename,
                'storage_key' => $stored['storage_key'],
                'download_url' => null,
                'checksum_sha256' => $stored['checksum'],
                'file_size_bytes' => $stored['size'],
                'java_version' => $javaVersion === null ? null : (int) $javaVersion,
            ]);
            $this->builds->replaceV2GameVersions($artifactId, $gameVersions);
            $this->builds->replaceV2CompatiblePlatforms($artifactId, $compatiblePlatforms);

            if ($previous !== null && $previous['storage_key'] !== '' && $previous['storage_key'] !== $stored['storage_key']) {
                $this->storage->delete($previous['storage_key']);
            }
            $this->audit->record(
                $request,
                'build_v2_ci_import',
                'success',
                'project:' . $projectSlug . ':build:' . $ciBuildId . ':platform:' . $platform,
                null
            );
            $this->jsonResponse(200, [
                'status' => 'ok',
                'api' => 'v2',
                'project' => $projectSlug,
                'release_id' => $releaseId,
                'artifact_id' => $artifactId,
                'platform' => $platform,
                'compatible_platforms' => $compatiblePlatforms,
                'game_versions' => $gameVersions,
            ]);
        } catch (BuildStorageCapacityException $exception) {
            if ($stored !== null) {
                $this->storage->delete($stored['storage_key']);
            }
            $this->audit->record($request, 'build_v2_ci_import', 'storage_full', 'project:' . $projectSlug, null);
            $this->jsonResponse(507, [
                'error' => $exception->getMessage(),
                'capacity' => $exception->status,
            ]);
        } catch (\Throwable $exception) {
            if ($stored !== null) {
                $this->storage->delete($stored['storage_key']);
            }
            $this->audit->record($request, 'build_v2_ci_import', 'failed', 'project:' . $projectSlug, null);
            $this->jsonResponse(422, ['error' => $exception->getMessage()]);
        }
    }

    private function retentionStatus(Request $request): void
    {
        if (!$this->authorized($request)) {
            $this->jsonResponse(401, ['error' => 'Invalid build token.']);
            return;
        }
        try {
            $this->jsonResponse(200, (new BuildRetentionService($this->builds, $this->storage))->status(true));
        } catch (\Throwable $exception) {
            $this->jsonResponse(500, ['error' => $exception->getMessage()]);
        }
    }

    private function retentionCleanup(Request $request): void
    {
        if (!$this->authorized($request)) {
            $this->audit->record($request, 'build_retention_cleanup', 'denied', 'retention', null);
            $this->jsonResponse(401, ['error' => 'Invalid build token.']);
            return;
        }
        try {
            $service = new BuildRetentionService($this->builds, $this->storage);
            $dryRun = in_array(strtolower($request->queryString('dry_run')), ['1', 'true', 'yes'], true);
            $result = $dryRun ? $service->preview() : $service->cleanup('manual');
            $this->audit->record($request, 'build_retention_cleanup', $dryRun ? 'preview' : 'success', 'retention', null);
            $this->jsonResponse(200, [
                'status' => 'ok',
                'dry_run' => $dryRun,
                'result' => $result,
                'storage' => $this->storage->statistics(),
            ]);
        } catch (\Throwable $exception) {
            $this->audit->record($request, 'build_retention_cleanup', 'failed', 'retention', null);
            $this->jsonResponse(500, ['error' => $exception->getMessage()]);
        }
    }

    private function retentionLock(Request $request, int $releaseId): void
    {
        if (!$this->authorized($request)) {
            $this->jsonResponse(401, ['error' => 'Invalid build token.']);
            return;
        }
        $payload = $request->json() ?? [];
        if ($releaseId < 1 || !array_key_exists('locked', $payload) || !is_bool($payload['locked'])) {
            $this->jsonResponse(400, ['error' => 'A valid release id and boolean locked field are required.']);
            return;
        }
        $locked = (bool) $payload['locked'];
        $ok = (new BuildRetentionService($this->builds, $this->storage))->setRetentionLock($releaseId, $locked);
        $this->audit->record(
            $request,
            'build_retention_lock',
            $ok ? 'success' : 'not_found',
            'release:' . $releaseId . ':locked:' . ($locked ? '1' : '0'),
            null
        );
        $this->jsonResponse($ok ? 200 : 404, $ok
            ? ['status' => 'ok', 'release_id' => $releaseId, 'locked' => $locked]
            : ['error' => 'Build release was not found or its lock state did not change.']);
    }

    private function options(string $projectSlug): void
    {
        try {
            $this->jsonResponse(200, [
                'project' => $projectSlug,
                ...$this->builds->v2Options($projectSlug, $this->auth->actualUser() !== null),
            ]);
        } catch (\Throwable $exception) {
            $this->jsonResponse(500, ['error' => $exception->getMessage()]);
        }
    }

    private function resolve(Request $request, string $projectSlug): void
    {
        $platform = strtolower($this->bounded($request->queryString('platform'), 80));
        $channel = strtolower($this->bounded($request->queryString('channel'), 20));
        $gameVersion = $this->bounded($request->queryString('game_version'), 40);
        if ($platform === '' || !isset(self::CHANNELS[$channel])) {
            $this->jsonResponse(400, ['error' => 'platform and a valid channel are required.']);
            return;
        }
        $artifact = $this->builds->resolveV2Artifact(
            $projectSlug,
            $platform,
            $channel,
            $gameVersion,
            $this->auth->actualUser() !== null
        );
        if ($artifact === null) {
            $this->jsonResponse(404, ['error' => 'No matching published artifact was found.']);
            return;
        }
        $this->jsonResponse(200, [
            'project' => $artifact['project_slug'],
            'version' => $artifact['version_label'],
            'channel' => $artifact['channel'],
            'platform' => $artifact['platform'],
            'artifact_id' => (int) $artifact['artifact_id'],
            'filename' => $artifact['filename'],
            'size' => $artifact['file_size_bytes'] !== null ? (int) $artifact['file_size_bytes'] : null,
            'sha256' => $artifact['checksum_sha256'],
            'java_version' => $artifact['java_version'] !== null ? (int) $artifact['java_version'] : null,
            'download' => 'index.php?' . http_build_query([
                'route' => '/builds/download/v2',
                'artifact' => (int) $artifact['artifact_id'],
            ]),
        ]);
    }

    private function renderExplore(string $projectSlug): void
    {
        $project = $this->builds->publicProjectBySlug($projectSlug);
        if ($project === null) {
            $this->theme->render_public_error(404, 'Project unavailable', 'No published Build Explorer project was found.', 'Back to downloads', '/builds');
            return;
        }
        if ($project['requires_auth'] && $this->auth->actualUser() === null) {
            http_response_code(401);
            $this->theme->render_public_error(401, 'Login required', 'This project is available only to signed-in users.', 'Sign in', 'index.php?route=/admin/login');
            return;
        }

        $authenticated = $this->auth->actualUser() !== null;
        $releases = $this->builds->v2ReleaseHistory($projectSlug, $authenticated);
        if ($releases === []) {
            header('Location: /builds/' . rawurlencode($projectSlug), true, 302);
            return;
        }

        $this->theme->start_page($project['name'] . ' - Build Explorer', 'Logical builds and platform artifacts.', false);
        $this->theme->start_header($project['name'], 'Build Explorer v2 history.', 'Build / Explore');
        $this->theme->end_header();
        $this->theme->start_section();
        $this->theme->render_breadcrumb([
            ['label' => 'Build', 'href' => '/builds'],
            ['label' => $project['name']],
            ['label' => 'Explore'],
        ]);

        $rows = [];
        foreach ($releases as $release) {
            $platforms = array_values(array_filter(explode(',', (string) ($release['platforms'] ?? ''))));
            $rows[] = [
                'cells' => [
                    (string) $release['version_label'],
                    self::CHANNELS[(string) $release['channel']] ?? ucfirst((string) $release['channel']),
                    $platforms !== [] ? implode(', ', array_map('ucfirst', $platforms)) : 'None',
                    (string) ($release['build_number'] ?: '—'),
                    (string) ($release['published_at'] ?? $release['ci_build_time'] ?? 'No date'),
                ],
                'actions' => [[
                    'label' => 'Explore build',
                    'href' => 'index.php?' . http_build_query([
                        'route' => '/builds/v2/release',
                        'project' => $projectSlug,
                        'release' => (int) $release['id'],
                    ]),
                    'variant' => 'primary',
                ]],
            ];
        }
        $this->theme->render_action_table(['Version', 'Channel', 'Platforms', 'Build', 'Date'], $rows);
        $this->theme->end_section();
        $this->theme->end_page();
    }

    private function renderRelease(string $projectSlug, int $releaseId): void
    {
        $project = $this->builds->publicProjectBySlug($projectSlug);
        if ($project === null || $releaseId < 1) {
            $this->theme->render_public_error(404, 'Build unavailable', 'No published Build Explorer build was found.', 'Back to Build Explorer', '/builds');
            return;
        }
        if ($project['requires_auth'] && $this->auth->actualUser() === null) {
            http_response_code(401);
            $this->theme->render_public_error(401, 'Login required', 'This project is available only to signed-in users.', 'Sign in', 'index.php?route=/admin/login');
            return;
        }

        $authenticated = $this->auth->actualUser() !== null;
        $release = null;
        foreach ($this->builds->v2ReleaseHistory($projectSlug, $authenticated) as $candidate) {
            if ((int) $candidate['id'] === $releaseId) {
                $release = $candidate;
                break;
            }
        }
        if ($release === null) {
            $this->theme->render_public_error(404, 'Build unavailable', 'No published Build Explorer build was found.', 'Explore project', '/builds/' . rawurlencode($projectSlug) . '/explore');
            return;
        }

        $artifacts = $this->builds->v2ReleaseArtifacts($releaseId, $projectSlug, $authenticated);
        $this->theme->start_page($project['name'] . ' ' . $release['version_label'], 'Build artifacts and CI metadata.', false);
        $this->theme->start_header(
            $project['name'] . ' ' . $release['version_label'],
            (self::CHANNELS[(string) $release['channel']] ?? ucfirst((string) $release['channel'])) . ' build',
            'Build / Explore'
        );
        $this->theme->end_header();
        $this->theme->start_section();
        $this->theme->render_breadcrumb([
            ['label' => 'Build', 'href' => '/builds'],
            ['label' => $project['name'], 'href' => '/builds/' . rawurlencode($projectSlug) . '/explore'],
            ['label' => (string) $release['version_label']],
        ]);

        $this->theme->render_detail_card(
            'Build ' . ((string) ($release['build_number'] ?: $release['id'])),
            self::CHANNELS[(string) $release['channel']] ?? ucfirst((string) $release['channel']),
            [
                ['label' => 'Version', 'value' => (string) $release['version_label']],
                ['label' => 'CI run', 'value' => (string) ($release['ci_build_id'] ?? '—')],
                ['label' => 'Branch', 'value' => (string) ($release['branch'] ?? '—')],
                ['label' => 'Commit', 'value' => $release['commit_sha'] ? substr((string) $release['commit_sha'], 0, 12) : '—'],
                ['label' => 'Published', 'value' => (string) ($release['published_at'] ?? $release['ci_build_time'] ?? 'No date')],
            ]
        );

        foreach ($artifacts as $artifact) {
            $versions = array_values(array_filter(explode(',', (string) ($artifact['game_versions'] ?? ''))));
            $this->theme->render_detail_card(
                ucfirst((string) $artifact['platform']),
                ucfirst((string) $artifact['platform_family']),
                [
                    ['label' => 'File', 'value' => (string) $artifact['filename']],
                    ['label' => 'Minecraft', 'value' => $versions !== [] ? implode(', ', $versions) : 'Not applicable'],
                    ['label' => 'Java', 'value' => $artifact['java_version'] !== null ? (string) $artifact['java_version'] : 'Not specified'],
                    ['label' => 'Size', 'value' => $this->fileSize($artifact['file_size_bytes'] !== null ? (int) $artifact['file_size_bytes'] : null)],
                    ['label' => 'SHA-256', 'value' => (string) ($artifact['checksum_sha256'] ?? '—')],
                ],
                [],
                [],
                [[
                    'label' => 'Download',
                    'href' => 'index.php?' . http_build_query([
                        'route' => '/builds/download/v2',
                        'artifact' => (int) $artifact['artifact_id'],
                    ]),
                    'variant' => 'primary',
                ]]
            );
        }

        $this->theme->end_section();
        $this->theme->end_page();
    }

    private function fileSize(?int $bytes): string
    {
        if ($bytes === null) { return 'Not specified'; }
        if ($bytes >= 1048576) { return number_format($bytes / 1048576, 2, '.', ' ') . ' MB'; }
        if ($bytes >= 1024) { return number_format($bytes / 1024, 1, '.', ' ') . ' KB'; }
        return $bytes . ' B';
    }

    private function renderDownloadChooser(Request $request, string $projectSlug): void
    {
        $project = $this->builds->publicProjectBySlug($projectSlug);
        if ($project === null) {
            $this->theme->render_public_error(404, 'Project unavailable', 'No published Build Explorer project was found.', 'Back to downloads', '/builds');
            return;
        }
        if ($project['requires_auth'] && $this->auth->actualUser() === null) {
            http_response_code(401);
            $this->theme->render_public_error(401, 'Login required', 'This project is available only to signed-in users.', 'Sign in', 'index.php?route=/admin/login');
            return;
        }
        $options = $this->builds->v2Options($projectSlug, $this->auth->actualUser() !== null);
        if ($options['platforms'] === []) {
            $this->theme->render_public_error(404, 'No v2 downloads', 'This project has no Build Explorer v2 artifacts yet.', 'Explore builds', '/builds/' . rawurlencode($projectSlug));
            return;
        }

        $platform = strtolower($this->bounded($request->queryString('platform'), 80));
        if ($platform === '' || !in_array($platform, $options['platforms'], true)) {
            $platform = $options['platforms'][0];
        }
        $channel = strtolower($this->bounded($request->queryString('channel'), 20));
        if ($channel === '' || !in_array($channel, $options['channels'], true)) {
            $channel = in_array('release', $options['channels'], true) ? 'release' : ($options['channels'][0] ?? 'release');
        }
        $gameVersion = $this->bounded($request->queryString('game_version'), 40);
        if ($gameVersion === '' && $options['game_versions'] !== []) {
            $gameVersion = $options['game_versions'][0];
        }

        $artifact = $this->builds->resolveV2Artifact(
            $projectSlug,
            $platform,
            $channel,
            $gameVersion,
            $this->auth->actualUser() !== null
        );

        $this->theme->start_page('Download ' . $project['name'], 'Choose the platform, Minecraft version and release channel.', false);
        $this->theme->start_header('Download ' . $project['name'], 'Build Explorer selects the newest compatible artifact.', 'Build / Download');
        $this->theme->end_header();
        $this->theme->start_section();
        $this->theme->render_breadcrumb([
            ['label' => 'Build', 'href' => '/builds'],
            ['label' => $project['name'], 'href' => '/builds/' . rawurlencode($projectSlug)],
            ['label' => 'Download'],
        ]);
        $fields = [
            ['name' => 'route', 'label' => 'Route', 'type' => 'hidden', 'value' => '/builds/' . $projectSlug . '/download'],
            ['name' => 'platform', 'label' => 'Server software', 'type' => 'select', 'value' => $platform, 'options' => $this->labels($options['platforms'])],
        ];
        if ($options['game_versions'] !== []) {
            $fields[] = ['name' => 'game_version', 'label' => 'Minecraft version', 'type' => 'select', 'value' => $gameVersion, 'options' => array_combine($options['game_versions'], $options['game_versions']) ?: []];
        }
        $channelOptions = [];
        foreach ($options['channels'] as $availableChannel) {
            $channelOptions[$availableChannel] = self::CHANNELS[$availableChannel] ?? ucfirst($availableChannel);
        }
        $fields[] = ['name' => 'channel', 'label' => 'Release channel', 'type' => 'select', 'value' => $channel, 'options' => $channelOptions];

        $this->theme->start_card('Select download', 'Build Explorer');
        $this->theme->render_form('index.php', $fields, 'Find build', '', 'get');
        $this->theme->end_card();

        if ($artifact === null) {
            $this->theme->render_alert('No build matches the selected platform, Minecraft version and release channel.', 'warning');
        } else {
            $facts = [
                ['label' => 'Platform', 'value' => ucfirst((string) $artifact['platform'])],
                ['label' => 'Plugin version', 'value' => (string) $artifact['version_label']],
                ['label' => 'Channel', 'value' => self::CHANNELS[(string) $artifact['channel']] ?? (string) $artifact['channel']],
                ['label' => 'File', 'value' => (string) $artifact['filename']],
                ['label' => 'SHA-256', 'value' => (string) $artifact['checksum_sha256']],
            ];
            if ($gameVersion !== '') {
                $facts[] = ['label' => 'Minecraft', 'value' => $gameVersion];
            }
            $this->theme->render_detail_card(
                (string) $project['name'] . ' ' . (string) $artifact['version_label'],
                'Selected artifact',
                $facts,
                [],
                [],
                [[
                    'label' => 'Download',
                    'href' => 'index.php?' . http_build_query([
                        'route' => '/builds/download/v2',
                        'artifact' => (int) $artifact['artifact_id'],
                    ]),
                    'variant' => 'primary',
                ]]
            );
        }
        $this->theme->end_section();
        $this->theme->end_page();
    }

    private function downloadArtifact(Request $request): void
    {
        $artifact = $this->builds->findV2ArtifactPublic($request->queryInt('artifact', 0) ?? 0);
        if ($artifact === null) {
            http_response_code(404);
            $this->theme->render_public_error(404, 'File unavailable', 'No published artifact was found.', 'Back to downloads', '/builds');
            return;
        }
        if ((bool) $artifact['requires_auth'] && $this->auth->actualUser() === null) {
            http_response_code(401);
            $this->theme->render_public_error(401, 'Login required', 'This artifact is available only to signed-in users.', 'Sign in', 'index.php?route=/admin/login');
            return;
        }
        $path = $this->storage->path((string) ($artifact['storage_key'] ?? ''));
        if ($path !== null) {
            DownloadResponse::send($path, (string) $artifact['filename'], 'application/java-archive', false);
            return;
        }
        $url = trim((string) ($artifact['download_url'] ?? ''));
        if (filter_var($url, FILTER_VALIDATE_URL) !== false && str_starts_with(strtolower($url), 'https://')) {
            header('Location: ' . $url, true, 302);
            return;
        }
        http_response_code(404);
        $this->theme->render_public_error(404, 'File unavailable', 'The artifact metadata exists but its file is unavailable.', 'Back to downloads', '/builds');
    }

    private function authorized(Request $request): bool
    {
        if (strlen($this->ciToken) < 32) {
            return false;
        }
        $provided = $request->header('X-Build-Token');
        if ($provided === '' && preg_match('/^Bearer\s+(.+)$/i', $request->header('Authorization'), $matches) === 1) {
            $provided = trim($matches[1]);
        }
        return $provided !== '' && hash_equals($this->ciToken, $provided);
    }

    /** @return array<string, mixed>|null */
    private function payload(Request $request): ?array
    {
        $payload = $request->json();
        if ($payload !== null) {
            return $payload;
        }
        $metadata = $request->postString('metadata');
        if ($metadata === '') {
            $metadataFile = $request->file('metadata');
            if ($metadataFile !== null && $metadataFile['error'] === UPLOAD_ERR_OK && $metadataFile['size'] > 0 && $metadataFile['size'] <= 1048576 && is_file($metadataFile['tmp_name'])) {
                $contents = file_get_contents($metadataFile['tmp_name']);
                $metadata = is_string($contents) ? $contents : '';
            }
        }
        if ($metadata === '' || strlen($metadata) > 1048576) {
            return null;
        }
        try {
            $decoded = json_decode($metadata, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
        return is_array($decoded) && !array_is_list($decoded) ? $decoded : null;
    }

    /** @return list<array{sha:string,time:string,message:string}> */
    private function validateCommits(mixed $value): array
    {
        if (!is_array($value) || count($value) > 100) {
            throw new \RuntimeException('Invalid commits list.');
        }
        $result = [];
        foreach ($value as $commit) {
            if (!is_array($commit)) {
                throw new \RuntimeException('Invalid commit entry.');
            }
            $sha = strtolower(trim((string) ($commit['sha'] ?? '')));
            $message = $this->bounded((string) ($commit['message'] ?? ''), 2000);
            $time = $this->parseTime((string) ($commit['time'] ?? ''));
            if (preg_match('/^[a-f0-9]{40}$/', $sha) !== 1 || $message === '') {
                throw new \RuntimeException('Invalid commit SHA or message.');
            }
            $result[] = [
                'sha' => $sha,
                'time' => $time->setTimezone(new \DateTimeZone('UTC'))->format(DATE_ATOM),
                'message' => $message,
            ];
        }
        return $result;
    }

    /** @return list<string> */
    private function validateCompatiblePlatforms(mixed $value, string $primaryPlatform): array
    {
        if (!is_array($value) || count($value) > 20) {
            throw new \RuntimeException('compatible_platforms must be an array with at most 20 entries.');
        }
        $platforms = [$primaryPlatform => true];
        foreach ($value as $raw) {
            $platform = strtolower($this->bounded((string) $raw, 80));
            if ($platform === '' || preg_match('/^[a-z0-9._-]{1,80}$/', $platform) !== 1) {
                throw new \RuntimeException('Invalid platform in compatible_platforms.');
            }
            $platforms[$platform] = true;
        }
        return array_keys($platforms);
    }

    /** @return list<string> */
    private function validateGameVersions(mixed $value): array
    {
        if (!is_array($value) || count($value) > 100) {
            throw new \RuntimeException('game_versions must be an array with at most 100 entries.');
        }
        $versions = [];
        foreach ($value as $raw) {
            $version = $this->bounded((string) $raw, 40);
            if ($version === '' || preg_match('/^[0-9A-Za-z._+-]{1,40}$/', $version) !== 1) {
                throw new \RuntimeException('Invalid Minecraft version in game_versions.');
            }
            $versions[$version] = true;
        }
        return array_keys($versions);
    }

    private function parseTime(string $value): \DateTimeImmutable
    {
        $value = trim($value);
        if ($value === '' || preg_match('/^\d{4}-\d{2}-\d{2}T/', $value) !== 1) {
            throw new \RuntimeException('CI timestamps must use ISO-8601.');
        }
        return new \DateTimeImmutable($value);
    }

    /** @param list<string> $values @return array<string, string> */
    private function labels(array $values): array
    {
        $result = [];
        foreach ($values as $value) {
            $result[$value] = ucfirst($value);
        }
        return $result;
    }

    private function bounded(string $value, int $max): string
    {
        return function_exists('mb_substr') ? mb_substr(trim($value), 0, $max) : substr(trim($value), 0, $max);
    }

    /** @param array<string, mixed> $payload */
    private function jsonResponse(int $status, array $payload): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
