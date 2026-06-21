INSERT IGNORE INTO permissions (name, label) VALUES
    ('database.view', 'Podgląd struktury bazy danych');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT roles.id, permissions.id
FROM roles
JOIN permissions ON permissions.name = 'database.view'
WHERE roles.name = 'administrator';
