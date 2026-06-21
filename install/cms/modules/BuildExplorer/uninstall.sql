DROP TABLE IF EXISTS project_builds;
DELETE role_permissions FROM role_permissions
JOIN permissions ON permissions.id = role_permissions.permission_id
WHERE permissions.name LIKE 'builds.%';
DELETE FROM permissions WHERE name LIKE 'builds.%';
