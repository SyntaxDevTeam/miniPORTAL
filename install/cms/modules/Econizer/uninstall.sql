DELETE role_permissions FROM role_permissions
JOIN permissions ON permissions.id = role_permissions.permission_id
WHERE permissions.name IN ('econizer.view', 'econizer.platform.manage');
DELETE FROM permissions WHERE name IN ('econizer.view', 'econizer.platform.manage');
DROP TABLE IF EXISTS econizer_market_holdings;
DROP TABLE IF EXISTS econizer_market_quotes;
DROP TABLE IF EXISTS econizer_market_assets;
DROP TABLE IF EXISTS econizer_shop_orders;
DROP TABLE IF EXISTS econizer_shop_items;
DROP TABLE IF EXISTS econizer_transactions;
DROP TABLE IF EXISTS econizer_wallets;
DROP TABLE IF EXISTS econizer_memberships;
DROP TABLE IF EXISTS econizer_guilds;
DROP TABLE IF EXISTS econizer_platform_settings;
DROP TABLE IF EXISTS econizer_features;
