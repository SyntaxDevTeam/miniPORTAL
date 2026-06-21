DELETE role_permissions FROM role_permissions
JOIN permissions ON permissions.id = role_permissions.permission_id
WHERE permissions.name IN ('econify.view', 'econify.platform.manage');
DELETE FROM permissions WHERE name IN ('econify.view', 'econify.platform.manage');
DROP TABLE IF EXISTS econify_market_holdings;
DROP TABLE IF EXISTS econify_market_quotes;
DROP TABLE IF EXISTS econify_market_assets;
DROP TABLE IF EXISTS econify_shop_orders;
DROP TABLE IF EXISTS econify_shop_items;
DROP TABLE IF EXISTS econify_transactions;
DROP TABLE IF EXISTS econify_wallets;
DROP TABLE IF EXISTS econify_memberships;
DROP TABLE IF EXISTS econify_guilds;
DROP TABLE IF EXISTS econify_platform_settings;
DROP TABLE IF EXISTS econify_features;
