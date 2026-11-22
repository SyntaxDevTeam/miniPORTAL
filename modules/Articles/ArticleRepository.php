<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\Articles;

use DomainException;
use PDO;
use RuntimeException;
use SyntaxDevTeam\Cms\Database\CrudApp;

final class ArticleRepository
{
    public const PAGE_SIZE = 10;
    public const ADMIN_PAGE_SIZE = 50;

    private const SELECT_METADATA_COLUMNS = 'articles.id, articles.category_id, article_categories.name AS category_name, '
        . 'articles.title, articles.slug, articles.summary, articles.content_format, articles.status, '
        . 'articles.author_id, articles.published_at, articles.created_at, articles.updated_at, '
        . 'articles.language, articles.seo_title, articles.seo_description';

    private const SELECT_COLUMNS = self::SELECT_METADATA_COLUMNS . ', articles.content';

    public function __construct(
        private readonly CrudApp $database,
    ) {
    }

    /** @return array{articles: list<Article>, page: int, pages: int, total: int} */
    public function adminPage(string $status, int $category, int $page): array
    {
        $where = [];
        $parameters = [];
        if ($status !== '') {
            $where[] = 'articles.status = :status';
            $parameters[':status'] = $status;
        }
        if ($category > 0) {
            $where[] = '(articles.category_id = :category OR article_categories.parent_id = :parent)';
            $parameters[':category'] = $parameters[':parent'] = $category;
        }
        $from = ' FROM articles JOIN article_categories ON article_categories.id = articles.category_id';
        $from .= $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
        $total = (int) $this->database->query('SELECT COUNT(*)' . $from, $parameters)->fetchColumn();
        $pages = max(1, (int) ceil($total / self::ADMIN_PAGE_SIZE));
        $page = max(1, min($page, $pages));
        $offset = ($page - 1) * self::ADMIN_PAGE_SIZE;
        $rows = $this->database->query(
            'SELECT ' . self::SELECT_METADATA_COLUMNS . $from
            . ' ORDER BY articles.updated_at DESC, articles.id DESC LIMIT ' . self::ADMIN_PAGE_SIZE . ' OFFSET ' . $offset,
            $parameters
        )->fetchAll(PDO::FETCH_ASSOC);

        return ['articles' => array_map($this->hydrate(...), $rows), 'page' => $page, 'pages' => $pages, 'total' => $total];
    }

    /** @param non-empty-list<int> $ids */
    public function bulkSelection(array $ids): array
    {
        return $this->database->read('articles', ['id', 'title', 'status'], ['id' => $ids, 'ORDER' => ['id' => 'ASC']]) ?? [];
    }

    /** @param non-empty-list<int> $ids */
    public function applyBulk(string $operation, array $ids, int|string|null $target = null): int
    {
        if ($ids === []) {
            return 0;
        }
        if ($operation === 'delete') {
            return $this->database->delete('articles', ['id' => $ids])->rowCount();
        }

        if (in_array($operation, ['hide', 'archive'], true)) {
            $this->requireVisibilityStatuses();
        }

        [$values, $condition] = match ($operation) {
            'category' => [['category_id' => (int) $target], ['category_id[!]' => (int) $target]],
            'language' => [['language' => (string) $target], ['language[!]' => (string) $target]],
            'publish' => [['status' => 'published', 'published_at' => date('Y-m-d H:i:s')], ['status' => ['draft', 'hidden', '']]],
            'hide' => [['status' => 'hidden'], ['status' => 'published']],
            'archive' => [['status' => 'archived'], ['status[!]' => 'archived']],
            'restore' => [['status' => 'draft', 'published_at' => null], ['status' => ['archived', 'hidden']]],
            default => throw new RuntimeException('Nieznana operacja zbiorcza artykułów.'),
        };

        return $this->database->update('articles', $values, ['id' => $ids] + $condition)->rowCount();
    }

    private function requireVisibilityStatuses(): void
    {
        if ($this->database->connection()->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
            return;
        }

        $type = $this->database->query("SHOW COLUMNS FROM articles LIKE 'status'")->fetchColumn(1);
        if (!is_string($type) || !str_contains($type, "'hidden'") || !str_contains($type, "'archived'")) {
            throw new DomainException('Ukrycie i archiwizacja wymagają aktualizacji schematu modułu Artykuły.');
        }
    }

    /** @return list<array{slug: string, title: string, summary: string, seo_title: string, seo_description: string, updated_at: string}> */
    public function seoEntries(): array
    {
        return $this->database->read(
            'articles',
            ['slug', 'title', 'summary', 'seo_title', 'seo_description', 'updated_at'],
            ['status' => 'published', 'ORDER' => ['updated_at' => 'DESC', 'id' => 'DESC']]
        ) ?? [];
    }

