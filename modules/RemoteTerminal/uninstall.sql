DELETE role_permissions
FROM role_permissions
JOIN permissions ON permissions.id = role_permissions.permission_id
WHERE permissions.name = 'remote_terminal.access';

DELETE FROM permissions WHERE name = 'remote_terminal.access';
