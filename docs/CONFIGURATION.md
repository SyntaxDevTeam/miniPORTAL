# Konfiguracja środowiska

miniPORTAL odczytuje konfigurację z `config/config.php`. Wartości pochodzą najpierw ze
zmiennych procesu Apache/PHP, a następnie z pliku środowiskowego.

## Zalecane miejsce na serwerze

Dla `new.syntaxdevteam.pl` użyj pliku poza publicznym katalogiem strony:

```text
/etc/miniportal/miniportal.env
```

Nie umieszczaj prawdziwych haseł w `.env.example`, `config/config.php` ani innym pliku
śledzonym przez Git.

Utworzenie konfiguracji:

```bash
sudo install -d -m 750 -o root -g www-data /etc/miniportal
sudo install -m 640 -o root -g www-data \
  /var/www/syntaxdevteam.pl/new/.env.example \
  /etc/miniportal/miniportal.env
sudo editor /etc/miniportal/miniportal.env
sudo systemctl reload apache2
```

Domyślna ścieżka nie wymaga zmian w VirtualHost. Aby użyć innego miejsca, dodaj w obu
VirtualHostach domeny:

```apache
SetEnv MINIPORTAL_ENV_FILE /inna/sciezka/miniportal.env
```

Następnie sprawdź i przeładuj Apache:

```bash
sudo apache2ctl configtest
sudo systemctl reload apache2
```

## Pełny przykład

Każda wartość pliku INI musi mieścić się w jednej linii. Cudzysłów wewnątrz
wartości zapisuj jako `\"`; nie przenoś opisu ani tytułu do kolejnego wiersza,
ponieważ PHP odrzuci wtedy cały plik środowiskowy.

```dotenv
APP_NAME="miniPORTAL"
APP_DEBUG=false
APP_TIMEZONE="Europe/Warsaw"
APP_THEME="default"
SITE_URL="https://syntaxdevteam.pl"
SITE_NAME="SyntaxDevTeam"
SITE_DEFAULT_TITLE="SyntaxDevTeam - software dla serwerów, społeczności i urządzeń"
SITE_EYEBROW="Software dla społeczności"
SITE_META_DESCRIPTION="SyntaxDevTeam tworzy pluginy Minecraft, boty Discord, aplikacje Android i narzędzia backendowe."
SITE_META_KEYWORDS="SyntaxDevTeam, miniPORTAL, pluginy Minecraft"
SITE_META_AUTHOR="SyntaxDevTeam"
SITE_META_ROBOTS="index, follow, max-image-preview:large"
SITE_LOCALE="pl_PL"
SITE_SOCIAL_IMAGE_URL=""
SITE_SOCIAL_IMAGE_ALT="Logo SyntaxDevTeam"
SITE_TWITTER_SITE=""
SITE_THEME_COLOR="#080c12"
SITE_GOOGLE_VERIFICATION=""
SITE_BING_VERIFICATION=""
SESSION_NAME="MINIPORTALSESSID"
SESSION_SAME_SITE="Lax"

AUTH_STORAGE="database"
AUTH_DEMO_ENABLED=false
AUTH_AUDIT_HASH_KEY="wstaw_tutaj_losowy_sekret_minimum_32_znaki"
AUTH_OAUTH_WINDOW_SECONDS=600
AUTH_OAUTH_START_LIMIT=10
AUTH_OAUTH_CALLBACK_LIMIT=20
GITHUB_CLIENT_ID=""
GITHUB_CLIENT_SECRET=""
GITHUB_CALLBACK_URL="https://new.syntaxdevteam.pl/index.php?route=/admin/auth/github/callback"
DISCORD_CLIENT_ID=""
DISCORD_CLIENT_SECRET=""
DISCORD_CALLBACK_URL="https://new.syntaxdevteam.pl/index.php?route=/admin/auth/discord/callback"
GOOGLE_CLIENT_ID=""
GOOGLE_CLIENT_SECRET=""
GOOGLE_CALLBACK_URL="https://new.syntaxdevteam.pl/index.php?route=/admin/auth/google/callback"

DB_ENABLED=true
DB_DRIVER="mysql"
DB_HOST="127.0.0.1"
DB_PORT=3306
DB_NAME="miniportal"
DB_USER="miniportal_user"
DB_PASS="unikalne_silne_haslo"
DB_CHARSET="utf8mb4"
DB_COLLATION="utf8mb4_general_ci"
DB_LOGGING=false
```

