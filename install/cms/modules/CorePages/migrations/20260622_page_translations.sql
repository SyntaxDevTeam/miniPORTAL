CREATE TABLE core_page_translations (
    page_id BIGINT UNSIGNED NOT NULL,
    locale CHAR(2) NOT NULL,
    title VARCHAR(180) NOT NULL,
    eyebrow VARCHAR(160) NOT NULL DEFAULT '',
    summary VARCHAR(320) NOT NULL DEFAULT '',
    meta_description VARCHAR(255) NOT NULL DEFAULT '',
    content MEDIUMTEXT NOT NULL,
    content_format ENUM('html', 'markdown') NOT NULL DEFAULT 'html',
    navigation_label VARCHAR(80) NOT NULL DEFAULT '',
    status ENUM('draft', 'published') NOT NULL DEFAULT 'draft',
    origin ENUM('manual', 'google') NOT NULL DEFAULT 'manual',
    source_updated_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (page_id, locale),
    CONSTRAINT fk_core_page_translations_page
        FOREIGN KEY (page_id) REFERENCES core_pages(id) ON DELETE CASCADE,
    INDEX idx_core_page_translations_public (locale, status, page_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
