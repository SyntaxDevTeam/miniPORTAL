CREATE TABLE widgets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    widget_key VARCHAR(64) NOT NULL,
    name VARCHAR(160) NOT NULL,
    widget_type ENUM('terminal', 'card') NOT NULL DEFAULT 'card',
    placement ENUM('homepage_start', 'hero_aside', 'after_hero', 'before_section', 'after_section', 'before_footer') NOT NULL DEFAULT 'before_footer',
    target_section_key VARCHAR(64) NOT NULL DEFAULT '',
    theme_name VARCHAR(64) NOT NULL DEFAULT '*',
    title VARCHAR(180) NOT NULL DEFAULT '',
    content TEXT NOT NULL,
    content_format ENUM('html', 'markdown') NOT NULL DEFAULT 'html',
    button_label VARCHAR(120) NOT NULL DEFAULT '',
    button_url VARCHAR(500) NOT NULL DEFAULT '',
    sort_order INT UNSIGNED NOT NULL DEFAULT 100,
    is_visible TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_widgets_key_theme (widget_key, theme_name),
    INDEX idx_widgets_public (is_visible, theme_name, placement, sort_order),
    INDEX idx_widgets_section (target_section_key, placement)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO widgets
    (widget_key, name, widget_type, placement, theme_name, title, content, content_format, sort_order, is_visible)
VALUES
    ('syntax-terminal', 'Terminal SyntaxDevTeam', 'terminal', 'hero_aside', '*',
     'syntaxdevteam.pl/build',
     'Uruchamianie SyntaxDevTerminal...
CoreAuth          READY
CorePages         READY
ThemeEngine       ONLINE
SyntaxCrudApp     CONNECTED
architecture:     MODULAR
security:         ENABLED
status:           READY_TO_USE
Witaj w SyntaxDevTerminal 0.1.5. Wpisz help i naciśnij Enter, aby zobaczyć dostępne komendy.',
     'html', 10, 1);

INSERT IGNORE INTO permissions (name, label) VALUES
    ('widgets.manage', 'Zarządzanie widgetami publicznymi');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT roles.id, permissions.id
FROM roles
JOIN permissions ON permissions.name = 'widgets.manage'
WHERE roles.name IN ('administrator', 'maintainer', 'editor');
