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