`APP_DEBUG` i `AUTH_DEMO_ENABLED` powinny być `false` na publicznym serwerze.
`DB_LOGGING` włączaj tylko tymczasowo podczas diagnostyki.

## Przygotowanie MySQL

Zaloguj się jako administrator MySQL:

```bash
sudo mysql
```

Utwórz bazę i dedykowanego użytkownika. Wstaw inne, silne hasło niż w przykładzie:

```sql
CREATE DATABASE miniportal
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_general_ci;

CREATE USER 'miniportal_user'@'127.0.0.1'
  IDENTIFIED BY 'unikalne_silne_haslo';

GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP
  ON miniportal.* TO 'miniportal_user'@'127.0.0.1';

FLUSH PRIVILEGES;
```

Wartości `DB_HOST`, `DB_NAME`, `DB_USER` i `DB_PASS` w pliku środowiskowym muszą
odpowiadać tym ustawieniom. Host `localhost` i `127.0.0.1` mogą być przez MySQL
traktowane jako odrębne konta, dlatego używaj konsekwentnie `127.0.0.1`.

## Znaczenie ustawień

| Zmienna | Znaczenie |
|---------|-----------|
| `APP_NAME` | Nazwa aplikacji |
| `APP_DEBUG` | Szczegóły błędów diagnostycznych |
| `APP_TIMEZONE` | Strefa czasowa PHP |
| `APP_THEME` | Nazwa aktywnego katalogu w `templates/` |
| `SITE_URL` | Kanoniczny publiczny adres HTTPS używany w metadanych SEO i Open Graph |
| `SITE_NAME` | Publiczna nazwa marki używana w logo, tytułach i stopce |
| `SITE_DEFAULT_TITLE` | Domyślny tytuł strony głównej i podglądów społecznościowych |
| `SITE_EYEBROW` | Domyślny nadtytuł publicznych widoków bez własnej wartości |
| `SITE_META_DESCRIPTION` | Domyślny opis strony dla wyszukiwarek i social media |
| `SITE_META_KEYWORDS` | Pole zgodności wstecznej; Google nie używa go do rankingu |
| `SITE_META_AUTHOR` | Autor lub wydawca treści publicznych |
| `SITE_META_ROBOTS` | Globalna polityka `index/follow`; strony błędów zawsze mają `noindex` |
| `SITE_LOCALE` | Locale Open Graph i język dokumentu, np. `pl_PL` |
| `SITE_SOCIAL_IMAGE_URL` | Opcjonalny lokalny lub zewnętrzny HTTPS obraz podglądu społecznościowego |
| `SITE_SOCIAL_IMAGE_ALT` | Tekstowy opis obrazu Open Graph i Twitter Card |
| `SITE_TWITTER_SITE` | Nazwa konta X/Twitter bez znaku `@` |
| `SITE_THEME_COLOR` | Kolor paska przeglądarki w formacie `#rrggbb` |
| `SITE_GOOGLE_VERIFICATION` | Opcjonalny token Google Search Console |
| `SITE_BING_VERIFICATION` | Opcjonalny token Bing Webmaster Tools |
| `SESSION_NAME` | Nazwa bezpiecznego cookie sesji |
| `SESSION_SAME_SITE` | Polityka cookie: `Lax`, `Strict` albo `None` wyłącznie przez HTTPS |
| `AUTH_STORAGE` | Repozytorium użytkowników: `database` produkcyjnie, `memory` wyłącznie do testów |
| `AUTH_DEMO_ENABLED` | Włącza lokalne konta demonstracyjne; na serwerze publicznym zawsze `false` |
| `AUTH_AUDIT_HASH_KEY` | Sekret HMAC do pseudonimizacji adresów IP w `auth_events`; minimum 32 losowe znaki |
| `AUTH_OAUTH_WINDOW_SECONDS` | Okno sesyjnego limitera prób OAuth; minimum 60 sekund |
| `AUTH_OAUTH_START_LIMIT` | Maksymalna liczba rozpoczęć OAuth na provider i sesję w jednym oknie |
| `AUTH_OAUTH_CALLBACK_LIMIT` | Maksymalna liczba callbacków OAuth na provider i sesję w jednym oknie |
| `GITHUB_CLIENT_ID` | Client ID zarejestrowanej GitHub App albo OAuth App |
| `GITHUB_CLIENT_SECRET` | Sekret aplikacji GitHub, przechowywany wyłącznie poza repozytorium |
| `GITHUB_CALLBACK_URL` | Dokładny callback: `https://new.syntaxdevteam.pl/index.php?route=/admin/auth/github/callback` |
| `DISCORD_CLIENT_ID` | Client ID aplikacji z Discord Developer Portal |
| `DISCORD_CLIENT_SECRET` | Sekret aplikacji Discord, przechowywany poza repozytorium |
| `DISCORD_CALLBACK_URL` | Dokładny callback: `https://new.syntaxdevteam.pl/index.php?route=/admin/auth/discord/callback` |
| `GOOGLE_CLIENT_ID` | Client ID klienta OAuth 2.0 typu Web application |
| `GOOGLE_CLIENT_SECRET` | Sekret klienta Google, przechowywany poza repozytorium |
| `GOOGLE_CALLBACK_URL` | Dokładny callback: `https://new.syntaxdevteam.pl/index.php?route=/admin/auth/google/callback` |
| `DB_ENABLED` | Włączenie połączenia przez `CrudApp` |
| `DB_DRIVER` | Sterownik Medoo/PDO, obecnie `mysql` |
| `DB_HOST`, `DB_PORT` | Adres i port serwera bazy |
| `DB_NAME` | Nazwa bazy danych |
| `DB_USER`, `DB_PASS` | Dane dedykowanego użytkownika bazy |
| `DB_CHARSET`, `DB_COLLATION` | Kodowanie i porównywanie tekstu |
| `DB_LOGGING` | Rejestrowanie zapytań przez Medoo |

