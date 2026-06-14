ALTER TABLE homepage_sections
    MODIFY COLUMN layout ENUM('full', 'split', 'columns', 'accent', 'contact')
        NOT NULL DEFAULT 'full';

ALTER TABLE homepage_section_items
    ADD COLUMN item_kind ENUM('card', 'channel', 'person') NOT NULL DEFAULT 'card'
        AFTER content_format,
    ADD COLUMN icon_key VARCHAR(32) NOT NULL DEFAULT '' AFTER item_kind;
