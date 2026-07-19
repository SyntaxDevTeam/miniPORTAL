DELETE role_permissions
FROM role_permissions
JOIN permissions ON permissions.id = role_permissions.permission_id
WHERE permissions.name IN ('plugin_translator.use', 'plugin_translator.review');

DELETE FROM permissions WHERE name IN ('plugin_translator.use', 'plugin_translator.review');

DROP TABLE IF EXISTS plugin_translation_submissions;
DROP TABLE IF EXISTS plugin_translation_projects;