## Ustawienia w panelu

Chroniona trasa `/admin/settings` przechowuje w tabeli `system_settings` wyłącznie
bezpieczne nadpisania:

- aktywny motyw oraz publiczny branding,
- canonical, domyślny tytuł, opis, autora, locale i politykę robots,
- obraz i opis podglądu social media, konto X/Twitter oraz kolor urządzenia,
- jawne tokeny weryfikacyjne Google i Bing; nie są to sekrety uwierzytelniające.

Wartości z bazy mają pierwszeństwo przed odpowiadającymi im zmiennymi środowiskowymi.
Jeżeli zapisany motyw przestanie istnieć, system wraca do motywu z pliku
środowiskowego albo do `default`.

Hasła bazy, sekrety OAuth, klucz `AUTH_AUDIT_HASH_KEY` i parametry sesji nie są
edytowalne w przeglądarce. Panel pokazuje jedynie ich zredagowany stan. Zmiany tych
wartości nadal wykonuje się w `/etc/miniportal/miniportal.env`.

## Zaufani wydawcy modułów

Publiczne klucze wydawców są rejestrowane przez `config/module_publishers.php`.
Klucz prywatny nie może trafić do repozytorium, katalogu WWW ani panelu.
Oficjalny publiczny klucz `syntaxdevteam-modules-2026` jest częścią czystej
dystrybucji w `config/keys/`. Kreator sprawdza jego obecność i poprawność przed
instalacją, dzięki czemu nowa instancja od początku ufa wyłącznie paczkom
podpisanym odpowiadającym mu kluczem prywatnym projektu macierzystego.

