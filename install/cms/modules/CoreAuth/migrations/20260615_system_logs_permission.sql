INSERT IGNORE INTO permissions (name, label) VALUES
    ('logs.view', 'Podgląd dziennika zdarzeń');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT roles.id, permissions.id
FROM roles
JOIN permissions ON permissions.name = 'logs.view'
WHERE roles.name = 'administrator';
