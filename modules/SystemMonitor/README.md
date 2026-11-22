# SystemMonitor

Moduł administratora miniPORTAL do podglądu zasobów oraz podstawowej infrastruktury hosta VPS.

## Metryki live

Widok `System → Monitor VPS` odświeża lekkie metryki co 2 sekundy przy aktywnej karcie:

- CPU,
- RAM,
- swap,
- główny system plików `/`,
- load average,
- uptime,
- transfer RX/TX.

RAM i zajętość dysku są również dostępne jako lekkie metryki głównego Dashboardu.

## Infrastruktura

Osobny endpoint `/admin/system-monitor/infrastructure` ładuje cięższe informacje dopiero po wejściu do modułu. Wynik jest cache'owany (domyślnie 10 minut), dzięki czemu skan katalogów i zapytania do silników baz danych nie obciążają każdego requestu.

Zakres:

- wykrywanie Apache2, MariaDB/MySQL, PostgreSQL, PHP bieżącego SAPI, OpenSSH, systemd, Node Agenta i Docker,
- liczba procesów oraz suma ich RSS,
- lista i logiczny rozmiar baz MariaDB/MySQL,
- lista i logiczny rozmiar baz PostgreSQL,
- aktywne połączenia do silników, jeżeli konto ma odpowiednie uprawnienia,
- udział wybranych katalogów w zajętym miejscu na dysku,
- kategoria `System / pozostałe` obliczana jako różnica między zajętością `/` a rozpoznanymi kategoriami.

## Cleaner logów — wyłącznie przez Node Agenta

miniPORTAL **nie usuwa logów systemowych jako `www-data`** i nie otrzymuje uprawnień do `/var/log`.

Cleaner używa tego samego lokalnego Node Agenta co `NodeManager` i komunikuje się z nim przez skonfigurowany Unix Socket. Warstwa WWW odpowiada wyłącznie za:

- wybór retencji,
- prezentację podglądu,
- CSRF i kontrolę uprawnienia `settings.manage`,
- zlecenie semantycznego zadania `system.logs.clean`,
- obserwowanie stanu zadania przez istniejące job API,
- zapis operacji administratora do audit logu miniPORTAL.

### Podgląd

miniPORTAL odpytuje agenta:

```http
GET /v1/system/logs?retention_days=14
```

Agent zwraca między innymi liczbę kandydatów, rozmiar możliwy do odzyskania, dane systemd-journal oraz ograniczoną listę największych starych logów.

### Czyszczenie

Operacja jest kolejką istniejącego Node Agenta:

```json
{
  "action": "system.logs.clean",
  "parameters": {
    "retention_days": 14,
    "include_journal": true
  }
}
```

Panel otrzymuje `job_id` i odpytuje `/v1/jobs/{job_id}` aż do stanu `succeeded` albo `failed`. Dzięki temu uprzywilejowana operacja nie wykonuje się wewnątrz requestu PHP.

Dokładny kontrakt i wymagania bezpieczeństwa znajdują się w `NODE_AGENT_LOGS_API.md`.

### Brak fallbacku

Jeżeli zainstalowana wersja Node Agenta nie obsługuje `/v1/system/logs` lub `system.logs.clean`, panel pokazuje cleaner jako niedostępny. **Nie istnieje fallback do `unlink()`, `sudo`, `shell_exec()` ani bezpośredniego kasowania jako użytkownik serwera WWW.**

Allowlista katalogów, maksymalna liczba plików, maksymalny rozmiar pojedynczej operacji, reguły rozpoznawania rotowanych logów i polityka journald należą do konfiguracji Node Agenta, a nie do miniPORTAL.

## Konfiguracja

Opcjonalną konfigurację monitora umieść w:

```text
config/modules/system-monitor.env
```

Repozytorium zawiera wzór `system-monitor.env.example`. Katalog `config/modules/` jest ignorowany przez Git i zachowywany przez mechanizm aktualizacji platformy, dlatego prawdziwe hasła nie powinny trafić do repozytorium.

Konfiguracja cleanera nie znajduje się w tym pliku — operacja uprzywilejowana jest konfigurowana po stronie Node Agenta.

### MariaDB / MySQL

Jeżeli `MYSQL_MONITOR_DSN` pozostaje pusty, moduł próbuje użyć istniejącego połączenia miniPORTAL. Zobaczy wtedy tylko te schematy, do których konto DB miniPORTAL ma dostęp.

Dla pełniejszego obrazu można skonfigurować osobne konto monitorujące:

```ini
MYSQL_MONITOR_DSN="mysql:host=127.0.0.1;port=3306;charset=utf8mb4"
MYSQL_MONITOR_USER="miniportal_monitor"
MYSQL_MONITOR_PASSWORD="..."
```

Konto powinno otrzymać wyłącznie minimalne uprawnienia potrzebne do odczytu metadanych. Nie należy używać konta `root`.

### PostgreSQL

PostgreSQL jest opcjonalny i wymaga osobnego DSN, jeżeli główna baza miniPORTAL nie korzysta z `pgsql`:

