DROP TABLE IF EXISTS media_assets;
DROP TABLE IF EXISTS media_optimization_usage;

DELETE role_permissions FROM role_permissions
JOIN permissions ON permissions.id = role_permissions.permission_id
WHERE permissions.name LIKE 'media.%';
DELETE FROM permissions WHERE name LIKE 'media.%';
