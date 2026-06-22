DELETE role_permissions
FROM role_permissions
JOIN permissions ON permissions.id = role_permissions.permission_id
WHERE permissions.name LIKE 'articles.%';

DELETE FROM permissions WHERE name LIKE 'articles.%';

DROP TABLE IF EXISTS article_translations;
DROP TABLE IF EXISTS articles;
DROP TABLE IF EXISTS article_categories;
