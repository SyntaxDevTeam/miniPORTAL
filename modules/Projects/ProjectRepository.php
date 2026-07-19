<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\Projects;

use PDO;
use RuntimeException;
use SyntaxDevTeam\Cms\Database\CrudApp;

final class ProjectRepository
{
    private const COLUMNS = 'projects.id, projects.name, projects.slug, projects.integration_uuid, '
        . 'projects.integration_priority, projects.icon_url, projects.summary, '
        . 'projects.lifecycle_status, projects.page_id, projects.wiki_project_id, projects.sort_order, '
        . 'projects.is_published, core_pages.title AS page_title, core_pages.slug AS page_slug, '
        . 'core_pages.status AS page_status, wiki_projects.name AS wiki_name, wiki_projects.slug AS wiki_slug, '
        . 'wiki_projects.status AS wiki_status';

    public function __construct(private readonly CrudApp $database)
    {
    }

    /** @return list<Project> */
    public function all(bool $publishedOnly = false): array
    {
        $sql = 'SELECT ' . self::COLUMNS . ' FROM projects '
            . 'LEFT JOIN core_pages ON core_pages.id = projects.page_id '
            . 'LEFT JOIN wiki_projects ON wiki_projects.id = projects.wiki_project_id';
        if ($publishedOnly) {
            $sql .= ' WHERE projects.is_published = 1';
        }
        $sql .= ' ORDER BY projects.sort_order ASC, projects.name ASC';
        $statement = $this->database->query($sql);
        if ($statement === null) {
            throw new RuntimeException('Nie można pobrać katalogu projektów.');
        }

        return array_map($this->hydrate(...), $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function find(int $id): ?Project
    {
        return $this->one('projects.id = :value', $id);
    }

    public function findPublishedBySlug(string $slug): ?Project
    {
        return $this->one('projects.slug = :value AND projects.is_published = 1', $slug);
    }

    public function slugExists(string $slug, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM projects WHERE slug = :slug';
        $parameters = [':slug' => $slug];
        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $parameters[':id'] = $exceptId;
        }
        return (int) $this->database->query($sql, $parameters)?->fetchColumn() > 0;
    }

    public function integrationUuidExists(string $uuid, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM projects WHERE integration_uuid = :uuid';
        $parameters = [':uuid' => $uuid];
        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $parameters[':id'] = $exceptId;
        }

        return (int) $this->database->query($sql, $parameters)?->fetchColumn() > 0;
    }

    /** @return array<int, string> */
    public function pageOptions(): array
    {
        return $this->options('SELECT id, title FROM core_pages ORDER BY title', 'title');
    }

    /** @return array<int, string> */
    public function wikiOptions(): array
    {
        return $this->options('SELECT id, name FROM wiki_projects ORDER BY name', 'name');
    }

    /** @param array<string, int|string|null> $data */
    public function create(array $data): int
    {
        return (int) $this->database->create('projects', $data);
    }

    /** @param array<string, int|string|null> $data */
    public function update(int $id, array $data): bool
    {
        return $this->database->update('projects', $data, ['id' => $id]) !== null;
    }

    public function delete(int $id): bool
    {
        $statement = $this->database->delete('projects', ['id' => $id]);
        return $statement !== null && $statement->rowCount() === 1;
    }

    private function one(string $where, int|string $value): ?Project
    {
        $statement = $this->database->query(
            'SELECT ' . self::COLUMNS . ' FROM projects '
            . 'LEFT JOIN core_pages ON core_pages.id = projects.page_id '
            . 'LEFT JOIN wiki_projects ON wiki_projects.id = projects.wiki_project_id '
            . 'WHERE ' . $where . ' LIMIT 1',
            [':value' => $value]
        );
        $row = $statement?->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $this->hydrate($row) : null;
    }

    /** @return array<int, string> */
    private function options(string $sql, string $label): array
    {
        $statement = $this->database->query($sql);
        if ($statement === null) {
            throw new RuntimeException('Nie można pobrać powiązanych treści projektu.');
        }
        $result = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $result[(int) $row['id']] = (string) $row[$label];
        }
        return $result;
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): Project
    {
        return new Project(
            (int) $row['id'], (string) $row['name'], (string) $row['slug'],
            (string) ($row['integration_uuid'] ?? ''), (int) ($row['integration_priority'] ?? 100),
            (string) ($row['icon_url'] ?? ''), (string) $row['summary'],
            (string) $row['lifecycle_status'], $row['page_id'] !== null ? (int) $row['page_id'] : null,
            $row['wiki_project_id'] !== null ? (int) $row['wiki_project_id'] : null,
            (int) $row['sort_order'], (bool) $row['is_published'], (string) ($row['page_title'] ?? ''),
            (string) ($row['page_slug'] ?? ''), (string) ($row['page_status'] ?? ''),
            (string) ($row['wiki_name'] ?? ''), (string) ($row['wiki_slug'] ?? ''),
            (string) ($row['wiki_status'] ?? '')
        );
    }
}
