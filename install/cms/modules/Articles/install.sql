CREATE TABLE article_categories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(191) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
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
    status ENUM('draft', 'published') NOT NULL DEFAULT 'draft',
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
    INDEX idx_articles_category_published (category_id, status, published_at),
    INDEX idx_articles_author_updated (author_id, updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE article_translations (
    article_id BIGINT UNSIGNED NOT NULL,
    locale CHAR(2) NOT NULL,
    title VARCHAR(180) NOT NULL,
    summary VARCHAR(500) NOT NULL,
    content MEDIUMTEXT NOT NULL,
    content_format ENUM('html', 'markdown') NOT NULL DEFAULT 'html',
    status ENUM('draft', 'published') NOT NULL DEFAULT 'draft',
    origin ENUM('manual', 'google') NOT NULL DEFAULT 'manual',
    source_updated_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (article_id, locale),
    CONSTRAINT fk_article_translations_article
        FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE,
    INDEX idx_article_translations_public (locale, status, article_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO article_categories (name, slug) VALUES ('Ogólne', 'ogolne');
