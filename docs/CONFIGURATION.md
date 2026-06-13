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

```dotenv
APP_NAME="miniPORTAL"
APP_DEBUG=false
APP_TIMEZONE="Europe/Warsaw"
APP_THEME="default"
SESSION_NAME="MINIPORTALSESSID"
SESSION_SAME_SITE="Lax"

AUTH_STORAGE="database"
AUTH_DEMO_ENABLED=false
GITHUB_CLIENT_ID=""
GITHUB_CLIENT_SECRET=""
GITHUB_CALLBACK_URL="https://new.syntaxdevteam.pl/index.php?route=/admin/auth/github/callback"
DISCORD_CLIENT_ID=""
DISCORD_CLIENT_SECRET=""
DISCORD_CALLBACK_URL="https://new.syntaxdevteam.pl/index.php?route=/admin/auth/discord/callback"

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
| `SESSION_NAME` | Nazwa bezpiecznego cookie sesji |
| `SESSION_SAME_SITE` | Polityka cookie: `Lax`, `Strict` albo `None` wyłącznie przez HTTPS |
| `AUTH_STORAGE` | Repozytorium użytkowników: `database` produkcyjnie, `memory` wyłącznie do testów |
| `AUTH_DEMO_ENABLED` | Włącza lokalne konta demonstracyjne; na serwerze publicznym zawsze `false` |
| `GITHUB_CLIENT_ID` | Client ID zarejestrowanej GitHub App albo OAuth App |
| `GITHUB_CLIENT_SECRET` | Sekret aplikacji GitHub, przechowywany wyłącznie poza repozytorium |
| `GITHUB_CALLBACK_URL` | Dokładny callback: `https://new.syntaxdevteam.pl/index.php?route=/admin/auth/github/callback` |
| `DISCORD_CLIENT_ID` | Client ID aplikacji z Discord Developer Portal |
| `DISCORD_CLIENT_SECRET` | Sekret aplikacji Discord, przechowywany poza repozytorium |
| `DISCORD_CALLBACK_URL` | Dokładny callback: `https://new.syntaxdevteam.pl/index.php?route=/admin/auth/discord/callback` |
| `DB_ENABLED` | Włączenie połączenia przez `CrudApp` |
| `DB_DRIVER` | Sterownik Medoo/PDO, obecnie `mysql` |
| `DB_HOST`, `DB_PORT` | Adres i port serwera bazy |
| `DB_NAME` | Nazwa bazy danych |
| `DB_USER`, `DB_PASS` | Dane dedykowanego użytkownika bazy |
| `DB_CHARSET`, `DB_COLLATION` | Kodowanie i porównywanie tekstu |
| `DB_LOGGING` | Rejestrowanie zapytań przez Medoo |

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

Po otrzymaniu Client ID i sekretu uzupełnij `GITHUB_CLIENT_ID` oraz
`GITHUB_CLIENT_SECRET` w `/etc/miniportal/miniportal.env`, a następnie przeładuj
Apache. Bez obu wartości przycisk GitHub pozostaje niewidoczny.

## Bootstrap pierwszego administratora

Mechanizm działa wyłącznie, gdy tabela `users` jest pusta. Najpierw sprawdź,
jakie konto i niezmienny identyfikator GitHub zostaną użyte:

```bash
sudo -u www-data php bin/bootstrap-admin.php --dry-run LOGIN_GITHUB
```

Jeśli dane są poprawne, wykonaj operację bez `--dry-run`:

```bash
sudo -u www-data php bin/bootstrap-admin.php LOGIN_GITHUB
```

Komenda pobiera numeryczny GitHub `subject`, a następnie w jednej transakcji tworzy
aktywnego użytkownika, tożsamość GitHub i przypisanie roli `administrator`.
Ponowne uruchomienie po utworzeniu konta jest blokowane.

## Konfiguracja Discord

W Discord Developer Portal zarejestruj dokładny redirect:

```text
https://new.syntaxdevteam.pl/index.php?route=/admin/auth/discord/callback
```

Następnie ustaw `DISCORD_CLIENT_ID` i `DISCORD_CLIENT_SECRET`. Adapter prosi
wyłącznie o zakresy `identify email`, waliduje jednorazowy `state` i nie zapisuje
tokenów dostawcy.
