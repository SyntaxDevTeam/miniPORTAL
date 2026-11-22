CREATE TABLE help_center_categories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description VARCHAR(240) NOT NULL DEFAULT '',
    icon VARCHAR(16) NOT NULL DEFAULT '✦',
    sort_order INT UNSIGNED NOT NULL DEFAULT 100,
    is_visible TINYINT(1) NOT NULL DEFAULT 1,
    visibility VARCHAR(16) NOT NULL DEFAULT 'public',
    INDEX idx_help_category_visibility (visibility, is_visible, sort_order, id),
    INDEX idx_help_category_order (is_visible, sort_order, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE help_center_questions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id BIGINT UNSIGNED NOT NULL,
    question VARCHAR(240) NOT NULL,
    answer MEDIUMTEXT NOT NULL,
    content_format VARCHAR(16) NOT NULL DEFAULT 'html',
    keywords VARCHAR(500) NOT NULL DEFAULT '',
    sort_order INT UNSIGNED NOT NULL DEFAULT 100,
    is_published TINYINT(1) NOT NULL DEFAULT 0,
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    visibility VARCHAR(16) NOT NULL DEFAULT 'public',
    INDEX idx_help_question_visibility (visibility, is_published, category_id, sort_order, id),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_help_question_category FOREIGN KEY (category_id) REFERENCES help_center_categories(id) ON DELETE RESTRICT,
    INDEX idx_help_question_category (category_id, is_published, sort_order, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE help_center_settings (
    id TINYINT UNSIGNED PRIMARY KEY,
    title VARCHAR(100) NOT NULL,
    intro VARCHAR(700) NOT NULL,
    contact_url VARCHAR(500) NOT NULL DEFAULT '',
    contact_label VARCHAR(80) NOT NULL DEFAULT 'Skontaktuj się z nami'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT INTO help_center_settings (id, title, intro) VALUES (1, 'Jak możemy Ci pomóc?', 'Dobre odpowiedzi, bez szukania na oślep. Wybierz temat lub wpisz swoje pytanie.');
INSERT INTO help_center_categories (name, description, icon, sort_order) VALUES
('Pierwsze kroki', 'Wszystko, czego potrzebujesz na dobry początek.', '✦', 10),
('Konto i dostęp', 'Logowanie, profil i Twoje bezpieczeństwo.', '◎', 20),
('Pomoc i kontakt', 'Gdy potrzebujesz dodatkowego wsparcia.', '♡', 30);
INSERT IGNORE INTO permissions (name, label) VALUES ('help_center.manage', 'Zarządzanie Centrum pomocy');
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT roles.id, permissions.id FROM roles CROSS JOIN permissions
WHERE roles.name IN ('administrator', 'editor', 'maintainer') AND permissions.name = 'help_center.manage';

INSERT IGNORE INTO permissions (name, label) VALUES ('help_center.staff.read', 'Odczyt FAQ administracji serwera');
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT roles.id, permissions.id FROM roles CROSS JOIN permissions
WHERE roles.name = 'administrator' AND permissions.name = 'help_center.staff.read';
