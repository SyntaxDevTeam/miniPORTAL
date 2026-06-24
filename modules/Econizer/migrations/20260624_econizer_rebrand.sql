RENAME TABLE
    econify_features TO econizer_features,
    econify_platform_settings TO econizer_platform_settings,
    econify_guilds TO econizer_guilds,
    econify_memberships TO econizer_memberships,
    econify_wallets TO econizer_wallets,
    econify_transactions TO econizer_transactions,
    econify_shop_items TO econizer_shop_items,
    econify_shop_orders TO econizer_shop_orders,
    econify_market_assets TO econizer_market_assets,
    econify_market_quotes TO econizer_market_quotes,
    econify_market_holdings TO econizer_market_holdings;

UPDATE permissions
SET
    name = CASE name
        WHEN 'econify.view' THEN 'econizer.view'
        WHEN 'econify.platform.manage' THEN 'econizer.platform.manage'
        ELSE name
    END,
    label = REPLACE(label, 'Econify', 'Econizer')
WHERE name IN ('econify.view', 'econify.platform.manage');
