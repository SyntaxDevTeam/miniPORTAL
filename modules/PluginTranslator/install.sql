INSERT IGNORE INTO permissions (name, label) VALUES
    ('plugin_translator.use', 'Używanie translatora plików YAML pluginów');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT roles.id, permissions.id
FROM roles
JOIN permissions ON permissions.name = 'plugin_translator.use'
WHERE roles.name = 'administrator';
