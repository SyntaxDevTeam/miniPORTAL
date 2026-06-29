# miniPORTAL
Autorski mini-CMS w systemie modularnym

## Dokumentacja
- [Pierwotny zarys - koncepcja](docs/SZKIC.md)
- [Specyfikacja techniczna i plan rozwoju](docs/TECHNICAL_SPECIFICATION.md)
- [Konfiguracja środowiska](docs/CONFIGURATION.md)
- [Publikacja buildów PunisherX z GitHub Actions](docs/CI_BUILDEXPLORER_PUNISHERX.md)
- [Plan panelu administracyjnego i logowania](docs/ADMIN_PANEL_PLAN.md)
- [Opis modułu Econizer](docs/ECONIZER_MODULE.md)
- [Przykładowy moduł edukacyjny](install/mod/LearningModule/README.md)
- [Czysta dystrybucja z kreatorem](install/cms/INSTALL.md)

## Dystrybucja instalacyjna

Folder `install/cms` jest gotową, pozbawioną lokalnych danych kopią miniPORTAL.
Po wgraniu na serwer wystarczy otworzyć `install.php`; kreator sprawdza środowisko,
tworzy schemat pustej bazy, instaluje wybrane moduły i przygotowuje bootstrap
pierwszego Ownera.
Kreator pozwala wybrać GitHub, Discord, Google lub Microsoft; pierwsze poprawne
logowanie przez włączonego dostawcę atomowo przejmuje rolę Ownera. Kolejnych
dostawców można później skonfigurować w panelu bez ręcznej edycji `.env`.
Pakiet można odtworzyć po każdej zmianie poleceniem:

```bash
php bin/build-cms-distribution.php
```

## Aktualizacje platformy

miniPORTAL ma osobny kanał aktualizacji całego runtime, niezależny od managera
modułów. Wydania znajdują się w chronionym katalogu `releases/`; `catalog.json`
zawiera wersję, wymaganie wersji bazowej, SHA-256 archiwum i listę zmian.
Dashboard informuje administratora o nowszym zgodnym wydaniu, a panel
`/admin/system-updates` wykonuje staging, backup plików, podmianę runtime, migracje
Core i aktualizacje zainstalowanych modułów.
Opcjonalne `PLATFORM_RELEASE_CATALOG_URL` wskazuje centralny `catalog.json` przez
HTTPS; wtedy panel pobiera archiwum do chronionego cache. Bez tego ustawienia
korzysta z lokalnego katalogu `releases/`.
Instalacja macierzysta udostępnia katalog i wyłącznie wpisane w nim archiwa przez
`/api/platform-releases/catalog` oraz `/api/platform-releases/{filename}`; sam
katalog `releases/` pozostaje niedostępny bezpośrednio przez HTTP.

Pakiet nie zawiera treści bazy, uploadów, cache, `config/installed.env`,
`config/installed.lock` ani konfiguracji modułowych z sekretami. Wydanie tworzy:

```bash
php bin/build-platform-release.php 0.2.0 releases/notes-0.2.0.json
```

W instalacji macierzystej ten sam generator jest dostępny dla Ownera w
`/admin/system-updates`. Formularz przyjmuje wersję SemVer, najstarszą obsługiwaną
wersję i listę zmian. Może przebudować bieżące wydanie albo ustawić wyższą wersję.
Czysta dystrybucja nie zawiera generatora ani formularza publikacji.

## Weryfikacja

```bash
php tests/run.php
find core modules templates config tests bin install/mod install/cms-source install/cms -type f -name '*.php' -print0 | xargs -0 -n1 php -l
php bin/migrate-core.php
```

Zewnętrzne moduły z własną fabryką wymagają jawnego pochodzenia oraz podpisu
RSA-SHA256 zweryfikowanego aktywnym albo poprawnie wycofanym kluczem publicznym
z `config/module_publishers.php`. Klucz unieważniony blokuje pakiet.
Zweryfikowany import można zatwierdzić w managerze: pakiet jest ponownie sprawdzany
i atomowo przenoszony z `cache/module-quarantine` do `modules/`. Zatwierdzenie nie
instaluje modułu ani nie wykonuje jego fabryki; instalacja pozostaje osobną akcją.
Każdy zainstalowany moduł, również chroniony `core` lub `system`, można eksportować
z managera do archiwum ZIP. Import wyższej wersji istniejącego pakietu atomowo
podmienia kod i uruchamia kontrolowane migracje; błąd przywraca poprzednią wersję.
Po jednorazowym skonfigurowaniu lokalnego wydawcy przez
`bin/setup-module-signing.php` panelowy eksport automatycznie podpisuje kopię
pakietu bez modyfikowania źródłowego katalogu modułu. Instalacja produkcyjna bez
prywatnego klucza wydawcy nie pokazuje ani nie wykonuje eksportu modułów.
Eksport blokuje dowiązania symboliczne i ukryte ścieżki, a paczka zachowuje
top-level katalog modułu z `info.json`. Lokalny `.env` jest zawsze pomijany;
bezpieczny plik wzorcowy `.env.example` może wejść do pakietu.
Kwarantanna pozwala usunąć pojedynczy import oraz audytowanie wyczyścić wpisy
starsze niż skonfigurowane `MODULE_QUARANTINE_RETENTION_DAYS`.

