# Instrukcje pracy nad miniPORTAL

> **Ostatnia aktualizacja:** 2026-06-13 - audyt zgodności, ochrona wdrożenia i korekta kontraktów modułów.

Plan projektu jest źródłem prawdy. Przed rozpoczęciem każdego etapu przeczytaj:

1. `README.md` - mapę dokumentacji projektu.
2. `docs/SZKIC.md` - pierwotne założenia i wymaganie maksymalnej modularności.
3. `docs/TECHNICAL_SPECIFICATION.md` - architekturę, bezpieczeństwo i plan wykonawczy.

Jeśli kod i dokumentacja są niespójne, wybierz rozwiązanie zgodne ze specyfikacją techniczną albo najpierw zaktualizuj dokumentację.

## Zasady pracy

- Stosuj Outside-In: prototyp, `ThemeInterface`, rdzeń, moduły, manager modułów.
- Wskaż fazę i krok specyfikacji realizowane przez zmianę.
- Nie rozpoczynaj kolejnej fazy bez spełnienia zależności poprzedniej.
- Ogranicz zmianę do najmniejszego kompletnego elementu, który można niezależnie sprawdzić.
- Zachowuj podział Core -> Modules -> Templates.
- Moduły nie mogą zależeć od HTML ani konkretnego frameworka CSS.
- Preferowaną fasadą operacji bazodanowych rdzenia jest `core/database/CrudApp.class.php`, korzystająca z Medoo.
- Nie omijaj warstw Theme, Database/CrudApp i Security bez udokumentowanego powodu.
- Nie odczytuj bezpośrednio `$_GET` ani `$_POST` w modułach; dane wejściowe waliduj i normalizuj.
- Koduj dane HTML przez `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`, używaj przygotowanych zapytań i tokenów CSRF.
- Zachowuj zgodność z PHP 8.5 i nie wprowadzaj frameworka aplikacyjnego bez zmiany dokumentacji.
- Minimalizuj diff i nie rozbudowuj starego katalogu `theme/`; nowa prezentacja trafia do `templates/`.

## Weryfikacja

- Uruchom wszystkie kontrole dostępne w repozytorium.
- Dla zmienionych plików PHP uruchom co najmniej `php -l`.
- Dla statycznego HTML/CSS/JavaScript użyj dostępnego walidatora albo testu uruchomieniowego.
- Jeśli repozytorium zawiera `gradlew`, uruchom `chmod +x gradlew` i `./gradlew test --console=plain`.
- Nie deklaruj ukończenia bez wykazania kryteriów lub opisania ograniczeń środowiska.

### Faza 0 - dokumentacja i fundament

| Status | Zadanie |
|--------|---------|
| [x] | Specyfikacja techniczna |
| [x] | README i instrukcje agentów |
| [x] | Podstawowa struktura `config/`, `core/`, `modules/`, `templates/`, `cache/` |
| [x] | `config/config.php` |
| [x] | Punkt wejścia `index.php` |

### Krok 2 - prototyp wizualny i stylebook

| Status | Zadanie |
|--------|---------|
| [x] | `templates/default/stylebook.html` |
| [x] | Navbar, cards, tables, forms, buttons, alerts i footer |
| [x] | Dopracowanie CSS i animacji |
| [x] | Wersja 1 strony głównej SyntaxDevTeam.pl |

### Krok 3 - odwzorowanie prototypu w PHP

| Status | Zadanie |
|--------|---------|
| [x] | `ThemeInterface` |
| [x] | `templates/default/theme.php` |
| [x] | Metody kart, przycisków, alertów, formularzy, tabel i CSRF |
| [x] | Użycie metod motywu przez integracyjny `index.php` |

### Krok 4 - rdzeń systemu

| Status | Zadanie |
|--------|---------|
| [x] | Autoloader PSR-4 z mapą zgodności dla `CrudApp.class.php` |
| [x] | Router z obsługą 404 i 405 |
| [x] | Startowa integracja bazy przez fasadę `CrudApp`/Medoo |
| [x] | Security: sesja, CSRF, CSP i nagłówki ochronne |
| [x] | Startowy Bootstrap |
| [x] | ThemeEngine z wyborem motywu |
| [x] | Filtrowany i normalizowany obiekt Request |

