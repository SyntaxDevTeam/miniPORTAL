CREATE TABLE homepage_section_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    section_id BIGINT UNSIGNED NOT NULL,
    label VARCHAR(120) NULL,
    title VARCHAR(180) NOT NULL,
    content TEXT NOT NULL,
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
    INDEX idx_homepage_section_items_order (section_id, is_visible, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO homepage_section_items
    (section_id, label, title, content, button_label, button_url, variant, width, sort_order)
SELECT id, 'PROJECT / 001', 'PunisherX',
    'System moderacji dla Paper i Folia: kary, historia działań, uprawnienia oraz API.',
    'Zapytaj o projekt', '#contact', 'primary', 'wide', 10
FROM homepage_sections WHERE section_key = 'projects'
UNION ALL
SELECT id, 'PROJECT / 002', 'SyntaxCore',
    'Wspólna biblioteka komunikatów, konfiguracji, logowania i integracji.',
    'Zobacz fundamenty', '#contact', 'violet', 'standard', 20
FROM homepage_sections WHERE section_key = 'projects'
UNION ALL
SELECT id, 'PROJECT / 003', 'Econify',
    'Bot Discord łączący ekonomię społeczności, zadania, sklep i panel WWW.',
    'Sprawdź możliwości', '#contact', 'violet', 'standard', 30
FROM homepage_sections WHERE section_key = 'projects'
UNION ALL
SELECT id, 'PROJECT / 004', 'miniPORTAL',
    'Czysty PHP, wymienne motywy, lokalne ACL i niezależne moduły treści.',
    'Poznaj system', '#contact', 'primary', 'wide', 40
FROM homepage_sections WHERE section_key = 'projects'
UNION ALL
SELECT id, 'SERWERY', 'Paper & Folia',
    'Kotlin, Adventure i nowoczesne środowiska serwerowe.',
    '', '', 'primary', 'standard', 10
FROM homepage_sections WHERE section_key = 'stack'
UNION ALL
SELECT id, 'AUTOMATYZACJA', 'Discord & OAuth',
    'Boty, logowanie federacyjne, ACL i integracje API.',
    '', '', 'primary', 'standard', 20
FROM homepage_sections WHERE section_key = 'stack'
UNION ALL
SELECT id, 'WEB', 'PHP & CrudApp',
    'PHP 8.5, Medoo, MySQL i wymienna warstwa Theme.',
    '', '', 'primary', 'standard', 30
FROM homepage_sections WHERE section_key = 'stack';

UPDATE homepage_sections
SET content_html = '<p>Każdy produkt jest osobnym modułem, ale korzysta ze sprawdzonych fundamentów.</p>'
WHERE section_key = 'projects';

UPDATE homepage_sections
SET content_html = '<p>Bez zbędnej warstwy abstrakcji, z naciskiem na bezpieczeństwo i utrzymanie.</p>'
WHERE section_key = 'stack';
