INSERT IGNORE INTO permissions (name, label) VALUES
    ('database.manage', 'Zarządzanie bazą danych w Managerze SQL');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT roles.id, permissions.id
FROM roles
JOIN permissions ON permissions.name = 'database.manage'
WHERE roles.name = 'administrator';
