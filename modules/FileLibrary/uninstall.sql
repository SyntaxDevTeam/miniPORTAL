DROP TABLE IF EXISTS file_library_article_links;
DROP TABLE IF EXISTS file_library_files;
DELETE FROM role_permissions WHERE permission_id IN (SELECT id FROM permissions WHERE name='files.manage');
DELETE FROM permissions WHERE name='files.manage';
