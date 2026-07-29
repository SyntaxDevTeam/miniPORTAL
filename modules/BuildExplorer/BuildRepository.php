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
        if ($where !== []) { $sql .= ' WHERE ' . implode(' AND ', $where); }
        $sql .= ' ORDER BY projects.sort_order ASC, projects.name ASC, project_builds.published_at DESC, project_builds.id DESC';
        $statement = $this->database->query($sql, $parameters);
        if ($statement === null) { throw new RuntimeException('Nie można pobrać listy buildów.'); }
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
        if ($statement === null) { throw new RuntimeException('Nie można policzyć buildów.'); }
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
        if ($statement === null) { throw new RuntimeException('Nie można pobrać strony buildów.'); }
        return array_map($this->hydrate(...), $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array{total: int, published: int, public_available: int, authenticated_available: int, local: int, registered_bytes: int, release: int, snapshot: int, dev: int, wip: int} */
    public function statistics(): array
    {
        $statement = $this->database->query(
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
        $row = $statement?->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) { throw new RuntimeException('Nie można pobrać statystyk buildów.'); }
        return [
            'total' => (int) $row['total'], 'published' => (int) $row['published'],
            'public_available' => (int) $row['public_available'],
            'authenticated_available' => (int) $row['authenticated_available'],
            'local' => (int) $row['local'], 'registered_bytes' => (int) $row['registered_bytes'],
            'release' => (int) $row['release_count'], 'snapshot' => (int) $row['snapshot_count'],
            'dev' => (int) $row['dev_count'], 'wip' => (int) $row['wip_count'],
        ];
    }

    public function find(int $id): ?ProjectBuild
    {
        $statement = $this->database->query(
            'SELECT ' . self::COLUMNS . ' FROM project_builds' . self::PROJECT_JOINS . ' '
            . 'WHERE project_builds.id = :id LIMIT 1', [':id' => $id]
        );
        $row = $statement?->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function findPublic(int $id): ?ProjectBuild
    {
        $statement = $this->database->query(
            'SELECT ' . self::COLUMNS . ' FROM project_builds' . self::PROJECT_JOINS . ' '
            . 'WHERE project_builds.id = :id AND project_builds.is_published = 1 '
            . 'AND projects.is_published = 1 LIMIT 1', [':id' => $id]
        );
        $row = $statement?->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $this->hydrate($row) : null;
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
        if ($statement === null) { throw new RuntimeException('Nie można pobrać projektów.'); }
        $result = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) { $result[(int) $row['id']] = (string) $row['name']; }
        return $result;
    }

    /** @return list<array{id: int, name: string, slug: string, build_count: int}> */
    public function publicProjects(bool $authenticated): array
    {
        $statement = $this->database->query(
            'SELECT projects.id, projects.name, projects.slug, COUNT(project_builds.id) AS build_count '
            . 'FROM projects LEFT JOIN project_builds ON project_builds.project_id = projects.id '
            . 'AND project_builds.is_published = 1 '
            . 'LEFT JOIN build_explorer_project_settings '
            . 'ON build_explorer_project_settings.project_id = projects.id '
            . 'WHERE projects.is_published = 1 '
            . 'AND (COALESCE(build_explorer_project_settings.requires_auth, 0) = 0 OR :authenticated = 1) '
            . 'GROUP BY projects.id, projects.name, projects.slug, build_explorer_project_settings.requires_auth '
            . 'HAVING build_count > 0 '
            . 'ORDER BY projects.sort_order, projects.name',
            [':authenticated' => $authenticated ? 1 : 0]
        );
        if ($statement === null) { throw new RuntimeException('Nie można pobrać projektów Build Explorera.'); }

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'], 'name' => (string) $row['name'], 'slug' => (string) $row['slug'],
            'build_count' => (int) $row['build_count'],
        ], $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array{id: int, name: string, slug: string}|null */
    public function projectBySlug(string $slug): ?array
    {
        $statement = $this->database->query(
            'SELECT id, name, slug FROM projects WHERE slug = :slug LIMIT 1', [':slug' => $slug]
        );
        $row = $statement?->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? ['id' => (int) $row['id'], 'name' => (string) $row['name'], 'slug' => (string) $row['slug']] : null;
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
            . 'COUNT(project_builds.id) AS build_count '
            . 'FROM projects LEFT JOIN build_explorer_project_settings '
            . 'ON build_explorer_project_settings.project_id = projects.id '
            . 'LEFT JOIN project_builds ON project_builds.project_id = projects.id '
            . 'GROUP BY projects.id, projects.name, projects.slug, projects.is_published, '
            . 'build_explorer_project_settings.requires_auth '
            . 'ORDER BY projects.sort_order, projects.name'
        );
        if ($statement === null) { throw new RuntimeException('Nie można pobrać widoczności projektów Build Explorera.'); }

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
            [':project_id' => $data['project_id'], ':channel' => $data['channel'],
                ':server_type' => $data['server_type'], ':ci_build_id' => $data['ci_build_id']]
        );
        $id = (int) ($statement?->fetchColumn() ?: 0);
        if ($id > 0) {
            $this->update($id, $data);
            return $id;
        }

        return $this->create($data);
    }

    /** @param array<string, int|string|null> $data */
    public function create(array $data): int { return (int) $this->database->create('project_builds', $data); }
    /** @param array<string, int|string|null> $data */
    public function update(int $id, array $data): bool { return $this->database->update('project_builds', $data, ['id' => $id]) !== null; }
    public function delete(int $id): bool
    {
        $statement = $this->database->delete('project_builds', ['id' => $id]);
        return $statement !== null && $statement->rowCount() === 1;
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
            (int) $row['id'], (int) $row['project_id'], (string) $row['project_name'],
            (string) $row['project_slug'], (bool) $row['project_published'],
            (bool) $row['project_requires_auth'], (string) $row['server_type'],
            (string) $row['version_label'], (string) $row['channel'], (string) $row['build_number'],
            (string) $row['filename'], (string) ($row['storage_key'] ?? ''), (string) ($row['download_url'] ?? ''),
            (string) ($row['checksum_sha256'] ?? ''), $row['file_size_bytes'] !== null ? (int) $row['file_size_bytes'] : null,
            (string) $row['changelog'], (bool) $row['is_published'],
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
        if (!is_array($decoded)) { return []; }
        $result = [];
        foreach ($decoded as $commit) {
            if (!is_array($commit)) { continue; }
            $result[] = [
                'sha' => (string) ($commit['sha'] ?? ''),
                'time' => (string) ($commit['time'] ?? ''),
                'message' => (string) ($commit['message'] ?? ''),
            ];
        }
        return $result;
    }
}
