<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\CorePages;

use PDO;
use RuntimeException;
use SyntaxDevTeam\Cms\Database\CrudApp;

final class PageRepository
{
    private const COLUMNS = 'id, title, slug, summary, meta_description, content, page_type, '
        . 'navigation_area, navigation_label, sort_order, status, author_id, published_at, created_at, updated_at';

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
            'SELECT ' . self::COLUMNS . ' '
            . 'FROM core_pages ORDER BY updated_at DESC, id DESC'
        );

        if ($statement === null) {
            throw new RuntimeException('Nie można pobrać stron.');
        }

        return array_map($this->hydrate(...), $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * @return list<Page>
     */
    public function published(): array
    {
        $statement = $this->database->query(
            'SELECT ' . self::COLUMNS . ' '
            . 'FROM core_pages WHERE status = :status ORDER BY sort_order ASC, published_at DESC, id DESC',
            [':status' => 'published']
        );

        if ($statement === null) {
            throw new RuntimeException('Nie można pobrać opublikowanych stron.');
        }

        return array_map($this->hydrate(...), $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * @return list<Page>
     */
    public function publishedInNavigation(string $area): array
    {
        if (!in_array($area, ['main', 'footer'], true)) {
            return [];
        }

        $statement = $this->database->query(
            'SELECT ' . self::COLUMNS . ' FROM core_pages '
            . 'WHERE status = :status AND navigation_area = :area '
            . 'ORDER BY sort_order ASC, title ASC',
            [':status' => 'published', ':area' => $area]
        );

        return array_map($this->hydrate(...), $statement?->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    public function find(int $id): ?Page
    {
        $statement = $this->database->query(
            'SELECT ' . self::COLUMNS . ' FROM core_pages WHERE id = :id LIMIT 1',
            [':id' => $id]
        );
        $row = $statement?->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function findPublishedBySlug(string $slug): ?Page
    {
        $statement = $this->database->query(
            'SELECT ' . self::COLUMNS . ' FROM core_pages '
            . 'WHERE slug = :slug AND status = :status LIMIT 1',
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

    /**
     * @param array<string, scalar|null> $data
     */
    public function create(array $data, int $authorId): int
    {
        return (int) $this->database->create('core_pages', [
            ...$data,
            'status' => 'draft',
            'author_id' => $authorId,
        ]);
    }

    /**
     * @param array<string, scalar|null> $data
     */
    public function update(int $id, array $data): bool
    {
        $statement = $this->database->update('core_pages', $data, ['id' => $id]);

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
            (string) ($row['summary'] ?? ''),
            (string) ($row['meta_description'] ?? ''),
            (string) $row['content'],
            (string) ($row['page_type'] ?? 'standard'),
            (string) ($row['navigation_area'] ?? 'none'),
            (string) ($row['navigation_label'] ?? ''),
            (int) ($row['sort_order'] ?? 100),
            (string) $row['status'],
            (int) $row['author_id'],
            $row['published_at'] !== null ? (string) $row['published_at'] : null,
            (string) $row['created_at'],
            (string) $row['updated_at'],
        );
    }
}
