INSERT IGNORE INTO permissions (name, label) VALUES
    ('roles.view', 'Podgląd ról i uprawnień'),
    ('roles.manage', 'Zarządzanie rolami i uprawnieniami');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT roles.id, permissions.id
FROM roles
JOIN permissions ON permissions.name IN ('roles.view', 'roles.manage')
WHERE roles.name = 'administrator';
