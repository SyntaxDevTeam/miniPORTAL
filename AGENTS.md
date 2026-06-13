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
| [ ] | WYSIWYG po ukończeniu formularza podstawowego |
| [ ] | `articles` jako niezależny moduł z własnymi trasami i menu |

### Krok 6 - system modułów

| Status | Zadanie |
|--------|---------|
| [ ] | Stabilny `ModuleInterface` na podstawie działających modułów |
| [x] | Startowy `ModuleRegistry` jako jeden punkt uruchamiania modułów |
| [ ] | Walidacja `info.json`, zależności i zgodności wersji |
| [ ] | Instalator SQL i migracje |
| [ ] | Rejestr `modules_config` |
| [ ] | Aktywacja i deaktywacja tras, menu oraz uprawnień |
| [ ] | Aktualizacja i odinstalowanie modułu |
| [ ] | Ochrona `core_auth` i `core_pages` przed usunięciem |
| [ ] | Uprawnienia managera i audit log operacji |

## Następne kroki

1. Przygotować `articles` jako drugi rzeczywisty moduł korzystający wyłącznie z ogólnych komponentów Theme.
2. Na podstawie `core_pages` i `articles` ustabilizować `ModuleInterface` oraz metadane `info.json`.
3. Zastąpić demonstracyjne sekcje użytkowników i modułów rzeczywistymi modułami lub ukryć je do czasu implementacji.
4. Dopiero potem dodać WYSIWYG oraz zaprojektować cache z jednoznacznym unieważnianiem.

## Uwagi / blokery

### Aktywne blokery

| Data | Opis | Wymagane działanie |
|------|------|--------------------|
| 2026-06-13 | `DemoAdminModule` nadal udostępnia atrapy sekcji Artykuły, Użytkownicy i Moduły. | Zastępować je kolejno rzeczywistymi modułami; nie traktować atrap jako ukończonych funkcji. |
| 2026-06-13 | Cache szablonów nie ma jeszcze kontraktu unieważniania. | Zaprojektować po stabilizacji modułów i przed oznaczeniem wymagania wydajności jako ukończonego. |

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

### Sesja: 2026-06-12 - Krok 5A

**Wykonano:**
- utworzono `templates/default/admin-stylebook.html`,
- przygotowano jednolity ekran logowania GitHub, Discord i Google,
- zbudowano responsywny shell panelu z sidebar, topbar, breadcrumb i mobilnym offcanvasem,
- dodano dashboard, metryki, aktywność i stan modułów,
- dodano filtrowaną tabelę, paginację, formularz redakcyjny i modal potwierdzenia,
- zdefiniowano widoki sukcesu, błędu, 403, 404, pustego stanu i ładowania,
- podłączono prototyp do nawigacji istniejących widoków.

**Zaktualizowano status:** wszystkie zadania Kroku 5A ukończone.

**Następne kroki:** komponenty panelu w `ThemeInterface`, `AdminMenuRegistry` i `ModuleInterface`.

### Sesja: 2026-06-12 - początek Kroku 5B

**Wykonano:**
- odwzorowano shell panelu, breadcrumb, metryki, panele i tabele w `ThemeInterface`,
- zaimplementowano komponenty panelu w domyślnym motywie,
- dodano `AdminMenuRegistry` z filtrowaniem według uprawnień i ochroną duplikatów,
- zdefiniowano minimalny `ModuleInterface`,
- utworzono `DemoAdminModule`, który rejestruje menu i trasy bez generowania HTML,
- uruchomiono dynamiczny panel pod `/admin-demo`, `/admin-demo/pages` i `/admin-demo/articles`.

**Zaktualizowano status:** dwa pierwsze zadania Kroku 5B ukończone.

**Następne kroki:** konfiguracja DB, model tożsamości i lokalne ACL.

### Sesja: 2026-06-13 - model i ACL `core_auth`

**Wykonano:**
- dodano migrację użytkowników, tożsamości, ról, uprawnień i zdarzeń logowania,
- utworzono modele `User` i `ExternalIdentity` oraz repozytoria pamięciowe i `CrudApp`,
- zaimplementowano `AuthService`, `AuthorizationService` i `AdminAccessGate`,
- przeniesiono panel na chronione trasy `/admin/*`,
- dodano filtrowanie menu według ACL, odpowiedzi 401/403 i bezpieczne wylogowanie,
- zabezpieczono lokalne konta demonstracyjne flagą `AUTH_DEMO_ENABLED`.

**Zaktualizowano status:** model i lokalne ACL Kroku 5B są gotowe; wykonanie migracji
na rzeczywistej bazie pozostaje zablokowane przez brak konfiguracji DB.

**Następne kroki:** uruchomienie migracji, bootstrap pierwszego administratora
i kontrakt adapterów GitHub, Discord oraz Google.

### Sesja: 2026-06-13 - migracja i adapter GitHub

**Wykonano:**
- potwierdzono odczyt `/etc/miniportal/miniportal.env` przez `www-data`,
- potwierdzono połączenie MySQL przez `CrudApp` i sterownik `pdo_mysql`,
- wykonano migrację `modules/CoreAuth/install.sql`,
- zweryfikowano siedem tabel, role, uprawnienia i indeks tożsamości,
- dodano `IdentityProviderInterface`, rejestr dostawców i magazyn OAuth,
- zaimplementowano adapter GitHub z Authorization Code, `state` i PKCE,
- podłączono trasy startu i callbacku do `CoreAuthModule`,
- sprawdzono publiczny widok logowania przez HTTPS.

