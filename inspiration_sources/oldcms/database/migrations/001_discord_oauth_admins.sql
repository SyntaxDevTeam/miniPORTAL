ALTER TABLE admins
    DROP INDEX username,
    DROP COLUMN password_hash,
    ADD COLUMN discord_user_id VARCHAR(32) NULL UNIQUE AFTER id,
    ADD COLUMN global_name VARCHAR(120) NULL AFTER username,
    ADD COLUMN avatar_url VARCHAR(255) NULL AFTER global_name,
    ADD COLUMN last_login_at TIMESTAMP NULL DEFAULT NULL AFTER created_at;

-- Po migracji wpisz swoje Discord ID dla istniejacego konta admina, jesli chcesz zachowac ten rekord:
-- UPDATE admins SET discord_user_id = '123456789012345678' WHERE username = 'admin';
--
-- Opcjonalnie po uzupelnieniu wszystkich rekordow:
-- ALTER TABLE admins MODIFY discord_user_id VARCHAR(32) NOT NULL;
