CREATE TABLE core_pages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(180) NOT NULL,
    slug VARCHAR(191) NOT NULL,
    eyebrow VARCHAR(160) NOT NULL DEFAULT '',
    summary VARCHAR(320) NOT NULL DEFAULT '',
    meta_description VARCHAR(255) NOT NULL DEFAULT '',
    content MEDIUMTEXT NOT NULL,
    content_format ENUM('html', 'markdown') NOT NULL DEFAULT 'html',
    page_type ENUM('standard', 'project', 'legal') NOT NULL DEFAULT 'standard',
    navigation_area ENUM('none', 'main', 'footer') NOT NULL DEFAULT 'none',
    navigation_label VARCHAR(80) NOT NULL DEFAULT '',
    sort_order INT UNSIGNED NOT NULL DEFAULT 100,
    status ENUM('draft', 'published') NOT NULL DEFAULT 'draft',
    author_id BIGINT UNSIGNED NOT NULL,
    published_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_core_pages_author
        FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE RESTRICT,
    UNIQUE KEY uq_core_pages_slug (slug),
    INDEX idx_core_pages_navigation (status, navigation_area, sort_order),
    INDEX idx_core_pages_status_published (status, published_at),
    INDEX idx_core_pages_author_updated (author_id, updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE homepage_sections (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    section_key VARCHAR(64) NOT NULL,
    section_type ENUM('hero', 'content', 'cta') NOT NULL DEFAULT 'content',
    eyebrow VARCHAR(160) NULL,
    acrostic_words VARCHAR(500) NOT NULL DEFAULT '',
    title VARCHAR(220) NOT NULL,
    content_html MEDIUMTEXT NOT NULL,
    content_format ENUM('html', 'markdown') NOT NULL DEFAULT 'html',
    layout ENUM('full', 'split', 'columns', 'accent', 'contact') NOT NULL DEFAULT 'full',
    button_label VARCHAR(120) NULL,
    button_url VARCHAR(500) NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 10,
    is_visible TINYINT(1) NOT NULL DEFAULT 1,
    author_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_homepage_sections_author
        FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE RESTRICT,
    UNIQUE KEY uq_homepage_sections_key (section_key),
    INDEX idx_homepage_sections_visible_order (is_visible, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE homepage_section_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    section_id BIGINT UNSIGNED NOT NULL,
    page_id BIGINT UNSIGNED NULL,
    label VARCHAR(120) NULL,
    title VARCHAR(180) NOT NULL,
    content TEXT NOT NULL,
    content_format ENUM('html', 'markdown') NOT NULL DEFAULT 'html',
    item_kind ENUM('card', 'channel', 'person') NOT NULL DEFAULT 'card',
    icon_key VARCHAR(32) NOT NULL DEFAULT '',
    button_label VARCHAR(120) NULL,
    button_url VARCHAR(500) NULL,
    variant ENUM('primary', 'violet', 'neutral') NOT NULL DEFAULT 'primary',
    width ENUM('standard', 'wide') NOT NULL DEFAULT 'standard',
    sort_order INT UNSIGNED NOT NULL DEFAULT 10,
    is_visible TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_homepage_section_items_section
        FOREIGN KEY (section_id) REFERENCES homepage_sections(id) ON DELETE CASCADE,
    CONSTRAINT fk_homepage_section_items_page
        FOREIGN KEY (page_id) REFERENCES core_pages(id) ON DELETE SET NULL,
    INDEX idx_homepage_section_items_page (page_id),
    INDEX idx_homepage_section_items_order (section_id, is_visible, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO homepage_sections
    (section_key, section_type, eyebrow, title, content_html, layout, button_label, button_url, sort_order, author_id)
SELECT
    seed.section_key,
    seed.section_type,
    seed.eyebrow,
    seed.title,
    seed.content_html,
    seed.layout,
    seed.button_label,
    seed.button_url,
    seed.sort_order,
    users.id
FROM (
    SELECT
        'top' AS section_key,
        'hero' AS section_type,
        'Minecraft / Discord / Android / Backend' AS eyebrow,
        'Code that powers communities.' AS title,
        '<p>We build server plugins, Discord automation, mobile apps and modular web systems that can grow without rewriting everything from scratch.</p>' AS content_html,
        'split' AS layout,
        'Explore projects' AS button_label,
        '#projects' AS button_url,
        10 AS sort_order
    UNION ALL
    SELECT
        'projects',
        'content',
        '01 / Featured work',
        'Independent projects. One shared quality standard.',
        '<h3>PunisherX</h3><p>A moderation system for Paper and Folia: punishments, action history, permissions and API.</p><h3>SyntaxCore</h3><p>A shared library for messages, configuration, logging and integrations.</p><h3>Econizer</h3><p>A Discord bot connecting community economy, tasks, shop and a web panel.</p><h3>miniPORTAL</h3><p>Plain PHP, swappable themes, local ACL and independent content modules.</p>',
        'columns',
        '',
        '',
        20
    UNION ALL
    SELECT
        'stack',
        'content',
        '02 / Technology',
        'We choose tools for the problem.',
        '<h3>Paper & Folia</h3><p>Kotlin, Adventure and modern server environments.</p><h3>Discord & OAuth</h3><p>Bots, federated sign-in, ACL and API integrations.</p><h3>PHP & CrudApp</h3><p>PHP 8.4+, Medoo, MySQL and a swappable Theme layer.</p>',
        'columns',
        '',
        '',
        30
    UNION ALL
    SELECT
        'contact',
        'cta',
        'Contact and support',
        'Let us stay in touch.',
        '<p>Choose the best channel for the topic: Discord for quick contact, GitHub for code and issues, or e-mail for direct conversation.</p>',
        'contact',
        '',
        '',
        40
) AS seed
CROSS JOIN (
    SELECT id FROM users ORDER BY id ASC LIMIT 1
) AS users;

INSERT INTO homepage_section_items
    (section_id, label, title, content, item_kind, icon_key, button_label, button_url, variant, width, sort_order)
SELECT id, 'PROJECT / 001', 'PunisherX',
    'A moderation system for Paper and Folia: punishments, action history, permissions and API.',
    'card', '', 'Ask about this project', '#contact', 'primary', 'wide', 10
FROM homepage_sections WHERE section_key = 'projects'
UNION ALL
SELECT id, 'PROJECT / 002', 'SyntaxCore',
    'A shared library for messages, configuration, logging and integrations.',
    'card', '', 'View the foundations', '#contact', 'violet', 'standard', 20
FROM homepage_sections WHERE section_key = 'projects'
UNION ALL
SELECT id, 'PROJECT / 003', 'Econizer',
    'A Discord bot connecting community economy, tasks, shop and a web panel.',
    'card', '', 'Explore features', '#contact', 'violet', 'standard', 30
FROM homepage_sections WHERE section_key = 'projects'
UNION ALL
SELECT id, 'PROJECT / 004', 'miniPORTAL',
    'Plain PHP, swappable themes, local ACL and independent content modules.',
    'card', '', 'Discover the system', '#contact', 'primary', 'wide', 40
FROM homepage_sections WHERE section_key = 'projects'
UNION ALL
SELECT id, 'SERVERS', 'Paper & Folia',
    'Kotlin, Adventure and modern server environments.',
    'card', '', '', '', 'primary', 'standard', 10
FROM homepage_sections WHERE section_key = 'stack'
UNION ALL
SELECT id, 'AUTOMATION', 'Discord & OAuth',
    'Bots, federated sign-in, ACL and API integrations.',
    'card', '', '', '', 'primary', 'standard', 20
FROM homepage_sections WHERE section_key = 'stack'
UNION ALL
SELECT id, 'WEB', 'PHP & CrudApp',
    'PHP 8.4+, Medoo, MySQL and a swappable Theme layer.',
    'card', '', '', '', 'primary', 'standard', 30
FROM homepage_sections WHERE section_key = 'stack'
UNION ALL
SELECT id, 'DISCORD', 'SyntaxDevTeam.pl/Discord',
    'Quick contact, discussions and community updates.',
    'channel', 'discord', 'Join', 'https://syntaxdevteam.pl/discord', 'violet', 'standard', 10
FROM homepage_sections WHERE section_key = 'contact'
UNION ALL
SELECT id, 'GITHUB', 'SyntaxDevTeam',
    'Repozytoria, zgłoszenia, pull requesty i historia rozwoju.',
    'channel', 'github', 'Repozytoria', 'https://github.com/SyntaxDevTeam', 'neutral', 'standard', 20
FROM homepage_sections WHERE section_key = 'contact'
UNION ALL
SELECT id, 'E-MAIL', 'Zespół SyntaxDevTeam',
    'Bezpośredni kontakt w sprawie projektu lub współpracy.',
    'person', 'mail', 'Napisz', 'mailto:contact@syntaxdevteam.pl', 'primary', 'standard', 30
FROM homepage_sections WHERE section_key = 'contact';

UPDATE homepage_sections
SET content_html = '<p>Each product is a separate module, built on proven foundations.</p>'
WHERE section_key = 'projects';

UPDATE homepage_sections
SET content_html = '<p>No unnecessary abstraction, with a focus on security and maintainability.</p>'
WHERE section_key = 'stack';
