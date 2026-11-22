<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\BuildExplorer;

use PDO;
use RuntimeException;
use SyntaxDevTeam\Cms\Database\CrudApp;

final class BuildRepository
{
    private const COLUMNS = 'project_builds.id, project_builds.project_id, projects.name AS project_name, '
        . 'projects.slug AS project_slug, projects.is_published AS project_published, '
        . 'COALESCE(build_explorer_project_settings.requires_auth, 0) AS project_requires_auth, '
        . 'project_builds.server_type, project_builds.version_label, project_builds.channel, project_builds.build_number, '
        . 'project_builds.filename, project_builds.storage_key, project_builds.download_url, '
        . 'project_builds.checksum_sha256, project_builds.file_size_bytes, '
        . 'project_builds.changelog, project_builds.is_published, project_builds.published_at, '
        . 'project_builds.ci_build_id, project_builds.ci_build_time, project_builds.commits_json';

    private const PROJECT_JOINS = ' JOIN projects ON projects.id = project_builds.project_id '
        . 'LEFT JOIN build_explorer_project_settings '
        . 'ON build_explorer_project_settings.project_id = projects.id';

    public function __construct(private readonly CrudApp $database) {}

    public function releaseNotes(int $id): ?array
    {
        $row = $this->database->query('SELECT id,version_label,channel,changelog FROM build_releases WHERE id=:id', [':id' => $id])->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) { return null; }
        $row['revision'] = hash('sha256', json_encode($row, JSON_THROW_ON_ERROR));
        return $row;
    }

    public function saveReleaseNotes(int $id, string $notes, string $revision): void
    {
        if (mb_strlen($notes) > 10000) { throw new RuntimeException('Opis zmian może mieć najwyżej 10000 znaków.'); }
        if (!$this->acquireRetentionLock(5)) { throw new RuntimeException('Trwa operacja magazynu. Spróbuj ponownie.'); }
        $pdo = $this->database->connection()->pdo;
        try {
            $pdo->beginTransaction();
            $suffix = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
            $this->database->query('SELECT id FROM build_releases WHERE id=:id' . $suffix, [':id' => $id])->fetchColumn();
            $row = $this->releaseNotes($id);
            if ($row === null || !hash_equals($row['revision'], $revision)) { throw new RuntimeException('Wydanie zostało zmienione. Otwórz formularz ponownie.'); }
            if ($row['channel'] === 'release' && trim($notes) === '') { throw new RuntimeException('Wydanie Release wymaga opisu zmian.'); }
            $this->database->update('build_releases', ['changelog' => $notes], ['id' => $id]);
            $pdo->commit();
        } catch (\Throwable $error) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            throw $error;
        } finally { $this->releaseRetentionLock(); }
    }

    public function publicApi(): PublicBuildRepository
    {
        return new PublicBuildRepository($this->database);
    }

    public function manualBuilds(): ManualBuildRepository
    {
        return new ManualBuildRepository($this->database);
    }

    /** @return list<ProjectBuild> */
    public function all(bool $publicOnly = false, ?string $projectSlug = null): array
    {
        $sql = 'SELECT ' . self::COLUMNS . ' FROM project_builds' . self::PROJECT_JOINS;
        $where = [];
        $parameters = [];
        if ($publicOnly) {
            $where[] = 'project_builds.is_published = 1 AND projects.is_published = 1';
        }
        if ($projectSlug !== null) {
            $where[] = 'projects.slug = :slug';
            $parameters[':slug'] = $projectSlug;
        }
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY projects.sort_order ASC, projects.name ASC, project_builds.published_at DESC, project_builds.id DESC';
        $statement = $this->database->query($sql, $parameters);
        if ($statement === null) {
            throw new RuntimeException('Nie można pobrać listy buildów.');
        }
        return array_map($this->hydrate(...), $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @param array{project_id: int, channel: string, published: string, search: string} $filters */
    public function adminCount(array $filters): int
    {
        [$where, $parameters] = $this->adminWhere($filters);
        $statement = $this->database->query(
            'SELECT COUNT(*) FROM project_builds JOIN projects ON projects.id = project_builds.project_id' . $where,
            $parameters
        );
        if ($statement === null) {
            throw new RuntimeException('Nie można policzyć buildów.');
        }
        return (int) $statement->fetchColumn();
    }

    /**
     * @param array{project_id: int, channel: string, published: string, search: string} $filters
     * @return list<ProjectBuild>
     */
    public function adminPage(int $page, int $perPage, array $filters, string $sort, string $direction): array
    {
        [$where, $parameters] = $this->adminWhere($filters);
        $orders = [
            'date' => 'COALESCE(project_builds.published_at, project_builds.created_at)',
            'project' => 'projects.name',
            'version' => 'project_builds.version_label',
            'size' => 'project_builds.file_size_bytes',
            'channel' => 'project_builds.channel',
            'filename' => 'project_builds.filename',
            'published' => 'project_builds.is_published',
        ];
        $order = $orders[$sort] ?? $orders['date'];
        $direction = strtolower($direction) === 'asc' ? 'ASC' : 'DESC';
        $limit = max(1, min(100, $perPage));
        $offset = max(0, ($page - 1) * $limit);
        $statement = $this->database->query(
            'SELECT ' . self::COLUMNS . ' FROM project_builds' . self::PROJECT_JOINS
            . $where . ' ORDER BY ' . $order . ' ' . $direction . ', project_builds.id ' . $direction
            . ' LIMIT ' . $limit . ' OFFSET ' . $offset,
            $parameters
        );
        if ($statement === null) {
            throw new RuntimeException('Nie można pobrać strony buildów.');
        }
        return array_map($this->hydrate(...), $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array{total:int,published:int,public_available:int,authenticated_available:int,local:int,registered_bytes:int,release:int,snapshot:int,dev:int,wip:int,legacy_builds:int,v2_releases:int,v2_artifacts:int} */
    public function statistics(): array
    {
        $legacyStatement = $this->database->query(
            "SELECT COUNT(*) AS total, "
            . "COALESCE(SUM(project_builds.is_published = 1), 0) AS published, "
            . "COALESCE(SUM(project_builds.is_published = 1 AND projects.is_published = 1 "
            . "AND COALESCE(build_explorer_project_settings.requires_auth, 0) = 0), 0) AS public_available, "
            . "COALESCE(SUM(project_builds.is_published = 1 AND projects.is_published = 1), 0) AS authenticated_available, "
            . "COALESCE(SUM(project_builds.storage_key IS NOT NULL AND project_builds.storage_key <> ''), 0) AS local, "
            . "COALESCE(SUM(CASE WHEN project_builds.storage_key IS NOT NULL AND project_builds.storage_key <> '' "
            . "THEN project_builds.file_size_bytes ELSE 0 END), 0) AS registered_bytes, "
            . "COALESCE(SUM(project_builds.channel = 'release'), 0) AS release_count, "
            . "COALESCE(SUM(project_builds.channel = 'snapshot'), 0) AS snapshot_count, "
            . "COALESCE(SUM(project_builds.channel = 'dev'), 0) AS dev_count, "
            . "COALESCE(SUM(project_builds.channel = 'wip'), 0) AS wip_count "
            . "FROM project_builds" . self::PROJECT_JOINS
        );

        $v2Statement = $this->database->query(
            "SELECT COUNT(DISTINCT build_releases.id) AS total, "
            . "COUNT(DISTINCT CASE WHEN build_releases.is_published = 1 THEN build_releases.id END) AS published, "
            . "COUNT(DISTINCT CASE WHEN build_releases.is_published = 1 AND projects.is_published = 1 "
            . "AND COALESCE(build_explorer_project_settings.requires_auth, 0) = 0 THEN build_releases.id END) AS public_available, "
            . "COUNT(DISTINCT CASE WHEN build_releases.is_published = 1 AND projects.is_published = 1 THEN build_releases.id END) AS authenticated_available, "
            . "COUNT(DISTINCT CASE WHEN build_artifacts.storage_key IS NOT NULL AND build_artifacts.storage_key <> '' THEN build_artifacts.id END) AS local, "
            . "COALESCE(SUM(CASE WHEN build_artifacts.storage_key IS NOT NULL AND build_artifacts.storage_key <> '' "
            . "THEN build_artifacts.file_size_bytes ELSE 0 END), 0) AS registered_bytes, "
            . "COUNT(DISTINCT CASE WHEN build_releases.channel = 'release' THEN build_releases.id END) AS release_count, "
            . "COUNT(DISTINCT CASE WHEN build_releases.channel = 'snapshot' THEN build_releases.id END) AS snapshot_count, "
            . "COUNT(DISTINCT CASE WHEN build_releases.channel = 'dev' THEN build_releases.id END) AS dev_count, "
            . "COUNT(DISTINCT CASE WHEN build_releases.channel = 'wip' THEN build_releases.id END) AS wip_count, "
            . "COUNT(DISTINCT build_artifacts.id) AS artifact_count "
            . "FROM build_releases JOIN projects ON projects.id = build_releases.project_id "
            . "LEFT JOIN build_explorer_project_settings ON build_explorer_project_settings.project_id = projects.id "
            . "LEFT JOIN build_artifacts ON build_artifacts.release_id = build_releases.id"
        );

        $legacy = $legacyStatement?->fetch(PDO::FETCH_ASSOC);
        $v2 = $v2Statement?->fetch(PDO::FETCH_ASSOC);
        if (!is_array($legacy) || !is_array($v2)) {
            throw new RuntimeException('Nie można pobrać statystyk buildów.');
        }

        return [
            'total' => (int) $legacy['total'] + (int) $v2['total'],
            'published' => (int) $legacy['published'] + (int) $v2['published'],
            'public_available' => (int) $legacy['public_available'] + (int) $v2['public_available'],
            'authenticated_available' => (int) $legacy['authenticated_available'] + (int) $v2['authenticated_available'],
            'local' => (int) $legacy['local'] + (int) $v2['local'],
            'registered_bytes' => (int) $legacy['registered_bytes'] + (int) $v2['registered_bytes'],
            'release' => (int) $legacy['release_count'] + (int) $v2['release_count'],
            'snapshot' => (int) $legacy['snapshot_count'] + (int) $v2['snapshot_count'],
            'dev' => (int) $legacy['dev_count'] + (int) $v2['dev_count'],
            'wip' => (int) $legacy['wip_count'] + (int) $v2['wip_count'],
            'legacy_builds' => (int) $legacy['total'],
            'v2_releases' => (int) $v2['total'],
            'v2_artifacts' => (int) $v2['artifact_count'],
        ];
    }

    public function find(int $id): ?ProjectBuild
    {
        $statement = $this->database->query(
            'SELECT ' . self::COLUMNS . ' FROM project_builds' . self::PROJECT_JOINS . ' '
            . 'WHERE project_builds.id = :id LIMIT 1',
            [':id' => $id]
        );
        $row = $statement?->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $this->hydrate($row) : null;
    }

    /** @return list<array<string, mixed>> */
    public function pluginV2Artifacts(): array
    {
        $statement = $this->database->query(
            $this->pluginV2ArtifactSelect()
            . ' GROUP BY build_artifacts.id, build_artifacts.release_id, build_artifacts.platform, '
            . 'build_artifacts.filename, build_artifacts.storage_key, build_artifacts.download_url, build_artifacts.file_size_bytes, build_artifacts.checksum_sha256, '
            . 'build_releases.version_label, build_releases.channel, build_releases.build_number, '
            . 'build_releases.is_published, build_releases.published_at, build_releases.ci_build_time, '
            . 'projects.name, projects.sort_order, projects.is_published, build_explorer_project_settings.requires_auth '
            . 'ORDER BY projects.sort_order ASC, projects.name ASC, '
            . 'COALESCE(build_releases.published_at, build_releases.ci_build_time) DESC, '
            . 'build_releases.id DESC, build_artifacts.id DESC'
        );
        if ($statement === null) {
            throw new RuntimeException('Nie można pobrać artefaktów pluginów Build Explorera v2.');
        }
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string, string> */
    public function projectIconUrls(): array
    {
        $statement = $this->database->query(
            "SELECT name, icon_url FROM projects WHERE icon_url <> '' ORDER BY sort_order, name"
        );
        $rows = $statement?->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($rows)) {
            return [];
        }
        $icons = [];
        foreach ($rows as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            $iconUrl = trim((string) ($row['icon_url'] ?? ''));
            if ($name !== '' && $iconUrl !== '') {
                $icons[$name] = $iconUrl;
            }
        }
        return $icons;
    }

    /** @return array<string, mixed>|null */
    public function findPluginV2Artifact(int $artifactId): ?array
    {
        $statement = $this->database->query(
            $this->pluginV2ArtifactSelect()
            . ' WHERE build_artifacts.id = :artifact_id '
            . 'GROUP BY build_artifacts.id, build_artifacts.release_id, build_artifacts.platform, '
            . 'build_artifacts.filename, build_artifacts.storage_key, build_artifacts.download_url, build_artifacts.file_size_bytes, build_artifacts.checksum_sha256, '
            . 'build_releases.version_label, build_releases.channel, build_releases.build_number, '
            . 'build_releases.is_published, build_releases.published_at, build_releases.ci_build_time, '
            . 'projects.name, projects.sort_order, projects.is_published, build_explorer_project_settings.requires_auth '
            . 'LIMIT 1',
            [':artifact_id' => $artifactId]
        );
        $row = $statement?->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function findPublic(int $id): ?ProjectBuild
    {
        $statement = $this->database->query(
            'SELECT ' . self::COLUMNS . ' FROM project_builds' . self::PROJECT_JOINS . ' '
            . 'WHERE project_builds.id = :id AND project_builds.is_published = 1 '
            . 'AND projects.is_published = 1 LIMIT 1',
            [':id' => $id]
        );
        $row = $statement?->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $this->hydrate($row) : null;
    }

    private function pluginV2ArtifactSelect(): string
    {
        return 'SELECT build_artifacts.id AS artifact_id, build_artifacts.release_id, '
            . 'build_artifacts.platform, build_artifacts.filename, build_artifacts.storage_key, build_artifacts.download_url, build_artifacts.file_size_bytes, '
            . 'build_artifacts.checksum_sha256, build_releases.version_label, build_releases.channel, '
            . 'build_releases.build_number, build_releases.is_published, projects.name AS project_name, '
            . 'projects.is_published AS project_published, '
            . 'COALESCE(build_explorer_project_settings.requires_auth, 0) AS project_requires_auth, '
            . "GROUP_CONCAT(DISTINCT build_artifact_compatible_platforms.platform "
            . "ORDER BY build_artifact_compatible_platforms.platform SEPARATOR ',') AS compatible_platforms "
            . 'FROM build_artifacts JOIN build_releases ON build_releases.id = build_artifacts.release_id '
            . 'JOIN projects ON projects.id = build_releases.project_id '
            . 'LEFT JOIN build_explorer_project_settings ON build_explorer_project_settings.project_id = projects.id '
            . 'LEFT JOIN build_artifact_compatible_platforms '
            . 'ON build_artifact_compatible_platforms.artifact_id = build_artifacts.id';
    }

    public function findCi(int $projectId, string $channel, string $serverType, int $ciBuildId): ?ProjectBuild
    {
        $statement = $this->database->query(
            'SELECT ' . self::COLUMNS . ' FROM project_builds' . self::PROJECT_JOINS . ' '
            . 'WHERE project_builds.project_id = :project_id AND project_builds.channel = :channel '
            . 'AND project_builds.server_type = :server_type AND project_builds.ci_build_id = :ci_build_id LIMIT 1',
            [
                ':project_id' => $projectId,
                ':channel' => $channel,
                ':server_type' => $serverType,
                ':ci_build_id' => $ciBuildId,
            ]
        );
        $row = $statement?->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function nextCiBuildNumber(int $projectId, string $channel, string $versionLabel): string
    {
        $statement = $this->database->query(
            'SELECT COALESCE(MAX(CAST(build_number AS UNSIGNED)), 0) + 1 '
            . 'FROM project_builds WHERE project_id = :project_id AND channel = :channel '
            . "AND version_label = :version_label AND build_number REGEXP '^[0-9]+$'",
            [
                ':project_id' => $projectId,
                ':channel' => $channel,
                ':version_label' => $versionLabel,
            ]
        );
        if ($statement === null) {
            throw new RuntimeException('Nie można wyznaczyć kolejnego numeru buildu CI.');
        }
        return (string) max(1, (int) $statement->fetchColumn());
    }

    /** @return array<int, string> */
    public function projectOptions(): array
    {
        $statement = $this->database->query('SELECT id, name FROM projects ORDER BY sort_order, name');
        if ($statement === null) {
            throw new RuntimeException('Nie można pobrać projektów.');
        }
        $result = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $result[(int) $row['id']] = (string) $row['name'];
        }
        return $result;
    }

    /** @return list<array{id: int, name: string, slug: string, build_count: int}> */
    public function publicProjects(bool $authenticated): array
    {
        $statement = $this->database->query(
            'SELECT projects.id, projects.name, projects.slug, '
            . '(COALESCE(legacy.build_count, 0) + COALESCE(v2.build_count, 0)) AS build_count '
            . 'FROM projects '
            . 'LEFT JOIN (SELECT project_id, COUNT(*) AS build_count FROM project_builds WHERE is_published = 1 GROUP BY project_id) legacy '
            . 'ON legacy.project_id = projects.id '
            . 'LEFT JOIN (SELECT project_id, COUNT(*) AS build_count FROM build_releases WHERE is_published = 1 GROUP BY project_id) v2 '
            . 'ON v2.project_id = projects.id '
            . 'LEFT JOIN build_explorer_project_settings ON build_explorer_project_settings.project_id = projects.id '
            . 'WHERE projects.is_published = 1 '
            . 'AND (COALESCE(build_explorer_project_settings.requires_auth, 0) = 0 OR :authenticated = 1) '
            . 'AND (COALESCE(legacy.build_count, 0) + COALESCE(v2.build_count, 0)) > 0 '
            . 'ORDER BY projects.sort_order, projects.name',
            [':authenticated' => $authenticated ? 1 : 0]
        );
        if ($statement === null) {
            throw new RuntimeException('Nie można pobrać projektów Build Explorera.');
        }
        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'slug' => (string) $row['slug'],
            'build_count' => (int) $row['build_count'],
        ], $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array{id: int, name: string, slug: string}|null */
    public function projectBySlug(string $slug): ?array
    {
        $statement = $this->database->query(
            'SELECT id, name, slug FROM projects WHERE slug = :slug LIMIT 1',
            [':slug' => $slug]
        );
        $row = $statement?->fetch(PDO::FETCH_ASSOC);
        return is_array($row)
            ? ['id' => (int) $row['id'], 'name' => (string) $row['name'], 'slug' => (string) $row['slug']]
            : null;
    }

    /** @return array{id: int, name: string, slug: string, requires_auth: bool}|null */
    public function publicProjectBySlug(string $slug): ?array
    {
        $statement = $this->database->query(
            'SELECT projects.id, projects.name, projects.slug, '
            . 'COALESCE(build_explorer_project_settings.requires_auth, 0) AS requires_auth '
            . 'FROM projects LEFT JOIN build_explorer_project_settings '
            . 'ON build_explorer_project_settings.project_id = projects.id '
            . 'WHERE projects.slug = :slug AND projects.is_published = 1 LIMIT 1',
            [':slug' => $slug]
        );
        $row = $statement?->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'slug' => (string) $row['slug'],
            'requires_auth' => (bool) $row['requires_auth'],
        ] : null;
    }

    /** @return list<array{id: int, name: string, slug: string, published: bool, requires_auth: bool, build_count: int}> */
    public function projectAccessSettings(): array
    {
        $statement = $this->database->query(
            'SELECT projects.id, projects.name, projects.slug, projects.is_published, '
            . 'COALESCE(build_explorer_project_settings.requires_auth, 0) AS requires_auth, '
            . '(COALESCE(legacy.build_count, 0) + COALESCE(v2.build_count, 0)) AS build_count '
            . 'FROM projects '
            . 'LEFT JOIN build_explorer_project_settings ON build_explorer_project_settings.project_id = projects.id '
            . 'LEFT JOIN (SELECT project_id, COUNT(*) AS build_count FROM project_builds GROUP BY project_id) legacy ON legacy.project_id = projects.id '
            . 'LEFT JOIN (SELECT project_id, COUNT(*) AS build_count FROM build_releases GROUP BY project_id) v2 ON v2.project_id = projects.id '
            . 'ORDER BY projects.sort_order, projects.name'
        );
        if ($statement === null) {
            throw new RuntimeException('Nie można pobrać widoczności projektów Build Explorera.');
        }
        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'slug' => (string) $row['slug'],
            'published' => (bool) $row['is_published'],
            'requires_auth' => (bool) $row['requires_auth'],
            'build_count' => (int) $row['build_count'],
        ], $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function setProjectRequiresAuth(int $projectId, bool $requiresAuth): bool
    {
        $statement = $this->database->query(
            'INSERT INTO build_explorer_project_settings (project_id, requires_auth) '
            . 'VALUES (:project_id, :requires_auth) '
            . 'ON DUPLICATE KEY UPDATE requires_auth = VALUES(requires_auth), updated_at = CURRENT_TIMESTAMP',
            [':project_id' => $projectId, ':requires_auth' => $requiresAuth ? 1 : 0]
        );
        return $statement !== null;
    }

    /** @return list<string> */
    public function projectSlugs(): array
    {
        $statement = $this->database->query('SELECT slug FROM projects ORDER BY id');
        return $statement === null ? [] : array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @param array<string, int|string|null> $data */
    public function upsertCi(array $data): int
    {
        $statement = $this->database->query(
            'SELECT id FROM project_builds WHERE project_id = :project_id AND channel = :channel '
            . 'AND server_type = :server_type AND ci_build_id = :ci_build_id LIMIT 1',
            [
                ':project_id' => $data['project_id'],
                ':channel' => $data['channel'],
                ':server_type' => $data['server_type'],
                ':ci_build_id' => $data['ci_build_id'],
            ]
        );
        $id = (int) ($statement?->fetchColumn() ?: 0);
        if ($id > 0) {
            $this->update($id, $data);
            return $id;
        }
        return $this->create($data);
    }

    /** @param array<string, int|string|null> $data */
    public function create(array $data): int
    {
        return (int) $this->database->create('project_builds', $data);
    }

    /** @param array<string, int|string|null> $data */
    public function update(int $id, array $data): bool
    {
        return $this->database->update('project_builds', $data, ['id' => $id]) !== null;
    }

    public function delete(int $id): bool
    {
        $statement = $this->database->delete('project_builds', ['id' => $id]);
        return $statement !== null && $statement->rowCount() === 1;
    }

    /**
     * @param array{project_id:int,version_label:string,channel:string,build_number:string,changelog:string,is_published:int,published_at:string,ci_build_id:int,ci_build_time:string,commit_sha:?string,branch:?string,commits_json:string} $data
     */
    public function upsertV2Release(array $data): int
    {
        $statement = $this->database->query(
            'INSERT INTO build_releases '
            . '(project_id, version_label, channel, build_number, changelog, is_published, published_at, ci_build_id, ci_build_time, commit_sha, branch, commits_json) '
            . 'VALUES (:project_id, :version_label, :channel, :build_number, :changelog, :is_published, :published_at, :ci_build_id, :ci_build_time, :commit_sha, :branch, :commits_json) '
            . 'ON DUPLICATE KEY UPDATE version_label = VALUES(version_label), build_number = VALUES(build_number), '
            . 'changelog = VALUES(changelog), is_published = VALUES(is_published), published_at = VALUES(published_at), '
            . 'ci_build_time = VALUES(ci_build_time), commit_sha = VALUES(commit_sha), branch = VALUES(branch), commits_json = VALUES(commits_json)',
            [
                ':project_id' => $data['project_id'],
                ':version_label' => $data['version_label'],
                ':channel' => $data['channel'],
                ':build_number' => $data['build_number'],
                ':changelog' => $data['changelog'],
                ':is_published' => $data['is_published'],
                ':published_at' => $data['published_at'],
                ':ci_build_id' => $data['ci_build_id'],
                ':ci_build_time' => $data['ci_build_time'],
                ':commit_sha' => $data['commit_sha'],
                ':branch' => $data['branch'],
                ':commits_json' => $data['commits_json'],
            ]
        );
        if ($statement === null) {
            throw new RuntimeException('Nie można zapisać logicznego buildu v2.');
        }
        $lookup = $this->database->query(
            'SELECT id FROM build_releases '
            . 'WHERE project_id = :project_id AND channel = :channel AND ci_build_id = :ci_build_id LIMIT 1',
            [
                ':project_id' => $data['project_id'],
                ':channel' => $data['channel'],
                ':ci_build_id' => $data['ci_build_id'],
            ]
        );
        $id = (int) ($lookup?->fetchColumn() ?: 0);
        if ($id < 1) {
            throw new RuntimeException('Nie można odczytać logicznego buildu v2 po zapisie.');
        }
        return $id;
    }

    /** @return array{id:int,storage_key:string}|null */
    public function v2ArtifactForPlatform(int $releaseId, string $platform): ?array
    {
        $statement = $this->database->query(
            'SELECT id, storage_key FROM build_artifacts '
            . 'WHERE release_id = :release_id AND platform = :platform LIMIT 1',
            [':release_id' => $releaseId, ':platform' => $platform]
        );
        $row = $statement?->fetch(PDO::FETCH_ASSOC);
        return is_array($row)
            ? ['id' => (int) $row['id'], 'storage_key' => (string) ($row['storage_key'] ?? '')]
            : null;
    }

    /**
     * @param array{release_id:int,platform:string,platform_family:string,filename:string,storage_key:?string,download_url:?string,checksum_sha256:string,file_size_bytes:int,java_version:?int} $data
     */
    public function upsertV2Artifact(array $data): int
    {
        $statement = $this->database->query(
            'INSERT INTO build_artifacts '
            . '(release_id, platform, platform_family, filename, storage_key, download_url, checksum_sha256, file_size_bytes, java_version) '
            . 'VALUES (:release_id, :platform, :platform_family, :filename, :storage_key, :download_url, :checksum_sha256, :file_size_bytes, :java_version) '
            . 'ON DUPLICATE KEY UPDATE platform_family = VALUES(platform_family), filename = VALUES(filename), '
            . 'storage_key = VALUES(storage_key), download_url = VALUES(download_url), '
            . 'checksum_sha256 = VALUES(checksum_sha256), file_size_bytes = VALUES(file_size_bytes), java_version = VALUES(java_version)',
            [
                ':release_id' => $data['release_id'],
                ':platform' => $data['platform'],
                ':platform_family' => $data['platform_family'],
                ':filename' => $data['filename'],
                ':storage_key' => $data['storage_key'],
                ':download_url' => $data['download_url'],
                ':checksum_sha256' => $data['checksum_sha256'],
                ':file_size_bytes' => $data['file_size_bytes'],
                ':java_version' => $data['java_version'],
            ]
        );
        if ($statement === null) {
            throw new RuntimeException('Nie można zapisać artefaktu buildu v2.');
        }
        $lookup = $this->database->query(
            'SELECT id FROM build_artifacts WHERE release_id = :release_id AND platform = :platform LIMIT 1',
            [':release_id' => $data['release_id'], ':platform' => $data['platform']]
        );
        $id = (int) ($lookup?->fetchColumn() ?: 0);
        if ($id < 1) {
            throw new RuntimeException('Nie można odczytać artefaktu buildu v2 po zapisie.');
        }
        return $id;
    }

    /** @param list<string> $versions */
    public function replaceV2GameVersions(int $artifactId, array $versions): void
    {
        $delete = $this->database->query(
            'DELETE FROM build_artifact_game_versions WHERE artifact_id = :artifact_id',
            [':artifact_id' => $artifactId]
        );
        if ($delete === null) {
            throw new RuntimeException('Nie można wyczyścić wersji gry artefaktu v2.');
        }
        foreach ($versions as $version) {
            $insert = $this->database->query(
                'INSERT INTO build_artifact_game_versions (artifact_id, game_version) '
                . 'VALUES (:artifact_id, :game_version)',
                [':artifact_id' => $artifactId, ':game_version' => $version]
            );
            if ($insert === null) {
                throw new RuntimeException('Nie można przypisać wersji gry do artefaktu v2.');
            }
        }
    }

    /** @param list<string> $platforms */
    public function replaceV2CompatiblePlatforms(int $artifactId, array $platforms): void
    {
        $delete = $this->database->query(
            'DELETE FROM build_artifact_compatible_platforms WHERE artifact_id = :artifact_id',
            [':artifact_id' => $artifactId]
        );
        if ($delete === null) {
            throw new RuntimeException('Nie można wyczyścić kompatybilnych platform artefaktu v2.');
        }
        foreach ($platforms as $platform) {
            $insert = $this->database->query(
                'INSERT INTO build_artifact_compatible_platforms (artifact_id, platform) '
                . 'VALUES (:artifact_id, :platform)',
                [':artifact_id' => $artifactId, ':platform' => $platform]
            );
            if ($insert === null) {
                throw new RuntimeException('Nie można przypisać kompatybilnej platformy do artefaktu v2.');
            }
        }
    }

    /** @return array{platforms:list<string>,game_versions:list<string>,channels:list<string>} */
    public function v2Options(string $slug, bool $authenticated): array
    {
        $parameters = [':slug' => $slug, ':authenticated' => $authenticated ? 1 : 0];
        $from = ' FROM build_releases JOIN projects ON projects.id = build_releases.project_id '
            . 'JOIN build_artifacts ON build_artifacts.release_id = build_releases.id '
            . 'LEFT JOIN build_explorer_project_settings ON build_explorer_project_settings.project_id = projects.id ';
        $where = 'WHERE projects.slug = :slug AND projects.is_published = 1 AND build_releases.is_published = 1 '
            . 'AND (COALESCE(build_explorer_project_settings.requires_auth, 0) = 0 OR :authenticated = 1)';

        $platformStmt = $this->database->query(
            'SELECT DISTINCT build_artifact_compatible_platforms.platform' . $from
            . 'JOIN build_artifact_compatible_platforms ON build_artifact_compatible_platforms.artifact_id = build_artifacts.id '
            . $where . ' ORDER BY build_artifact_compatible_platforms.platform',
            $parameters
        );
        $channelStmt = $this->database->query(
            "SELECT DISTINCT build_releases.channel" . $from . $where
            . " ORDER BY FIELD(build_releases.channel, 'release', 'snapshot', 'dev', 'wip')",
            $parameters
        );
        $versionStmt = $this->database->query(
            'SELECT DISTINCT build_artifact_game_versions.game_version' . $from
            . 'JOIN build_artifact_game_versions ON build_artifact_game_versions.artifact_id = build_artifacts.id '
            . $where . ' ORDER BY build_artifact_game_versions.game_version DESC',
            $parameters
        );
        if ($platformStmt === null || $channelStmt === null || $versionStmt === null) {
            throw new RuntimeException('Nie można pobrać opcji Build Explorer v2.');
        }
        return [
            'platforms' => array_values(array_map('strval', $platformStmt->fetchAll(PDO::FETCH_COLUMN))),
            'game_versions' => array_values(array_map('strval', $versionStmt->fetchAll(PDO::FETCH_COLUMN))),
            'channels' => array_values(array_map('strval', $channelStmt->fetchAll(PDO::FETCH_COLUMN))),
        ];
    }

    /** @return array<string, mixed>|null */
    public function resolveV2Artifact(
        string $slug,
        string $platform,
        string $channel,
        string $gameVersion,
        bool $authenticated
    ): ?array {
        $sql = 'SELECT build_artifacts.id AS artifact_id, build_artifacts.release_id, build_artifacts.platform, '
            . 'build_artifacts.platform_family, build_artifacts.filename, build_artifacts.storage_key, build_artifacts.download_url, '
            . 'build_artifacts.checksum_sha256, build_artifacts.file_size_bytes, build_artifacts.java_version, '
            . 'build_releases.version_label, build_releases.channel, build_releases.build_number, build_releases.published_at, '
            . 'projects.name AS project_name, projects.slug AS project_slug, '
            . 'COALESCE(build_explorer_project_settings.requires_auth, 0) AS requires_auth '
            . 'FROM build_artifacts JOIN build_releases ON build_releases.id = build_artifacts.release_id '
            . 'JOIN projects ON projects.id = build_releases.project_id '
            . 'LEFT JOIN build_explorer_project_settings ON build_explorer_project_settings.project_id = projects.id '
            . 'WHERE projects.slug = :slug AND projects.is_published = 1 AND build_releases.is_published = 1 '
            . 'AND EXISTS (SELECT 1 FROM build_artifact_compatible_platforms cp '
            . 'WHERE cp.artifact_id = build_artifacts.id AND cp.platform = :platform) '
            . 'AND build_releases.channel = :channel '
            . 'AND (COALESCE(build_explorer_project_settings.requires_auth, 0) = 0 OR :authenticated = 1)';
        $parameters = [
            ':slug' => $slug,
            ':platform' => $platform,
            ':channel' => $channel,
            ':authenticated' => $authenticated ? 1 : 0,
        ];
        if ($gameVersion !== '') {
            $sql .= ' AND EXISTS (SELECT 1 FROM build_artifact_game_versions gv '
                . 'WHERE gv.artifact_id = build_artifacts.id AND gv.game_version = :game_version)';
            $parameters[':game_version'] = $gameVersion;
        }
        $sql .= ' ORDER BY COALESCE(build_releases.published_at, build_releases.ci_build_time) DESC, '
            . 'build_releases.id DESC LIMIT 1';
        $statement = $this->database->query($sql, $parameters);
        $row = $statement?->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed>|null */
    public function findV2ArtifactPublic(int $artifactId): ?array
    {
        $statement = $this->database->query(
            'SELECT build_artifacts.id AS artifact_id, build_artifacts.filename, build_artifacts.storage_key, '
            . 'build_artifacts.download_url, projects.slug AS project_slug, '
            . 'COALESCE(build_explorer_project_settings.requires_auth, 0) AS requires_auth '
            . 'FROM build_artifacts JOIN build_releases ON build_releases.id = build_artifacts.release_id '
            . 'JOIN projects ON projects.id = build_releases.project_id '
            . 'LEFT JOIN build_explorer_project_settings ON build_explorer_project_settings.project_id = projects.id '
            . 'WHERE build_artifacts.id = :id AND build_releases.is_published = 1 '
            . 'AND projects.is_published = 1 LIMIT 1',
            [':id' => $artifactId]
        );
        $row = $statement?->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /** @return list<array<string, mixed>> */
    public function v2ReleaseHistory(string $slug, bool $authenticated): array
    {
        $statement = $this->database->query(
            "SELECT build_releases.id, build_releases.version_label, build_releases.channel, build_releases.build_number, "
            . "build_releases.published_at, build_releases.ci_build_id, build_releases.ci_build_time, "
            . "build_releases.commit_sha, build_releases.branch, build_releases.changelog, build_releases.commits_json, "
            . "GROUP_CONCAT(DISTINCT build_artifacts.platform ORDER BY build_artifacts.platform SEPARATOR ',') AS platforms "
            . "FROM build_releases JOIN projects ON projects.id = build_releases.project_id "
            . "JOIN build_artifacts ON build_artifacts.release_id = build_releases.id "
            . "LEFT JOIN build_explorer_project_settings ON build_explorer_project_settings.project_id = projects.id "
            . "WHERE projects.slug = :slug AND projects.is_published = 1 AND build_releases.is_published = 1 "
            . "AND (COALESCE(build_explorer_project_settings.requires_auth, 0) = 0 OR :authenticated = 1) "
            . "GROUP BY build_releases.id ORDER BY COALESCE(build_releases.published_at, build_releases.ci_build_time) DESC, "
            . "build_releases.id DESC",
            [':slug' => $slug, ':authenticated' => $authenticated ? 1 : 0]
        );
        if ($statement === null) {
            throw new RuntimeException('Nie można pobrać historii Build Explorer v2.');
        }
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<array<string, mixed>> */
    public function v2ReleaseArtifacts(int $releaseId, string $slug, bool $authenticated): array
    {
        $statement = $this->database->query(
            "SELECT build_artifacts.id AS artifact_id, build_artifacts.platform, build_artifacts.platform_family, "
            . "build_artifacts.filename, build_artifacts.checksum_sha256, build_artifacts.file_size_bytes, build_artifacts.java_version, "
            . "(SELECT GROUP_CONCAT(cp.platform ORDER BY cp.platform SEPARATOR ',') "
            . "FROM build_artifact_compatible_platforms cp WHERE cp.artifact_id = build_artifacts.id) AS compatible_platforms, "
            . "GROUP_CONCAT(build_artifact_game_versions.game_version ORDER BY build_artifact_game_versions.game_version DESC SEPARATOR ',') AS game_versions "
            . "FROM build_artifacts JOIN build_releases ON build_releases.id = build_artifacts.release_id "
            . "JOIN projects ON projects.id = build_releases.project_id "
            . "LEFT JOIN build_explorer_project_settings ON build_explorer_project_settings.project_id = projects.id "
            . "LEFT JOIN build_artifact_game_versions ON build_artifact_game_versions.artifact_id = build_artifacts.id "
            . "WHERE build_releases.id = :release_id AND projects.slug = :slug AND projects.is_published = 1 "
            . "AND build_releases.is_published = 1 "
            . "AND (COALESCE(build_explorer_project_settings.requires_auth, 0) = 0 OR :authenticated = 1) "
            . "GROUP BY build_artifacts.id ORDER BY build_artifacts.platform",
            [
                ':release_id' => $releaseId,
                ':slug' => $slug,
                ':authenticated' => $authenticated ? 1 : 0,
            ]
        );
        if ($statement === null) {
            throw new RuntimeException('Nie można pobrać artefaktów buildu v2.');
        }
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string, int|string|null> */
    public function retentionSettings(): array
    {
        $statement = $this->database->query(
            'SELECT * FROM build_retention_settings WHERE id = 1 LIMIT 1'
        );
        $row = $statement?->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new RuntimeException('Build retention settings are unavailable; run the Build Explorer 2.1 migration.');
        }
        return [
            'release_keep' => (int) ($row['release_keep'] ?? 0),
            'snapshot_keep' => (int) $row['snapshot_keep'],
            'dev_keep' => (int) $row['dev_keep'],
            'wip_keep' => (int) $row['wip_keep'],
            'soft_limit_bytes' => (int) $row['soft_limit_bytes'],
            'hard_limit_bytes' => (int) $row['hard_limit_bytes'],
            'reserve_bytes' => (int) $row['reserve_bytes'],
            'cleanup_interval_hours' => (int) $row['cleanup_interval_hours'],
            'orphan_min_age_hours' => (int) $row['orphan_min_age_hours'],
            'last_cleanup_at' => $row['last_cleanup_at'] !== null ? (string) $row['last_cleanup_at'] : null,
            'last_cleanup_reason' => $row['last_cleanup_reason'] !== null ? (string) $row['last_cleanup_reason'] : null,
            'last_cleanup_releases' => (int) $row['last_cleanup_releases'],
            'last_cleanup_artifacts' => (int) $row['last_cleanup_artifacts'],
            'last_cleanup_bytes' => (int) $row['last_cleanup_bytes'],
            'last_cleanup_orphans' => (int) $row['last_cleanup_orphans'],
        ];
    }

    /** @param array{release_keep:int,snapshot_keep:int,dev_keep:int,wip_keep:int} $policy */
    public function saveRetentionPolicy(array $policy): void
    {
        $parameters = [];
        foreach (['release_keep', 'snapshot_keep', 'dev_keep', 'wip_keep'] as $field) {
            $value = $policy[$field] ?? null;
            if (!is_int($value) || $value < 0 || $value > 100000) {
                throw new RuntimeException('Limity retencji muszą być liczbami całkowitymi od 0 do 100000.');
            }
            $parameters[':' . $field] = $value;
        }
        if (!$this->acquireRetentionLock(5)) {
            throw new RuntimeException('Trwa czyszczenie magazynu. Spróbuj zapisać ustawienia później.');
        }
        try {
            $this->retentionSettings();
            $statement = $this->database->query(
                'UPDATE build_retention_settings SET release_keep = :release_keep, snapshot_keep = :snapshot_keep, '
                . 'dev_keep = :dev_keep, wip_keep = :wip_keep WHERE id = 1',
                $parameters
            );
            if ($statement === null) {
                throw new RuntimeException('Nie można zapisać limitów retencji.');
            }
        } finally {
            $this->releaseRetentionLock();
        }
    }

    public function acquireRetentionLock(int $timeoutSeconds = 0): bool
    {
        $statement = $this->database->query(
            'SELECT GET_LOCK(:name, :timeout)',
            [':name' => 'build_explorer_retention', ':timeout' => max(0, $timeoutSeconds)]
        );
        return $statement !== null && (int) $statement->fetchColumn() === 1;
    }

    public function releaseRetentionLock(): void
    {
        $this->database->query('SELECT RELEASE_LOCK(:name)', [':name' => 'build_explorer_retention']);
    }

    /** @return list<array<string, int|string|null>> */
    public function retentionCandidates(int $snapshotKeep, int $devKeep, int $wipKeep, int $releaseKeep = 0): array
    {
        $statement = $this->database->query(
            "SELECT ranked.id, ranked.project_id, ranked.project_name, ranked.version_label, ranked.channel, "
            . "ranked.build_number, ranked.release_time, ranked.retention_rank, "
            . "(SELECT COUNT(*) FROM build_artifacts candidate_artifacts WHERE candidate_artifacts.release_id = ranked.id) AS artifact_count, "
            . "(SELECT COALESCE(SUM(CASE WHEN candidate_artifacts.storage_key IS NOT NULL AND candidate_artifacts.storage_key <> '' "
            . "THEN candidate_artifacts.file_size_bytes ELSE 0 END), 0) FROM build_artifacts candidate_artifacts "
            . "WHERE candidate_artifacts.release_id = ranked.id) AS file_size_bytes FROM ("
            . "SELECT build_releases.id, build_releases.project_id, projects.name AS project_name, "
            . "build_releases.version_label, build_releases.channel, build_releases.build_number, "
            . "COALESCE(build_releases.published_at, build_releases.ci_build_time, build_releases.created_at) AS release_time, "
            . "ROW_NUMBER() OVER (PARTITION BY build_releases.project_id, CASE WHEN build_releases.channel = 'release' THEN '' ELSE build_releases.version_label END, build_releases.channel "
            . "ORDER BY COALESCE(build_releases.published_at, build_releases.ci_build_time, build_releases.created_at) DESC, build_releases.id DESC) AS retention_rank "
            . "FROM build_releases JOIN projects ON projects.id = build_releases.project_id "
            . "WHERE build_releases.retention_lock = 0 AND build_releases.channel IN ('release','snapshot','dev','wip')"
            . ") ranked WHERE (ranked.channel = 'snapshot' AND :snapshot_enabled > 0 AND ranked.retention_rank > :snapshot_keep) "
            . "OR (ranked.channel = 'dev' AND :dev_enabled > 0 AND ranked.retention_rank > :dev_keep) "
            . "OR (ranked.channel = 'wip' AND :wip_enabled > 0 AND ranked.retention_rank > :wip_keep) "
            . "OR (ranked.channel = 'release' AND :release_enabled > 0 AND ranked.retention_rank > :release_keep) "
            . "ORDER BY CASE ranked.channel WHEN 'wip' THEN 1 WHEN 'dev' THEN 2 ELSE 3 END, ranked.release_time, ranked.id",
            [
                ':release_enabled' => max(0, $releaseKeep),
                ':snapshot_enabled' => max(0, $snapshotKeep),
                ':dev_enabled' => max(0, $devKeep),
                ':wip_enabled' => max(0, $wipKeep),
                ':release_keep' => max(1, $releaseKeep),
                ':snapshot_keep' => max(1, $snapshotKeep),
                ':dev_keep' => max(1, $devKeep),
                ':wip_keep' => max(1, $wipKeep),
            ]
        );
        if ($statement === null) {
            throw new RuntimeException('Nie można wyznaczyć kandydatów retencji Build Explorera.');
        }
        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'project_id' => (int) $row['project_id'],
            'project_name' => (string) $row['project_name'],
            'version_label' => (string) $row['version_label'],
            'channel' => (string) $row['channel'],
            'build_number' => (string) $row['build_number'],
            'release_time' => $row['release_time'] !== null ? (string) $row['release_time'] : null,
            'retention_rank' => (int) $row['retention_rank'],
            'artifact_count' => (int) $row['artifact_count'],
            'file_size_bytes' => (int) $row['file_size_bytes'],
        ], $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return list<array<string, int|string|null>> */
    public function retentionLockedReleases(): array
    {
        $statement = $this->database->query(
            "SELECT build_releases.id, build_releases.project_id, projects.name AS project_name, "
            . "build_releases.version_label, build_releases.channel, build_releases.build_number, "
            . "COALESCE(build_releases.published_at, build_releases.ci_build_time, build_releases.created_at) AS release_time, "
            . "COUNT(build_artifacts.id) AS artifact_count, "
            . "COALESCE(SUM(CASE WHEN build_artifacts.storage_key IS NOT NULL AND build_artifacts.storage_key <> '' "
            . "THEN build_artifacts.file_size_bytes ELSE 0 END), 0) AS file_size_bytes "
            . "FROM build_releases JOIN projects ON projects.id = build_releases.project_id "
            . "LEFT JOIN build_artifacts ON build_artifacts.release_id = build_releases.id "
            . "WHERE build_releases.retention_lock = 1 AND build_releases.channel IN ('release','snapshot','dev','wip') "
            . "GROUP BY build_releases.id, build_releases.project_id, projects.name, build_releases.version_label, "
            . "build_releases.channel, build_releases.build_number, build_releases.published_at, "
            . "build_releases.ci_build_time, build_releases.created_at "
            . "ORDER BY release_time DESC, build_releases.id DESC LIMIT 100"
        );
        if ($statement === null) {
            throw new RuntimeException('Nie można pobrać blokad retencji Build Explorera.');
        }
        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'project_id' => (int) $row['project_id'],
            'project_name' => (string) $row['project_name'],
            'version_label' => (string) $row['version_label'],
            'channel' => (string) $row['channel'],
            'build_number' => (string) $row['build_number'],
            'release_time' => $row['release_time'] !== null ? (string) $row['release_time'] : null,
            'artifact_count' => (int) $row['artifact_count'],
            'file_size_bytes' => (int) $row['file_size_bytes'],
        ], $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return list<array{storage_key:string,file_size_bytes:int}> */
    public function v2ReleaseStorageKeys(int $releaseId): array
    {
        $statement = $this->database->query(
            'SELECT storage_key, COALESCE(file_size_bytes, 0) AS file_size_bytes FROM build_artifacts '
            . "WHERE release_id = :release_id AND storage_key IS NOT NULL AND storage_key <> ''",
            [':release_id' => $releaseId]
        );
        if ($statement === null) {
            throw new RuntimeException('Nie można pobrać plików logicznego buildu do retencji.');
        }
        return array_map(static fn (array $row): array => [
            'storage_key' => (string) $row['storage_key'],
            'file_size_bytes' => (int) $row['file_size_bytes'],
        ], $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /** Includes external files for accurate retention preview and artifact counts. */
    public function v2RetentionArtifacts(int $releaseId): array
    {
        return $this->database->query(
            'SELECT storage_key, download_url, COALESCE(file_size_bytes,0) AS file_size_bytes '
            . 'FROM build_artifacts WHERE release_id=:id', [':id' => $releaseId]
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function deleteV2Release(int $releaseId, bool $allowRelease = false): bool
    {
        $statement = $this->database->query(
            "DELETE FROM build_releases WHERE id = :id AND (channel <> 'release' OR :allow_release = 1) AND retention_lock = 0",
            [':id' => $releaseId, ':allow_release' => $allowRelease ? 1 : 0]
        );
        return $statement !== null && $statement->rowCount() === 1;
    }

    /** @return list<string> */
    public function registeredV2StorageKeys(): array
    {
        $statement = $this->database->query(
            "SELECT storage_key FROM build_artifacts WHERE storage_key IS NOT NULL AND storage_key <> ''"
        );
        return $statement === null ? [] : array_values(array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN)));
    }

    /** @param array{releases:int,artifacts:int,bytes:int,orphans:int} $result */
    public function markRetentionCleanup(string $reason, array $result): void
    {
        $statement = $this->database->query(
            'UPDATE build_retention_settings SET last_cleanup_at = UTC_TIMESTAMP(), last_cleanup_reason = :reason, '
            . 'last_cleanup_releases = :releases, last_cleanup_artifacts = :artifacts, '
            . 'last_cleanup_bytes = :bytes, last_cleanup_orphans = :orphans WHERE id = 1',
            [
                ':reason' => substr($reason, 0, 80),
                ':releases' => max(0, $result['releases']),
                ':artifacts' => max(0, $result['artifacts']),
                ':bytes' => max(0, $result['bytes']),
                ':orphans' => max(0, $result['orphans']),
            ]
        );
        if ($statement === null) {
            throw new RuntimeException('Nie można zapisać stanu retencji Build Explorera.');
        }
    }

    public function setV2RetentionLock(int $releaseId, bool $locked): bool
    {
        $statement = $this->database->query(
            "UPDATE build_releases SET retention_lock = :locked WHERE id = :id AND channel IN ('release','snapshot','dev','wip')",
            [':locked' => $locked ? 1 : 0, ':id' => $releaseId]
        );
        return $statement !== null && $statement->rowCount() === 1;
    }

    /**
     * @param array{project_id:int,channel:string,published:string,search:string} $filters
     * @return list<array<string,mixed>>
     */
    public function v2AdminRecent(array $filters, int $limit = 100): array
    {
        $where = [];
        $parameters = [];

        if ($filters['project_id'] > 0) {
            $where[] = 'projects.id = :project_id';
            $parameters[':project_id'] = $filters['project_id'];
        }
        if (in_array($filters['channel'], ['release', 'snapshot', 'dev', 'wip'], true)) {
            $where[] = 'build_releases.channel = :channel';
            $parameters[':channel'] = $filters['channel'];
        }
        if (in_array($filters['published'], ['0', '1'], true)) {
            $where[] = 'build_releases.is_published = :published';
            $parameters[':published'] = (int) $filters['published'];
        }
        if ($filters['search'] !== '') {
            $where[] = '(projects.name LIKE :search OR projects.slug LIKE :search OR build_releases.version_label LIKE :search '
                . 'OR build_releases.build_number LIKE :search OR build_releases.branch LIKE :search OR build_releases.commit_sha LIKE :search)';
            $parameters[':search'] = '%' . $filters['search'] . '%';
        }

        $sql = "SELECT build_releases.id, build_releases.version_label, build_releases.channel, build_releases.build_number, "
            . "build_releases.is_published, build_releases.published_at, build_releases.ci_build_id, build_releases.ci_build_time, "
            . "build_releases.commit_sha, build_releases.branch, projects.name AS project_name, projects.slug AS project_slug, "
            . "COUNT(DISTINCT build_artifacts.id) AS artifact_count, "
            . "COALESCE(SUM(build_artifacts.file_size_bytes), 0) AS file_size_bytes, "
            . "GROUP_CONCAT(DISTINCT build_artifacts.platform ORDER BY build_artifacts.platform SEPARATOR ',') AS platforms "
            . "FROM build_releases JOIN projects ON projects.id = build_releases.project_id "
            . "LEFT JOIN build_artifacts ON build_artifacts.release_id = build_releases.id";
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= " GROUP BY build_releases.id, build_releases.version_label, build_releases.channel, build_releases.build_number, "
            . "build_releases.is_published, build_releases.published_at, build_releases.ci_build_id, build_releases.ci_build_time, "
            . "build_releases.commit_sha, build_releases.branch, projects.name, projects.slug "
            . "ORDER BY COALESCE(build_releases.published_at, build_releases.ci_build_time) DESC, build_releases.id DESC LIMIT "
            . max(1, min(500, $limit));

        $statement = $this->database->query($sql, $parameters);
        if ($statement === null) {
            throw new RuntimeException('Nie można pobrać administracyjnej listy buildów v2.');
        }
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param array{project_id: int, channel: string, published: string, search: string} $filters
     * @return array{0: string, 1: array<string, int|string>}
     */
    private function adminWhere(array $filters): array
    {
        $conditions = [];
        $parameters = [];
        if ($filters['project_id'] > 0) {
            $conditions[] = 'project_builds.project_id = :project_id';
            $parameters[':project_id'] = $filters['project_id'];
        }
        if (in_array($filters['channel'], ['release', 'snapshot', 'dev', 'wip'], true)) {
            $conditions[] = 'project_builds.channel = :channel';
            $parameters[':channel'] = $filters['channel'];
        }
        if (in_array($filters['published'], ['0', '1'], true)) {
            $conditions[] = 'project_builds.is_published = :published';
            $parameters[':published'] = (int) $filters['published'];
        }
        if ($filters['search'] !== '') {
            $conditions[] = '(projects.name LIKE :search OR project_builds.version_label LIKE :search '
                . 'OR project_builds.filename LIKE :search OR project_builds.server_type LIKE :search)';
            $parameters[':search'] = '%' . $filters['search'] . '%';
        }
        return [$conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions), $parameters];
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): ProjectBuild
    {
        return new ProjectBuild(
            (int) $row['id'],
            (int) $row['project_id'],
            (string) $row['project_name'],
            (string) $row['project_slug'],
            (bool) $row['project_published'],
            (bool) $row['project_requires_auth'],
            (string) $row['server_type'],
            (string) $row['version_label'],
            (string) $row['channel'],
            (string) $row['build_number'],
            (string) $row['filename'],
            (string) ($row['storage_key'] ?? ''),
            (string) ($row['download_url'] ?? ''),
            (string) ($row['checksum_sha256'] ?? ''),
            $row['file_size_bytes'] !== null ? (int) $row['file_size_bytes'] : null,
            (string) $row['changelog'],
            (bool) $row['is_published'],
            $row['published_at'] !== null ? (string) $row['published_at'] : null,
            $row['ci_build_id'] !== null ? (int) $row['ci_build_id'] : null,
            $row['ci_build_time'] !== null ? (string) $row['ci_build_time'] : null,
            $this->commits((string) ($row['commits_json'] ?? ''))
        );
    }

    /** @return list<array{sha: string, time: string, message: string}> */
    private function commits(string $json): array
    {
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }
        $result = [];
        foreach ($decoded as $commit) {
            if (!is_array($commit)) {
                continue;
            }
            $result[] = [
                'sha' => (string) ($commit['sha'] ?? ''),
                'time' => (string) ($commit['time'] ?? ''),
                'message' => (string) ($commit['message'] ?? ''),
            ];
        }
        return $result;
    }
}
