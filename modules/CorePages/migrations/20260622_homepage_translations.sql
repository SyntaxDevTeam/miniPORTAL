CREATE TABLE homepage_section_translations (
    section_id BIGINT UNSIGNED NOT NULL,
    locale CHAR(2) NOT NULL,
    eyebrow VARCHAR(160) NOT NULL DEFAULT '',
    acrostic_words VARCHAR(500) NOT NULL DEFAULT '',
    title VARCHAR(220) NOT NULL,
    content_html MEDIUMTEXT NOT NULL,
    content_format ENUM('html', 'markdown') NOT NULL DEFAULT 'html',
    button_label VARCHAR(120) NOT NULL DEFAULT '',
    status ENUM('draft', 'published') NOT NULL DEFAULT 'draft',
    origin ENUM('manual', 'google') NOT NULL DEFAULT 'manual',
    source_updated_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (section_id, locale),
    CONSTRAINT fk_homepage_section_translations_section
        FOREIGN KEY (section_id) REFERENCES homepage_sections(id) ON DELETE CASCADE,
    INDEX idx_homepage_section_translations_public (locale, status, section_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE homepage_section_item_translations (
    item_id BIGINT UNSIGNED NOT NULL,
    locale CHAR(2) NOT NULL,
    label VARCHAR(120) NOT NULL DEFAULT '',
    title VARCHAR(180) NOT NULL,
    content TEXT NOT NULL,
    content_format ENUM('html', 'markdown') NOT NULL DEFAULT 'html',
    button_label VARCHAR(120) NOT NULL DEFAULT '',
    status ENUM('draft', 'published') NOT NULL DEFAULT 'draft',
    origin ENUM('manual', 'google') NOT NULL DEFAULT 'manual',
    source_updated_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (item_id, locale),
    CONSTRAINT fk_homepage_section_item_translations_item
        FOREIGN KEY (item_id) REFERENCES homepage_section_items(id) ON DELETE CASCADE,
    INDEX idx_homepage_section_item_translations_public (locale, status, item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
