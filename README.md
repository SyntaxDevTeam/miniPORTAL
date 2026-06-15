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
RSA-SHA256 zweryfikowanego kluczem publicznym z `config/module_publishers.php`.

Publiczny serwer udostępnia wyłącznie Front Controller, statyczne prototypy i assety.
Kod, migracje, dokumentacja techniczna, testy oraz repozytorium Git są blokowane przez
główny `.htaccess`.
