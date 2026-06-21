DELETE role_permissions
FROM role_permissions
JOIN permissions ON permissions.id = role_permissions.permission_id
WHERE permissions.name = 'team.manage';

DELETE FROM permissions WHERE name = 'team.manage';

DROP TABLE IF EXISTS team_members;
