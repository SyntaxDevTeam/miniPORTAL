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

CREATE TABLE homepage_sections (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    section_key VARCHAR(64) NOT NULL,
    section_type ENUM('hero', 'content', 'cta') NOT NULL DEFAULT 'content',
    eyebrow VARCHAR(160) NULL,
    title VARCHAR(220) NOT NULL,
    content_html MEDIUMTEXT NOT NULL,
    layout ENUM('full', 'split', 'columns', 'accent') NOT NULL DEFAULT 'full',
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
        'Kod, który zasila społeczności.' AS title,
        '<p>Projektujemy pluginy serwerowe, automatyzacje Discord, aplikacje mobilne i modułowe systemy WWW, które można rozwijać bez przepisywania wszystkiego od początku.</p>' AS content_html,
        'split' AS layout,
        'Poznaj projekty' AS button_label,
        '#projects' AS button_url,
        10 AS sort_order
    UNION ALL
    SELECT
        'projects',
        'content',
        '01 / Wybrane realizacje',
        'Niezależne projekty. Wspólny standard jakości.',
        '<h3>PunisherX</h3><p>System moderacji dla Paper i Folia: kary, historia działań, uprawnienia oraz API.</p><h3>SyntaxCore</h3><p>Wspólna biblioteka komunikatów, konfiguracji, logowania i integracji.</p><h3>Econify</h3><p>Bot Discord łączący ekonomię społeczności, zadania, sklep i panel WWW.</p><h3>miniPORTAL</h3><p>Czysty PHP, wymienne motywy, lokalne ACL i niezależne moduły treści.</p>',
        'columns',
        '',
        '',
        20
    UNION ALL
    SELECT
        'stack',
        'content',
        '02 / Technologie',
        'Dobieramy narzędzia do problemu.',
        '<h3>Paper & Folia</h3><p>Kotlin, Adventure i nowoczesne środowiska serwerowe.</p><h3>Discord & OAuth</h3><p>Boty, logowanie federacyjne, ACL i integracje API.</p><h3>PHP & CrudApp</h3><p>PHP 8.5, Medoo, MySQL i wymienna warstwa Theme.</p>',
        'columns',
        '',
        '',
        30
    UNION ALL
    SELECT
        'contact',
        'cta',
        '03 / Kontakt',
        'Zbudujmy coś użytecznego.',
        '<p>Plugin, bot, aplikacja czy system WWW - zacznijmy od konkretnego problemu.</p>',
        'accent',
        'contact@syntaxdevteam.pl',
        'mailto:contact@syntaxdevteam.pl',
        40
) AS seed
CROSS JOIN (
    SELECT id FROM users ORDER BY id ASC LIMIT 1
) AS users;
