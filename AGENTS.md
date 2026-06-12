# Instrukcje pracy nad miniPORTAL

> **Ostatnia aktualizacja:** 2026-06-12 - szczegółowo rozpisano Kroki 5-6, panel administracyjny, ACL oraz logowanie GitHub, Discord i Google.

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
| [ ] | `templates/default/admin-stylebook.html` |
| [ ] | Ekran logowania GitHub / Discord / Google |
| [ ] | Dashboard, sidebar, topbar i breadcrumb |
| [ ] | Tabele, filtry, formularze, paginacja i potwierdzenia |
| [ ] | Widoki 403, 404, stan pusty, sukces i błąd |
| [ ] | Responsywność i dostępność panelu |

### Krok 5B - kontrakt panelu i uwierzytelnianie

| Status | Zadanie |
|--------|---------|
| [ ] | Komponenty panelu w `ThemeInterface` |
| [ ] | `ModuleInterface` i `AdminMenuRegistry` |
| [ ] | Model `users`, `user_identities`, ról i uprawnień |
| [ ] | `AuthService`, ACL i ochrona tras `/admin/*` |
| [ ] | Adapter GitHub OAuth |
| [ ] | Adapter Discord OAuth |
| [ ] | Adapter Google OpenID Connect |
| [ ] | Łączenie wielu tożsamości z jednym kontem |
| [ ] | Bootstrap pierwszego administratora |
| [ ] | Audit log logowań i operacji administracyjnych |

### Krok 5C - moduły treści

| Status | Zadanie |
|--------|---------|
| [ ] | `core_pages`: CRUD, slug, status i publikacja |
| [ ] | Uprawnienia granularne `pages.*` |
| [ ] | WYSIWYG po ukończeniu formularza podstawowego |
| [ ] | `articles` jako niezależny moduł z własnymi trasami i menu |

### Krok 6 - system modułów

| Status | Zadanie |
|--------|---------|
| [ ] | Stabilny `ModuleInterface` na podstawie działających modułów |
| [ ] | Walidacja `info.json`, zależności i zgodności wersji |
| [ ] | Instalator SQL i migracje |
| [ ] | Rejestr `modules_config` |
| [ ] | Aktywacja i deaktywacja tras, menu oraz uprawnień |
| [ ] | Aktualizacja i odinstalowanie modułu |
| [ ] | Ochrona `core_auth` i `core_pages` przed usunięciem |
| [ ] | Uprawnienia managera i audit log operacji |

## Następne kroki

1. Przygotować `templates/default/admin-stylebook.html` z ekranem logowania i szkieletem dashboardu.
2. Na podstawie prototypu zdefiniować komponenty panelu wymagane w `ThemeInterface`.
3. Przed implementacją OAuth skonfigurować bazę oraz produkcyjny plik środowiskowy.

## Uwagi / blokery

### Aktywne blokery

| Data | Opis | Wymagane działanie |
|------|------|--------------------|
| 2026-06-12 | Plik `/etc/miniportal/miniportal.env` nie jest obecnie dostępny dla aplikacji. | Utworzyć plik według `docs/CONFIGURATION.md` i nadać grupie `www-data` prawo odczytu. |
| 2026-06-12 | Baza danych jest wyłączona (`DB_ENABLED=false`), więc nie można jeszcze wykonywać testów `CrudApp` na rzeczywistych tabelach. | Skonfigurować `DB_*`, utworzyć bazę i włączyć połączenie przed modelem `core_auth` i `core_pages`. |
| 2026-06-12 | Stary katalog `theme/` nadal istnieje obok docelowego `templates/`. | Po potwierdzeniu migracji potrzebnych zasobów usunąć stary katalog w osobnym, kontrolowanym zadaniu. |

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

## Historia sesji

### Sesja: 2026-06-12

**Wykonano:**
- przygotowano `index.php` jako widoczny punkt integracji,
- dodano startowy `Bootstrap` i diagnostykę warstw,
- podłączono `CrudApp` jako preferowaną fasadę Medoo,
- utworzono kontrakt oraz domyślną implementację motywu,
- przeniesiono konfigurację bazy do zmiennych środowiskowych.

**Zaktualizowano status:** fundament projektu, pierwszy kontrakt motywu i startowa integracja rdzenia.

**Następne kroki:** autoloader, `ThemeEngine`, `Security`.

### Sesja: 2026-06-12 - konfiguracja środowiska

