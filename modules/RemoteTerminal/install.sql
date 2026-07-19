INSERT IGNORE INTO permissions (name, label) VALUES
    ('remote_terminal.access', 'Dostęp do prywatnego terminala SSH');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT roles.id, permissions.id
FROM roles
JOIN permissions ON permissions.name = 'remote_terminal.access'
WHERE roles.name IN ('owner', 'administrator');
