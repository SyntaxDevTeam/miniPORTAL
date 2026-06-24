ALTER TABLE econizer_features
    DROP FOREIGN KEY fk_econify_features_user;
ALTER TABLE econizer_features
    RENAME INDEX fk_econify_features_user TO fk_econizer_features_user,
    RENAME INDEX idx_econify_features_order TO idx_econizer_features_order;
ALTER TABLE econizer_features
    ADD CONSTRAINT fk_econizer_features_user
        FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE econizer_platform_settings
    DROP FOREIGN KEY fk_econify_platform_settings_user;
ALTER TABLE econizer_platform_settings
    RENAME INDEX fk_econify_platform_settings_user TO fk_econizer_platform_settings_user;
ALTER TABLE econizer_platform_settings
    ADD CONSTRAINT fk_econizer_platform_settings_user
        FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE econizer_guilds
    DROP FOREIGN KEY fk_econify_guilds_owner;
ALTER TABLE econizer_guilds
    RENAME INDEX idx_econify_guilds_owner TO idx_econizer_guilds_owner;
ALTER TABLE econizer_guilds
    ADD CONSTRAINT fk_econizer_guilds_owner
        FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE econizer_memberships
    DROP FOREIGN KEY fk_econify_memberships_guild,
    DROP FOREIGN KEY fk_econify_memberships_user;
ALTER TABLE econizer_memberships
    RENAME INDEX uq_econify_membership_user TO uq_econizer_membership_user,
    RENAME INDEX uq_econify_membership_discord TO uq_econizer_membership_discord,
    RENAME INDEX idx_econify_memberships_user TO idx_econizer_memberships_user;
ALTER TABLE econizer_memberships
    ADD CONSTRAINT fk_econizer_memberships_guild
        FOREIGN KEY (guild_id) REFERENCES econizer_guilds(id) ON DELETE CASCADE,
    ADD CONSTRAINT fk_econizer_memberships_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;

ALTER TABLE econizer_wallets
    DROP FOREIGN KEY fk_econify_wallets_guild,
    DROP FOREIGN KEY fk_econify_wallets_user;
ALTER TABLE econizer_wallets
    RENAME INDEX fk_econify_wallets_user TO fk_econizer_wallets_user,
    RENAME INDEX idx_econify_wallets_ranking TO idx_econizer_wallets_ranking;
ALTER TABLE econizer_wallets
    ADD CONSTRAINT fk_econizer_wallets_guild
        FOREIGN KEY (guild_id) REFERENCES econizer_guilds(id) ON DELETE CASCADE,
    ADD CONSTRAINT fk_econizer_wallets_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;

ALTER TABLE econizer_transactions
    DROP FOREIGN KEY fk_econify_transactions_guild,
    DROP FOREIGN KEY fk_econify_transactions_user;
ALTER TABLE econizer_transactions
    RENAME INDEX uq_econify_transaction_reference TO uq_econizer_transaction_reference,
    RENAME INDEX fk_econify_transactions_user TO fk_econizer_transactions_user,
    RENAME INDEX idx_econify_transactions_user TO idx_econizer_transactions_user;
ALTER TABLE econizer_transactions
    ADD CONSTRAINT fk_econizer_transactions_guild
        FOREIGN KEY (guild_id) REFERENCES econizer_guilds(id) ON DELETE CASCADE,
    ADD CONSTRAINT fk_econizer_transactions_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;

ALTER TABLE econizer_shop_items
    DROP FOREIGN KEY fk_econify_shop_items_guild;
ALTER TABLE econizer_shop_items
    RENAME INDEX idx_econify_shop_catalog TO idx_econizer_shop_catalog;
ALTER TABLE econizer_shop_items
    ADD CONSTRAINT fk_econizer_shop_items_guild
        FOREIGN KEY (guild_id) REFERENCES econizer_guilds(id) ON DELETE CASCADE;

ALTER TABLE econizer_shop_orders
    DROP FOREIGN KEY fk_econify_orders_guild,
    DROP FOREIGN KEY fk_econify_orders_item,
    DROP FOREIGN KEY fk_econify_orders_user;
ALTER TABLE econizer_shop_orders
    RENAME INDEX fk_econify_orders_item TO fk_econizer_orders_item,
    RENAME INDEX fk_econify_orders_user TO fk_econizer_orders_user,
    RENAME INDEX idx_econify_orders_user TO idx_econizer_orders_user,
    RENAME INDEX idx_econify_orders_status TO idx_econizer_orders_status;
ALTER TABLE econizer_shop_orders
    ADD CONSTRAINT fk_econizer_orders_guild
        FOREIGN KEY (guild_id) REFERENCES econizer_guilds(id) ON DELETE CASCADE,
    ADD CONSTRAINT fk_econizer_orders_item
        FOREIGN KEY (item_id) REFERENCES econizer_shop_items(id) ON DELETE RESTRICT,
    ADD CONSTRAINT fk_econizer_orders_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;

ALTER TABLE econizer_market_assets
    DROP FOREIGN KEY fk_econify_assets_guild;
ALTER TABLE econizer_market_assets
    RENAME INDEX uq_econify_asset_symbol TO uq_econizer_asset_symbol;
ALTER TABLE econizer_market_assets
    ADD CONSTRAINT fk_econizer_assets_guild
        FOREIGN KEY (guild_id) REFERENCES econizer_guilds(id) ON DELETE CASCADE;

ALTER TABLE econizer_market_quotes
    DROP FOREIGN KEY fk_econify_quotes_asset;
ALTER TABLE econizer_market_quotes
    RENAME INDEX idx_econify_quotes_history TO idx_econizer_quotes_history;
ALTER TABLE econizer_market_quotes
    ADD CONSTRAINT fk_econizer_quotes_asset
        FOREIGN KEY (asset_id) REFERENCES econizer_market_assets(id) ON DELETE CASCADE;

ALTER TABLE econizer_market_holdings
    DROP FOREIGN KEY fk_econify_holdings_asset,
    DROP FOREIGN KEY fk_econify_holdings_user;
ALTER TABLE econizer_market_holdings
    RENAME INDEX fk_econify_holdings_user TO fk_econizer_holdings_user;
ALTER TABLE econizer_market_holdings
    ADD CONSTRAINT fk_econizer_holdings_asset
        FOREIGN KEY (asset_id) REFERENCES econizer_market_assets(id) ON DELETE CASCADE,
    ADD CONSTRAINT fk_econizer_holdings_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;
