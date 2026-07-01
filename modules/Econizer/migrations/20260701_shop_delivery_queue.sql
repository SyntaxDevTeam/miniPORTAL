ALTER TABLE econizer_shop_items
    MODIFY delivery_type ENUM('discord_role', 'virtual_item', 'code', 'manual') NOT NULL DEFAULT 'manual';
