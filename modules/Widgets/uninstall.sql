DELETE role_permissions
FROM role_permissions
JOIN permissions ON permissions.id = role_permissions.permission_id
WHERE permissions.name = 'widgets.manage';

DELETE FROM permissions WHERE name = 'widgets.manage';

DROP TABLE IF EXISTS widgets;
