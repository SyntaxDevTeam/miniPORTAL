DELETE role_permissions FROM role_permissions JOIN permissions ON permissions.id = role_permissions.permission_id WHERE permissions.name = 'cooperation.manage';
DELETE FROM permissions WHERE name = 'cooperation.manage';
DROP TABLE IF EXISTS cooperation_partners;
DROP TABLE IF EXISTS cooperation_settings;
