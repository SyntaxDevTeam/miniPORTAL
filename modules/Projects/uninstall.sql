DROP TABLE IF EXISTS projects;
DROP TABLE IF EXISTS project_categories;
DELETE role_permissions FROM role_permissions
JOIN permissions ON permissions.id = role_permissions.permission_id
WHERE permissions.name LIKE 'projects.%';
DELETE FROM permissions WHERE name LIKE 'projects.%';