**Wykonano:**
- dodano obsługę `/etc/miniportal/miniportal.env`,
- dodano `.env.example`, reguły Git i dokumentację konfiguracji Apache,
- opisano komplet ustawień aplikacji i połączenia `CrudApp`.

**Zaktualizowano status:** konfiguracja środowiskowa jest gotowa do uzupełnienia na serwerze.

**Następne kroki:** utworzenie produkcyjnego pliku poza repozytorium i test połączenia z bazą.

### Sesja: 2026-06-12 - ukończenie Kroku 2

**Wykonano:**
- dopracowano wspólny system kolorów, stanów interakcji i responsywności,
- dodano animacje wejścia, orbitę demonstracyjną i obsługę `prefers-reduced-motion`,
- uzupełniono stylebook o demonstrację ruchu i nawigację między prototypami,
- utworzono `templates/default/homepage.html` jako wersję 1 strony SyntaxDevTeam.pl,
- podłączono oba prototypy do integracyjnego `index.php`.

**Zaktualizowano status:** wszystkie zadania Kroku 2 oznaczono jako ukończone.

**Następne kroki:** audyt kontraktu `ThemeInterface`, autoloader PSR-4 i `ThemeEngine`.

### Sesja: 2026-06-12 - autoloader i ThemeEngine

**Wykonano:**
- rozszerzono formularze motywu o `select`, `textarea`, checkbox i automatyczne pole CSRF,
- dodano autoloader przestrzeni `Core`, `Modules` i `Templates`,
- zachowano ładowanie istniejącego `CrudApp.class.php` przez mapę zgodności,
- dodano `ThemeEngine` walidujący i ładujący motyw wskazany przez `APP_THEME`,
- usunięto ręczne zależności Bootstrapa od konkretnego motywu,
- zapisano `CrudApp` jako preferowaną fasadę bazy w specyfikacji.

**Zaktualizowano status:** autoloader i `ThemeEngine` ukończone.

**Następne kroki:** `Security`, bezpieczny obiekt żądania i Router.

### Sesja: 2026-06-12 - ukończenie Kroku 4

**Wykonano:**
- dodano bezpieczną sesję z cookie `HttpOnly`, `SameSite` i trybem ścisłym,
- dodano CSP, ochronę ramek, MIME sniffing, referrer policy, permissions policy i HSTS dla HTTPS,
- dodano 256-bitowe tokeny CSRF i walidację `hash_equals`,
- utworzono obiekt `Request` normalizujący GET, POST, URI, typy logiczne i liczby,
- utworzono Router rozróżniający trasy, metody oraz odpowiedzi 404 i 405,
- podłączono demonstracyjny formularz CSRF do Front Controllera,
- potwierdzono kodowanie prób XSS przez warstwę motywu.

**Zaktualizowano status:** wszystkie zaplanowane elementy Kroku 4 ukończone.

**Następne kroki:** Krok 5, moduł `core_pages` i kontrakt rejestracji modułów.

### Sesja: 2026-06-12 - aktualizacja blokerów

**Wykonano:**
- usunięto nieaktualny bloker PHP 8.4 po potwierdzeniu PHP 8.5.7,
- rozdzielono aktywne blokery od trwałych uwag architektonicznych,
- potwierdzono brak dostępnego pliku środowiskowego i wyłączone połączenie DB,
- doprecyzowano zadanie migracji starego katalogu `theme/`.

**Zaktualizowano status:** sekcja „Uwagi / blokery” odpowiada bieżącemu stanowi repozytorium i środowiska.

**Następne kroki:** konfiguracja produkcyjnego środowiska i bazy przed implementacją trwałego modelu `core_pages`.

### Sesja: 2026-06-12 - szczegółowa roadmapa panelu

**Wykonano:**
- rozpisano panel administracyjny jako osobny ciąg etapów Outside-In,
- zdefiniowano model użytkownika, wielu tożsamości i lokalnego ACL,
- zaplanowano adaptery GitHub, Discord i Google OIDC,
- rozpisano dashboard, profil, użytkowników, role, audit log i ochronę tras,
- uszczegółowiono moduły `core_pages`, `articles` oraz manager modułów.

**Zaktualizowano status:** Kroki 5-6 mają szczegółowe, niezależnie weryfikowalne zadania.

**Następne kroki:** statyczny prototyp panelu i ekranu logowania.