**Zaktualizowano status:** migracja i implementacja adaptera GitHub są ukończone.
Pełny test z GitHub wymaga zewnętrznej rejestracji aplikacji i sekretów.

**Następne kroki:** bootstrap pierwszego administratora oraz adapter Discord.

### Sesja: 2026-06-13 - bootstrap administratora i Discord

**Wykonano:**
- potwierdzono aktywną konfigurację i przekierowanie GitHub OAuth,
- dodano `FirstAdminBootstrapper` z transakcją i blokadą bazodanową,
- dodano komendę `bin/bootstrap-admin.php` oraz bezpieczny tryb `--dry-run`,
- bootstrap rozwiązuje login GitHub do niezmiennego numerycznego `subject`,
- dodano adapter Discord Authorization Code z walidacją `state`,
- podłączono Discord do wspólnego rejestru providerów i widoku logowania,
- potwierdzono, że nieskonfigurowany Discord pozostaje niewidoczny i zwraca 503.

 **Zaktualizowano status:** mechanizm bootstrapu i adapter Discord są ukończone.
Operacyjne utworzenie administratora wymaga potwierdzonego loginu GitHub, a pełny
test Discord wymaga zewnętrznych danych aplikacji.

**Następne kroki:** utworzenie pierwszego konta administratora i adapter Google OIDC.

### Sesja: 2026-06-13 - Google OIDC, tożsamości i audit log

**Wykonano:**
- dodano adapter Google OIDC z `state`, `nonce`, PKCE i walidacją podpisu RS256,
- dodano kontekst OAuth rozróżniający logowanie od łączenia kont,
- dodano widok `/admin/identities` oraz operacje łączenia i odłączania providerów,
- zablokowano automatyczne łączenie po e-mailu i usunięcie ostatniej tożsamości,
- dodano `AuditLogService` zapisujący operacje do `auth_events`,
- objęto audytem logowania, callbacki, wylogowanie, ACL, bootstrap i zmiany kont,
- potwierdzono aktywną konfigurację GitHub i Discord.

**Zaktualizowano status:** trzy ostatnie implementacyjne zadania Kroku 5B są ukończone.
Google i pseudonimizacja IP oczekują wyłącznie na zewnętrzną konfigurację sekretów.
Pierwszy administrator istnieje już w bazie.

**Następne kroki:** konfiguracja Google i klucza audit logu, następnie przejście
do Kroku 5C `core_pages`.

### Sesja: 2026-06-13 - podstawowy moduł `core_pages`

**Wykonano:**
- potwierdzono aktywną konfigurację Google i klucza HMAC audit logu,
- dodano migrację tabeli `core_pages` z unikalnym slugiem i indeksami,
- dodano model `Page` oraz repozytorium korzystające z `CrudApp`,
- wdrożono listę, tworzenie, edycję, publikację, cofnięcie publikacji i usuwanie,
- dodano granularną ochronę `pages.view/create/edit/delete/publish`,
- podłączono menu i trasy modułu bez generowania HTML w logice modułu,
- dodano publiczny odczyt opublikowanej strony po slugu,
- potwierdzono CSRF, audit log i kodowanie próby XSS.

**Zaktualizowano status:** podstawowy CRUD `core_pages` i uprawnienia `pages.*`
są ukończone. Formularz podstawowy jest gotowy do rozszerzenia o WYSIWYG.

**Następne kroki:** integracja WYSIWYG oraz niezależny moduł `articles`.

### Sesja: 2026-06-13 - spięcie przepływu Outside-In

**Wykonano:**
- zastąpiono diagnostyczny widok `/` dynamiczną wersją gotowego prototypu homepage,
- dodano na stronie głównej link do logowania albo panelu zależnie od sesji,
- podłączono listę opublikowanych stron `core_pages` do homepage,
- zachowano statyczne prototypy jako źródła wyglądu i dokumentację komponentów,
- dodano chronioną sekcję `/admin/design-system` z linkami do stylebooków,
- przeniesiono test `Security` i `Request` do zestawu materiałów panelu,
- poprawiono link marki panelu tak, aby prowadził do właściwego dashboardu.

**Zaktualizowano status:** publiczna strona, logowanie, panel, treści i wizualne
źródła projektu tworzą jeden namacalny przepływ Outside-In.

**Następne kroki:** WYSIWYG dla `core_pages` oraz niezależny moduł `articles`.

### Sesja: 2026-06-13 - audyt zgodności i etap korekcyjny

**Wykonano:**
- zablokowano publiczny dostęp do `.git`, kodu, SQL, konfiguracji, testów i dokumentacji technicznej,
- wyłączono zapisywanie callbacków OAuth w access logu i zredagowano historyczne `code` oraz `state`,
- dodano konfigurowalny limiter prób OAuth i testy CSRF, `state`, replay, ACL oraz blokady konta,
- dodano `ModuleRegistry` jako jeden punkt uruchamiania modułów,
- usunięto metody `core_pages` z globalnego `ThemeInterface` na rzecz ogólnej tabeli akcji,
- usunięto nieużywany katalog `theme/` po potwierdzeniu migracji do `templates/`.

**Zaktualizowano status:** projekt wrócił do komponentowego kontraktu Theme i kontrolowanej
rejestracji modułów; atrapy panelu oraz cache pozostają jawnie oznaczonymi brakami.

**Następne kroki:** rzeczywisty moduł `articles`, stabilizacja metadanych modułów,
a następnie WYSIWYG i cache z kontraktem unieważniania.
