# miniPORTAL
Autorski mini-CMS w systemie modularnym

## Dokumentacja
- [Pierwotny zarys - koncepcja](docs/SZKIC.md)
- [Specyfikacja techniczna i plan rozwoju](docs/TECHNICAL_SPECIFICATION.md)
- [Konfiguracja środowiska](docs/CONFIGURATION.md)
- [Plan panelu administracyjnego i logowania](docs/ADMIN_PANEL_PLAN.md)
- [Przykładowy moduł edukacyjny](install/mod/LearningModule/README.md)

## Weryfikacja

```bash
php tests/run.php
find core modules templates config tests bin install/mod -type f -name '*.php' -print0 | xargs -0 -n1 php -l
php bin/migrate-core.php
```

Zewnętrzne moduły z własną fabryką wymagają jawnego pochodzenia oraz podpisu
RSA-SHA256 zweryfikowanego aktywnym albo poprawnie wycofanym kluczem publicznym
z `config/module_publishers.php`. Klucz unieważniony blokuje pakiet.
Zweryfikowany import można zatwierdzić w managerze: pakiet jest ponownie sprawdzany
i atomowo przenoszony z `cache/module-quarantine` do `modules/`. Zatwierdzenie nie
instaluje modułu ani nie wykonuje jego fabryki; instalacja pozostaje osobną akcją.
Zainstalowane moduły typu `extension` można eksportować z managera do archiwum ZIP;
eksport blokuje dowiązania symboliczne i ukryte ścieżki, a paczka zachowuje
top-level katalog modułu z `info.json`.

Anonimowa strona główna korzysta z tagowego cache szablonów w `cache/templates`.
Publiczne podstrony i artykuły używają tego samego cache z granularnymi tagami.
Zmiany stron, artykułów, sekcji i motywu automatycznie unieważniają zależne wpisy.
Moduły `wikipedia` i `articles` dodają publiczne sekcje dokumentacji oraz artykułów.
Aktywne moduły mogą deklarować publiczne linki, którym administrator nadaje etykietę
i niezależnie przypina je do głównego menu, stopki albo obu obszarów w `/admin/settings`.
Publiczne linki generowane przez motywy używają przyjaznych adresów, np. `/wiki`
i `/wiki/project/punisherx`, zamiast technicznych parametrów `index.php?route=...`.
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
`projects` jest katalogiem projektów łączącym status realizacji z istniejącą
podstroną `core_pages` i dokumentacją `wikipedia`. Udostępnia publiczne adresy
`/projects` oraz `/projects/{slug}` i nie duplikuje treści należących do tych modułów.

Projekt deklaruje PHP 8.4 lub nowszy jako wymaganie runtime; PHP 8.5 nie jest już
wymagane do uruchomienia produkcyjnego handlera.

Publiczny serwer udostępnia wyłącznie Front Controller, statyczne prototypy i assety.
Kod, migracje, dokumentacja techniczna, testy oraz repozytorium Git są blokowane przez
główny `.htaccess`.
