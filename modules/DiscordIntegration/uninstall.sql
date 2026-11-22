DROP TABLE IF EXISTS discord_integration_outbox;
DELETE rp FROM role_permissions rp JOIN permissions p ON p.id=rp.permission_id WHERE p.name='discord.manage';
DELETE FROM permissions WHERE name='discord.manage';
