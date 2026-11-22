CREATE TABLE article_categories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(191) NOT NULL,
    parent_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_article_categories_parent
        FOREIGN KEY (parent_id) REFERENCES article_categories(id) ON DELETE RESTRICT,
    UNIQUE KEY uq_article_categories_name (name),
    UNIQUE KEY uq_article_categories_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE articles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(180) NOT NULL,
    slug VARCHAR(191) NOT NULL,
    summary VARCHAR(500) NOT NULL,
    content MEDIUMTEXT NOT NULL,
    content_format ENUM('html', 'markdown') NOT NULL DEFAULT 'html',
    language ENUM('und', 'pl', 'en') NOT NULL DEFAULT 'und',
    seo_title VARCHAR(180) NOT NULL DEFAULT '',
    seo_description VARCHAR(320) NOT NULL DEFAULT '',
    status ENUM('draft', 'published', 'hidden', 'archived') NOT NULL DEFAULT 'draft',
    author_id BIGINT UNSIGNED NOT NULL,
    published_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_articles_category
        FOREIGN KEY (category_id) REFERENCES article_categories(id) ON DELETE RESTRICT,
    CONSTRAINT fk_articles_author
        FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE RESTRICT,
    UNIQUE KEY uq_articles_slug (slug),
    INDEX idx_articles_status_published (status, published_at),
    INDEX idx_articles_status_language_published (status, language, published_at),
    INDEX idx_articles_category_published (category_id, status, published_at),
    INDEX idx_articles_author_updated (author_id, updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO article_categories (name, slug) VALUES ('Ogólne', 'ogolne');

CREATE TABLE article_statistics (
    article_id BIGINT UNSIGNED PRIMARY KEY,
    views BIGINT UNSIGNED NOT NULL DEFAULT 0,
    shares BIGINT UNSIGNED NOT NULL DEFAULT 0,
    downloads BIGINT UNSIGNED NOT NULL DEFAULT 0,
    CONSTRAINT fk_article_statistics_article
        FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