### Krok 5A - prototyp panelu administracyjnego

| Status | Zadanie |
|--------|---------|
| [x] | `templates/default/admin-stylebook.html` |
| [x] | Ekran logowania GitHub / Discord / Google |
| [x] | Dashboard, sidebar, topbar i breadcrumb |
| [x] | Tabele, filtry, formularze, paginacja i potwierdzenia |
| [x] | Widoki 403, 404, stan pusty, sukces i błąd |
| [x] | Responsywność i dostępność panelu |

### Krok 5B - kontrakt panelu i uwierzytelnianie

| Status | Zadanie |
|--------|---------|
| [x] | Komponenty panelu w `ThemeInterface` |
| [x] | `ModuleInterface` i `AdminMenuRegistry` |
| [x] | Model `users`, `user_identities`, ról i uprawnień |
| [x] | `AuthService`, ACL i ochrona tras `/admin/*` |
| [x] | Wykonanie migracji `CoreAuth/install.sql` na skonfigurowanej bazie |
| [x] | Adapter GitHub OAuth |
| [x] | Adapter Discord OAuth |
| [x] | Adapter Google OpenID Connect |
| [x] | Łączenie wielu tożsamości z jednym kontem |
| [x] | Bootstrap pierwszego administratora |
| [x] | Audit log logowań i operacji administracyjnych |
| [x] | Sesyjny limiter rozpoczęć i callbacków OAuth |
| [x] | Testy CSRF, `state`, replay, ACL i blokady konta |

### Krok 5C - moduły treści

| Status | Zadanie |
|--------|---------|
| [x] | `core_pages`: CRUD, slug, status i publikacja |
| [x] | Uprawnienia granularne `pages.*` |
| [x] | Podstrony projektowe, informacyjne i prawne z opisem SEO |
| [x] | Nawigacja główna/stopka, kolejność i publiczne adresy `/p/slug` |
| [x] | Powiązanie kart strony głównej z opublikowanymi podstronami |
| [x] | Sekcje strony głównej: typ, nagłówki, treść, układ, kolejność i widoczność |
| [x] | Elementy sekcji kolumnowych: karty, CTA, wariant, szerokość i kolejność |
| [x] | Kontrolowany WYSIWYG strony głównej z sanitizacją po stronie serwera |
| [x] | Rozszerzenie WYSIWYG na zwykłe podstrony `core_pages` |
| [x] | Przełącznik WYSIWYG / Markdown dla sekcji, kart, podstron i artykułów |
| [x] | Podgląd roboczy i lokalny autozapis formularzy treści |
| [x] | `articles` jako niezależny moduł z kategoriami, własnymi trasami i menu |

### Krok 6 - system modułów

| Status | Zadanie |
|--------|---------|
| [x] | Stabilny `ModuleInterface` na podstawie działających modułów |
| [x] | Startowy `ModuleRegistry` jako jeden punkt uruchamiania modułów |
| [x] | Walidacja `info.json`, zależności i zgodności wersji |
| [x] | Deklaratywne fabryki modułów poza `index.php` |
| [x] | Instalator SQL i migracje |
| [x] | Rejestr `modules_config` |
| [x] | Aktywacja i deaktywacja tras, menu oraz uprawnień |
| [ ] | Aktualizacja i odinstalowanie modułu |
| [x] | Ochrona `core_auth` i `core_pages` przed wyłączeniem i usunięciem |
| [x] | Uprawnienia managera i audit log operacji |

## Następne kroki

1. Dodać kontrolowaną aktualizację wersji modułu.
2. Dodać bezpieczne odinstalowanie rozszerzeń wraz z opcją zachowania danych.
3. Rozbudować manager o podgląd historii wykonanych migracji.
4. Zastąpić demonstracyjny widok `/admin/users` rzeczywistym modułem użytkowników.

## Uwagi / blokery

### Aktywne blokery

