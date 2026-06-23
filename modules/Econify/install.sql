CREATE TABLE econify_features (
    feature_key VARCHAR(64) PRIMARY KEY,
    label VARCHAR(120) NOT NULL,
    description VARCHAR(500) NOT NULL DEFAULT '',
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 100,
    updated_by BIGINT UNSIGNED NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_econify_features_user FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_econify_features_order (sort_order, feature_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE econify_platform_settings (
    id TINYINT UNSIGNED PRIMARY KEY,
    default_locale VARCHAR(8) NOT NULL DEFAULT 'pl',
    default_daily_amount BIGINT UNSIGNED NOT NULL DEFAULT 250,
    default_work_min BIGINT UNSIGNED NOT NULL DEFAULT 50,
    default_work_max BIGINT UNSIGNED NOT NULL DEFAULT 150,
    freemium_shop_limit SMALLINT UNSIGNED NOT NULL DEFAULT 5,
    updated_by BIGINT UNSIGNED NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_econify_platform_settings_user FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE econify_guilds (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    discord_guild_id VARCHAR(32) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    owner_user_id BIGINT UNSIGNED NULL,
    plan ENUM('freemium', 'premium') NOT NULL DEFAULT 'freemium',
    locale VARCHAR(8) NOT NULL DEFAULT 'pl',
    currency_name VARCHAR(40) NOT NULL DEFAULT 'kredyty',
    daily_amount BIGINT UNSIGNED NOT NULL DEFAULT 250,
    work_min BIGINT UNSIGNED NOT NULL DEFAULT 50,
    work_max BIGINT UNSIGNED NOT NULL DEFAULT 150,
    transfer_tax_bps SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    vip_role_id VARCHAR(32) NULL,
    vip_daily_amount BIGINT UNSIGNED NOT NULL DEFAULT 0,
    shop_enabled TINYINT(1) NOT NULL DEFAULT 1,
    market_enabled TINYINT(1) NOT NULL DEFAULT 1,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_econify_guilds_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_econify_guilds_owner (owner_user_id, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE econify_memberships (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    guild_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    discord_user_id VARCHAR(32) NOT NULL,
    access_role ENUM('guild_owner', 'guild_admin', 'player') NOT NULL DEFAULT 'player',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_econify_memberships_guild FOREIGN KEY (guild_id) REFERENCES econify_guilds(id) ON DELETE CASCADE,
    CONSTRAINT fk_econify_memberships_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uq_econify_membership_user (guild_id, user_id),
    UNIQUE KEY uq_econify_membership_discord (guild_id, discord_user_id),
    INDEX idx_econify_memberships_user (user_id, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE econify_wallets (
    guild_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    balance BIGINT UNSIGNED NOT NULL DEFAULT 0,
    experience BIGINT UNSIGNED NOT NULL DEFAULT 0,
    level SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (guild_id, user_id),
    CONSTRAINT fk_econify_wallets_guild FOREIGN KEY (guild_id) REFERENCES econify_guilds(id) ON DELETE CASCADE,
    CONSTRAINT fk_econify_wallets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_econify_wallets_ranking (guild_id, balance)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE econify_transactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    guild_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    transaction_type ENUM('daily', 'work', 'vip_daily', 'transfer_in', 'transfer_out', 'shop_purchase', 'market_buy', 'market_sell', 'adjustment') NOT NULL,
    amount BIGINT NOT NULL,
    balance_after BIGINT UNSIGNED NOT NULL,
    description VARCHAR(255) NOT NULL DEFAULT '',
    external_reference VARCHAR(96) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_econify_transactions_guild FOREIGN KEY (guild_id) REFERENCES econify_guilds(id) ON DELETE CASCADE,
    CONSTRAINT fk_econify_transactions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uq_econify_transaction_reference (guild_id, external_reference),
    INDEX idx_econify_transactions_user (guild_id, user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE econify_shop_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    guild_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(1000) NOT NULL DEFAULT '',
    price BIGINT UNSIGNED NOT NULL,
    stock INT UNSIGNED NULL,
    delivery_type ENUM('discord_role', 'code', 'manual') NOT NULL DEFAULT 'manual',
    delivery_reference VARCHAR(120) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_econify_shop_items_guild FOREIGN KEY (guild_id) REFERENCES econify_guilds(id) ON DELETE CASCADE,
    INDEX idx_econify_shop_catalog (guild_id, is_active, price)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE econify_shop_orders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    guild_id BIGINT UNSIGNED NOT NULL,
    item_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    price BIGINT UNSIGNED NOT NULL,
    status ENUM('pending', 'fulfilled', 'cancelled') NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fulfilled_at DATETIME NULL,
    CONSTRAINT fk_econify_orders_guild FOREIGN KEY (guild_id) REFERENCES econify_guilds(id) ON DELETE CASCADE,
    CONSTRAINT fk_econify_orders_item FOREIGN KEY (item_id) REFERENCES econify_shop_items(id) ON DELETE RESTRICT,
    CONSTRAINT fk_econify_orders_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_econify_orders_user (guild_id, user_id, created_at),
    INDEX idx_econify_orders_status (guild_id, status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE econify_market_assets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    guild_id BIGINT UNSIGNED NOT NULL,
    symbol VARCHAR(12) NOT NULL,
    name VARCHAR(80) NOT NULL,
    current_price BIGINT UNSIGNED NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_econify_assets_guild FOREIGN KEY (guild_id) REFERENCES econify_guilds(id) ON DELETE CASCADE,
    UNIQUE KEY uq_econify_asset_symbol (guild_id, symbol)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE econify_market_quotes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    asset_id BIGINT UNSIGNED NOT NULL,
    price BIGINT UNSIGNED NOT NULL,
    quoted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_econify_quotes_asset FOREIGN KEY (asset_id) REFERENCES econify_market_assets(id) ON DELETE CASCADE,
    INDEX idx_econify_quotes_history (asset_id, quoted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE econify_market_holdings (
    asset_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    quantity INT UNSIGNED NOT NULL DEFAULT 0,
    average_price BIGINT UNSIGNED NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (asset_id, user_id),
    CONSTRAINT fk_econify_holdings_asset FOREIGN KEY (asset_id) REFERENCES econify_market_assets(id) ON DELETE CASCADE,
    CONSTRAINT fk_econify_holdings_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO econify_features (feature_key, label, description, sort_order) VALUES
    ('economy', 'Ekonomia', 'Komendy daily, work, transfery i portfele graczy.', 10),
    ('shop', 'Sklep serwerowy', 'Przedmioty, rangi Discord i realizacja zamówień.', 20),
    ('market', 'Giełda', 'Wirtualne aktywa, notowania i portfel gracza.', 30),
    ('vip_daily', 'VIP Daily', 'Automatyczna premia dla wskazanej roli Discord.', 40);

INSERT INTO econify_platform_settings (id) VALUES (1);

INSERT IGNORE INTO permissions (name, label) VALUES
    ('econify.view', 'Dostęp do centrum Econify'),
    ('econify.platform.manage', 'Zarządzanie platformą Econify');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT roles.id, permissions.id FROM roles
JOIN permissions ON permissions.name IN ('econify.view', 'econify.platform.manage')
WHERE roles.name IN ('owner', 'administrator');
