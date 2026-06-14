ALTER TABLE core_pages
    ADD COLUMN content_format ENUM('html', 'markdown') NOT NULL DEFAULT 'html' AFTER content;

ALTER TABLE homepage_sections
    ADD COLUMN content_format ENUM('html', 'markdown') NOT NULL DEFAULT 'html' AFTER content_html;

ALTER TABLE homepage_section_items
    ADD COLUMN content_format ENUM('html', 'markdown') NOT NULL DEFAULT 'html' AFTER content;
