# Instrukcje pracy nad miniPORTAL

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
| [ ] | Router |
| [x] | Startowa integracja bazy przez fasadę `CrudApp`/Medoo |
| [ ] | Security |
| [x] | Startowy Bootstrap |
| [x] | ThemeEngine z wyborem motywu |

### Kroki 5-6

| Status | Zadanie |
|--------|---------|
| [ ] | Moduły `core_pages`, `core_auth`, `articles` |
| [ ] | Manager i instalator modułów |
| [ ] | Aktywacja modułów przez router |

## Następne kroki

1. Dodać `Security` z bezpieczną sesją, tokenami CSRF i filtrowaniem wejścia.
2. Utworzyć obiekt żądania bez bezpośredniego używania `$_GET` i `$_POST` w modułach.
3. Zaimplementować Router korzystający z bezpiecznego obiektu żądania.

## Uwagi / blokery

| Data | Opis |
|------|------|
| 2026-06-10 | Stary katalog `theme/` pozostaje materiałem migracyjnym; nowy kod trafia do `templates/`. |
| 2026-06-12 | Środowisko CLI udostępnia PHP 8.4.15, mimo że docelowa specyfikacja wymaga PHP 8.5. |
| 2026-06-12 | Połączenie z bazą wymaga zmiennych środowiskowych `DB_ENABLED=1` i `DB_*`; dane dostępowe usunięto z kodu. |
| 2026-06-12 | Produkcyjny plik środowiskowy znajduje się domyślnie w `/etc/miniportal/miniportal.env`; `.env.example` nie może zawierać sekretów. |
| 2026-06-12 | `CrudApp.class.php` zachowuje historyczną nazwę pliku; autoloader obsługuje ją przez jawną mapę zgodności. |

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
