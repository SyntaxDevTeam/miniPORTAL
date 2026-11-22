CREATE TABLE file_library_files (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    storage_key VARCHAR(64) NOT NULL UNIQUE,
    original_name VARCHAR(180) NOT NULL,
    size_bytes BIGINT UNSIGNED NOT NULL,
    sha256 CHAR(64) NOT NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    deleted_at DATETIME NULL,
    INDEX idx_file_library_visible (deleted_at,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE file_library_article_links (
    file_id BIGINT UNSIGNED NOT NULL,
    article_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (file_id,article_id),
    INDEX idx_file_library_article (article_id,file_id),
    CONSTRAINT fk_file_library_file FOREIGN KEY (file_id) REFERENCES file_library_files(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT IGNORE INTO permissions (name,label) VALUES ('files.manage','Biblioteka plików: upload, pobieranie prywatne, powiązania i usuwanie');
INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT roles.id,permissions.id FROM roles CROSS JOIN permissions
WHERE roles.name IN ('owner','administrator') AND permissions.name='files.manage';