Projekt macierzysty może skonfigurować automatyczne podpisywanie eksportów jednym
poleceniem:

```bash
php bin/setup-module-signing.php /etc/miniportal/module-signing syntaxdevteam-modules-2026
```

Następnie wartości pokazane przez skrypt należy dodać do pliku środowiskowego:

```dotenv
MODULE_SIGNING_KEY_ID=syntaxdevteam-modules-2026
MODULE_SIGNING_PRIVATE_KEY_FILE=/etc/miniportal/module-signing/syntaxdevteam-modules-2026-private.pem
MODULE_SIGNING_PUBLIC_KEY_FILE=/etc/miniportal/module-signing/syntaxdevteam-modules-2026-public.pem
MODULE_QUARANTINE_RETENTION_DAYS=7
```

Od tego momentu panelowa akcja `Eksportuj ZIP` automatycznie tworzy podpisaną
kopię pakietu. Nie zmienia katalogu źródłowego modułu i nie zapisuje prywatnego
klucza w archiwum. Proces PHP musi mieć prawo odczytu klucza prywatnego. Instalacja
odbierająca paczki potrzebuje tego samego `MODULE_SIGNING_KEY_ID` i kopii wyłącznie
klucza publicznego; zmienna klucza prywatnego może tam pozostać pusta.
Eksport jest funkcją instancji wydawniczej i bez prywatnego klucza nie pojawia się
w panelu. Kwarantanna pozwala usuwać pojedyncze importy oraz audytowanie czyścić
wpisy starsze niż ustawiony limit wieku.

Przykładowe podpisanie wydania:

```bash
php bin/sign-module.php \
  install/mod/LearningModule \
  /bezpieczna/sciezka/private.pem \
  syntaxdevteam-learning-2026-rotated
```

Podpis obejmuje identyfikator, wersję, źródło pochodzenia, `signed_at` i SHA-256
każdego pliku. Wpis klucza ma stan `active`, `retired` albo `revoked` oraz opcjonalne
granice `valid_from` i `valid_until`. Rotacja polega na dodaniu nowego aktywnego
klucza i oznaczeniu poprzedniego jako `retired` z `replacement_key_id`. Stan
`revoked` natychmiast blokuje wszystkie podpisane nim pakiety.

Po zmianie choćby dokumentacji pakiet trzeba podpisać ponownie. Nieznany klucz
oznacza pakiet jako niezaufany, a niezgodność pliku lub podpisu blokuje cały pakiet.

Panelowy import i zatwierdzanie pakietów wymagają zapisywalnej kwarantanny oraz
prawa utworzenia nowego katalogu w `modules/`. Na serwerze produkcyjnym przygotuj
wyłącznie te dwa katalogi z dziedziczeniem grupy procesu WWW:

```bash
sudo install -d -m 2770 -o debian -g www-data cache/module-quarantine
sudo chgrp www-data modules
sudo chmod 2775 modules
```

Manager pokazuje te polecenia bezpośrednio przy imporcie, jeżeli proces PHP nie
ma prawa tworzyć lub atomowo podmieniać katalogów modułów.

## Aktualizacja całego miniPORTALu z panelu

Jednoklikowa aktualizacja runtime wymaga, aby proces PHP mógł zapisywać kod
zarządzany przez wydanie. Jest to świadoma granica bezpieczeństwa: konto serwera
WWW otrzymuje zapis do katalogów kodu wyłącznie dlatego, że panel ma kontrolowany
aktualizator z ACL, CSRF, stagingiem, SHA-256 i kopią bezpieczeństwa.

```bash
cd /var/www/syntaxdevteam.pl/new
sudo chgrp www-data .
sudo chmod 2775 .
sudo chgrp -R www-data core modules templates bin tools
sudo find core modules templates bin tools -type d -exec chmod 2775 {} \;
sudo find core modules templates bin tools -type f -exec chmod 0664 {} \;
sudo chgrp www-data config config/*.php
sudo chmod 2770 config
sudo chmod 0664 config/*.php
sudo chgrp www-data index.php .htaccess
sudo chmod 0664 index.php .htaccess
sudo install -d -m 2770 -o debian -g www-data cache/platform-updates
```

