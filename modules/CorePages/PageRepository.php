<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\CorePages;

use PDO;
use RuntimeException;
use SyntaxDevTeam\Cms\Database\CrudApp;

final class PageRepository
{
    private const COLUMNS = 'id, title, slug, eyebrow, summary, meta_description, content, content_format, page_type, '
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

    /** @return list<Page> */
    public function publishedForLocale(string $locale): array
    {
        if ($locale === 'pl') {
            return $this->published();
        }
        $statement = $this->database->query(
            'SELECT p.id, t.title, p.slug, t.eyebrow, t.summary, t.meta_description, t.content, '
            . 't.content_format, p.page_type, p.navigation_area, t.navigation_label, p.sort_order, '
            . 't.status, p.author_id, p.published_at, p.created_at, t.updated_at '
            . 'FROM core_pages p JOIN core_page_translations t ON t.page_id = p.id '
            . 'WHERE p.status = :published AND t.status = :published AND t.locale = :locale '
            . 'ORDER BY p.sort_order ASC, p.published_at DESC, p.id DESC',
            [':published' => 'published', ':locale' => $locale]
        );

        return array_map($this->hydrate(...), $statement?->fetchAll(PDO::FETCH_ASSOC) ?: []);
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

    public function findPublishedBySlugForLocale(string $slug, string $locale): ?Page
    {
        if ($locale === 'pl') {
            return $this->findPublishedBySlug($slug);
        }
        $statement = $this->database->query(
            'SELECT p.id, t.title, p.slug, t.eyebrow, t.summary, t.meta_description, t.content, '
            . 't.content_format, p.page_type, p.navigation_area, t.navigation_label, p.sort_order, '
            . 't.status, p.author_id, p.published_at, p.created_at, t.updated_at '
            . 'FROM core_pages p JOIN core_page_translations t ON t.page_id = p.id '
            . 'WHERE p.slug = :slug AND p.status = :published AND t.status = :published '
            . 'AND t.locale = :locale LIMIT 1',
            [':slug' => $slug, ':published' => 'published', ':locale' => $locale]
        );
        $row = $statement?->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function translation(int $pageId, string $locale): ?PageTranslation
    {
        $row = $this->database->read('core_page_translations', '*', [
            'page_id' => $pageId,
            'locale' => $locale,
        ]);
        if (!is_array($row)) {
            return null;
        }
        if (array_is_list($row)) {
            $row = $row[0] ?? null;
        }

        return is_array($row) ? $this->hydrateTranslation($row) : null;
    }

    /** @param array<string, string> $data */
    public function saveTranslation(int $pageId, string $locale, array $data, string $origin = 'manual'): bool
    {
        $values = [
            'title' => $data['title'],
            'eyebrow' => $data['eyebrow'],
            'summary' => $data['summary'],
            'meta_description' => $data['meta_description'],
            'content' => $data['content'],
            'content_format' => $data['content_format'],
            'navigation_label' => $data['navigation_label'],
            'origin' => $origin,
            'source_updated_at' => $data['source_updated_at'],
            'status' => 'draft',
        ];
        if ($this->translation($pageId, $locale) === null) {
            return (int) $this->database->create('core_page_translations', [
                'page_id' => $pageId,
                'locale' => $locale,
                ...$values,
            ]) > 0;
        }

        return $this->database->update('core_page_translations', $values, [
            'page_id' => $pageId,
            'locale' => $locale,
        ]) !== null;
    }

    public function setTranslationStatus(int $pageId, string $locale, string $status): bool
    {
        if (!in_array($status, ['draft', 'published'], true)) {
            return false;
        }
        $statement = $this->database->update('core_page_translations', ['status' => $status], [
            'page_id' => $pageId,
            'locale' => $locale,
        ]);

        return $statement !== null && $statement->rowCount() === 1;
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
            (string) ($row['eyebrow'] ?? ''),
            (string) ($row['summary'] ?? ''),
            (string) ($row['meta_description'] ?? ''),
            (string) $row['content'],
            (string) ($row['content_format'] ?? 'html'),
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

    private function hydrateTranslation(array $row): PageTranslation
    {
        return new PageTranslation(
            (int) $row['page_id'],
            (string) $row['locale'],
            (string) $row['title'],
            (string) ($row['eyebrow'] ?? ''),
            (string) ($row['summary'] ?? ''),
            (string) ($row['meta_description'] ?? ''),
            (string) $row['content'],
            (string) ($row['content_format'] ?? 'html'),
            (string) ($row['navigation_label'] ?? ''),
            (string) $row['status'],
            (string) ($row['origin'] ?? 'manual'),
            $row['source_updated_at'] !== null ? (string) $row['source_updated_at'] : null,
            (string) $row['updated_at'],
        );
    }
}
