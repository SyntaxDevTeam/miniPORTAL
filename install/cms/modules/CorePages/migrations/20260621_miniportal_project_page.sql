INSERT INTO core_pages
    (title, slug, eyebrow, summary, meta_description, content, content_format,
     page_type, navigation_area, navigation_label, sort_order, status, author_id, published_at)
SELECT
    'miniPORTAL',
    'miniportal',
    'PROJEKT / MINIPORTAL',
    'Modułowy mini-CMS SyntaxDevTeam napisany w PHP 8.4+, z wymiennymi motywami, lokalnym ACL, OAuth i niezależnymi modułami treści.',
    'Poznaj miniPORTAL: modułowy CMS SyntaxDevTeam w PHP 8.4+ z panelem administracyjnym, OAuth, ACL, audytem, wymiennymi motywami i instalatorem.',
    '# miniPORTAL

miniPORTAL to autorski, modułowy system zarządzania treścią rozwijany przez SyntaxDevTeam. Powstał dla serwisów projektowych, dokumentacji, zespołów i publikacji buildów, które potrzebują lekkiego panelu bez ciężkiego frameworka aplikacyjnego.

System działa na PHP 8.4 lub nowszym i MySQL/MariaDB. Warstwa prezentacji pozostaje oddzielona od modułów, dzięki czemu motyw można zmienić bez przenoszenia logiki biznesowej do HTML.

## Najważniejsze możliwości

- [x] Modułowe sekcje strony głównej, podstrony i kontrolowany edytor WYSIWYG lub Markdown
- [x] Artykuły, dokumentacja projektowa, katalog projektów i publiczne profile zespołu
- [x] Build Explorer dla kanałów Release, Snapshot, Dev i WIP z importem danych z CI
- [x] Logowanie przez GitHub, Discord i Google z lokalnymi kontami oraz wieloma tożsamościami
- [x] Role Owner, Administrator, Maintainer, Redaktor, Audytor i Support z granularnym ACL
- [x] Audit log, eksport CSV, retencja zdarzeń oraz chroniona diagnostyka systemowa
- [x] Manager modułów z migracjami, kontrolą SHA-256, podpisami RSA i kwarantanną pakietów
- [x] Dwa aktywne motywy, responsywny panel, metadane SEO, social cards i dane schema.org
- [x] Kreator instalacji, który konfiguruje bazę, OAuth, moduły i pierwszego Ownera

## Architektura

miniPORTAL zachowuje wyraźny podział odpowiedzialności:

1. **Core** odpowiada za routing, bezpieczeństwo, bazę danych, cache, rejestry i kontrakty.
2. **Modules** przechowują dane i logikę funkcjonalną bez zależności od konkretnego HTML lub frameworka CSS.
3. **Templates** renderują stronę publiczną i panel przez wspólny kontrakt `ThemeInterface`.

Moduły deklarują wersje, zależności, uprawnienia oraz pliki migracji w `info.json`. Manager uruchamia je w kolejności topologicznej i izoluje błędny pakiet, aby pojedyncze rozszerzenie nie wyłączało całego panelu.

## Bezpieczeństwo

- OAuth Authorization Code, jednorazowy `state`, PKCE i rotacja sesji po zalogowaniu
- tokeny CSRF dla operacji zmieniających stan
- przygotowane zapytania i fasada `CrudApp` oparta na Medoo
- CSP, nagłówki ochronne, kodowanie HTML i kontrolowana sanitizacja treści
- lokalne role i uprawnienia niezależne od danych dostawcy logowania
- ochrona ostatniego aktywnego Ownera przed blokadą lub degradacją
- audyt logowań, zmian administracyjnych i operacji managera modułów

## Moduły serwisu

Instalacja może udostępniać między innymi:

- **Core Pages** - podstrony oraz sekcje strony głównej
- **Wikipedia** - dokumentację podzieloną na projekty i strony
- **Projects** - katalog projektów powiązany ze stronami i dokumentacją
- **Build Explorer** - wersje, buildy CI, sumy SHA-256 i kontrolowane pobieranie
- **Team i User Profile** - publiczne profile oraz ustawienia kont użytkowników
- **Plugin Translator** - społecznościowe tłumaczenie i weryfikację plików YAML
- **Database Manager** - chroniony podgląd i kontrolowane operacje bazodanowe

## Instalacja

Czysta dystrybucja zawiera kreator WWW. Po wgraniu plików na serwer administrator otwiera `install.php`, podaje dane strony, pustej bazy MySQL i aplikacji GitHub OAuth, a następnie wybiera moduły. Kreator uruchamia migracje, tworzy pierwszego Ownera, generuje sekrety i blokuje ponowną instalację.

Wymagane są PHP 8.4+, rozszerzenie `pdo_mysql`, MySQL lub MariaDB oraz HTTPS w środowisku produkcyjnym.

## Technologie

- PHP 8.4+
- MySQL lub MariaDB
- Medoo i `CrudApp`
- HTML5, CSS3 i Bootstrap 5
- JavaScript bez frameworka aplikacyjnego
- OAuth 2.0 oraz OpenID Connect

## Rozwój

miniPORTAL jest rozwijany jako zaplecze serwisów SyntaxDevTeam, ale jego architektura pozostaje przygotowana do niezależnych instalacji i dalszego rozszerzania. Kod projektu i historia zmian są dostępne w repozytorium [SyntaxDevTeam/miniPORTAL](https://github.com/SyntaxDevTeam/miniPORTAL).

## Kontakt

Pytania, propozycje modułów i zgłoszenia można kierować przez [SyntaxDevTeam](https://syntaxdevteam.pl) oraz kanały kontaktowe zespołu.',
    'markdown',
    'project',
    'none',
    'miniPORTAL',
    400,
    'published',
    owner.id,
    CURRENT_TIMESTAMP
FROM (
    SELECT users.id
    FROM users
    INNER JOIN user_roles ON user_roles.user_id = users.id
    INNER JOIN roles ON roles.id = user_roles.role_id
    WHERE users.status = 'active' AND roles.name = 'owner'
    ORDER BY users.id ASC
    LIMIT 1
) AS owner
WHERE NOT EXISTS (
    SELECT 1 FROM core_pages WHERE slug = 'miniportal'
);
