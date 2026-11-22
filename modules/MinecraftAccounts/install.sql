CREATE TABLE IF NOT EXISTS minecraft_account_profiles (
    user_id BIGINT UNSIGNED PRIMARY KEY,
    minecraft_uuid CHAR(36) NOT NULL,
    minecraft_username VARCHAR(16) NOT NULL,
    verified_at DATETIME NOT NULL,
    verification_method VARCHAR(32) NOT NULL DEFAULT 'microsoft_xbox',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_minecraft_account_profiles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uq_minecraft_account_profiles_uuid (minecraft_uuid),
    UNIQUE KEY uq_minecraft_account_profiles_username (minecraft_username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS minecraft_account_revocations (
    user_id BIGINT UNSIGNED PRIMARY KEY,
    revoked_at DATETIME NOT NULL,
    CONSTRAINT fk_minecraft_account_revocations_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
