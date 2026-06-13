CREATE TABLE core_pages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(180) NOT NULL,
    slug VARCHAR(191) NOT NULL,
    content MEDIUMTEXT NOT NULL,
    status ENUM('draft', 'published') NOT NULL DEFAULT 'draft',
    author_id BIGINT UNSIGNED NOT NULL,
    published_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_core_pages_author
        FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE RESTRICT,
    UNIQUE KEY uq_core_pages_slug (slug),
    INDEX idx_core_pages_status_published (status, published_at),
    INDEX idx_core_pages_author_updated (author_id, updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
