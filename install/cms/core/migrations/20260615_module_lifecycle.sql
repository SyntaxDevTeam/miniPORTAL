ALTER TABLE modules_config
    MODIFY COLUMN status ENUM('discovered', 'active', 'disabled', 'uninstalled')
        NOT NULL DEFAULT 'discovered',
    ADD COLUMN data_preserved TINYINT(1) NOT NULL DEFAULT 0 AFTER is_protected;
