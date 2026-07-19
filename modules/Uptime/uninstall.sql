DELETE role_permissions
FROM role_permissions
JOIN permissions ON permissions.id = role_permissions.permission_id
WHERE permissions.name = 'uptime.manage';

DELETE FROM permissions WHERE name = 'uptime.manage';

DROP TABLE IF EXISTS uptime_monitors;