Sekrety powinny nadal znajdować się poza kodem w `/etc/miniportal/miniportal.env`
albo lokalnym `config/installed.env`. Aktualizator nigdy nie umieszcza tych plików
w wydaniu i ich nie nadpisuje. Powyższe polecenia celowo nie zmieniają praw
`config/installed.env` ani plików w `config/modules/`.

Jeżeli Owner ma budować release przez panel instalacji macierzystej, proces PHP
musi dodatkowo zapisywać katalog `releases/` i źródło wersji instalatora:

```bash
sudo chgrp -R www-data releases install/cms-source
sudo find releases install/cms-source -type d -exec chmod 2770 {} \;
sudo find releases install/cms-source -type f -exec chmod 0660 {} \;
```

Formularz publikacji jest pokazywany tylko wtedy, gdy istnieje
`bin/build-platform-release.php`; generator nie trafia do czystej dystrybucji.

Centralny kanał wydań można włączyć bez zmiany kodu:

```dotenv
PLATFORM_RELEASE_CATALOG_URL=https://downloads.example.org/miniportal/catalog.json
PLATFORM_RELEASE_MAX_BYTES=52428800
```

Akceptowany jest wyłącznie HTTPS bez przekierowań. Pobrane archiwum trafia do
`cache/platform-updates/downloads`, a przed użyciem musi odpowiadać sumie SHA-256
z katalogu. Pusty URL pozostawia lokalny tryb `releases/`.

Zatwierdzenie wykonuje atomowe przeniesienie w obrębie tego samego systemu plików.
Nie nadaje zapisu do istniejących katalogów modułów i nie uruchamia ich kodu.

## Cache szablonów

```dotenv
TEMPLATE_CACHE_ENABLED=true
TEMPLATE_CACHE_TTL=300
```

Pliki trafiają do `cache/templates`, który musi być zapisywalny dla użytkownika
serwera WWW. Panel `/admin/settings` pokazuje statystyki i udostępnia audytowane
czyszczenie. Zmiana treści `core_pages` albo ustawień motywu unieważnia właściwe tagi.

Cache jest zapełniany wyłącznie przez anonimowe wejścia na obsługiwane widoki
publiczne. Zalogowany administrator zawsze go omija, więc samo otwarcie panelu nie
zwiększa licznika. Statystyki pokazują ważne i wygasłe wpisy, rozmiar HTML, TTL
oraz możliwość zapisu.

Generator ikon w sekcji Branding wymaga Node.js i PNG od 512 x 512 do
4096 x 4096 px, maksymalnie 8 MiB. PHP musi mieć prawo zapisu do
`uploads/branding`; katalog `uploads/` blokuje wykonanie plików skryptowych.

## Tokeny integracji modułów

```dotenv
BUILD_UPLOAD_MAX_BYTES=20971520
BUILD_CI_TOKEN="wygenerowany_losowy_sekret_minimum_32_znaki"
```

`BUILD_CI_TOKEN` chroni endpointy `POST /api/builds/ci/{slug}`. W GitHub Actions
należy zapisać tę samą wartość jako sekret repozytorium i wysyłać ją w nagłówku
`X-Build-Token` albo `Authorization: Bearer`. Token nie jest przyjmowany w JSON,
nie jest przechowywany w bazie i nie trafia do audit logu.

Econizer nie korzysta z powyższego pliku. Wszystkie jego sekrety znajdują się w
`config/modules/econizer.env`, tworzonym przez instalator z prawami `0600`. Szablon
`modules/Econizer/.env.example` opisuje token API, token bota, Client ID, Client
Secret, callback i maskę uprawnień. Testy mogą wskazać osobny plik przez
`ECONIZER_ENV_FILE`. Starsza lokalizacja `modules/Econizer/.env` pozostaje
obsługiwana wyłącznie dla istniejących instalacji. Szczegółowy kontrakt znajduje
się w README modułu.