```ini
POSTGRES_MONITOR_DSN="pgsql:host=127.0.0.1;port=5432;dbname=postgres;connect_timeout=3"
POSTGRES_MONITOR_USER="miniportal_monitor"
POSTGRES_MONITOR_PASSWORD="..."
```

W razie braku konfiguracji panel wyświetli PostgreSQL jako niedostępny, bez wpływu na pozostałe metryki.

## Analiza dysku

Domyślne kategorie:

- `/var/www`,
- `/var/lib/mysql`,
- `/var/lib/postgresql`,
- `/var/log`,
- `/var/lib/docker`,
- `/home/debian/server` — jeśli katalog istnieje.

Listę można nadpisać przez `SYSTEM_MONITOR_STORAGE_PATHS`:

```ini
SYSTEM_MONITOR_STORAGE_PATHS="WWW=/var/www;MariaDB=/var/lib/mysql;Minecraft=/home/debian/server"
```

Skan części informacyjnej:

- nie podąża za symlinkami,
- ma limit liczby plików,
- ma limit czasu,
- jest cache'owany,
- nie uruchamia `du`, `find`, `systemctl`, `mysql`, `psql` ani innych poleceń powłoki.

Jeżeli użytkownik PHP nie może odczytać katalogu MariaDB/PostgreSQL, moduł próbuje wykorzystać logiczny rozmiar baz zwrócony przez silnik DB. Dla innych niedostępnych katalogów pokazuje brak dostępu zamiast podnoszenia uprawnień procesu WWW.

## Bezpieczeństwo

Endpointy modułu wymagają `settings.manage`. Operacja cleanera dodatkowo wymaga prawidłowego tokenu CSRF. Uprzywilejowane czyszczenie jest delegowane do Node Agenta; miniPORTAL przekazuje jedynie zwalidowaną retencję i flagę `include_journal`.

Klient Unix Socket po stronie miniPORTAL również waliduje te parametry i nie pozwala wysłać dla `system.logs.clean` dowolnego `server_id`, ścieżki ani dodatkowych pól.

Cache z nazwami i rozmiarami baz jest zapisywany w katalogu tymczasowym systemu z prawami `0600`, a nie w publicznym katalogu WWW.


## GitHub Actions (0.2.0)

Monitor pokazuje karty lokalnych runnerów: gotowy, wykonuje zadanie, łączenie
lub wyłączony. Dla zadania pokazuje nazwę i czas, a także ostatni wynik.
Osobny endpoint `/admin/system-monitor/actions` wymaga `settings.manage`.
Odświeżanie odbywa się co 5 sekund w aktywnej karcie. Pomiar starszy niż
30 sekund jest oznaczany jako nieaktualny, bez sugerowania aktualnego stanu.

Snapshot jest opcjonalny; brak kolektora nie zakłóca pozostałych metryk.
WWW nie wywołuje systemctl/journalctl ani GitHub API i nie czyta tokenów.
Kolektor instaluje administrator hosta według
`deploy/github-actions-monitor/README.md` w repozytorium źródłowym.
Czysta dystrybucja zawiera czytnik i prezentację; nie instaluje usługi hosta.


## Uzupełnienia 0.3.0

Dokładny RAM, stany procesów, opcjonalny pidfd/SIGTERM i pełny dysk hosta opisuje
`deploy/vps-monitor/README.md` w źródłach instalacji macierzystej. Odczytowy timer
jest aktywny; usługa zamykania procesów wymaga dodatkowej zgody i pozostaje wyłączona.
Bez snapshotu dysku nadal działa ograniczony odczyt PHP, jawnie oznaczony jako
niepełny. Niezmierzonej reszty nie opisuje się już jako zmierzonego rozmiaru systemu.
Pakiety i ich wersje są odczytywane z dpkg; brak wersji jest pokazywany jawnie.


## Wspólne pomiary 0.4.0

Preferowane źródło: istniejący Node Agent, `/v1/system/monitor`. Dashboard,
Monitor VPS, procesy i Actions czytają cache. Lokalny odczyt pozostaje zgodnym
fallbackiem przy braku agenta. Panel oznacza źródło danych. PHP SAPI i SQL
pozostają lokalne. Pełny dysk skanowany najwyżej co godzinę, stare dane po 2 h.
Źródła, limity, wdrożenie i rollback: `deploy/node-telemetry/README.md` w repo
macierzystym. Konsolidacja nie włącza zamykania procesów.


## Okresowy Cleaner miejsca — 2026-11-13

Dotychczasowe `system.logs.clean` może dodatkowo czyścić wygasły cache i stare
anonimowe, niepodpięte woluminy Docker. Rozszerzenie wymaga aktualizacji helpera
oraz Agenta i jawnego markera aktywacji root; bez niego działa tylko Cleaner logów.
Panel pokazuje osobną liczbę woluminów i bajty cache, chroni opublikowane buildy,
a harmonogram jest realizowany przez systemd. Pełny kontrakt, patch Go i rollback:
`deploy/resource-cleaner/README.md` w instalacji macierzystej. Instalacje klienckie
wymagają osobnego wdrożenia Agenta przez operatora hosta.
