DROP TABLE IF EXISTS gallery_photos;
DROP TABLE IF EXISTS gallery_categories;
DELETE FROM role_permissions WHERE permission_id IN (SELECT id FROM permissions WHERE name='gallery.manage');
DELETE FROM permissions WHERE name='gallery.manage';