Przykładowe wywołanie z GitHub Actions przesyłające plik pod kontrolę
BuildExplorera:

```bash
curl --fail-with-body \
  -X POST "https://new.syntaxdevteam.pl/api/builds/ci/punisherx-paper" \
  -H "X-Build-Token: ${{ secrets.BUILD_CI_TOKEN }}" \
  -F "metadata=<build-info.json;type=application/json" \
  -F "artifact=@punisherx-paper/build/libs/PunisherX-Paper.jar;type=application/java-archive"
```

`metadata` wymaga dodatniego `id`, czasu ISO-8601, kanału `DEV` albo `WIP`,
platformy `server`, wersji `version`, opcjonalnej publicznej nazwy `filename` oraz
listy commitów. BuildExplorer zapisuje przesłany `.jar`, wylicza rozmiar i
SHA-256, publikuje rekord i przy powtórzeniu tego samego ID joba aktualizuje
rekord dla tej samej platformy zamiast tworzyć duplikat. Opcjonalne pola
`sha256` i `size` są traktowane jako kontrola zgodności z przesłanym plikiem.

Pełny przykład workflow dla PunisherX znajduje się w
[`docs/CI_BUILDEXPLORER_PUNISHERX.md`](CI_BUILDEXPLORER_PUNISHERX.md).

Starszy wariant JSON z mapą `downloads` nadal działa dla instalacji, które chcą
publikować bez kopiowania pliku do miniPORTAL. W tym trybie każdy wpis wymaga
nazwy `.jar`, SHA-256, rozmiaru w bajtach i zewnętrznego adresu HTTPS.

Pliki JAR trafiają do `cache/build-artifacts`, który pozostaje zablokowany przez
główny `.htaccess`. Katalog musi należeć do grupy procesu WWW:

```bash
sudo install -d -m 2770 -o debian -g www-data cache/build-artifacts
```

Limit aplikacji nie może przekraczać `upload_max_filesize` i `post_max_size`
aktywnego handlera PHP. Produkcyjny Apache ma obecnie odpowiednio 20 MB i 64 MB.

## Uruchomienie lokalne

Możesz wskazać dowolny plik tylko dla danego procesu:

```bash
MINIPORTAL_ENV_FILE="$PWD/.env.local" php -S 127.0.0.1:8765
```

Pliku `.env.local` nie dodawaj do Git.

Do lokalnego sprawdzenia ACL bez bazy można jawnie uruchomić tryb demonstracyjny:

```bash
AUTH_STORAGE=memory AUTH_DEMO_ENABLED=1 php -S 127.0.0.1:8765
```

Tryb ten udostępnia testowe role administratora i redaktora. Nie wolno włączać go
w środowisku publicznym.

## Konfiguracja GitHub

Adapter GitHub korzysta z Authorization Code, jednorazowego `state` i PKCE `S256`.
W ustawieniach GitHub App lub OAuth App wpisz dokładnie:

```text
https://new.syntaxdevteam.pl/index.php?route=/admin/auth/github/callback
```

Po otrzymaniu Client ID i sekretu Owner może uzupełnić provider w panelu
`Ustawienia`. Sekrety trafiają do `config/modules/auth-providers.env` z trybem
`0600`; ręczna konfiguracja przez zmienne środowiskowe pozostaje zgodnością
wsteczną.

## Bootstrap pierwszego Ownera

Gotowa dystrybucja `install/cms` wymaga co najmniej jednego dowolnego providera.
Pierwsze poprawne logowanie po instalacji atomowo tworzy pierwszego Ownera,
wyłącznie gdy tabela `users` pozostaje pusta. Konfiguracja providerów jest
zapisywana w `config/modules/auth-providers.env`, a pozostała konfiguracja w
`config/installed.env`. Jawna zmienna
`MINIPORTAL_ENV_FILE` ma najwyższy priorytet; bez niej aplikacja wybiera lokalny
plik instalacji, a dopiero następnie `/etc/miniportal/miniportal.env`. Pozwala to
utrzymywać kilka niezależnych instalacji na jednym serwerze.