Anonimowa strona główna korzysta z tagowego cache szablonów w `cache/templates`.
Publiczne podstrony i artykuły używają tego samego cache z granularnymi tagami.
Zmiany stron, artykułów, sekcji i motywu automatycznie unieważniają zależne wpisy.
Sekcja strony głównej typu `hero` w układzie `split` może opcjonalnie wyświetlać
pionowy akrostych: wyrazy są ustawiane w panelu, a motyw wyróżnia ich pierwsze litery.
Nagłówki sekcji przyjmują do czterech ręcznych wierszy; Enter określa zamierzone
miejsce podziału, a etykiety nawigacji pozostają jednoliniowe.
Moduły `wikipedia` i `articles` dodają publiczne sekcje dokumentacji oraz artykułów.
Aktywne moduły mogą deklarować publiczne linki, którym administrator nadaje etykietę
i niezależnie przypina je do głównego menu, stopki albo obu obszarów w `/admin/settings`.
Ten sam widok rozdziela branding od SEO i udostępniania: zarządza tytułem, opisem,
canonical, robots, locale, podglądem social media oraz tokenami weryfikacji wyszukiwarek.
Publiczne linki generowane przez motywy używają przyjaznych adresów, np. `/wiki`
i `/wiki/project/punisherx`, zamiast technicznych parametrów `index.php?route=...`.
Router obsługuje parametry segmentów, np. `/article/{slug}`, więc moduły nie budują
już przy starcie osobnej trasy z każdego rekordu bazy.
`database_manager` jest osobnym modułem rozszerzenia panelu dla Managera SQL i
przechowuje własną historię operacji. Moduł rozdziela podgląd `database.view` od
operacji zapisowych `database.manage`, obsługuje eksport/import SQL, operacje tabel
oraz dodawanie, edycję i usuwanie rekordów. Główne akcje panelowych modułów są
renderowane w pełnoszerokim pasku pod nagłówkiem bieżącego widoku.
`plugin_translator` jest osobnym modułem do tłumaczenia plików YAML używanych przez
pluginy SyntaxDevTeam oraz managerem zaakceptowanych plików językowych. Publiczna
strona `/translations` grupuje pliki według kategorii tłumaczeń reprezentujących
pluginy, boty albo inne projekty i pozwala użytkownikom wgrać
`.yml/.yaml` metodą przeciągnij/upuść, wybrać język docelowy ISO `XX`, uzupełnić
tłumaczenie w edytorze z wyrównanym oryginałem i zapisać je domyślnie jako szkic.
Wprowadzanie i zapis wymagają logowania; konto oczekujące może pracować nad
tłumaczeniami publicznymi bez dostępu do panelu admina, a rozpoczęta praca jest
wznawiana po OAuth. Główne okno ma zakładki `Rozpocznij tłumaczenie`, `Moje wersje
robocze` i `Wyślij gotowy plik`, więc cały publiczny przepływ pozostaje pod
`/translations`. Edytor ma podgląd HTML
formatowania Minecraft legacy, RGB i MiniMessage, pokazuje zmienne typu `<player>`
oraz błędy składni formatowania. Panel `/admin/plugin-translator` pokazuje
zgłoszenia, postęp, statusy oraz akcje zatwierdzenia, odrzucenia i pobrania
zweryfikowanego YAML. Katalog kategorii jest pełnoszeroką tabelą pod oknem pracy;
nazwy kategorii prowadzą do zaakceptowanych plików. Każdy zaakceptowany plik można
pobrać albo otworzyć jako nową propozycję poprawki. Panel
`/admin/plugin-translator/plugins` zarządza
kategoriami, pozwala je edytować i usuwać oraz łączy je z istniejącą opublikowaną
stroną `/p/{slug}`. Przy usunięciu kategorii jej zgłoszenia trafiają do chronionej
pozycji `Nieprzypisane`.
Manager zgłoszeń udostępnia podgląd, pobranie, zatwierdzenie, odrzucenie i trwałe
usunięcie. Administracyjny edytor pliku YAML pozostał pod
`/admin/plugin-translator/tool`; zachowuje oryginalną nazwę edytowanego pliku.
Pliki tłumaczeń z katalogu są pobierane jako `messages_xx.yml`, np.
`messages_pl.yml` albo `messages_de.yml`.
`team` jest osobnym modułem prezentacji zespołu. Publiczne `/team` pokazuje
widocznych członków, a `/team/member/{slug}` prowadzi do publicznego profilu
powiązanego z lokalnym kontem użytkownika i jego avatarem. Panel `/admin/team`
zarządza widocznością, opisem, rolą i kolejnością profili.
`user_profile` jest osobnym modułem zależnym od `core_auth`. Przejmuje kompatybilne
trasy `/admin/profile*` dla podglądu i edycji danych, ustawień avatara oraz stanu
bezpieczeństwa. Łączenie i odłączanie kont OAuth pozostaje w chronionym `core_auth`,
do którego moduł profilu prowadzi bez kopiowania logiki uwierzytelniania.
Role systemowe tworzą hierarchię `Owner` → `Administrator` → `Maintainer` →
`Redaktor` / `Audytor` / `Support`. Owner jako jedyny ma wildcard obejmujący
przyszłe moduły i może zarządzać innymi kontami Ownerów; ostatniego aktywnego
Ownera nie można zablokować ani zdegradować.
Panel ma indeks wyszukiwania respektujący ACL; aktywne moduły mogą zgłaszać własne
akcje i słowa kluczowe. Ten sam kontrakt rozszerzeń pozwala modułom dodawać
konfigurowalne metryki oraz panele do Dashboardu.
Aktywne moduły mogą również implementować `HookProviderInterface`: akcje i filtry
`HookRegistry` są wykonywane według priorytetu bez modyfikowania modułu-konsumenta.
Filtr `homepage.sections` zasila moduł `widgets`. Panel `/admin/widgets` pozwala
umieszczać terminale, karty i panele uptime na początku strony, obok Hero, po Hero,
przed albo po wskazanej sekcji oraz przed stopką. Widget może działać we wszystkich
motywach albo tylko w `default`, `glassnight` lub `future`; HTML nadal renderuje
aktywny motyw. Panele uptime przyjmują linie `Etykieta | Wartość | status`, gdzie
status to `up`, `warn`, `down` albo `neutral`.
`projects` jest katalogiem projektów łączącym status realizacji z istniejącą
podstroną `core_pages` i dokumentacją `wikipedia`. Udostępnia publiczne adresy
`/projects` oraz `/projects/{slug}` i nie duplikuje treści należących do tych modułów.
`build_explorer` publikuje pliki JAR przypisane do projektów. Upload trafia poza
publiczny katalog WWW, a rozmiar i SHA-256 są obliczane automatycznie. Domyślna
nazwa ma postać `<projekt>-<serwer>-<wersja>-<typ>[-<build>].jar`, ale administrator
może ją edytować. Release i Snapshot nie wymagają numeru buildu. Publiczny katalog
prowadzi przez projekt, kanał, wersję i historię buildów pod `/builds`.
GitHub Actions może publikować DEV/WIP przez `POST /api/builds/ci/{slug-projektu}`
z `multipart/form-data`: polem `metadata` JSON, plikiem `artifact` oraz sekretem
`BUILD_CI_TOKEN` w `X-Build-Token` lub Bearer. Starszy import JSON z zewnętrznym
adresem HTTPS pozostaje kompatybilny.
Monorepo PunisherX publikuje docelowo cztery osobne projekty BuildExplorera:
`punisherx-paper`, `punisherx-spigot`, `punisherx-bungeecord-bridge` i
`punisherx-velocity-bridge`; workflow wybiera produkt po zmienionych ścieżkach.

