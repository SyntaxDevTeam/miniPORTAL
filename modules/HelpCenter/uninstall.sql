DROP TABLE IF EXISTS help_center_questions;
DROP TABLE IF EXISTS help_center_categories;
DROP TABLE IF EXISTS help_center_settings;
DELETE FROM role_permissions WHERE permission_id IN (SELECT id FROM permissions WHERE name IN ('help_center.manage', 'help_center.staff.read'));
DELETE FROM permissions WHERE name IN ('help_center.manage', 'help_center.staff.read');
