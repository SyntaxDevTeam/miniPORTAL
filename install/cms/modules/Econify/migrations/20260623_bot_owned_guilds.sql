ALTER TABLE econify_guilds
    DROP FOREIGN KEY fk_econify_guilds_owner;

ALTER TABLE econify_guilds
    MODIFY COLUMN owner_user_id BIGINT UNSIGNED NULL;

ALTER TABLE econify_guilds
    ADD CONSTRAINT fk_econify_guilds_owner
        FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE SET NULL;
