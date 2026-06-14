ALTER TABLE core_pages
    ADD COLUMN summary VARCHAR(320) NOT NULL DEFAULT '' AFTER slug,
    ADD COLUMN meta_description VARCHAR(255) NOT NULL DEFAULT '' AFTER summary,
    ADD COLUMN page_type ENUM('standard', 'project', 'legal') NOT NULL DEFAULT 'standard' AFTER content,
    ADD COLUMN navigation_area ENUM('none', 'main', 'footer') NOT NULL DEFAULT 'none' AFTER page_type,
    ADD COLUMN navigation_label VARCHAR(80) NOT NULL DEFAULT '' AFTER navigation_area,
    ADD COLUMN sort_order INT UNSIGNED NOT NULL DEFAULT 100 AFTER navigation_label,
    ADD INDEX idx_core_pages_navigation (status, navigation_area, sort_order);

ALTER TABLE homepage_section_items
    ADD COLUMN page_id BIGINT UNSIGNED NULL AFTER section_id,
    ADD CONSTRAINT fk_homepage_section_items_page
        FOREIGN KEY (page_id) REFERENCES core_pages(id) ON DELETE SET NULL,
    ADD INDEX idx_homepage_section_items_page (page_id);
