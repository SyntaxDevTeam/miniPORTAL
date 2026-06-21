CREATE TABLE admins (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    discord_user_id VARCHAR(32) NOT NULL UNIQUE,
    username VARCHAR(80) NOT NULL,
    global_name VARCHAR(120) NULL,
    avatar_url VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login_at TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(180) NOT NULL,
    slug VARCHAR(190) NOT NULL UNIQUE,
    excerpt TEXT NULL,
    content MEDIUMTEXT NOT NULL,
    is_published TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 100,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_pages_published_order (is_published, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO pages (title, slug, excerpt, content, sort_order) VALUES
(
    'Start',
    'start',
    'Mini-CMS dla publikacji o botach Discord, automatyzacji serwerow i backendzie.',
    '<p>To podstawowa instalacja Mini-CMSa w czystym PHP i MySQL. Mozesz zarzadzac stronami z panelu administracyjnego, publikowac artykuly i utrzymywac prosta baze wiedzy dla projektow zwiazanych z botami Discord.</p><h2>Co tu jest?</h2><p>Publiczny widok stron, panel logowania, CRUD dla podstron, bez frameworkow i bez bibliotek z vendora.</p>',
    10
),
(
    'Bot moderacyjny',
    'bot-moderacyjny',
    'Opis funkcji bota do automatycznej moderacji, logowania zdarzen i ochrony spolecznosci.',
    '<p>Bot moderacyjny moze reagowac na spam, linki, masowe wzmianki i podejrzane zachowania nowych uzytkownikow. Backend zapisuje ostrzezenia, blokady oraz dziennik akcji moderatorow.</p><h2>Podstawowe moduly</h2><ul><li>automatyczne filtry wiadomosci,</li><li>system warnow i mute,</li><li>logi kanalow, ról i banow,</li><li>panel konfiguracji per serwer.</li></ul>',
    20
),
(
    'Backend komend slash',
    'backend-komend-slash',
    'Jak zaplanowac backend obslugujacy komendy slash, kolejki zadan i webhooki Discorda.',
    '<p>Backend komend slash powinien szybko potwierdzac interakcje, a dluzsze operacje delegowac do kolejki. Dzieki temu bot nie przekracza limitow czasu Discorda i moze stabilnie obslugiwac wiele serwerow.</p><h2>Warstwy</h2><ul><li>endpoint interakcji HTTP,</li><li>walidacja podpisu Discorda,</li><li>kolejka zadan,</li><li>worker wykonujacy akcje,</li><li>repozytorium konfiguracji serwera.</li></ul>',
    30
),
(
    'Monitoring i logi',
    'monitoring-i-logi',
    'Praktyczne podejscie do obserwowalnosci bota: logi, metryki, alerty i retry.',
    '<p>Bot dzialajacy na wielu serwerach wymaga czytelnych logow i podstawowych metryk. Warto mierzyc czas odpowiedzi komend, liczbe bledow API, opoznienia kolejki oraz status shardow.</p><h2>Minimum produkcyjne</h2><ul><li>logi strukturalne dla zdarzen,</li><li>alerty dla wzrostu bledow,</li><li>retry z limitem prob,</li><li>dashboard statusu procesow.</li></ul>',
    40
);
