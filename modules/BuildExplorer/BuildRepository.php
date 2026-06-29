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
        . 'project_builds.server_type, project_builds.version_label, project_builds.channel, project_builds.build_number, '
        . 'project_builds.filename, project_builds.storage_key, project_builds.download_url, '
        . 'project_builds.checksum_sha256, project_builds.file_size_bytes, '
        . 'project_builds.changelog, project_builds.is_published, project_builds.published_at, '
        . 'project_builds.ci_build_id, project_builds.ci_build_time, project_builds.commits_json';

    public function __construct(private readonly CrudApp $database) {}

    /** @return list<ProjectBuild> */
    public function all(bool $publicOnly = false, ?string $projectSlug = null): array
    {
        $sql = 'SELECT ' . self::COLUMNS . ' FROM project_builds JOIN projects ON projects.id = project_builds.project_id';
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

    public function find(int $id): ?ProjectBuild
    {
        $statement = $this->database->query(
            'SELECT ' . self::COLUMNS . ' FROM project_builds JOIN projects ON projects.id = project_builds.project_id '
            . 'WHERE project_builds.id = :id LIMIT 1', [':id' => $id]
        );
        $row = $statement?->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function findPublic(int $id): ?ProjectBuild
    {
        $statement = $this->database->query(
            'SELECT ' . self::COLUMNS . ' FROM project_builds JOIN projects ON projects.id = project_builds.project_id '
            . 'WHERE project_builds.id = :id AND project_builds.is_published = 1 '
            . 'AND projects.is_published = 1 LIMIT 1', [':id' => $id]
        );
        $row = $statement?->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function findCi(int $projectId, string $channel, string $serverType, int $ciBuildId): ?ProjectBuild
    {
        $statement = $this->database->query(
            'SELECT ' . self::COLUMNS . ' FROM project_builds JOIN projects ON projects.id = project_builds.project_id '
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
    public function publicProjects(): array
    {
        $statement = $this->database->query(
            'SELECT projects.id, projects.name, projects.slug, COUNT(project_builds.id) AS build_count '
            . 'FROM projects LEFT JOIN project_builds ON project_builds.project_id = projects.id '
            . 'AND project_builds.is_published = 1 WHERE projects.is_published = 1 '
            . 'GROUP BY projects.id, projects.name, projects.slug '
            . 'ORDER BY projects.sort_order, projects.name'
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

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): ProjectBuild
    {
        return new ProjectBuild(
            (int) $row['id'], (int) $row['project_id'], (string) $row['project_name'],
            (string) $row['project_slug'], (bool) $row['project_published'], (string) $row['server_type'],
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
