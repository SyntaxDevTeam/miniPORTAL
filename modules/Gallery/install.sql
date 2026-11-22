CREATE TABLE gallery_categories (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(80) NOT NULL UNIQUE,
 sort_order INT NOT NULL DEFAULT 100
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE gallery_photos (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 category_id BIGINT UNSIGNED NOT NULL,
 title VARCHAR(120) NOT NULL,
 description VARCHAR(500) NOT NULL DEFAULT '',
 stored_name VARCHAR(64) NOT NULL,
 status VARCHAR(16) NOT NULL DEFAULT 'pending',
 moderation_note VARCHAR(500) NOT NULL DEFAULT '',
 revision INT UNSIGNED NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 moderated_at DATETIME NULL,
 moderated_by BIGINT UNSIGNED NULL,
 INDEX gallery_public (status, category_id, id),
 INDEX gallery_owner (user_id, id),
 CONSTRAINT gallery_category_fk FOREIGN KEY (category_id) REFERENCES gallery_categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT INTO gallery_categories (name, sort_order) VALUES ('Konie i stajnie',10),('Budowle',20),('Wydarzenia',30),('Krajobrazy',40),('Chwile z serwera',50);
INSERT IGNORE INTO permissions (name,label) VALUES ('gallery.manage','Zatwierdzanie zdjęć i zarządzanie galerią');
INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT r.id,p.id FROM roles r CROSS JOIN permissions p WHERE r.name IN ('owner','administrator') AND p.name='gallery.manage';
