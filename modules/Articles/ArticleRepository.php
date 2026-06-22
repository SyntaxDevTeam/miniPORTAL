<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\Articles;

use PDO;
use RuntimeException;
use SyntaxDevTeam\Cms\Database\CrudApp;

final class ArticleRepository
{
    private const SELECT_COLUMNS = 'articles.id, articles.category_id, article_categories.name AS category_name, '
        . 'articles.title, articles.slug, articles.summary, articles.content, articles.content_format, articles.status, '
        . 'articles.author_id, articles.published_at, articles.created_at, articles.updated_at';

    public function __construct(
        private readonly CrudApp $database,
    ) {
    }

    /**
     * @return list<Article>
     */
    public function all(): array
    {
        $statement = $this->database->query(
            'SELECT ' . self::SELECT_COLUMNS . ' FROM articles '
            . 'JOIN article_categories ON article_categories.id = articles.category_id '
            . 'ORDER BY articles.updated_at DESC, articles.id DESC'
        );

        if ($statement === null) {
            throw new RuntimeException('Nie można pobrać artykułów.');
        }

        return array_map($this->hydrate(...), $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * @return list<Article>
     */
    public function published(?string $categorySlug = null): array
    {
        $sql = 'SELECT ' . self::SELECT_COLUMNS . ' FROM articles '
            . 'JOIN article_categories ON article_categories.id = articles.category_id '
            . 'WHERE articles.status = :status';
        $parameters = [':status' => 'published'];

        if ($categorySlug !== null && $categorySlug !== '') {
            $sql .= ' AND article_categories.slug = :category_slug';
            $parameters[':category_slug'] = $categorySlug;
        }

        $sql .= ' ORDER BY articles.published_at DESC, articles.id DESC';
        $statement = $this->database->query($sql, $parameters);

        if ($statement === null) {
            throw new RuntimeException('Nie można pobrać opublikowanych artykułów.');
        }

        return array_map($this->hydrate(...), $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function find(int $id): ?Article
    {
        $statement = $this->database->query(
            'SELECT ' . self::SELECT_COLUMNS . ' FROM articles '
            . 'JOIN article_categories ON article_categories.id = articles.category_id '
            . 'WHERE articles.id = :id LIMIT 1',
            [':id' => $id]
        );
        $row = $statement?->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function findPublishedBySlug(string $slug): ?Article
    {
        $statement = $this->database->query(
            'SELECT ' . self::SELECT_COLUMNS . ' FROM articles '
            . 'JOIN article_categories ON article_categories.id = articles.category_id '
            . 'WHERE articles.slug = :slug AND articles.status = :status LIMIT 1',
            [':slug' => $slug, ':status' => 'published']
        );
        $row = $statement?->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->hydrate($row) : null;
    }

    /**
     * @return list<Category>
     */
    public function categories(): array
    {
        $rows = $this->database->read(
            'article_categories',
            ['id', 'name', 'slug'],
            ['ORDER' => ['name' => 'ASC']]
        ) ?? [];

        return array_map(
            static fn (array $row): Category => new Category(
                (int) $row['id'],
                (string) $row['name'],
                (string) $row['slug']
            ),
            $rows
        );
    }

    public function categoryExists(int $id): bool
    {
        return $id > 0 && $this->database->count('article_categories', ['id' => $id]) === 1;
    }

    public function categorySlugExists(string $slug): bool
    {
        return $this->database->count('article_categories', ['slug' => $slug]) > 0;
    }

    public function categoryNameExists(string $name): bool
    {
        return $this->database->count('article_categories', ['name' => $name]) > 0;
    }

    public function createCategory(string $name, string $slug): int
    {
        return (int) $this->database->create('article_categories', [
            'name' => $name,
            'slug' => $slug,
        ]);
    }

    public function deleteCategory(int $id): bool
    {
        if ($id <= 0 || $this->database->count('articles', ['category_id' => $id]) > 0) {
            return false;
        }

        $statement = $this->database->delete('article_categories', ['id' => $id]);

        return $statement !== null && $statement->rowCount() === 1;
    }

    public function slugExists(string $slug, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM articles WHERE slug = :slug';
        $parameters = [':slug' => $slug];

        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $parameters[':id'] = $exceptId;
        }

        return (int) $this->database->query($sql, $parameters)?->fetchColumn() > 0;
    }

    public function create(
        int $categoryId,
        string $title,
        string $slug,
        string $summary,
        string $content,
        string $contentFormat,
        int $authorId,
    ): int {
        return (int) $this->database->create('articles', [
            'category_id' => $categoryId,
            'title' => $title,
            'slug' => $slug,
            'summary' => $summary,
            'content' => $content,
            'content_format' => $contentFormat,
            'status' => 'draft',
            'author_id' => $authorId,
        ]);
    }

    public function update(
        int $id,
        int $categoryId,
        string $title,
        string $slug,
        string $summary,
        string $content,
        string $contentFormat,
    ): bool {
        $statement = $this->database->update('articles', [
            'category_id' => $categoryId,
            'title' => $title,
            'slug' => $slug,
            'summary' => $summary,
            'content' => $content,
            'content_format' => $contentFormat,
        ], ['id' => $id]);

        return $statement !== null;
    }

    public function publish(int $id): bool
    {
        $statement = $this->database->update('articles', [
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
        ], ['id' => $id]);

        return $statement !== null && $statement->rowCount() === 1;
    }

    public function unpublish(int $id): bool
    {
        $statement = $this->database->update('articles', [
            'status' => 'draft',
            'published_at' => null,
        ], ['id' => $id]);

        return $statement !== null && $statement->rowCount() === 1;
    }

    public function delete(int $id): bool
    {
        $statement = $this->database->delete('articles', ['id' => $id]);

        return $statement !== null && $statement->rowCount() === 1;
    }

    private function hydrate(array $row): Article
    {
        return new Article(
            (int) $row['id'],
            (int) $row['category_id'],
            (string) $row['category_name'],
            (string) $row['title'],
            (string) $row['slug'],
            (string) $row['summary'],
            (string) $row['content'],
            (string) ($row['content_format'] ?? 'html'),
            (string) $row['status'],
            (int) $row['author_id'],
            $row['published_at'] !== null ? (string) $row['published_at'] : null,
            (string) $row['created_at'],
            (string) $row['updated_at'],
        );
    }
}
