DELETE role_permissions
FROM role_permissions
JOIN permissions ON permissions.id = role_permissions.permission_id
WHERE permissions.name LIKE 'wikipedia.%';

DELETE FROM permissions WHERE name LIKE 'wikipedia.%';

DROP TABLE IF EXISTS wiki_pages;
DROP TABLE IF EXISTS wiki_projects;