    /** @return array{items: list<array>, page: int, pages: int, total: int} */
    public function searchPublished(string $query, string $category, string $language, int $page): array
    {
        $sql = ' FROM articles JOIN article_categories ON article_categories.id = articles.category_id '
            . "WHERE articles.status = 'published'";
        $parameters = [];

        if ($category !== '') {
            $sql .= ' AND (article_categories.slug = :category OR article_categories.parent_id = '
                . '(SELECT id FROM article_categories WHERE slug = :parent))';
            $parameters[':category'] = $parameters[':parent'] = $category;
        }
        if ($language !== '') {
            $sql .= ' AND articles.language = :language';
            $parameters[':language'] = $language;
        }
        if ($query !== '') {
            $pattern = '%' . strtr($query, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
            $sql .= " AND (articles.title LIKE :title ESCAPE '!' OR articles.summary LIKE :summary ESCAPE '!'"
                . " OR articles.content LIKE :content ESCAPE '!')";
            $parameters[':title'] = $parameters[':summary'] = $parameters[':content'] = $pattern;
        }

        $total = (int) $this->database->query('SELECT COUNT(*)' . $sql, $parameters)->fetchColumn();
        $pages = max(1, (int) ceil($total / self::PAGE_SIZE));
        $page = max(1, min($page, $pages));
        $offset = ($page - 1) * self::PAGE_SIZE;
        $items = $total === 0 ? [] : $this->database->query(
            'SELECT articles.category_id, articles.title, articles.slug, articles.summary, '
            . 'articles.language, articles.published_at' . $sql
            . ' ORDER BY articles.published_at DESC, articles.id DESC LIMIT ' . self::PAGE_SIZE . ' OFFSET ' . $offset,
            $parameters
        )->fetchAll(PDO::FETCH_ASSOC);

        return compact('items', 'page', 'pages', 'total');
    }

    /** @return array<int, array{views: string, shares: string, downloads: string}> */
    public function statistics(array $ids = []): array
    {
        $rows = $this->database->read(
            'article_statistics',
            ['article_id', 'views', 'shares', 'downloads'],
            $ids === [] ? null : ['article_id' => $ids]
        ) ?? [];
        $statistics = [];
        foreach ($rows as $row) {
            $statistics[(int) $row['article_id']] = [
                'views' => (string) $row['views'],
                'shares' => (string) $row['shares'],
                'downloads' => (string) $row['downloads'],
            ];
        }

        return $statistics;
    }

    public function incrementStatistic(string $column, string|int $article): void
    {
        if (!in_array($column, ['views', 'shares', 'downloads'], true)) {
            return;
        }

        $key = is_int($article) ? 'id' : 'slug';
        $this->database->query(
            "INSERT INTO article_statistics (article_id, `$column`)
             SELECT id, 1 FROM articles WHERE `$key` = :article AND status = 'published'
             ON DUPLICATE KEY UPDATE `$column` = article_statistics.`$column` + 1",
            [':article' => $article]
        );
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
    public function categories(bool $public = false): array
    {
        $rows = $public ? $this->database->query(
            'SELECT id, name, slug, parent_id FROM article_categories WHERE id IN ('
            . 'SELECT category.id FROM articles JOIN article_categories category ON category.id = articles.category_id '
            . "WHERE articles.status = 'published' UNION "
            . 'SELECT category.parent_id FROM articles JOIN article_categories category ON category.id = articles.category_id '
            . "WHERE articles.status = 'published' AND category.parent_id IS NOT NULL) ORDER BY name"
        )->fetchAll(PDO::FETCH_ASSOC) : $this->database->read(
            'article_categories',
            ['id', 'name', 'slug', 'parent_id'],
            ['ORDER' => ['name' => 'ASC']]
        ) ?? [];

        return array_map(
            static fn (array $row): Category => new Category(
                (int) $row['id'],
                (string) $row['name'],
                (string) $row['slug'],
                $row['parent_id'] !== null ? (int) $row['parent_id'] : null,
            ),
            $rows
        );
    }

    public function categoryExists(int $id): bool
    {
        return $id > 0 && $this->database->count('article_categories', ['id' => $id]) === 1;
    }

    public function rootCategoryExists(int $id): bool
    {
        return $id > 0 && $this->database->count('article_categories', ['id' => $id, 'parent_id' => null]) === 1;
    }

    public function categorySlugExists(string $slug): bool
    {
        return $this->database->count('article_categories', ['slug' => $slug]) > 0;
    }

    public function categoryNameExists(string $name): bool
    {
        return $this->database->count('article_categories', ['name' => $name]) > 0;
    }

    public function createCategory(string $name, string $slug, ?int $parentId = null): int
    {
        return (int) $this->database->create('article_categories', [
            'name' => $name,
            'slug' => $slug,
            'parent_id' => $parentId,
        ]);
    }

    public function deleteCategory(int $id): bool
    {
        if ($id <= 0 || $this->database->count('articles', ['category_id' => $id]) > 0
            || $this->database->count('article_categories', ['parent_id' => $id]) > 0
        ) {
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
        string $language = 'und',
        string $seoTitle = '',
        string $seoDescription = '',
    ): int {
        return (int) $this->database->create('articles', [
            'category_id' => $categoryId,
            'title' => $title,
            'slug' => $slug,
            'summary' => $summary,
            'content' => $content,
            'content_format' => $contentFormat,
            'language' => $language,
            'seo_title' => $seoTitle,
            'seo_description' => $seoDescription,
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
        string $language = 'und',
        string $seoTitle = '',
        string $seoDescription = '',
    ): bool {
        $statement = $this->database->update('articles', [
            'category_id' => $categoryId,
            'title' => $title,
            'slug' => $slug,
            'summary' => $summary,
            'content' => $content,
            'content_format' => $contentFormat,
            'language' => $language,
            'seo_title' => $seoTitle,
            'seo_description' => $seoDescription,
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
            (string) ($row['content'] ?? ''),
            (string) ($row['content_format'] ?? 'html'),
            (string) $row['status'],
            (int) $row['author_id'],
            $row['published_at'] !== null ? (string) $row['published_at'] : null,
            (string) $row['created_at'],
            (string) $row['updated_at'],
            (string) $row['language'],
            (string) $row['seo_title'],
            (string) $row['seo_description'],
        );
    }
}
