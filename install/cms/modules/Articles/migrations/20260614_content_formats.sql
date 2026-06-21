ALTER TABLE articles
    ADD COLUMN content_format ENUM('html', 'markdown') NOT NULL DEFAULT 'html' AFTER content;
