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

Anonimowa strona główna korzysta z tagowego cache szablonów w `cache/templates`.
Publiczne podstrony i artykuły używają tego samego cache z granularnymi tagami.
Zmiany stron, artykułów, sekcji i motywu automatycznie unieważniają zależne wpisy.
Moduł `wikipedia` dodaje projektową bazę wiedzy z projektami i stronami dokumentacji.

Projekt deklaruje PHP 8.4 lub nowszy jako wymaganie runtime; PHP 8.5 nie jest już
wymagane do uruchomienia produkcyjnego handlera.

Publiczny serwer udostępnia wyłącznie Front Controller, statyczne prototypy i assety.
Kod, migracje, dokumentacja techniczna, testy oraz repozytorium Git są blokowane przez
główny `.htaccess`.