`econizer` jest dedykowanym, wieloserwerowym centrum bota ekonomicznego Discord.
Rozdziela właściciela platformy, administrację konkretnego serwera i gracza,
zapewnia ustawienia ekonomii, podatki, VIP daily, plany Freemium/Premium, sklep,
transakcyjny portfel, historię oraz giełdę. Idempotentny endpoint bota aktualizuje
saldo i postęp bez powierzania klientowi dostępu do bazy.

Projekt deklaruje PHP 8.4 lub nowszy jako wymaganie runtime; PHP 8.5 nie jest już
wymagane do uruchomienia produkcyjnego handlera.

Warstwa prezentacji zawiera trzy wymienne motywy: `default`, `glassnight` oraz
`future`. Motyw `future` przenosi neonowy, grafitowy wygląd wcześniejszego projektu
edukacyjnego SyntaxDevTeam na aktualny `ThemeInterface`, bez przejmowania kodu ani
zależności starego CMS-a. Motyw wybiera się w panelu `/admin/settings`.

Pierwotny terminal Hero jest startowym rekordem modułu `widgets`, a nie zaszytym
elementem szablonu. Wyłączenie widgetu rozszerza treść Hero na pełną szerokość,
a wpis przypisany do konkretnego motywu może zastąpić wspólny element w jego slocie.

Publiczny serwer udostępnia wyłącznie Front Controller, statyczne prototypy i assety.
Kod, migracje, dokumentacja techniczna, testy oraz repozytorium Git są blokowane przez
główny `.htaccess`.