Awaryjna komenda CLI nadal działa wyłącznie, gdy tabela `users` jest pusta.
Najpierw sprawdź,
jakie konto i niezmienny identyfikator GitHub zostaną użyte:

```bash
sudo -u www-data php bin/bootstrap-admin.php --dry-run LOGIN_GITHUB
```

Jeśli dane są poprawne, wykonaj operację bez `--dry-run`:

```bash
sudo -u www-data php bin/bootstrap-admin.php LOGIN_GITHUB
```

Komenda pobiera numeryczny GitHub `subject`, a następnie w jednej transakcji tworzy
aktywnego użytkownika, tożsamość GitHub i przypisanie roli `owner`.
Ponowne uruchomienie po utworzeniu konta jest blokowane.

## Konfiguracja Discord

W Discord Developer Portal zarejestruj dokładny redirect:

```text
https://new.syntaxdevteam.pl/index.php?route=/admin/auth/discord/callback
```

Następnie ustaw `DISCORD_CLIENT_ID` i `DISCORD_CLIENT_SECRET`. Adapter prosi
wyłącznie o zakresy `identify email`, waliduje jednorazowy `state` i nie zapisuje
tokenów dostawcy.

## Konfiguracja Microsoft

W Microsoft Entra zarejestruj aplikację Web i callback:

```text
https://new.syntaxdevteam.pl/index.php?route=/admin/auth/microsoft/callback
```

Provider używa Authorization Code, PKCE, zakresu `User.Read` i pobiera stały
identyfikator konta przez Microsoft Graph. Client ID i sekret można zapisać w
panelu `Ustawienia`.

## Konfiguracja Google OpenID Connect

W Google Cloud Console utwórz klienta OAuth 2.0 typu Web application i dodaj:

```text
https://new.syntaxdevteam.pl/index.php?route=/admin/auth/google/callback
```

Następnie ustaw `GOOGLE_CLIENT_ID` i `GOOGLE_CLIENT_SECRET`. Adapter używa zakresów
`openid email profile`, `state`, `nonce` i PKCE. ID token jest lokalnie sprawdzany
pod kątem podpisu RS256, `iss`, `aud`, `exp`, `iat` oraz `nonce`.

Wygeneruj również osobny sekret audit logu:

```bash
openssl rand -hex 32
```

Wynik zapisz jako `AUTH_AUDIT_HASH_KEY`. Bez tej wartości zdarzenia nadal są
zapisywane, ale adres IP jest pomijany zamiast używania słabego klucza.

## Łączenie tożsamości

Zalogowany użytkownik może otworzyć:

```text
https://new.syntaxdevteam.pl/index.php?route=/admin/identities
```

Nowy provider jest dołączany wyłącznie w aktywnej sesji tego samego użytkownika.
System nie łączy kont na podstawie e-maila i nie pozwala odłączyć ostatniej
tożsamości umożliwiającej logowanie.

## Ochrona publicznego katalogu i logów

Główny `.htaccess` blokuje bezpośredni dostęp HTTP do `.git`, kodu Core, modułów,
migracji SQL, dokumentacji technicznej, konfiguracji, cache i plików motywu PHP.
Publiczne pozostają `index.php`, statyczne prototypy HTML oraz assety motywu.

Callbacki OAuth zawierają jednorazowy parametr `code`, dlatego oba VirtualHosty
muszą pomijać je w access logu:

```apache
SetEnvIfExpr "%{QUERY_STRING} =~ m#route=(?:%2[Ff]|/)admin(?:%2[Ff]|/)auth(?:%2[Ff]|/)[^&]+(?:%2[Ff]|/)callback#" oauth_callback
CustomLog ${APACHE_LOG_DIR}/new.syntaxdevteam.pl_access.log combined env=!oauth_callback
```

Po zmianie zawsze wykonaj `sudo apache2ctl configtest` przed przeładowaniem usługi.
