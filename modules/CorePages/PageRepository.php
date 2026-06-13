<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\CorePages;

use PDO;
use RuntimeException;
use SyntaxDevTeam\Cms\Database\CrudApp;

final class PageRepository
{
    public function __construct(
        private readonly CrudApp $database,
    ) {
    }

    /**
     * @return list<Page>
     */
    public function all(): array
    {
        $statement = $this->database->query(
            'SELECT id, title, slug, content, status, author_id, published_at, created_at, updated_at '
            . 'FROM core_pages ORDER BY updated_at DESC, id DESC'
        );

        if ($statement === null) {
            throw new RuntimeException('Nie można pobrać stron.');
        }

        return array_map($this->hydrate(...), $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function find(int $id): ?Page
    {
        $statement = $this->database->query(
            'SELECT id, title, slug, content, status, author_id, published_at, created_at, updated_at '
            . 'FROM core_pages WHERE id = :id LIMIT 1',
            [':id' => $id]
        );
        $row = $statement?->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function findPublishedBySlug(string $slug): ?Page
    {
        $statement = $this->database->query(
            'SELECT id, title, slug, content, status, author_id, published_at, created_at, updated_at '
            . 'FROM core_pages WHERE slug = :slug AND status = :status LIMIT 1',
            [
                ':slug' => $slug,
                ':status' => 'published',
            ]
        );
        $row = $statement?->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function slugExists(string $slug, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM core_pages WHERE slug = :slug';
        $parameters = [':slug' => $slug];

        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $parameters[':id'] = $exceptId;
        }

        return (int) $this->database->query($sql, $parameters)?->fetchColumn() > 0;
    }

    public function create(string $title, string $slug, string $content, int $authorId): int
    {
        return (int) $this->database->create('core_pages', [
            'title' => $title,
            'slug' => $slug,
            'content' => $content,
            'status' => 'draft',
            'author_id' => $authorId,
        ]);
    }

    public function update(int $id, string $title, string $slug, string $content): bool
    {
        $statement = $this->database->update('core_pages', [
            'title' => $title,
            'slug' => $slug,
            'content' => $content,
        ], ['id' => $id]);

        return $statement !== null;
    }

    public function publish(int $id): bool
    {
        $statement = $this->database->update('core_pages', [
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
        ], ['id' => $id]);

        return $statement !== null && $statement->rowCount() === 1;
    }

    public function unpublish(int $id): bool
    {
        $statement = $this->database->update('core_pages', [
            'status' => 'draft',
            'published_at' => null,
        ], ['id' => $id]);

        return $statement !== null && $statement->rowCount() === 1;
    }

    public function delete(int $id): bool
    {
        $statement = $this->database->delete('core_pages', ['id' => $id]);

        return $statement !== null && $statement->rowCount() === 1;
    }

    private function hydrate(array $row): Page
    {
        return new Page(
            (int) $row['id'],
            (string) $row['title'],
            (string) $row['slug'],
            (string) $row['content'],
            (string) $row['status'],
            (int) $row['author_id'],
            $row['published_at'] !== null ? (string) $row['published_at'] : null,
            (string) $row['created_at'],
            (string) $row['updated_at'],
        );
    }
}
