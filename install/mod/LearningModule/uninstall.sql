DELETE role_permissions
FROM role_permissions
JOIN permissions ON permissions.id = role_permissions.permission_id
WHERE permissions.name IN ('learning.view', 'learning.manage');

DELETE FROM permissions
WHERE name IN ('learning.view', 'learning.manage');

DROP TABLE IF EXISTS learning_entries;
