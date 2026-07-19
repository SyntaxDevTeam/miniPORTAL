<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\Wikipedia;

use PDO;
use RuntimeException;
use SyntaxDevTeam\Cms\Database\CrudApp;

final class WikiRepository
{
    private const PROJECT_COLUMNS = 'id, name, slug, summary, status, sort_order, created_at, updated_at';

    private const PAGE_COLUMNS = 'wiki_pages.id, wiki_pages.project_id, wiki_projects.name AS project_name, '
        . 'wiki_projects.slug AS project_slug, wiki_pages.title, wiki_pages.slug, wiki_pages.summary, '
        . 'wiki_pages.content, wiki_pages.content_format, wiki_pages.status, wiki_pages.sort_order, '
        . 'wiki_pages.author_id, wiki_pages.published_at, wiki_pages.created_at, wiki_pages.updated_at';

    public function __construct(
        private readonly CrudApp $database,
    ) {
    }

    /**
     * @return list<WikiProject>
     */
    public function projects(): array
    {
        $statement = $this->database->query(
            'SELECT ' . self::PROJECT_COLUMNS . ' FROM wiki_projects ORDER BY sort_order ASC, name ASC'
        );
        if ($statement === null) {
            throw new RuntimeException('Nie można pobrać projektów dokumentacji.');
        }

        return array_map($this->hydrateProject(...), $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * @return list<WikiProject>
     */
    public function publishedProjects(): array
    {
        $statement = $this->database->query(
            'SELECT ' . self::PROJECT_COLUMNS . ' FROM wiki_projects '
            . 'WHERE status = :status ORDER BY sort_order ASC, name ASC',
            [':status' => 'published']
        );
        if ($statement === null) {
            throw new RuntimeException('Nie można pobrać opublikowanych projektów dokumentacji.');
        }

        return array_map($this->hydrateProject(...), $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function findProject(int $id): ?WikiProject
    {
        $statement = $this->database->query(
            'SELECT ' . self::PROJECT_COLUMNS . ' FROM wiki_projects WHERE id = :id LIMIT 1',
            [':id' => $id]
        );
        $row = $statement?->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->hydrateProject($row) : null;
    }

    public function findPublishedProjectBySlug(string $slug): ?WikiProject
    {
        $statement = $this->database->query(
            'SELECT ' . self::PROJECT_COLUMNS . ' FROM wiki_projects '
            . 'WHERE slug = :slug AND status = :status LIMIT 1',
            [':slug' => $slug, ':status' => 'published']
        );
        $row = $statement?->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->hydrateProject($row) : null;
    }

    public function projectSlugExists(string $slug, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM wiki_projects WHERE slug = :slug';
        $parameters = [':slug' => $slug];
        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $parameters[':id'] = $exceptId;
        }

        return (int) $this->database->query($sql, $parameters)?->fetchColumn() > 0;
    }

    /**
     * @param array{name: string, slug: string, summary: string, sort_order: int} $data
     */
    public function createProject(array $data): int
    {
        return (int) $this->database->create('wiki_projects', [
            ...$data,
            'status' => 'draft',
        ]);
    }

    /**
     * @param array{name: string, slug: string, summary: string, sort_order: int} $data
     */
    public function updateProject(int $id, array $data): bool
    {
        $statement = $this->database->update('wiki_projects', $data, ['id' => $id]);

        return $statement !== null;
    }

    public function setProjectPublished(int $id, bool $published): bool
    {
        $statement = $this->database->update(
            'wiki_projects',
            ['status' => $published ? 'published' : 'draft'],
            ['id' => $id]
        );

        return $statement !== null && $statement->rowCount() === 1;
    }

    public function deleteProject(int $id): bool
    {
        $statement = $this->database->delete('wiki_projects', ['id' => $id]);

        return $statement !== null && $statement->rowCount() === 1;
    }

    /**
     * @return list<WikiPage>
     */
    public function pages(?int $projectId = null): array
    {
        $sql = 'SELECT ' . self::PAGE_COLUMNS . ' FROM wiki_pages '
            . 'JOIN wiki_projects ON wiki_projects.id = wiki_pages.project_id';
        $parameters = [];
        if ($projectId !== null) {
            $sql .= ' WHERE wiki_pages.project_id = :project_id';
            $parameters[':project_id'] = $projectId;
        }
        $sql .= ' ORDER BY wiki_projects.name ASC, wiki_pages.sort_order ASC, wiki_pages.title ASC';
        $statement = $this->database->query($sql, $parameters);
        if ($statement === null) {
            throw new RuntimeException('Nie można pobrać stron dokumentacji.');
        }

        return array_map($this->hydratePage(...), $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * @return list<WikiPage>
     */
    public function publishedPages(int $projectId): array
    {
        $statement = $this->database->query(
            'SELECT ' . self::PAGE_COLUMNS . ' FROM wiki_pages '
            . 'JOIN wiki_projects ON wiki_projects.id = wiki_pages.project_id '
            . 'WHERE wiki_pages.project_id = :project_id AND wiki_pages.status = :status '
            . 'ORDER BY wiki_pages.sort_order ASC, wiki_pages.title ASC',
            [':project_id' => $projectId, ':status' => 'published']
        );
        if ($statement === null) {
            throw new RuntimeException('Nie można pobrać opublikowanych stron dokumentacji.');
        }

        return array_map($this->hydratePage(...), $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function findPage(int $id): ?WikiPage
    {
        $statement = $this->database->query(
            'SELECT ' . self::PAGE_COLUMNS . ' FROM wiki_pages '
            . 'JOIN wiki_projects ON wiki_projects.id = wiki_pages.project_id '
            . 'WHERE wiki_pages.id = :id LIMIT 1',
            [':id' => $id]
        );
        $row = $statement?->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->hydratePage($row) : null;
    }

    public function findPublishedPage(string $projectSlug, string $pageSlug): ?WikiPage
    {
        $statement = $this->database->query(
            'SELECT ' . self::PAGE_COLUMNS . ' FROM wiki_pages '
            . 'JOIN wiki_projects ON wiki_projects.id = wiki_pages.project_id '
            . 'WHERE wiki_projects.slug = :project_slug AND wiki_projects.status = :project_status '
            . 'AND wiki_pages.slug = :page_slug AND wiki_pages.status = :page_status LIMIT 1',
            [
                ':project_slug' => $projectSlug,
                ':project_status' => 'published',
                ':page_slug' => $pageSlug,
                ':page_status' => 'published',
            ]
        );
        $row = $statement?->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->hydratePage($row) : null;
    }

    public function pageSlugExists(int $projectId, string $slug, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM wiki_pages WHERE project_id = :project_id AND slug = :slug';
        $parameters = [':project_id' => $projectId, ':slug' => $slug];
        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $parameters[':id'] = $exceptId;
        }

        return (int) $this->database->query($sql, $parameters)?->fetchColumn() > 0;
    }

    public function createPage(
        int $projectId,
        string $title,
        string $slug,
        string $summary,
        string $content,
        string $contentFormat,
        int $sortOrder,
        int $authorId,
    ): int {
        return (int) $this->database->create('wiki_pages', [
            'project_id' => $projectId,
            'title' => $title,
            'slug' => $slug,
            'summary' => $summary,
            'content' => $content,
            'content_format' => $contentFormat,
            'status' => 'draft',
            'sort_order' => $sortOrder,
            'author_id' => $authorId,
        ]);
    }

    public function updatePage(
        int $id,
        int $projectId,
        string $title,
        string $slug,
        string $summary,
        string $content,
        string $contentFormat,
        int $sortOrder,
    ): bool {
        $statement = $this->database->update('wiki_pages', [
            'project_id' => $projectId,
            'title' => $title,
            'slug' => $slug,
            'summary' => $summary,
            'content' => $content,
            'content_format' => $contentFormat,
            'sort_order' => $sortOrder,
        ], ['id' => $id]);

        return $statement !== null;
    }

    public function setPagePublished(int $id, bool $published): bool
    {
        $statement = $this->database->update('wiki_pages', [
            'status' => $published ? 'published' : 'draft',
            'published_at' => $published ? date('Y-m-d H:i:s') : null,
        ], ['id' => $id]);

        return $statement !== null && $statement->rowCount() === 1;
    }

    public function deletePage(int $id): bool
    {
        $statement = $this->database->delete('wiki_pages', ['id' => $id]);

        return $statement !== null && $statement->rowCount() === 1;
    }

    private function hydrateProject(array $row): WikiProject
    {
        return new WikiProject(
            (int) $row['id'],
            (string) $row['name'],
            (string) $row['slug'],
            (string) $row['summary'],
            (string) $row['status'],
            (int) $row['sort_order'],
            (string) $row['created_at'],
            (string) $row['updated_at'],
        );
    }

    private function hydratePage(array $row): WikiPage
    {
        return new WikiPage(
            (int) $row['id'],
            (int) $row['project_id'],
            (string) $row['project_name'],
            (string) $row['project_slug'],
            (string) $row['title'],
            (string) $row['slug'],
            (string) $row['summary'],
            (string) $row['content'],
            (string) ($row['content_format'] ?? 'markdown'),
            (string) $row['status'],
            (int) $row['sort_order'],
            (int) $row['author_id'],
            $row['published_at'] !== null ? (string) $row['published_at'] : null,
            (string) $row['created_at'],
            (string) $row['updated_at'],
        );
    }
}
