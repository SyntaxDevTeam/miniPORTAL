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
strona `/translations` grupuje pliki według katalogu pluginów i pozwala użytkownikom wgrać
`.yml/.yaml` metodą przeciągnij/upuść, wybrać język docelowy ISO `XX`, uzupełnić
tłumaczenie w edytorze z wyrównanym oryginałem i zapisać je domyślnie jako szkic.
Wprowadzanie i zapis wymagają logowania; konto oczekujące może pracować nad
tłumaczeniami publicznymi bez dostępu do panelu admina, a rozpoczęta praca jest
wznawiana po OAuth. Widok `/translations/mine` pozwala wrócić do własnych szkiców,
zgłoszeń gotowych do sprawdzenia i odrzuconych prac. Edytor ma podgląd HTML
formatowania Minecraft legacy, RGB i MiniMessage, pokazuje zmienne typu `<player>`
oraz błędy składni formatowania. Panel `/admin/plugin-translator` pokazuje
zgłoszenia, postęp, statusy oraz akcje zatwierdzenia, odrzucenia i pobrania
zweryfikowanego YAML. Zalogowany użytkownik może także przesłać gotowy plik przez
`/translations/upload-ready`; po akceptacji plik trafia do publicznego katalogu
pluginu i może zostać pobrany. Panel `/admin/plugin-translator/plugins` zarządza
katalogiem pluginów. Jednorazowe narzędzie eksportu administratora pozostało pod
`/admin/plugin-translator/tool`.
`team` jest osobnym modułem prezentacji zespołu. Publiczne `/team` pokazuje
widocznych członków, a `/team/member/{slug}` prowadzi do publicznego profilu
powiązanego z lokalnym kontem użytkownika i jego avatarem. Panel `/admin/team`
zarządza widocznością, opisem, rolą i kolejnością profili.

Projekt deklaruje PHP 8.4 lub nowszy jako wymaganie runtime; PHP 8.5 nie jest już
wymagane do uruchomienia produkcyjnego handlera.

Publiczny serwer udostępnia wyłącznie Front Controller, statyczne prototypy i assety.
Kod, migracje, dokumentacja techniczna, testy oraz repozytorium Git są blokowane przez
główny `.htaccess`.