| Data | Opis | Wymagane działanie |
|------|------|--------------------|
| 2026-06-14 | `SystemAdminModule` nadal udostępnia atrapę sekcji Użytkownicy. | Zastąpić ją rzeczywistym modułem zarządzania użytkownikami; nie traktować atrapy jako ukończonej funkcji. |
| 2026-06-13 | Cache szablonów nie ma jeszcze kontraktu unieważniania. | Zaprojektować po stabilizacji modułów i przed oznaczeniem wymagania wydajności jako ukończonego. |
| 2026-06-14 | CLI działa na PHP 8.5.7, ale Apache dla `new.syntaxdevteam.pl` używa PHP 8.4.15. | Przełączyć handler Apache na PHP 8.5; manifesty deklarują rzeczywiste minimum kodu `>=8.4`, a wersją docelową projektu pozostaje PHP 8.5. |

### Uwagi architektoniczne

| Data | Opis |
|------|------|
| 2026-06-12 | Środowisko CLI działa na PHP 8.5.7 i spełnia wymaganie wersji PHP 8.5. |
| 2026-06-12 | `CrudApp.class.php` zachowuje historyczną nazwę pliku; autoloader obsługuje ją przez jawną mapę zgodności. |
| 2026-06-12 | Statyczne prototypy HTML omijają Front Controller; nagłówki `Security` obejmują dynamiczne odpowiedzi aplikacji. |
| 2026-06-12 | Sekrety pozostają poza repozytorium; `.env.example` zawiera wyłącznie wartości przykładowe. |
| 2026-06-12 | Konto i role są lokalne; GitHub, Discord i Google dostarczają wyłącznie zewnętrzne tożsamości. Konto nie może być automatycznie łączone wyłącznie po adresie e-mail. |
| 2026-06-12 | Dla GitHub przed implementacją trzeba wybrać GitHub App albo OAuth App; GitHub rekomenduje GitHub App dla nowych integracji. |
| 2026-06-12 | Implementacja adapterów wymaga rejestracji aplikacji i callbacków u GitHub, Discord i Google; nie blokuje to statycznego prototypu panelu. |
| 2026-06-13 | Repozytorium pamięciowe i konta demonstracyjne są dostępne wyłącznie po jawnym ustawieniu `AUTH_DEMO_ENABLED=1`; domyślnie logowanie demonstracyjne jest wyłączone. |
| 2026-06-13 | `/etc/miniportal/miniportal.env` jest czytelny dla `www-data`, sterownik `pdo_mysql` działa, a migracja `CoreAuth` została wykonana na bazie. |
| 2026-06-13 | Adapter GitHub używa Authorization Code, jednorazowego `state` i PKCE `S256`; token dostawcy nie jest zapisywany. |
| 2026-06-13 | Konfiguracja GitHub jest aktywna; publiczny panel generuje poprawne przekierowanie OAuth. |
| 2026-06-13 | Bootstrap administratora korzysta z numerycznego GitHub `subject`, transakcji i blokady bazodanowej; nie łączy kont po e-mailu. |
| 2026-06-13 | Adapter Discord korzysta z Authorization Code, `state` i minimalnych zakresów `identify email`. |
| 2026-06-13 | GitHub i Discord są aktywne w środowisku i generują poprawne przekierowania OAuth. |
| 2026-06-13 | Google OIDC waliduje podpis RS256, `iss`, `aud`, `exp`, `iat`, `nonce` i PKCE. |
| 2026-06-13 | Łączenie tożsamości wymaga aktywnej sesji tego samego konta i nie pozwala usunąć ostatniego providera. |
| 2026-06-13 | Audit log zapisuje wyniki logowania, callbacków, wylogowania, ACL, bootstrapu oraz zmian tożsamości; nie zapisuje tokenów. |
| 2026-06-13 | Pierwszy użytkownik jest aktywny, ma tożsamość GitHub i lokalną rolę `administrator`; bootstrap nie jest już dostępny. |
| 2026-06-13 | GitHub, Discord i Google są aktywne w środowisku, a `AUTH_AUDIT_HASH_KEY` pseudonimizuje IP przez HMAC. |
| 2026-06-13 | `core_pages` używa `CrudApp`, unikalnego slugu, stanów `draft/published`, CSRF i uprawnień `pages.*`. |
| 2026-06-13 | Publiczna trasa `/page?slug=...` pokazuje wyłącznie strony opublikowane i koduje treść przed HTML. |
| 2026-06-13 | Trasa `/` renderuje dynamiczną wersję prototypu homepage i automatycznie pokazuje opublikowane strony z `core_pages`. |
| 2026-06-13 | Prototypy HTML pozostają źródłami wyglądu; administrator otwiera je przez chronioną sekcję `/admin/design-system`. |
| 2026-06-13 | Główny `.htaccess` blokuje `.git`, kod Core/Modules, SQL, konfigurację i dokumentację techniczną; assety i prototypy pozostają publiczne. |
| 2026-06-13 | Callbacki OAuth są pomijane w access logu Apache, a historyczne wartości `code` i `state` zostały zredagowane. |
| 2026-06-13 | `core_pages` składa panel z ogólnych komponentów Theme; kontrakt nie zawiera już metod nazwanych według modułu. |
| 2026-06-13 | `ModuleRegistry` jest pojedynczym punktem rejestracji menu i tras modułów w `index.php`. |
| 2026-06-13 | Stary katalog `theme/` nie miał aktywnych odwołań i został usunięty po potwierdzeniu migracji do `templates/`. |
| 2026-06-13 | `articles` ma osobne tabele kategorii i treści, pełny CRUD, publikację, publiczną listę i widok, ACL, CSRF oraz audit log. |
| 2026-06-13 | Migracja `modules/Articles/install.sql` została wykonana; DDL MySQL zatwierdza się automatycznie i nie może być traktowane jak jedna transakcja PDO. |
| 2026-06-13 | `core_pages` zarządza sekcjami strony głównej przez tabelę `homepage_sections`; moduł przechowuje dane i wariant układu, a aktywny motyw odpowiada za HTML oraz CSS. |
| 2026-06-13 | Lokalny edytor WYSIWYG dopuszcza wyłącznie kontrolowane znaczniki tekstowe; skrypty, osadzenia, obrazy i atrybuty HTML są usuwane po stronie serwera. |
| 2026-06-13 | Układ `columns` korzysta z tabeli `homepage_section_items`; moduł przechowuje warianty `primary`, `violet`, `neutral` i szerokość logiczną, a motyw mapuje je na responsywną siatkę kart. |
| 2026-06-14 | `ModuleInterface` deklaruje wersję, zależności i ochronę; `ModuleRegistry` uruchamia moduły w kolejności topologicznej i odrzuca brakujące lub cykliczne zależności. |
| 2026-06-14 | Każdy aktywny moduł ma walidowany `info.json`; deklaratywne fabryki znajdują się w `config/modules.php`, a `index.php` nie tworzy konkretnych modułów. |
| 2026-06-14 | `core_pages` przechowuje uniwersalne dokumenty typu `standard`, `project` i `legal`; opublikowane strony mogą pojawiać się w menu głównym albo stopce i mają adres `/p/slug`. |
| 2026-06-14 | Element strony głównej może wskazywać `page_id`; motyw tworzy link wyłącznie wtedy, gdy powiązana podstrona jest opublikowana. |
| 2026-06-14 | Sekcje homepage, karty, podstrony i artykuły przechowują format `html`/`markdown`; wspólny renderer Core koduje surowy HTML Markdown i obsługuje składnię w stylu GitHub. |
| 2026-06-14 | `modules_config` steruje uruchamianiem modułów; wyłączony moduł nie rejestruje tras ani menu, a moduły chronione pozostają aktywne. |
| 2026-06-14 | `module_migrations` przechowuje nazwę i SHA-256 migracji; zmiana wykonanego pliku jest odrzucana zamiast uruchamiana ponownie. |

## Historia sesji

Pełna historia sesji jest i powinna każdorazowo być zapisywana w `/docs/logs/HISTORIA_SESJI.md`.