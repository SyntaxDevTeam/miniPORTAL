DELETE role_permissions
FROM role_permissions
JOIN permissions ON permissions.id = role_permissions.permission_id
WHERE permissions.name = 'plugin_translator.use';

DELETE FROM permissions WHERE name = 'plugin_translator.use';
