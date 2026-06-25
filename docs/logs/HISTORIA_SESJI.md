### Sesja: 2026-06-14 - audyt SystemAdminModule i panel użytkowników

**Wykonano:**
- porównano odpowiedzialności panelu z `SZKIC.md` i specyfikacją techniczną,
- usunięto atrapę użytkowników z `SystemAdminModule`,
- przeniesiono menu użytkowników i tożsamości do ich właściciela `CoreAuth`,
- dodano listę kont, statusów, ról, providerów i dat ostatniego logowania,
- dodano atomową zmianę statusu i roli z ACL, CSRF i audit logiem,
- zabezpieczono własne konto i ostatniego aktywnego administratora,
- dashboard pokazuje rzeczywisty stan modułów i oczekujących migracji.
- zablokowano instalowanie manifestów bez zarejestrowanej fabryki wykonawczej.

**Zaktualizowano status:** podział Core -> Modules -> Templates jest ponownie
spójny z planem. Otwarte pozostają edytor definicji ról/uprawnień, widok audit logu
oraz aktualizacja i odinstalowanie rozszerzeń.

### Sesja: 2026-06-14 - trwały manager modułów

**Wykonano:**
- ukryto stały link `Podstrony` w top menu przed niezalogowanymi użytkownikami,
- dodano rejestr `modules_config` i historię `module_migrations`,
- dodano instalację SQL, migracje z sumą kontrolną i stan bazowy istniejących modułów,
- podłączono aktywność modułów do `ModuleBootstrapper`, tras i menu,
- zastąpiono atrapę `/admin/modules` rzeczywistym managerem,
- dodano ochronę zależności i modułów stałych, granularne ACL, CSRF oraz audit log.

**Zaktualizowano status:** cztery wcześniejsze zadania Kroku 6 są ukończone.
Wyłączenie `articles` usuwa jego trasy, a ponowne włączenie przywraca je w następnym żądaniu.

### Sesja: 2026-06-14 - Markdown w edytorach treści

**Wykonano:**
- dodano przełącznik edytora wizualnego i źródłowego Markdown,
- dodano wspólny `ContentRenderer` i bezpieczny renderer Markdown,
- objęto formatem sekcje homepage, karty, podstrony `core_pages` i artykuły,
- dodano migracje `content_format` oraz wykonano je na skonfigurowanej bazie,
- uzupełniono autozapis, style tabel, kodu, obrazów i list zadań,
- dodano test ochrony przed wykonywaniem HTML i niedozwolonymi linkami.

**Zaktualizowano status:** długie treści mogą być edytowane jako kontrolowany HTML
albo Markdown w stylu GitHub, a format źródłowy pozostaje zachowany w bazie.

### Sesja: 2026-06-14 - domknięcie treści i kontrakt modułów

**Wykonano:**
- rozszerzono kontrolowany WYSIWYG na podstrony `core_pages`,
- dodano podgląd roboczy strony głównej i autozapis do `localStorage`,
- ustabilizowano `ModuleInterface` oraz kolejność zależności,
- dodano walidację manifestów, wymagań wersji i plików instalacyjnych,
- wydzielono deklaratywne fabryki modułów z Front Controllera.

**Zaktualizowano status:** cztery pozycje z sekcji „Następne kroki” są ukończone.
Krok 6 może przejść do trwałego rejestru i managera modułów.

### Sesja: 2026-06-14 - uniwersalne podstrony

**Wykonano:**
- rozszerzono `core_pages` o typ dokumentu, skrót i opis SEO,
- dodano pozycję w menu głównym lub stopce oraz kolejność,
- dodano publiczną listę `/pages` i adresy dokumentów `/p/slug`,
- zachowano kompatybilną trasę `/page?slug=...`,
- umożliwiono wybór powiązanej podstrony w kartach homepage.

**Zaktualizowano status:** `core_pages` obsługuje opisy projektów, strony
informacyjne oraz dokumenty takie jak polityka prywatności i informacje RODO.

### Sesja: 2026-06-13 - edytor strony głównej

**Wykonano:**
- dodano model i migrację sekcji strony głównej,
- dodano panel CRUD sekcji, zmianę kolejności, układu i widoczności,
- dodano lokalny edytor WYSIWYG z serwerową allowlistą HTML,
- podłączono dynamiczne renderowanie sekcji przez `ThemeInterface`,
- wykonano migrację i utworzono cztery sekcje startowe.

**Zaktualizowano status:** edycja strony głównej ma pierwszeństwo przed dalszą
rozbudową modułu artykułów i stanowi ukończony element Kroku 5C.

**Następne kroki:** podgląd roboczy, autozapis i WYSIWYG zwykłych podstron.

### Sesja: 2026-06-13 - karty sekcji kolumnowych

**Wykonano:**
- dodano trwały model elementów przypisanych do sekcji strony głównej,
- dodano CRUD, widoczność i zmianę kolejności kart w panelu,
- dodano warianty `primary`, `violet`, `neutral` oraz szerokości `standard`, `wide`,
- przeniesiono projekty i technologie do osobnych elementów,
- odtworzono responsywną siatkę kart znaną z prototypu Outside-In.

**Zaktualizowano status:** układ kolumnowy nie dzieli już jednego pola tekstowego;
renderuje niezależne, konfigurowalne karty przez aktywny motyw.

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

### Sesja: 2026-06-13 - moduł `articles`

**Wykonano:**
- dodano encje kategorii i artykułu oraz repozytorium korzystające z `CrudApp`,
- dodano migrację z kluczami obcymi, unikalnymi slugami i indeksami publikacji,
- wdrożono panelowy CRUD artykułów, kategorie, publikację i granularne `articles.*`,
- dodano publiczną listę `/articles` i widok `/article?slug=...`,
- moduł rejestruje własne menu i trasy przez `ModuleRegistry`,
- usunięto demonstracyjną atrapę artykułów z `DemoAdminModule`,
- wykonano migrację oraz pełny test repozytorium i przepływu HTTP z CSRF.

**Zaktualizowano status:** Krok 5C ma dwa rzeczywiste moduły treści korzystające
z tych samych ogólnych komponentów Theme.

**Następne kroki:** walidator `info.json`, deklaratywna fabryka modułów i początek
rejestru `modules_config`.

### Sesja: 2026-06-14 - korekta formatu Markdown strony głównej

**Wykonano:**
- potwierdzono działanie renderera Markdown dla kodu inline,
- zastąpiono ukryte pole formatu jawnym wyborem HTML / Markdown w edytorze,
- dodano wersjonowanie lokalnych assetów CSS i JavaScript przez czas modyfikacji,
- dodano styl kodu inline dla treści publicznych i test regresji backticków,
- poprawiono kartę `PunisherX`, która zawierała Markdown zapisany jako HTML,
- zweryfikowano formularz administratora i wynik strony głównej przez HTTPS.

**Zaktualizowano status:** sekcje i elementy strony głównej poprawnie zachowują
jawny format źródłowy, a składnia `` `tekst` `` renderuje element `<code>`.

### Sesja: 2026-06-14 - korekta lokalnego autozapisu edytorów

**Wykonano:**
- usunięto automatyczne nadpisywanie formularza treścią z `localStorage`,
- dane z bazy pozostają domyślne, a lokalny szkic wymaga jawnego przywrócenia,
- dodano możliwość odrzucenia znalezionej wersji roboczej,
- zmieniono przestrzeń kluczy autozapisu i wygaszono stare wadliwe szkice,
- uzupełniono czyszczenie szkiców po zapisie kart homepage i artykułów,
- przeniesiono czyszczenie szkicu na poziom strony, aby działało także po
  przekierowaniu do widoku listy.

**Zaktualizowano status:** ponowne otwarcie edytora pokazuje stan z bazy.
Autozapis pełni wyłącznie rolę opcjonalnego mechanizmu odzyskiwania treści.

### Sesja: 2026-06-14 - obrazy i badge w Markdown

**Wykonano:**
- wykryto ciche obcięcie osadzonego SVG base64 do limitu 4000 znaków,
- zastąpiono obcinanie walidacją długości i czytelnym komunikatem,
- zablokowano obrazy `data:` na rzecz bezpiecznych adresów HTTPS lub assetów lokalnych,
- dodano podgląd obrazów Markdown w edytorze,
- oczyszczono uszkodzony wpis `CleanerX` i dodano lokalny badge SVG,
- dodano test renderowania obrazu Markdown.

**Zaktualizowano status:** obrazy używają składni `![opis](adres)` i nie są
zapisywane jako rozbudowany kod base64 w treści.

### Sesja: 2026-06-14 - przypisywanie podstron do elementów homepage

**Wykonano:**
- wykryto zmianę numerycznych identyfikatorów stron przez operator rozwijania tablicy,
- zachowano rzeczywiste ID podstron w opcjach pola `page_id`,
- sprawdzono pozostałe pola wyboru pod kątem tego samego wzorca.

**Zaktualizowano status:** element strony głównej wysyła rzeczywisty identyfikator
wybranej podstrony zamiast pozycji opcji na liście.

### Sesja: 2026-06-14 - linkowane badge na podstronach

**Wykonano:**
- odtworzono błąd zagnieżdżonych tokenów dla składni `[![badge](obraz)](link)`,
- dodano bezpieczne renderowanie linkowanego obrazu jako `<a><img></a>`,
- dodano analogiczny podgląd w edytorze Markdown,
- dodano test regresji dla badge prowadzącego do zewnętrznej strony.

**Zaktualizowano status:** badge z README GitHub renderują się jako klikalne
grafiki zamiast technicznych znaczników parsera.

### Sesja: 2026-06-14 - publiczny branding, nadtytuły i UTF-8

**Wykonano:**
- ujednolicono publiczne logo i stopkę jako `SyntaxDevTeam`,
- pozostawiono nazwę `miniPORTAL` wyłącznie dla panelu i warstwy systemowej,
- dodano konfigurację `SITE_NAME` i `SITE_EYEBROW`,
- rozszerzono kontrakt Theme o jawny nadtytuł widoku,
- dodano edytowalne pole `eyebrow` do `core_pages` wraz z migracją,
- ustawiono startowe nadtytuły istniejących stron projektowych i prawnych,
- dodano kontekstowe nadtytuły listy artykułów, artykułu, podstron i błędu 404,
- potwierdzono `utf8mb4` bazy oraz poprawne bajty UTF-8 treści `MedStock`,
- poprawiono parser Markdown, który bez trybu Unicode interpretował bajt litery
  `ą` jako znak nowej linii NEL.

**Zaktualizowano status:** publiczne widoki korzystają z jednej marki, a
nadtytuły podstron można zmieniać z panelu administracyjnego.

### Sesja: 2026-06-14 - usunięcie automatycznej listy podstron z homepage

**Wykonano:**
- usunięto zaszytą w motywie sekcję „Opublikowane strony”,
- zachowano niezależną listę `/pages`, widoki `/p/...`, linki menu i stopki,
- zachowano możliwość przypisywania podstron do zarządzanych kart homepage.

**Zaktualizowano status:** strona główna renderuje wyłącznie sekcje utworzone
w edytorze oraz elementy jawnie z nimi powiązane.

### Sesja: 2026-06-14 - układ kontaktowy i futurystyczna warstwa homepage

**Wykonano:**
- dodano układ sekcji `contact`,
- rozszerzono elementy o typ `card`, `channel` lub `person`,
- dodano kontrolowany wybór ikon Discord, GitHub, Hangar, e-mail, osoby i WWW,
- wdrożono responsywny panel kanałów komunikacji i kontaktów zespołu,
- dodano poświaty, szklaną powierzchnię, siatkę tła i subtelne interakcje kart,
- przekształcono istniejącą sekcję kontaktu i utworzono cztery elementy startowe,
- wykonano migrację `20260614_contact_layout.sql`.

**Zaktualizowano status:** administrator może budować rozbudowane sekcje
kontaktowe bez wprowadzania HTML i klas CSS do modułu treści.

### Sesja: 2026-06-15 - aktualizacja, odinstalowanie i moduł edukacyjny

**Wykonano:**
- dodano kontrolowaną aktualizację wyłącznie do wyższej wersji manifestu,
- dodano preflight sum SHA-256 wszystkich migracji przed wykonaniem pierwszego DDL,
- rozszerzono stan modułu o odinstalowanie z zachowanymi danymi,
- dodano odinstalowanie z zachowaniem danych albo wykonaniem jawnego `uninstall.sql`,
- zablokowano usuwanie modułów aktywnych, chronionych i wymaganych przez zależności,
- dodano potwierdzenia i audit log dla aktualizacji oraz obu wariantów usuwania,
- scalono wynik migracji modułów do ich aktualnych `install.sql`, bez zmiany plików
  historycznych i zapisanych sum kontrolnych,
- utworzono `install/mod/LearningModule` z przykładowym CRUD przez `CrudApp`,
  pełnymi DocBlockami, ACL, CSRF, migracją, fabryką i instrukcją,
- objęto katalog `install/` ochroną głównego `.htaccess`.

**Zaktualizowano status:** Krok 6 obejmuje pełny, kontrolowany cykl życia modułu.
Do dalszej rozbudowy pozostają historia migracji w panelu, edytor ról oraz kontrakt
bezpiecznego importu zewnętrznych pakietów.

### Sesja: 2026-06-15 - przywrócenie panelu po skopiowaniu modułu szkoleniowego

**Wykonano:**
- zidentyfikowano błąd 500 jako walidację kopii `modules/LearningModule` wymagającej
  PHP 8.5 przy handlerze Apache działającym na PHP 8.4.15,
- usunięto wyłącznie nieaktywny duplikat z `modules/`, zachowując pakiet źródłowy
  w `install/mod/LearningModule`,
- ustawiono rzeczywiste minimum pakietu edukacyjnego na PHP 8.4, aby jego ponowne
  skopiowanie nie blokowało panelu przed rejestracją fabryki,
- zweryfikowano manager pod PHP 8.4, stronę logowania i komplet testów.

**Zaktualizowano status:** panel administracyjny działa ponownie, a żadne dane
modułów ani konfiguracja istniejących rozszerzeń nie zostały usunięte.

### Sesja: 2026-06-15 - izolacja błędnych pakietów modułów

**Wykonano:**
- dodano bezpieczną inspekcję manifestu zwracającą błąd zamiast przerywać skan,
- manager pokazuje wadliwy katalog jako „Błąd pakietu” wraz z przyczyną i bez akcji,
- dashboard uwzględnia liczbę błędnych pakietów bez wywoływania HTTP 500,
- odizolowano także błędy historii migracji i stanu pojedynczego modułu,
- opcjonalne moduły z `config/modules.php` są pomijane przed wykonaniem fabryki,
- oznaczono `CoreAuth`, `CorePages` i `System` jako wymagane elementy rdzenia,
- dodano testy uszkodzonego JSON i pomijania wadliwej opcjonalnej fabryki.

**Zaktualizowano status:** skopiowanie niezgodnego lub uszkodzonego rozszerzenia
do `modules/` nie blokuje panelu ani pozostałych funkcji aplikacji.

### Sesja: 2026-06-15 - instalacja pakietu z własną fabryką

**Wykonano:**
- rozszerzono manifest o opcjonalne pole `factory`,
- walidator dopuszcza wyłącznie lokalną nazwę pliku PHP istniejącą w katalogu modułu,
- manager uznaje poprawny pakiet z własną fabryką za gotowy do instalacji,
- dodano przycisk instalacji bez konieczności modyfikowania `config/modules.php`,
- kod fabryki jest ładowany dopiero dla zainstalowanego i aktywnego rozszerzenia,
- błędna fabryka rozszerzenia jest izolowana i nie przerywa startu Core,
- moduł szkoleniowy otrzymał rzeczywisty `factory.php` zwracający kontrolowany callable,
- zaktualizowano instrukcję modułu oraz ostrzeżenie przed wykonaniem zaufanego kodu.

**Zaktualizowano status:** poprawnie zbudowany moduł z lokalną fabryką można
zainstalować jednym kliknięciem bez ręcznej edycji konfiguracji rdzenia.

### Sesja: 2026-06-15 - akceptacja użytkowników, role i uprawnienia

**Wykonano:**
- nieznana zweryfikowana tożsamość OAuth tworzy lokalne konto `pending` z rolą `user`,
- dodano przycisk szybkiej akceptacji konta oczekującego,
- dodano formularz ręcznego tworzenia konta z opcjonalnym `(provider, subject)`,
- rozszerzono konto użytkownika z jednej roli do wielu ról,
- dodano ogólny komponent `multiselect` Theme i filtrowany `Request::postStringList`,
- utworzono ekran `/admin/roles` z rolami systemowymi i niestandardowymi,
- dodano tworzenie, edycję i bezpieczne usuwanie nieużywanych ról niestandardowych,
- dodano przypisywanie wielu uprawnień do roli,
- zabezpieczono identyfikatory ról systemowych i pełny zestaw praw administratora,
- dodano uprawnienia `roles.view` i `roles.manage` oraz migrację CoreAuth `1.1.0`,
- wykonano aktualizację produkcyjnego stanu CoreAuth z `1.0.0` do `1.1.0`,
- przetestowano cykl pending → active, wiele ról i sumowanie uprawnień.

**Zaktualizowano status:** moduł użytkowników obsługuje pełną lokalną akceptację
kont oraz zarządzanie rolami i uprawnieniami niezależnie od OAuth.

### Sesja: 2026-06-15 - grupowany wybór uprawnień

**Wykonano:**
- zastąpiono niewygodny wielokrotny select edytora roli listą checkboxów,
- pogrupowano uprawnienia według przestrzeni nazw modułów i obszarów Core,
- dodano liczniki oraz akcje zaznaczania i czyszczenia całej grupy,
- zachowano filtrowanie danych przez `Request::postStringList` i istniejący zapis ról,
- dodano responsywny wygląd komponentu do aktywnego motywu.

**Zaktualizowano status:** edytor ról nie wymaga klawisza Ctrl i pozostaje czytelny
przy zwiększaniu liczby modułów oraz uprawnień.

### Sesja: 2026-06-15 - ustawienia systemowe i dziennik zdarzeń

**Wykonano:**
- rozszerzono chroniony `SystemAdminModule` do wersji `1.1.0`,
- dodano `/admin/settings` z wyborem motywu, nazwą marki i domyślnym nadtytułem,
- bezpieczne nadpisania zapisuje `SystemSettingsRepository` przez `CrudApp`,
- `Bootstrap` stosuje ustawienia przed załadowaniem Theme i ma fallback brakującego motywu,
- dodano zredagowaną diagnostykę pliku środowiskowego, bazy, sesji, OAuth i HMAC,
- sekrety są prezentowane wyłącznie jako „Ustawiono” albo „Brak”,
- dodano `/admin/logs` z paginowanym odczytem `auth_events`,
- dodano uprawnienie `logs.view` i zaktualizowano CoreAuth do `1.2.0`,
- wykonano migracje produkcyjne `system_settings` i `logs.view`,
- zweryfikowano repozytoria, ACL tras oraz start strony publicznej.

**Zaktualizowano status:** chroniony moduł systemowy zarządza bezpieczną częścią
konfiguracji i udostępnia audyt działań bez ujawniania sekretów.

### Sesja: 2026-06-15 - redukcja szumu w audit logu

**Wykonano:**
- zatrzymano zapisywanie `admin_access/allowed` przy zwykłym otwieraniu stron panelu,
- zachowano audyt prób niezalogowanych, odmów ACL i operacji zmieniających stan,
- historyczne rutynowe wpisy pozostają w bazie, lecz są ukryte w domyślnym widoku,
- licznik i paginacja dziennika pomijają ukryte wpisy,
- panel informuje o liczbie historycznych rekordów wyłączonych z widoku.

**Zaktualizowano status:** odświeżanie panelu nie zasypuje dziennika zdarzeń.

### Sesja: 2026-06-15 - historia migracji, filtry audytu i podpisy pakietów

**Wykonano:**
- dodano trasę historii migracji modułu z datą, zapisanym i aktualnym SHA-256,
- stany integralności rozróżniają zgodny, zmieniony i brakujący plik migracji,
- audit log otrzymał filtry typu zdarzenia, wyniku oraz dat `od/do`,
- filtry używają przygotowanych zapytań i są zachowywane w paginacji,
- manifest obsługuje jawne pochodzenie pakietu i plik podpisu,
- zewnętrzne fabryki są uruchamiane wyłącznie po weryfikacji RSA-SHA256,
- podpis obejmuje wersję, pochodzenie i SHA-256 wszystkich plików pakietu,
- dodano lokalny rejestr kluczy publicznych oraz narzędzie `bin/sign-module.php`,
- prywatny klucz wydawcy zapisano poza repozytorium z prawami `600`,
- podpisano moduł edukacyjny i dodano test wykrywający manipulację jednym plikiem.

**Zaktualizowano status:** wszystkie trzy zaplanowane rozszerzenia Kroku 6 są
wdrożone i mają kontrolę integralności oraz testy negatywne.

### Sesja: 2026-06-15 - eksport audytu, cykl życia kluczy i cache szablonów

**Wykonano:**
- dodano chroniony eksport bieżącego filtra audit logu do CSV z limitem 10 000 wpisów,
- eksport ma nagłówki `no-store`, neutralizuje formuły arkusza i sam zapisuje zdarzenie audytu,
- podpis pakietu zawiera czas `signed_at`, a rejestr kluczy obsługuje stany
  `active`, `retired`, `revoked`, okres ważności i identyfikator następcy,
- wygenerowano nowy klucz wydawcy poza repozytorium, wycofano poprzedni i ponownie
  podpisano moduł edukacyjny aktywnym kluczem,
- ustawienia systemowe pokazują zredagowany rejestr wydawców i fingerprinty kluczy,
- dodano `TemplateCacheInterface` i plikową implementację z atomowym zapisem,
  TTL, statystykami, pełnym czyszczeniem i unieważnianiem tagów,
- anonimowa strona główna korzysta z cache, a zmiany `core_pages` i motywu
  unieważniają zależne wpisy,
- panel systemowy udostępnia audytowane czyszczenie cache,
- produkcyjny stan chronionego `system_admin` podniesiono do `1.3.0` bez migracji SQL,
- dodano testy CSV injection, tagowego unieważniania oraz unieważnionego klucza.

**Zaktualizowano status:** trzy kolejne punkty planu są wdrożone; bloker kontraktu
unieważniania cache został zamknięty.

### Sesja: 2026-06-16 - alternatywny motyw Glassnight

**Faza i krok specyfikacji:** Krok 3 oraz kontynuacja Kroku 6 w obszarze `ThemeEngine` i kontraktu szablonów.

**Wykonano:**
- przeanalizowano kontrakt `ThemeInterface`, ładowanie klas przez `ThemeEngine` oraz sposób wyboru motywu w ustawieniach systemowych,
- dodano alternatywny szablon `glassnight` jako osobny katalog w `templates/`, bez zmian w modułach,
- zachowano pełną implementację metod motywu i statyczne prototypy stylebook/homepage/admin-stylebook,
- zmieniono warstwę wizualną na szklane panele, paletę czerni, granatu, ciemnego niebieskiego i błękitu oraz subtelne akcenty ciemnej czerwieni,
- dodano nadpisania CSS dla publicznej strony, komponentów i panelu administracyjnego.

**Weryfikacja:** `php -l templates/glassnight/theme.php`, kontrola listy motywów przez `ThemeEngine`, lokalny podgląd statycznego stylebooka.

### Sesja: 2026-06-16 - PHP 8.4, cache treści, kwarantanna modułów i retencja audytu

**Faza i krok specyfikacji:** Krok 6 oraz wydajność/bezpieczeństwo z sekcji 5.2.

**Wykonano:**
- zadeklarowano PHP 8.4 lub nowszy jako wymaganie runtime projektu, bez wymogu PHP 8.5,
- rozszerzono tagowy cache szablonów na publiczne podstrony, listę podstron, listę artykułów i pojedyncze artykuły,
- dodano granularne tagi `page:{slug}`, `article:{slug}`, `pages:index` i `articles:index` oraz unieważnianie po zmianach treści,
- dodano `Request::file()` i obsługę pól uploadu w aktywnych motywach,
- dodano `ModuleArchiveImporter`, który rozpakowuje `.tar`, `.tar.gz`, `.tgz` i `.zip` wyłącznie do `cache/module-quarantine`,
- panel modułów otrzymał formularz importu archiwum do kwarantanny, listę ostatnich importów i audyt operacji,
- dodano tabelę `auth_events_archive`, migrację Core oraz panelową operację archiwizacji wpisów starszych niż skonfigurowana retencja,
- podniesiono stan `articles` do `1.0.2` i `system_admin` do `1.4.0` przez manager modułów.

**Weryfikacja:** `php tests/run.php`, pełny `php -l` dla PHP w `core`, `modules`, `templates`, `config`, `tests`, `bin` i `install/mod`, `php bin/migrate-core.php`.

**Korekta po przeglądzie:** zaktualizowano `AGENTS.md`, aby instrukcje agentów
odzwierciedlały PHP 8.4+, wykonane punkty Kroku 6, brak aktywnego blokera PHP 8.5
oraz nowe uwagi o cache, kwarantannie modułów i retencji audytu.

### Sesja: 2026-06-16 - moduł wikipedia dokumentacji projektowej

**Faza i krok specyfikacji:** Krok 5C oraz Krok 6 - opcjonalny moduł treści
instalowany przez manager modułów.

**Wykonano:**
- dodano opcjonalny moduł `wikipedia` z manifestem, `install.sql`, `uninstall.sql`,
  modelami `WikiProject` i `WikiPage`, repozytorium przez `CrudApp` oraz klasą modułu,
- moduł obsługuje projekty dokumentacji oraz strony dokumentacji z formatem
  `html`/`markdown`, statusem publikacji, slugiem, opisem i kolejnością,
- dodano publiczne trasy `/wiki`, `/wiki/project` i `/wiki/page` pokazujące wyłącznie
  opublikowane treści,
- dodano panel `/admin/wikipedia` z CRUD, publikacją, usuwaniem, CSRF, ACL
  `wikipedia.*`, audit logiem i cache publicznych widoków,
- dodano deklaratywną fabrykę w `config/modules.php` i test walidacji manifestu,
- zainstalowano moduł przez `ModuleManagerService`; stan produkcyjny to
  `wikipedia` 1.0.0 `active`,
- zaktualizowano README, specyfikację techniczną i `AGENTS.md`.

**Weryfikacja:** `php tests/run.php`, pełny `php -l`, walidacja manifestów,
test Front Controller dla `route=/wiki`.

### Sesja: 2026-06-16 - nawigacja stron dokumentacji Wiki

**Faza i krok specyfikacji:** Krok 3 oraz Krok 5C - ogólny komponent motywu użyty
przez moduł treści.

**Wykonano:**
- dodano `ThemeInterface::render_content_navigation()` jako ogólny komponent
  nawigacji między treściami,
- wdrożono komponent w motywach `default` i `glassnight` wraz ze stylami publicznymi,
- moduł `wikipedia` wylicza poprzednią i następną opublikowaną stronę projektu według
  kolejności i tytułu,
- pojedynczy przycisk „Wróć do projektu” na publicznej stronie Wiki zastąpiono
  trzema kaflami: poprzednia strona, spis projektu i następna strona,
- kafel następnej strony pokazuje rzeczywisty tytuł, np. `"Admin Guide"`,
- podniesiono moduł `wikipedia` do wersji 1.0.1 i zaktualizowano stan w bazie.

**Weryfikacja:** `php tests/run.php`, pełny `php -l`, test Front Controller dla
`/wiki/page?project=punisherx&slug=home` potwierdzający `content-navigation` i etykietę
`Następna strona...`.

**Doprecyzowanie UI:** kafle poprzedniej i następnej strony otrzymały kierunek
`previous`/`next` oraz duże znaki `<` i `>` w tle, aby nawigacja była bardziej
czytelna wizualnie.

### Sesja: 2026-06-16 - breadcrumb edycji Wiki

**Faza i krok specyfikacji:** Krok 5C - panel modułu dokumentacji projektowej.

**Wykonano:**
- rozszerzono breadcrumb formularzy Wiki o kontekst projektu oraz edytowanej strony,
- ekran edycji strony pokazuje ścieżkę w stylu
  `Panel / Dokumentacja / PunisherX / Konfiguracja / Edytuj stronę dokumentacji`,
- ekran dodawania strony z wybranego projektu pokazuje nazwę projektu przed akcją,
- podniesiono moduł `wikipedia` do wersji 1.0.2.

**Weryfikacja:** `php tests/run.php`, pełny `php -l`, `php bin/migrate-core.php`
oraz test renderu Front Controller dla formularza edycji strony Wiki.

### Sesja: 2026-06-16 - publiczna nawigacja modułów

**Faza i krok specyfikacji:** Krok 5C oraz Krok 6 - połączenie strony głównej
z aktywnymi modułami bez wiązania modułów z HTML motywu.

**Wykonano:**
- dodano `PublicNavigationRegistry` oraz opcjonalny
  `PublicNavigationProviderInterface` dla modułów wystawiających publiczne wejścia,
- `ModuleRegistry` rejestruje publiczne linki aktywnych modułów razem z trasami
  i menu administracyjnym,
- `/admin/settings` pokazuje panel „Publiczna nawigacja modułów” i pozwala przypisać
  link do głównego menu, stopki albo ukryć go,
- strona główna łączy opublikowane podstrony `core_pages` z wybranymi linkami
  modułów i przekazuje je do motywu jako jedną listę nawigacji,
- moduł `wikipedia` deklaruje link `Dokumentacja` do `index.php?route=/wiki`,
- podniesiono moduł `wikipedia` do wersji 1.0.3 i zaktualizowano stan w bazie.

**Weryfikacja:** `php tests/run.php`, pełny `php -l`, `php bin/migrate-core.php`,
test renderu `/admin/settings` oraz test renderu homepage z linkiem Wiki ustawionym
chwilowo w głównym menu.

### Sesja: 2026-06-16 - przyjazne URL i wspólna publiczna nawigacja

**Faza i krok specyfikacji:** Krok 3, Krok 5C oraz Krok 6 - ujednolicenie
publicznej prezentacji modułów z nawigacją strony głównej.

**Wykonano:**
- dodano `ThemeInterface::set_public_navigation()` i wdrożono wspólne publiczne menu
  oraz stopkę w motywach `default` i `glassnight`,
- `index.php` buduje jedną listę publicznej nawigacji z opublikowanych podstron
  `core_pages` oraz linków modułów z `PublicNavigationRegistry`,
- publiczne widoki modułów renderowane przez `start_page()` mają teraz tę samą stopkę,
  w tym linki typu polityka prywatności publikowane w stopce strony głównej,
- moduł Wiki generuje przyjazne linki `/wiki`, `/wiki/project/{slug}` i
  `/wiki/page/{project}/{slug}` oraz zachowuje stare trasy query string jako wejścia
  kompatybilne,
- moduł Articles generuje przyjazne linki pojedynczych artykułów `/article/{slug}`,
- podniesiono `wikipedia` do wersji 1.0.4 i `articles` do wersji 1.0.3.

**Weryfikacja:** testy renderu `/wiki`, `/wiki/project/punisherx` i
`/wiki/page/punisherx/home` potwierdziły przyjazne linki oraz obecność stopki
z `/p/polityka-prywatnosci`; wykonano pełny `php tests/run.php`, pełny `php -l`
i `php bin/migrate-core.php`.

### Sesja: 2026-06-17 - rozszerzone ustawienia linków modułów

**Faza i krok specyfikacji:** Krok 6 - system modułów oraz publiczne wejścia
rozszerzeń konfigurowane z panelu.

**Wykonano:**
- przeanalizowano `docs/POPRAWKI_I_ULEPSZENIA.md` i rozpoczęto wdrażanie od ustawień
  linków modułów rozszerzeń,
- rozszerzono `PublicNavigationRegistry` o kompatybilny format ustawień z etykietą
  oraz niezależnymi flagami `main` i `footer`,
- `/admin/settings` pozwala teraz zmienić etykietę linku modułu oraz zaznaczyć menu
  główne, stopkę albo oba obszary,
- wspólna publiczna nawigacja w `index.php` tworzy osobne wpisy dla menu i stopki,
  jeśli administrator włączy oba miejsca,
- zachowano odczyt starego zapisu `id => "main|footer|none"` bez migracji bazy.

**Weryfikacja:** dodano test jednostkowy dla starego i nowego formatu publicznej
nawigacji; wykonano `php tests/run.php`, lint zmienionych plików PHP, pełny
`find core modules templates config tests bin install/mod -type f -name '*.php' -print0 | xargs -0 -n1 php -l`
oraz `php bin/migrate-core.php`.

**Doprecyzowanie po przeglądzie:** moduł `articles` również implementuje
`PublicNavigationProviderInterface`, deklaruje link `Artykuły` do `/articles`,
a jego manifest i klasa zostały podniesione do wersji 1.0.4. Dodano test pilnujący,
że `ArticlesModule` pozostaje providerem publicznej nawigacji.

### Sesja: 2026-06-17 - Branding i SEO w ustawieniach

**Faza i krok specyfikacji:** Krok 3 oraz Krok 6 - publiczny branding motywu,
SEO i ustawienia systemowe bez wiązania modułów z HTML.

**Wykonano:**
- rozdzielono `/admin/settings` na osobne panele „Branding i SEO” oraz „Szablon”,
- dodano edytowalne ustawienia: publiczna nazwa, domyślny nadtytuł, opis meta,
  słowa kluczowe meta i tekst stopki,
- formularz nawigacji modułów nie przenosi już ukrytych pól brandingu,
- motywy `default` i `glassnight` używają skonfigurowanego opisu meta, keywords
  oraz tekstu stopki w publicznych widokach,
- dodano migrację Core `20260617_system_settings_text.sql`, aby `system_settings`
  mogło bezpiecznie przechowywać dłuższe ustawienia i JSON publicznej nawigacji.

**Weryfikacja:** wykonano `php tests/run.php`, lint zmienionych plików PHP, pełny
`find core modules templates config tests bin install/mod -type f -name '*.php' -print0 | xargs -0 -n1 php -l`
oraz dwukrotnie `php bin/migrate-core.php`; pierwszy przebieg wykonał
`20260617_system_settings_text.sql`, drugi potwierdził `SKIP`.

### Sesja: 2026-06-17 - wspólne menu i publiczne strony błędów

**Faza i krok specyfikacji:** Krok 3 oraz Krok 5C - wspólna prezentacja publiczna
niezależna od modułów.

**Wykonano:**
- publiczne widoki renderowane przez `start_page()` pokazują teraz link `Home`
  oraz link `Kontakt` do sekcji kontaktowej strony głównej,
- strona główna nadal nie dubluje `Home`, a kontakt z sekcji homepage pozostaje
  lokalnym anchorem,
- dodano `ThemeInterface::render_public_error()` i wdrożono go w motywach `default`
  oraz `glassnight`,
- Front Controller renderuje 404 i 405 jako przyjazne publiczne strony z powrotem
  na stronę główną, bez komunikatu o dashboardzie,
- dodano testy dla wspólnej nawigacji publicznej i przyjaznej strony 404.

**Weryfikacja:** wykonano `php tests/run.php`, lint zmienionych plików PHP, pełny
`find core modules templates config tests bin install/mod -type f -name '*.php' -print0 | xargs -0 -n1 php -l`
oraz `php bin/migrate-core.php`.

### Sesja: 2026-06-17 - eksport ZIP modułów rozszerzeń

**Faza i krok specyfikacji:** Krok 6 - manager modułów i kontrolowane operacje na
pakietach rozszerzeń.

**Wykonano:**
- dodano `ModulePackageExporter`, który tworzy ZIP z katalogu zainstalowanego modułu,
- eksport obejmuje jeden top-level katalog modułu z `info.json`,
- eksport blokuje dowiązania symboliczne i ukryte segmenty ścieżek,
- `ModuleManagerService::exportPackage()` dopuszcza wyłącznie zainstalowane,
  niechronione moduły typu `extension`,
- `/admin/modules` pokazuje akcję „Eksportuj ZIP” dla takich rozszerzeń,
- pobranie wymaga ACL `modules.install`, CSRF i zapisuje audit event `module_export`,
- gdy `ZipArchive` nie jest dostępny, exporter korzysta z systemowego narzędzia `zip`.

**Weryfikacja:** dodano test tworzący ZIP i importujący go z powrotem przez
`ModuleArchiveImporter`; wykonano `php tests/run.php`, lint zmienionych plików PHP,
pełny lint repo oraz `php bin/migrate-core.php`.

### Sesja: 2026-06-17 - dashboard jako centrum informacji

**Faza i krok specyfikacji:** Krok 5B oraz Krok 6 - panel administracyjny,
moduły i audit log jako źródła decyzji operacyjnych.

**Wykonano:**
- rozszerzono metryki dashboardu o zainstalowane rozszerzenia, wyłączone moduły,
  zdarzenia dzisiejsze i dzisiejsze nieudane operacje,
- dodano panel „Sygnały operacyjne” z wnioskami dla błędów modułów, migracji,
  wyłączonych modułów, nieudanych zdarzeń i widoczności menu,
- dodano panel ostatniej aktywności oparty o `SystemLogRepository`, dostępny gdy
  aktywna jest baza danych i audit log,
- pozostawiono dotychczasowy panel architektury jako kontekst techniczny Kroku 6.

**Weryfikacja:** wykonano `php tests/run.php`, lint `modules/System/SystemAdminModule.php`,
pełny lint repo oraz `php bin/migrate-core.php`.

### Sesja: 2026-06-17 - zwarte układy Dashboardu i Ustawień

**Faza i krok specyfikacji:** Krok 3 oraz Krok 5B - komponenty panelu w
`ThemeInterface` i ergonomia administracyjna.

**Wykonano:**
- dodano `ThemeInterface::start_admin_panel_grid()` i `end_admin_panel_grid()`,
- wdrożono responsywne siatki paneli w motywach `default` i `glassnight`,
- dodano style `admin-panel-grid-*` do obu plików `admin.css`,
- Dashboard układa sygnały operacyjne i aktywność obok siebie na desktopie,
- Ustawienia układają „Branding i SEO” z „Szablonem”, nawigację z cache oraz
  konfigurację z diagnostyką w zwarte sekcje,
- na mniejszych ekranach układ przechodzi do jednej kolumny.

**Weryfikacja:** dodano test renderowania siatki paneli; wykonano `php tests/run.php`,
pełny lint PHP oraz `php bin/migrate-core.php`.

### Sesja: 2026-06-17 - profil użytkownika w topbarze panelu

**Faza i krok specyfikacji:** Krok 5B - kontrakt panelu, `CoreAuth` i ergonomia
uwierzytelnionego użytkownika.

**Wykonano:**
- usunięto osobną sekcję sidebaru `Profil / Połączone konta`,
- dodano trasę `/admin/profile` z widokiem danych konta, ról, statusu,
  połączonych tożsamości i podstawowego stanu bezpieczeństwa,
- przeniesiono wejścia profilu do dropdownu pod użytkownikiem w topbarze panelu,
- dropdown zawiera: `Pokaż profil`, `Edytuj dane`, `Połączone konta`,
  `Ustawienia avatara`, `Bezpieczeństwo` i `Wyloguj`,
- rozszerzono dokumentację `ThemeInterface::start_admin_page()` o opcjonalne
  `profile_links`, zachowując domyślne linki dla istniejących modułów,
- wdrożono identyczne style dropdownu w motywach `default` i `glassnight`,
- podniesiono `core_auth` do wersji `1.3.0` bez migracji SQL.

**Weryfikacja:** dodano test renderowania dropdownu profilu w topbarze; wykonano
`php tests/run.php`, pełny lint PHP oraz `php bin/migrate-core.php`.

### Sesja: 2026-06-17 - rozbudowa profilu użytkownika

**Faza i krok specyfikacji:** Krok 5B - `CoreAuth`, profil użytkownika i spójność
panelu administracyjnego.

**Wykonano:**
- usunięto drugi znacznik użytkownika z dolnej części sidebaru; topbar pozostaje
  jedynym miejscem menu profilu,
- dropdown profilu prowadzi teraz do konkretnych tras: podglądu, edycji danych,
  połączonych kont, ustawień avatara i bezpieczeństwa,
- dodano zapis nazwy wyświetlanej oraz e-maila kontaktowego bieżącego użytkownika
  przez `AuthService` i `UserRepositoryInterface`,
- dodano zapis adresu avatara z walidacją URL oraz możliwością wyczyszczenia pola,
- widok bezpieczeństwa i panel bezpieczeństwa w profilu używają responsywnych kafli
  faktów zamiast tabeli z poziomym przewijaniem,
- operacje edycji profilu i avatara wymagają CSRF oraz zapisują audit eventy,
- podniesiono `core_auth` do wersji `1.4.0` bez migracji SQL.

**Weryfikacja:** dodano test zapisu profilu i avatara przez `AuthService` oraz
rozszerzono test dropdownu o nowe trasy i brak footera użytkownika w sidebarze.

### Sesja: 2026-06-17 - dopracowanie avatara i powrotu z tożsamości

**Faza i krok specyfikacji:** Krok 5B - spójność profilu użytkownika w panelu.

**Wykonano:**
- widok `Połączone konta` prowadzi przyciskiem powrotu do profilu zamiast dashboardu,
- `ThemeInterface::start_admin_page()` przyjmuje opcjonalny `avatar_url`,
- topbar panelu pokazuje obraz avatara, gdy użytkownik ma zapisany bezpieczny URL,
  a w przeciwnym razie zachowuje inicjały,
- helpery użytkownika panelu w `CoreAuth`, `System`, `CorePages`, `Articles`
  i `Wikipedia` przekazują avatar do motywu,
- oba motywy dostały styl obrazka avatara z kadrowaniem do stałego kafla.

**Weryfikacja:** dodano test renderowania avatara w topbarze i test powrotu z
widoku połączonych kont do profilu.

### Sesja: 2026-06-17 - Manager SQL, etap read-only

**Faza i krok specyfikacji:** Krok 5B oraz Krok 6 - narzędzia systemowe panelu,
ACL i bezpieczny podgląd infrastruktury.

**Wykonano:**
- dodano uprawnienie `database.view` w `CoreAuth` oraz migrację
  `20260617_database_view_permission.sql`,
- dodano `DatabaseExplorerRepository`, które czyta metadane tabel i kolumn przez
  `information_schema`,
- dodano menu `System / Baza danych` oraz trasę `/admin/database`,
- widok pokazuje nazwę bazy, liczbę tabel, tryb read-only, listę tabel, silnik,
  przybliżoną liczbę wierszy, rozmiar, kodowanie oraz strukturę kolumn wybranej tabeli,
- operacje zapisu, własny SQL, import/export i akcje destrukcyjne pozostają poza tym
  etapem i wymagają osobnych zabezpieczeń,
- podniesiono `system_admin` do wersji `1.5.0`.

**Weryfikacja:** dodano test wersji `system_admin` oraz deklaracji uprawnienia
`database.view` w install SQL i migracji.

### Sesja: 2026-06-17 - Manager SQL, podgląd danych tabel

**Faza i krok specyfikacji:** Krok 5B oraz Krok 6 - narzędzia systemowe panelu,
ACL i bezpieczny podgląd danych.

**Wykonano:**
- rozszerzono `DatabaseExplorerRepository` o bezpieczny odczyt rekordów wybranej
  tabeli z walidowanego identyfikatora,
- dodano dokładne liczenie rekordów dla wybranej tabeli,
- `/admin/database` pokazuje panel `Dane: tabela` z rekordami w trybie read-only,
- limit podglądu jest ograniczony do 10-50 wierszy na stronę,
- dodano prostą paginację `Poprzednia strona` / `Następna strona`,
- komórki danych są normalizowane i skracane, aby nie rozsadzać układu panelu,
- podniesiono `system_admin` do wersji `1.6.0`.

**Weryfikacja:** zaktualizowano test manifestu `system_admin` do wersji `1.6.0`.

### Sesja: 2026-06-17 - Manager SQL, eksport CSV tabeli

**Faza i krok specyfikacji:** Krok 5B oraz Krok 6 - narzędzia systemowe panelu,
read-only eksport danych i audyt operacji.

**Wykonano:**
- dodano `DatabaseTableCsvExporter` z BOM UTF-8, separatorem `;` i neutralizacją
  wartości zaczynających się od `=`, `+`, `-` albo `@`,
- rozszerzono `DatabaseExplorerRepository` o eksport danych tabeli do 10 000 rekordów,
- dodano trasę `/admin/database/export`,
- widok danych tabeli pokazuje akcję `Eksportuj CSV`,
- eksport wymaga ACL `database.view`, waliduje nazwę tabeli przez listę z
  `information_schema` i zapisuje audit event `database_export`,
- nazwa pliku eksportu jest sanitizowana,
- podniesiono `system_admin` do wersji `1.7.0`.

**Weryfikacja:** dodano test neutralizacji formuł w eksporcie tabeli do CSV oraz
zaktualizowano test manifestu `system_admin` do wersji `1.7.0`.

### Sesja: 2026-06-17 - Manager SQL, eksport SQL tabeli

**Faza i krok specyfikacji:** Krok 5B oraz Krok 6 - narzędzia systemowe panelu,
backup danych i audyt eksportu.

**Wykonano:**
- dodano `DatabaseTableSqlExporter`,
- `/admin/database/export` obsługuje `format=sql` jako domyślny format eksportu,
- widok tabeli pokazuje osobne akcje `Eksportuj CSV` i `Eksportuj SQL`,
- eksport SQL zawiera `DROP TABLE IF EXISTS`, `CREATE TABLE`, paczkowane `INSERT`
  oraz tymczasowe wyłączenie i przywrócenie `FOREIGN_KEY_CHECKS`,
- wartości dumpa SQL są cytowane przez PDO aktywnego połączenia,
- nazwa pliku jest sanitizowana i otrzymuje rozszerzenie `.sql`,
- podniesiono `system_admin` do wersji `1.8.0`.

**Weryfikacja:** dodano test generowania przenośnego dumpa SQL i zaktualizowano
test manifestu `system_admin` do wersji `1.8.0`.

### Sesja: 2026-06-17 - Manager SQL, konsola read-only

**Faza i krok specyfikacji:** Krok 5B oraz Krok 6 - narzędzia systemowe panelu,
bezpieczne wykonywanie zapytań i audyt operacji.

**Wykonano:**
- dodano `/admin/database/query` dla metody GET i POST,
- formularz konsoli SQL używa CSRF i komponentów `ThemeInterface`,
- repozytorium waliduje pojedyncze zapytanie read-only,
- dozwolone są tylko `SELECT`, `SHOW`, `DESCRIBE`, `DESC` i `EXPLAIN`,
- wynik zapytania jest renderowany tabelą i ograniczony do 100 wierszy,
- powodzenie i odrzucenia zapisują audit event `database_query`,
- podniesiono `system_admin` do wersji `1.9.0`.

**Weryfikacja:** dodano test normalizacji i odrzucania zapytań konsoli SQL oraz
zaktualizowano test manifestu `system_admin` do wersji `1.9.0`.

### Sesja: 2026-06-17 - Manager SQL jako osobny moduł

**Faza i krok specyfikacji:** Krok 5C oraz Krok 6 - separacja modułów rozszerzeń,
manifesty, instalator SQL i zachowanie granicy Core -> Modules -> Templates.

**Wykonano:**
- skorygowano wcześniejszą decyzję architektoniczną: Manager SQL został wydzielony
  z `system_admin` do osobnego modułu `database_manager`,
- dodano `modules/DatabaseManager/info.json`, `install.sql` oraz klasę
  `DatabaseManagerModule`,
- przeniesiono `DatabaseExplorerRepository`, `DatabaseTableCsvExporter` i
  `DatabaseTableSqlExporter` do namespace `SyntaxDevTeam\Cms\Modules\DatabaseManager`,
- usunięto trasy `/admin/database`, `/admin/database/export` i
  `/admin/database/query` z `SystemAdminModule`,
- przywrócono `system_admin` do wersji `1.4.0`; dalszy rozwój Managera SQL podnosi
  wersję modułu `database_manager`,
- `install.sql` modułu tworzy tabelę `database_manager_history` dla historii operacji,
  a `uninstall.sql` bezpiecznie usuwa dane własne modułu,
- eksport CSV/SQL i konsola read-only zapisują wpisy historii modułu oraz nadal
  korzystają z globalnego audit logu,
- menu panelu pokazuje osobną pozycję `Manager SQL` chronioną ACL `database.view`,
- systemowe strony panelu przekazują `avatar_url` do topbara tak jak pozostałe widoki.

**Weryfikacja:** zaktualizowano testy manifestów i klas eksportu tak, aby sprawdzały
`database_manager` 1.0.0 oraz brak dalszego bumpowania `system_admin`.

### Sesja: 2026-06-17 - Pasek akcji modułu i historia Managera SQL

**Faza i krok specyfikacji:** Krok 5A, Krok 5C oraz Krok 6 - ergonomia panelu,
kontrakt motywu i kolejny etap modułu `database_manager`.

**Wykonano:**
- zmieniono renderowanie `start_admin_content()` tak, aby główne akcje modułu były
  pokazywane w pełnoszerokim pasku pod nagłówkiem treści panelu,
- pasek akcji obsługuje jedną akcję albo listę akcji i zawija przyciski na urządzeniach
  mobilnych,
- lokalne akcje w panelach pozostają dla paginacji i operacji kontekstowych,
- Manager SQL pokazuje w górnym pasku `Konsola SQL`, `Historia` oraz eksporty
  wybranej tabeli,
- dodano trasę `/admin/database/history` z paginowaną historią operacji z tabeli
  `database_manager_history`,
- podniesiono moduł `database_manager` do wersji `1.1.0`.

**Weryfikacja:** dodano test renderowania pełnoszerokiego paska akcji modułu oraz
zaktualizowano test manifestu `database_manager` do wersji `1.1.0`.

### Sesja: 2026-06-17 - Manager SQL, operacje zapisowe

**Faza i krok specyfikacji:** Krok 5C oraz Krok 6 - pełniejszy moduł rozszerzenia,
ACL operacji mutujących, CSRF, audyt i historia modułu.

**Wykonano:**
- podniesiono `database_manager` do wersji `1.2.0`,
- dodano uprawnienie `database.manage` w `install.sql` modułu oraz migracji
  `20260617_database_manage_permission.sql`,
- rozszerzono repozytorium Managera SQL o whitelistę pojedynczych zapytań mutujących,
- dodano trasę `/admin/database/query/manage` dla zapytań zapisowych wymagających
  potwierdzenia `WRITE`,
- dodano trasę `/admin/database/table-operation` dla operacji tabeli: `OPTIMIZE`,
  `CHECK`, `ANALYZE`, `REPAIR`, `TRUNCATE` i `DROP`,
- akcje destrukcyjne `TRUNCATE` oraz `DROP` wymagają wpisania dokładnej nazwy tabeli,
- operacje zapisowe zapisują globalny audit log oraz historię modułu
  `database_manager_history`,
- podgląd i eksport pozostają chronione `database.view`, a operacje zapisowe wymagają
  `database.manage`.

**Weryfikacja:** dodano test walidacji zapytań zapisowych i test obecności
`database.manage` w instalatorze oraz migracji modułu.

### Sesja: 2026-06-17 - Manager SQL, import SQL

**Faza i krok specyfikacji:** Krok 5C oraz Krok 6 - domknięcie podstawowego cyklu
eksport/import w osobnym module `database_manager`.

**Wykonano:**
- podniesiono `database_manager` do wersji `1.3.0`,
- dodano kontrolowany import SQL w `/admin/database/import`,
- import przyjmuje plik `.sql` albo treść SQL w formularzu,
- import ma limit 2 MB, odrzuca pusty payload i bajt NUL,
- operacja wymaga ACL `database.manage`, CSRF oraz potwierdzenia `IMPORT`,
- powodzenie i błędy importu zapisują globalny audit log oraz historię modułu.

**Weryfikacja:** dodano test walidacji payloadu importu SQL.

### Sesja: 2026-06-18 - Manager SQL, CRUD rekordów

**Faza i krok specyfikacji:** Krok 5C oraz Krok 6 - finalizacja praktycznego
zarządzania danymi w module `database_manager`.

**Wykonano:**
- podniesiono `database_manager` do wersji `1.4.0`,
- dodano trasy `/admin/database/row/create`, `/admin/database/row/edit` i
  `/admin/database/row/delete`,
- formularz dodawania rekordu jest generowany z metadanych kolumn tabeli i pomija
  pola `AUTO_INCREMENT`,
- edycja oraz usuwanie rekordu są dostępne tylko dla tabel z dokładnie jednym kluczem
  głównym,
- widok danych tabeli pokazuje akcje `Edytuj` i `Usuń` przy rekordach, gdy spełniony
  jest warunek stabilnego klucza głównego,
- zapis, aktualizacja i usuwanie używają przygotowanych zapytań, cytowanych
  identyfikatorów, CSRF, ACL `database.manage`, audit logu i historii modułu,
- dodano `Request::postArray()` jako kontrolowaną warstwę pobierania tablic formularza
  bez bezpośredniego odczytu `$_POST`.

**Weryfikacja:** zaktualizowano test normalizacji `Request` o `postArray()` oraz
test manifestu `database_manager` do wersji `1.4.0`.

### Sesja: 2026-06-17 - Naprawa katalogu eksportu modułów ZIP

**Faza i krok specyfikacji:** Krok 6 - manager modułów, eksport pakietów rozszerzeń,
ACL/CSRF/audyt oraz operacyjne przygotowanie katalogów cache.

**Wykonano:**
- zdiagnozowano przyczynę komunikatu `Nie można utworzyć katalogu eksportu modułów.`
  jako brak zapisu procesu WWW do `cache/`,
- ustawiono `cache/` na grupę `www-data` z bitem setgid i utworzono
  `cache/module-exports` jako katalog zapisywalny dla panelu,
- `ModulePackageExporter` jawnie sprawdza teraz, czy katalog eksportu jest
  zapisywalny po utworzeniu,
- test eksportu ZIP sprawdza scenariusz, w którym katalog eksportu nie istnieje
  przed rozpoczęciem operacji,
- zsynchronizowano oczekiwaną wersję testu manifestu `database_manager` z aktualnym
  manifestem `1.2.0`.

**Weryfikacja:** `php tests/run.php`, lint wszystkich plików PHP oraz test zapisu
do `cache/module-exports` uruchomiony jako `www-data` zakończyły się powodzeniem.

### Sesja: 2026-06-18 - Mobilne linki menu panelu admina

**Faza i krok specyfikacji:** Krok 5A oraz Krok 5B - responsywność panelu
administracyjnego, kontrakt motywu i używalność chronionych tras `/admin/*`.

**Wykonano:**
- zdiagnozowano, że mobilne linki w offcanvas panelu miały `data-bs-dismiss="offcanvas"`,
  co w obsłudze Bootstrap mogło zamykać menu i anulować domyślną nawigację linku,
- zastąpiono atrybut Bootstrap własnym `data-admin-mobile-nav-link`,
- dodano obsługę JS, która zamyka offcanvas bez wywoływania `preventDefault()` na
  klikniętym odnośniku, więc przeglądarka może normalnie przejść pod wybrany adres,
- zmianę zastosowano w motywach `default` i `glassnight`.

**Weryfikacja:** uruchomiono testy repozytorium, lint PHP zmienionych motywów oraz
kontrolę składni JavaScript przez `node --check`.

### Sesja: 2026-06-18 - Translator pluginów YAML

**Faza i krok specyfikacji:** Krok 5C oraz Krok 6 - kolejny niezależny moduł
rozszerzenia panelu, zgodny z listą `POPRAWKI_I_ULEPSZENIA`.

**Wykonano:**
- dodano moduł `plugin_translator` w wersji `1.0.0`,
- dodano manifest, `install.sql`, `uninstall.sql` i uprawnienie
  `plugin_translator.use`,
- moduł rejestruje menu `Translator YAML` i trasy `/admin/plugin-translator` oraz
  `/admin/plugin-translator/export`,
- panel przyjmuje upload `.yml/.yaml` przez `Request::file()` albo treść YAML
  w formularzu,
- parser i eksporter korzystają z lokalnego `core/libs/Spyc.php`,
- źródłowy YAML jest spłaszczany do pól tłumaczenia i eksportowany jako
  `translation.yml` po ponownej walidacji,
- operacje otwarcia edytora i eksportu wymagają CSRF/ACL i zapisują audit log,
- poprawiono fallback linku `Połączone konta` w dropdownie profilu motywów
  `default` i `glassnight` na `/admin/profile/identities`.

**Weryfikacja:** dodano test parsera/eksportu YAML, test pustego YAML, walidację
manifestu `plugin_translator` i obecności uprawnienia w `install.sql`; uruchomiono
`php tests/run.php`, pełny lint PHP, `php bin/migrate-core.php`, instalację modułu
przez `ModuleInstaller` oraz kontrolę stanu `plugin_translator 1.0.0 active`.

### Sesja: 2026-06-18 - Translator pluginów, korekta kierunku publicznego

**Faza i krok specyfikacji:** Krok 5C oraz Krok 6 - doprecyzowanie wymagań modułu
`plugin_translator` przed kolejną implementacją.

**Ustalenie:**
- moduł nie powinien pozostać narzędziem dostępnym wyłącznie z panelu admina,
- użytkownicy mają pracować nad tłumaczeniami od strony publicznej serwisu,
- panel administracyjny ma pełnić rolę kolejki moderacji: podgląd prac, postęp,
  status ukończenia, oznaczenie „gotowe do zatwierdzenia” oraz akcje zatwierdzenia
  albo odrzucenia,
- dalszy etap wymaga trwałego modelu danych dla zgłoszeń tłumaczeń, autorów,
  statusów i wygenerowanego YAML.

**Weryfikacja:** zmiana dokumentacyjna; doprecyzowano README,
`TECHNICAL_SPECIFICATION.md`, `POPRAWKI_I_ULEPSZENIA.md` i historię sesji.

### Sesja: 2026-06-18 - Translator pluginów, publiczne zgłoszenia i moderacja

**Faza i krok specyfikacji:** Krok 5C oraz Krok 6 - rozwinięcie modułu
`plugin_translator` z narzędzia panelowego do publicznego workflow użytkownika i
administracyjnej kolejki zatwierdzania.

**Wykonano:**
- podniesiono `plugin_translator` do wersji `1.1.0`,
- dodano tabelę `plugin_translation_submissions` w `install.sql` i migracji
  `20260618_public_translation_workflow.sql`,
- dodano model `PluginTranslationSubmission` i repozytorium
  `PluginTranslationRepository` korzystające z `CrudApp`,
- dodano publiczną trasę `/translations` oraz akcje `/translations/open` i
  `/translations/submit`,
- użytkownik może zapisać tłumaczenie jako `draft` albo `ready_for_review`; status
  gotowy wymaga przetłumaczenia wszystkich pozycji,
- panel `/admin/plugin-translator` stał się kolejką prac z postępem, statusem,
  podglądem różnic, zatwierdzeniem, odrzuceniem i pobraniem wygenerowanego YAML,
- zachowano jednorazowe narzędzie eksportu administratora pod
  `/admin/plugin-translator/tool`,
- dodano uprawnienie `plugin_translator.review`, pozostawiając `plugin_translator.use`
  dla narzędzia eksportu,
- moduł deklaruje publiczny link `Tłumaczenia` przez `PublicNavigationRegistry`.

**Weryfikacja:** zaktualizowano test parsera o liczenie przetłumaczonych pozycji,
test manifestu do wersji `1.1.0` oraz test SQL dla tabeli i uprawnienia moderacji.
Uruchomiono `php tests/run.php`, pełny lint PHP, `php bin/migrate-core.php`,
aktualizację modułu przez `ModuleInstaller`, kontrolę stanu
`plugin_translator 1.1.0 active`, obecność tabeli i uprawnień oraz test renderowania
publicznej trasy `/translations` i chronionej trasy admina.

### Sesja: 2026-06-18 - Moduł Team, publiczna lista zespołu

**Faza i krok specyfikacji:** Krok 5C oraz Krok 6 - kolejny niezależny moduł
treściowy powiązany z profilem użytkownika.

**Wykonano:**
- dodano moduł `team` w wersji `1.0.0`,
- dodano manifest, `install.sql`, `uninstall.sql` i uprawnienie `team.manage`,
- dodano tabelę `team_members` powiązaną z lokalnymi kontami `users`,
- dodano model `TeamMember` i repozytorium `TeamRepository` korzystające z
  `CrudApp`,
- dodano publiczną listę `/team` i publiczne profile `/team/member/{slug}`,
- dodano panel `/admin/team` do dodawania, edycji, ukrywania i usuwania profili
  członków zespołu,
- moduł deklaruje publiczny link `Zespół` przez `PublicNavigationRegistry`,
- dodano ogólny komponent motywu `render_avatar()` oraz style publicznego avatara
  w motywach `default` i `glassnight`.

**Weryfikacja:** dodano test manifestu i SQL modułu `team` oraz test renderowania
komponentu avatara; uruchomiono testy repozytorium, lint PHP zmienionych plików,
instalację modułu przez `ModuleInstaller`, kontrolę stanu `team 1.0.0 active`,
obecność tabeli i uprawnień oraz test renderowania publicznej trasy `/team` i
chronionej trasy `/admin/team`.

### Sesja: 2026-06-18 - Translator pluginów, edytor publiczny i formatowanie Minecraft

**Faza i krok specyfikacji:** Krok 5C oraz Krok 6 - dopracowanie publicznego
modułu `plugin_translator` i zasad dostępu użytkowników oczekujących.

**Wykonano:**
- podniesiono `plugin_translator` do wersji `1.2.0`,
- dodano migrację `20260618_translation_language_and_ux.sql` oraz kolumnę
  `target_language` w `plugin_translation_submissions`,
- formularz startowy `/translations` obsługuje upload YAML przez pole
  przeciągnij/upuść i wybór języka docelowego z listy kodów ISO `XX`,
- edytor tłumaczenia pokazuje `Oryginał` i `Twoje tłumaczenie` w jednym układzie,
  z każdą linijką tekstu wyrównaną do odpowiadającego pola formularza,
- domyślnym statusem zapisu jest `Kopia robocza`,
- wprowadzanie i zapis tłumaczeń wymagają logowania, ale rozpoczęta praca jest
  zachowywana w sesji i wznawiana po powrocie OAuth przez `/translations/resume`,
- konto w statusie `pending` może korzystać z publicznego tłumaczenia bez dostępu
  do panelu administracyjnego,
- dodano podgląd `Sprawdź formatowanie` dla Minecraft legacy, RGB i MiniMessage,
- poprawiono słownictwo liczników na `linijki tekstu`.

**Weryfikacja:** dodano test parsera formatowania Minecraft i zaktualizowano testy
manifestu oraz SQL translatora do wersji `1.2.0`; wykonano migrację modułu przez
`ModuleInstaller`, sprawdzono stan `plugin_translator 1.2.0 active`, obecność
kolumny `target_language` oraz renderowanie publicznej trasy `/translations`.

### Sesja: 2026-06-18 - Poprawka 500 panelu translatora

**Faza i krok specyfikacji:** Krok 5C oraz Krok 6 - stabilizacja paneli modułów
rozszerzeń po dodaniu translatora i modułu Team.

**Wykonano:**
- naprawiono błąd 500 na `/admin/plugin-translator` spowodowany wywołaniem
  nieistniejącej metody `AdminMenuRegistry::items()`,
- panel translatora używa teraz istniejącego filtrowania menu
  `AdminMenuRegistry::visibleFor()`,
- tę samą korektę zastosowano w panelu modułu `team`, gdzie występował analogiczny
  błąd pomocnika strony admina.

**Weryfikacja:** `php -l` dla `PluginTranslatorModule.php` i `TeamModule.php`,
`php tests/run.php`, `php bin/migrate-core.php`, kontrola braku błędnych wywołań
`$this->menu->items()` oraz test HTTP
`/index.php?route=%2Fadmin%2Fplugin-translator`, który zwraca poprawny widok 401
dla niezalogowanego użytkownika zamiast HTTP 500.

### Sesja: 2026-06-18 - Translator pluginów, powrót do szkiców i walidacja podglądu

**Faza i krok specyfikacji:** Krok 5C oraz Krok 6 - domknięcie publicznego
workflow edycji tłumaczeń.

**Wykonano:**
- podniesiono `plugin_translator` do wersji `1.2.1`,
- dodano publiczny widok `/translations/mine` z listą własnych szkiców, zgłoszeń
  gotowych do sprawdzenia, odrzuconych i zatwierdzonych prac,
- dodano `/translations/edit`, który pozwala wrócić do własnego szkicu albo
  odrzuconego zgłoszenia,
- zapis istniejącej pracy aktualizuje rekord po `submission_id` i sprawdza
  właściciela oraz edytowalny status,
- podgląd `Sprawdź formatowanie` renderuje realny HTML z kolorami i stylami
  Minecraft legacy, RGB oraz MiniMessage,
- walidator podglądu pokazuje zmienne typu `<player>` jako placeholdery i zgłasza
  błędy składni formatowania, m.in. brak zamknięcia tagu oraz niepoprawny HEX.

**Weryfikacja:** zaktualizowano test `MinecraftFormatPreview` o placeholder
`<player>` i błędy MiniMessage; uruchomiono lint zmienionych plików PHP oraz
`php tests/run.php`.

### Sesja: 2026-06-19 - Translator jako manager plików językowych

**Faza i krok specyfikacji:** Krok 5C oraz Krok 6 - rozwinięcie
`plugin_translator` z narzędzia tłumacza do katalogu zaakceptowanych lokalizacji.

**Wykonano:**
- podniesiono moduł do wersji `1.3.0`,
- dodano tabelę `plugin_translation_projects` i migrację
  `20260619_translation_project_catalog.sql`,
- istniejące zgłoszenia przypisano do ukrytego katalogu `Nieprzypisane`,
- zgłoszenia przechowują plugin, opcjonalną wersję oraz rodzaj `editor` albo
  `completed_upload`,
- `/translations` prezentuje katalog pluginów i zaakceptowanych plików,
- `/translations/project` pokazuje pliki językowe wybranego pluginu, a publiczny
  download udostępnia wyłącznie rekordy `approved`,
- `/translations/upload-ready` przyjmuje od zalogowanego użytkownika gotowy YAML,
  waliduje go przez `Spyc` i zapisuje jako `ready_for_review`,
- `/admin/plugin-translator/plugins` zarządza katalogiem i widocznością pluginów,
- kolejki użytkownika i administratora pokazują plugin, wersję oraz rodzaj pracy.

**Weryfikacja:** wykonano migrację modułu przez `ModuleInstaller`, pełny lint PHP,
`git diff --check`, `php tests/run.php` oraz test HTTP `/translations` zakończony
statusem 200. Stan produkcyjny: `plugin_translator 1.3.0 active`.

### Sesja: 2026-06-19 - Powiązane strony i akcje managera tłumaczeń

**Faza i krok specyfikacji:** Krok 5C oraz Krok 6 - dopracowanie katalogu
pluginów i administracyjnego cyklu życia zgłoszeń.

**Wykonano:**
- podniesiono `plugin_translator` do wersji `1.3.1` i zadeklarowano zależność od
  `core_pages`,
- usunięto z katalogu pluginów pola opisu i ręcznego adresu URL,
- dodano opcjonalne `page_id` wskazujące opublikowaną stronę `core_pages`; formularz
  pokazuje istniejące strony wraz z docelowymi adresami `/p/{slug}`,
- publiczny katalog pluginu pokazuje przycisk do powiązanej strony,
- manager pluginów pozwala usuwać wyłącznie pozycje bez zgłoszeń i chroni katalog
  historyczny `Nieprzypisane`,
- kolejka zgłoszeń ma szybkie akcje `Zatwierdź`, `Odrzuć` i `Usuń`; akcje POST
  korzystają z CSRF, potwierdzeń i audit logu.

**Weryfikacja:** uruchomiono testy repozytorium, lint PHP i `git diff --check`,
wykonano migrację `20260619_translation_page_link_and_manager_actions.sql` przez
`ModuleInstaller` oraz sprawdzono realny schemat. Stan produkcyjny:
`plugin_translator 1.3.1 active`, `page_id=yes`, stare kolumny opisu i URL nie
istnieją.

### Sesja: 2026-06-19 - Nazwy plików tłumaczeń i edytor YAML

**Faza i krok specyfikacji:** Krok 5C oraz Krok 6 - ujednolicenie kontraktu plików
językowych i nazewnictwa narzędzia administracyjnego.

**Wykonano:**
- podniesiono `plugin_translator` do wersji `1.3.2`,
- download zaakceptowanego zgłoszenia, zarówno publiczny, jak i administracyjny,
  używa nazwy `messages_xx.yml` wynikającej z języka zgłoszenia,
- `Narzędzie eksportu YAML` przemianowano na `Edytor pliku YAML`,
- edytor przekazuje oryginalną nazwę uploadu w chronionym polu formularza,
  normalizuje ją przed nagłówkiem odpowiedzi i zachowuje rozszerzenie `.yml` albo
  `.yaml`,
- domyślną nazwą dla treści bez uploadu pozostaje `messages.yml`.

**Weryfikacja:** zaktualizowano test manifestu i kontraktu nazw plików, uruchomiono
pełny lint PHP, `php tests/run.php`, `git diff --check` oraz aktualizację modułu
przez `ModuleInstaller`. Stan produkcyjny: `plugin_translator 1.3.2 active`.

### Sesja: 2026-06-19 - Kategorie tłumaczeń i pełna edycja katalogu

**Faza i krok specyfikacji:** Krok 5C oraz Krok 6 - korekta modelu pojęciowego i
pełny cykl życia kategorii managera tłumaczeń.

**Wykonano:**
- podniesiono `plugin_translator` do wersji `1.3.3`,
- etykietę `Pluginy translatora` zastąpiono nazwą `Kategorie tłumaczeń`, obejmującą
  pluginy, boty i inne projekty,
- dodano ekran edycji nazwy, slugu i powiązanej strony istniejącej kategorii,
- dodano akcje `Edytuj`, `Ukryj/Pokaż` i `Usuń` przy każdej zwykłej kategorii,
- usuwanie kategorii działa transakcyjnie: zgłoszenia są przepinane do
  `Nieprzypisane`, a następnie kategoria jest usuwana,
- techniczna kategoria `Nieprzypisane` jest chroniona przed edycją, zmianą
  widoczności i usunięciem,
- operacje aktualizacji i usunięcia zapisują audit log oraz wymagają CSRF.

**Weryfikacja:** uruchomiono pełny lint PHP, testy repozytorium,
`git diff --check` oraz aktualizację modułu przez `ModuleInstaller`. Stan
produkcyjny: `plugin_translator 1.3.3 active`.

### Sesja: 2026-06-19 - Poprawka publicznego menu mobilnego

**Faza i krok specyfikacji:** Krok 5C oraz Krok 6 - stabilizacja wspólnej
publicznej nawigacji modułów i podstron poza stroną główną.

**Wykonano:**
- naprawiono publiczne widoki poza stroną główną, które renderowały przycisk
  hamburgera Bootstrap, ale nie dołączały `bootstrap.bundle.min.js`, więc collapse
  menu mobilnego nie miał obsługi JavaScript,
- `end_page()` w motywach `default` i `glassnight` dołącza teraz ten sam bundle
  Bootstrap oraz `site.js`, którego używa strona główna.

**Weryfikacja:** wykonano lint PHP zmienionych motywów oraz pełne testy repozytorium.

### Sesja: 2026-06-19 - Publiczne centrum translatora i propozycje poprawek

**Faza i krok specyfikacji:** Krok 5C oraz Krok 6 - domknięcie publicznego
przepływu pracy modułu `plugin_translator`.

**Wykonano:**
- podniesiono `plugin_translator` do wersji `1.4.0`,
- połączono rozpoczęcie pracy, własne wersje robocze i upload gotowego YAML w
  trzech zakładkach publicznego widoku `/translations`,
- pozostawiono dotychczasowe adresy jako kompatybilne wejścia do zakładek,
- przeniesiono katalog kategorii pod centrum pracy i rozciągnięto go na pełną
  szerokość; nazwy kategorii są teraz linkami w tabeli,
- dodano akcję `Zaproponuj poprawkę` dla zaakceptowanych plików; tworzy ona nowy
  szkic zarówno z pracy edytora, jak i z gotowego uploadu, bez zmiany oryginału,
- dodano responsywny układ zakładek i grupy akcji dla obu aktywnych motywów.

**Weryfikacja:** lint PHP, testy repozytorium, kontrola diffu i aktualizacja modułu
zostały wykonane po implementacji. Lokalny endpoint HTTPS nie odpowiedział przed
limitem czasu, więc nie wykonano końcowego testu odpowiedzi przez serwer WWW.

### Sesja: 2026-06-19 - Synchronizacja dokumentacji i moduł profilu użytkownika

**Faza i krok specyfikacji:** Krok 5C oraz Krok 6 - domknięcie modułów zespołu i
profilu użytkownika z zachowaniem granicy odpowiedzialności `CoreAuth`.

**Wykonano:**
- zsynchronizowano checklistę z istniejącym modułem `team` 1.0.0,
- dodano opcjonalny moduł `user_profile` 1.0.0 zależny od `core_auth`,
- przeniesiono do niego trasy `/admin/profile`, edycję danych, ustawienia avatara
  i przegląd bezpieczeństwa bez zmiany adresów używanych przez dropdown użytkownika,
- zapis nadal przechodzi przez `AuthService`, CSRF i audit log,
- łączenie oraz odłączanie tożsamości OAuth pozostawiono w chronionym `core_auth`,
  który po wydzieleniu profilu ma wersję 1.4.1,
- dodano zgodny z dropdownem alias `/admin/profile/identities` do ekranu tożsamości
  obsługiwanego przez `core_auth`,
- dodano test manifestu, zależności i własności tras modułu profilu.

**Weryfikacja:** `php tests/run.php`, pełny lint PHP, `git diff --check`, migracja
Core oraz instalacja i aktywacja modułu przez `ModuleInstaller`.

### Sesja: 2026-06-19 - Grupowanie sekcji menu panelu

**Faza i krok specyfikacji:** Krok 5A i Krok 6 - stabilizacja wspólnej nawigacji
panelu dla pozycji rejestrowanych przez niezależne moduły.

**Wykonano:**
- poprawiono sortowanie `AdminMenuRegistry`, które wcześniej mieszało sekcje według
  globalnej wartości `order` i mogło wielokrotnie renderować nagłówki `Treść` oraz
  `System`,
- menu grupuje teraz wszystkie pozycje tej samej sekcji, zachowując kolejność sekcji
  według ich najwcześniejszej pozycji i kolejność wpisów wewnątrz grupy,
- dodano test regresji odtwarzający pozycję `Zespół` pomiędzy wpisami systemowymi.

**Weryfikacja:** `php tests/run.php`, lint zmienionych plików PHP,
`git diff --check` oraz test odpowiedzi panelu.

### Sesja: 2026-06-19 - Zatwierdzanie pakietu z kwarantanny

**Faza i krok specyfikacji:** Krok 6 - bezpieczny lifecycle zewnętrznego pakietu
między importem archiwum a instalacją modułu.

**Wykonano:**
- podniesiono `system_admin` do wersji 1.5.0,
- dodano ponowną walidację importu i podpisu bezpośrednio przed zatwierdzeniem,
- dopuszczono wyłącznie niechronione rozszerzenia z podpisem zaufanego wydawcy,
- dodano kontrolę nazwy katalogu, konfliktu katalogu i identyfikatora modułu,
- zweryfikowany pakiet jest atomowo przenoszony z kwarantanny do `modules/`, a
  instalacja i wykonanie fabryki pozostają osobną operacją,
- dodano akcję `/admin/modules/approve` z ACL `modules.install`, CSRF,
  potwierdzeniem i zdarzeniem audytu `module_archive_approve`,
- dodano test blokady niepodpisanego pakietu, poprawnej promocji podpisanego
  `LearningModule` oraz konfliktu istniejącego katalogu,
- przygotowano produkcyjne katalogi `modules/` i `cache/module-quarantine` z grupą
  `www-data`, setgid i minimalnymi prawami wymaganymi do atomowego przeniesienia.

**Weryfikacja:** `php tests/run.php`, pełny lint PHP, `git diff --check`, migracja
Core, kontrolowana aktualizacja `system_admin` i test odpowiedzi panelu. Środowisko
nie udostępnia `runuser`, a `su` wymaga hasła, dlatego prawa procesu WWW
zweryfikowano przez właściciela, grupę i tryby katalogów zamiast zapisu jako UID.

### Sesja: 2026-06-19 - Moduł katalogu projektów

**Faza i krok specyfikacji:** Krok 5C i Krok 6 - kolejny niezależny moduł treści,
agregujący istniejące podstrony i dokumentację bez duplikowania ich danych.

**Wykonano:**
- dodano moduł `projects` 1.0.0 zależny od `core_auth`, `core_pages` i `wikipedia`,
- dodano tabelę `projects` z kluczami obcymi do `core_pages` i `wiki_projects`,
- dodano stany projektu `planned`, `development`, `released`, `paused`, kolejność
  i niezależną publikację wpisu katalogowego,
- dodano publiczne `/projects`, `/projects/{slug}` i kompatybilne wejście query,
- dodano panel `/admin/projects` z CRUD, ACL `projects.view` / `projects.manage`,
  CSRF i audytem operacji,
- dodano publiczny link modułu przez `PublicNavigationRegistry`,
- dodano test manifestu, zależności, SQL i własności tras.

**Weryfikacja:** `php tests/run.php`, pełny lint PHP, `git diff --check`, instalacja
przez `ModuleInstaller`, kontrola stanu produkcyjnego i testy HTTP tras publicznej
oraz administracyjnej.

### Sesja: 2026-06-19 - Build Explorer, etap 1

**Faza i krok specyfikacji:** Krok 5C i Krok 6 - pliki wydań jako niezależne
rozszerzenie zależne od katalogu projektów.

**Wykonano:**
- dodano moduł `build_explorer` 1.0.0 zależny od `core_auth` i `projects`,
- dodano tabelę `project_builds` z kanałami Release, Snapshot, Dev i WIP,
- dodano wersję, nazwę pliku, zewnętrzny URL HTTPS, opcjonalny rozmiar i SHA-256,
  changelog, datę oraz stan publikacji,
- dodano publiczne `/builds`, `/builds/project/{slug}` i wejście query,
- publiczne zapytania wymagają publikacji zarówno buildu, jak i projektu,
- dodano panel `/admin/builds` z CRUD, ACL `builds.view` / `builds.manage`, CSRF
  i audytem operacji,
- dodano testy manifestu, zależności, SQL, tras i walidacji HTTPS.

**Weryfikacja:** `php tests/run.php`, pełny lint PHP, `git diff --check`, instalacja
przez `ModuleInstaller`, kontrola stanu i testy HTTP tras publicznej oraz panelowej.

### Sesja: 2026-06-19 - Widoczność publiczna Projektów i Build Explorer

**Faza i krok specyfikacji:** Krok 5C - integracja nowych modułów ze wspólną
nawigacją publiczną.

**Wykonano:**
- potwierdzono działanie tras `/projects` i `/builds`,
- podniesiono `projects` i `build_explorer` do wersji 1.0.1,
- poprawiono domyślny obszar linków `projects.index` i `build_explorer.index` z
  `none` na `main`,
- zachowano możliwość niezależnego ukrycia, przeniesienia do stopki albo pokazania
  w obu obszarach przez `/admin/settings`,
- dodano test regresji kontraktu deklaracji publicznej nawigacji obu modułów.

**Weryfikacja:** testy repozytorium, pełny lint PHP, `git diff --check` oraz test
HTML strony głównej i publicznych tras modułów.

### Sesja: 2026-06-19 - Build Explorer, bezpośredni upload JAR

**Faza i krok specyfikacji:** Krok 5C i Krok 6 - Etap 2 plików wydań projektów.

**Wykonano:**
- podniesiono `build_explorer` do wersji 1.1.0,
- dodano migrację pól platformy, numeru buildu i niepublicznego klucza magazynu,
- dodano upload `.jar` do `cache/build-artifacts` z limitem 20 MB i kontrolą
  rozszerzenia oraz sygnatury ZIP,
- rozmiar i SHA-256 są obliczane automatycznie z zapisanego artefaktu,
- domyślna nazwa ma wzór `<projekt>-<serwer>-<wersja>-<kanał>-<build>.jar` i może
  zostać ręcznie zmieniona na inny bezpieczny basename `.jar`,
- dodano kontrolowane publiczne pobieranie tylko dla opublikowanego projektu i
  buildu, z nagłówkami typu, długości, nazwy i `nosniff`,
- podmiana oraz usunięcie buildu sprzątają poprzedni plik,
- dodano test generatora nazwy, zapisu, SHA-256, rozmiaru i usuwania artefaktu.

**Weryfikacja:** pełne testy i lint PHP, `git diff --check`, migracja modułu,
uprawnienia magazynu, test jednostkowy zapisu artefaktu oraz testy HTTP publicznej
listy, ochrony panelu i odpowiedzi 404 dla brakującego pliku.

### Sesja: 2026-06-20 - Poprawka sprzątania po utworzeniu buildu

**Faza i krok specyfikacji:** Krok 5C - stabilizacja Etapu 2 Build Explorer.

**Wykonano:**
- podniesiono `build_explorer` do wersji 1.1.1,
- poprawiono próbę usunięcia starego `storageKey` dla nowego rekordu, gdzie obiekt
  poprzedniego buildu nie istnieje,
- `BuildArtifactStorage::delete()` bezpiecznie ignoruje pusty i brakujący klucz,
- blok wycofujący nowy upload obejmuje teraz wyłącznie nieudany zapis bazy; po
  utrwaleniu rekordu późniejsze sprzątanie nie może usunąć nowego artefaktu,
- wykryto i usunięto pojedynczy osierocony rekord utworzony przez zgłoszoną próbę,
- dodano test regresji wywołania sprzątania bez poprzedniego artefaktu.

**Weryfikacja:** pełne testy repozytorium, lint PHP, `git diff --check`, kontrola
spójności rekordów z magazynem oraz ponowny test zapisu buildu.

### Sesja: 2026-06-20 - Projekty 1.1 i Build Explorer 1.2 z CI

**Faza i krok specyfikacji:** Krok 5C - Etap 2 katalogu projektów i Etap 3
Build Explorera.

**Wykonano:**
- publiczna siatka projektów dobiera szerokość dla jednego, dwóch, trzech i czterech
  wpisów oraz pokazuje zasoby zamiast przycisku szczegółów i opisu,
- formularz projektu nie przechowuje już wymaganego opisu,
- `/builds` prowadzi przez projekt, kanał, tabelę wersji i historię buildów,
- tabela wersji pobiera najnowszy build, a historia pokazuje metadane CI i commity,
- dodano idempotentny `POST /api/builds/ci/{slug}` dla DEV/WIP, chroniony sekretem
  środowiskowym w nagłówku i walidujący cały JSON, SHA-256 oraz HTTPS,
- Release i Snapshot mogą nie mieć numeru buildu; rewizja pozostaje częścią wersji,
- rozszerzono `Request` o limitowany JSON i nagłówki oraz Theme o ogólne listy
  odnośników i publiczne tabele akcji.

**Weryfikacja:** testy repozytorium, pełny lint PHP, `git diff --check`, migracje
obu modułów oraz testy HTTP widoków publicznych i odmowy endpointu bez tokenu.

### Sesja: 2026-06-20 - Integracja CI projektu PunisherX

**Faza i krok specyfikacji:** Krok 5C - integracja producenta danych z endpointem
Build Explorer 1.2.

**Wykonano:**
- zmodyfikowano `.github/workflows/gradle.yml` w `SyntaxDevTeam/PunisherX` na
  gałęzi `main`, commit `6346bdc90b1d1e3fac567d1649f43f479e7b15d4`,
- workflow normalizuje właściwe pliki Shadow JAR i publikuje je bez dodatkowego
  archiwum ZIP przez `actions/upload-artifact@v7`,
- dla push generuje JSON z numerem runu, czasem, commitami, nazwą, SHA-256,
  rozmiarem i adresem artefaktu Paper,
- JSON trafia do `/api/builds/ci/punisherx` z `BUILD_CI_TOKEN`; pull requesty nie
  publikują buildów do portalu,
- mostki BungeeCord i Velocity pozostają artefaktami GitHub, ale nie są błędnie
  zgłaszane jako kanał DEV, ponieważ mają osobną wersję Snapshot,
- dependency submission ograniczono do push na `main`.

**Ograniczenie środowiska:** konektor GitHub nie zarządza repo secrets, a `gh` nie
jest zainstalowane. Sekret `BUILD_CI_TOKEN` trzeba dodać w ustawieniach repozytorium
PunisherX z wartością zgodną z konfiguracją miniPORTAL.

### Sesja: 2026-06-20 - Selektywna publikacja monorepo PunisherX

**Faza i krok specyfikacji:** Krok 5C - integracja trzech niezależnych projektów
Build Explorera z jednym repozytorium źródłowym.

**Wykonano:**
- potwierdzono slugi `punisherx`, `punisherx-bungeecord-bridge` oraz
  `punisherx-velocity-bridge`,
- workflow nadal zawsze buduje i testuje cały projekt Gradle,
- zmiany w `src/`, `bungee-bridge/` i `velocity-bridge/` niezależnie sterują
  uploadem artefaktu oraz publikacją odpowiedniego projektu,
- zmiany wspólnych plików Gradle oznaczają wszystkie trzy komponenty, a zmiany
  dokumentacji i samego workflow nie tworzą sztucznych buildów,
- każdy payload zawiera wyłącznie commity dotyczące katalogu komponentu albo
  wspólnych plików buildu,
- główny JAR trafia do `punisherx`, BungeeCord do `punisherx-bungeecord-bridge`,
  a Velocity do `punisherx-velocity-bridge`,
- finalny workflow zapisano w repozytorium PunisherX w commicie
  `44f530f0227b1e435bb9ba354d760848bd47c7c5`.

**Weryfikacja:** GitHub Actions run `27860806406` zakończył joby `build` i
`dependency-submission` sukcesem. Dla zmiany wyłącznie workflow trzy publikacje
zostały prawidłowo pominięte. Endpointy obu mostków zaakceptowały token i odrzuciły
celowo niepełny JSON kodem 422 bez utworzenia rekordów.

### Sesja: 2026-06-20 - Uproszczenie formularza logowania

**Faza i krok specyfikacji:** Krok 5A/5B - dopracowanie publicznego wejścia do
uwierzytelniania bez zmian w zabezpieczeniach Core.

**Wykonano:**
- usunięto z formularza logowania techniczną notatkę `[SEC]` opisującą `state`,
  PKCE i rotację sesji,
- zsynchronizowano oba motywy oraz statyczne prototypy panelu,
- usunięto nieużywaną klasę CSS `security-note`,
- mechanizmy OAuth i bezpieczeństwa sesji pozostały bez zmian,
- dodano test regresji HTML formularza logowania.

**Weryfikacja:** testy repozytorium, lint zmienionych plików PHP, `git diff --check`
oraz kontrola odpowiedzi HTTP `/admin/login`.

### Sesja: 2026-06-20 - Hierarchia ról i usunięcie Admin stylebook

**Faza i krok specyfikacji:** Krok 5A/5B i Krok 6 - uporządkowanie działającego
panelu oraz stabilizacja systemowych ról i ACL.

**Wykonano:**
- usunięto z panelu pozycję `Wzorce UI`, trasę `/admin/design-system`, widok zasobów
  developerskich i globalny przycisk `Admin stylebook`,
- podniesiono `system_admin` do 1.5.1 oraz `core_auth` do 1.5.0,
- dodano systemowe role Owner, Administrator, Maintainer, Redaktor, Audytor,
  Support i Użytkownik z trwałymi presetami uprawnień,
- Owner jako jedyny otrzymuje wildcard `*`, obejmujący przyszłe moduły,
- Administrator zachowuje wszystkie bieżące prawa granularne bez wildcarda,
- Maintainer zarządza kontami, projektami, buildami i bezpieczną konfiguracją bez
  Managera SQL oraz instalacji/usuwania modułów,
- Redaktor zarządza treściami publicznymi, Audytor ma przekrojowy odczyt i logi,
  a Support pracuje z dokumentacją oraz kolejką tłumaczeń,
- zablokowano nadawanie Ownera przez niższe role, eskalację Maintainer →
  Administrator/Maintainer i wildcard w rolach niestandardowych,
- konta Ownerów mogą być zarządzane wyłącznie przez Ownera; ostatni aktywny Owner
  nie może zostać zablokowany ani zdegradowany,
- definicje ról systemowych są tylko do odczytu i zmieniane wyłącznie migracjami,
- bootstrap pustej instalacji tworzy pierwszego Ownera zamiast Administratora,
- migracja awansowała najstarszego aktywnego administratora (`WieszczY`) do Ownera;
  `DomiKserQ` pozostał Administratorem.

**Weryfikacja:** pełne testy repozytorium, pełny lint PHP, `git diff --check`,
migracja produkcyjna, kontrola wildcarda i liczby praw każdej roli, kontrola
przypisań użytkowników oraz odpowiedź 404 usuniętej trasy design systemu.

### Sesja: 2026-06-20 - Logo, ikony urządzeń i metadane marki

**Faza i krok specyfikacji:** Krok 2/3 - uzupełnienie prototypów wizualnych i ich
odwzorowania w PHP o wspólny branding SyntaxDevTeam.

**Wykonano:**
- zastąpiono tekstowy znak `</>` właściwym logo w publicznej nawigacji, panelu,
  logowaniu i połączonych kontach obu motywów,
- wygenerowano favikony 16/32/48 px, ICO, Apple Touch Icon, ikony 192/512 px oraz
  bezpieczny wariant maskowalny z pliku `theme/ico/SyntaxDevTeam_logo.png`,
- dodano manifesty aplikacji, Open Graph, Twitter Card i dane Organization
  schema.org; kanoniczny host pochodzi z `SITE_URL`,
- widoki administracyjne i statyczne stylebooki otrzymały `noindex, nofollow`,
- dodano bez zależności zewnętrznych generator `tools/generate-brand-assets.mjs`,
  aby warianty można było odtworzyć po zmianie pliku źródłowego.

**Weryfikacja:** testy repozytorium, lint zmienionych plików PHP, kontrola JSON,
regeneracja zasobów, kontrola odpowiedzi HTML i `git diff --check`.

### Sesja: 2026-06-20 - Rozszerzone SEO, dostępność i UX szablonów

**Faza i krok specyfikacji:** Krok 2/3 oraz Krok 5B - rozszerzenie prototypów,
warstwy Theme i bezpiecznych ustawień systemowych.

**Wykonano:**
- rozdzielono panel Branding od SEO i udostępniania oraz dodano bazowy URL,
  domyślny tytuł, autora, robots, locale, obraz social z opisem, konto X/Twitter,
  kolor urządzenia i tokeny weryfikacyjne Google/Bing,
- każda wartość ma walidację serwerową, limit oraz odpowiadające atrybuty formularza;
  pomoc pól jest połączona przez `aria-describedby`,
- canonical korzysta ze znormalizowanej ścieżki `Request`; błędy publiczne i panel
  mają `noindex`, a zwykłe strony respektują konfigurowalną politykę robots,
- oba motywy generują pełne Open Graph i Twitter Card oraz graf Organization i
  WebSite w JSON-LD,
- dodano konfigurowalny `lang`, semantyczną nawigację stopki, `aria-current`,
  fokusowalny cel skip-linku, cele dotykowe 44 px, obsługę forced colors i pełniejsze
  `prefers-reduced-motion`,
- podniesiono `system_admin` do 1.6.0; schemat bazy nie wymaga migracji, ponieważ
  `system_settings` przechowuje dynamiczne klucze w kolumnie tekstowej; wersję
  zsynchronizowano w manifeście, metodzie runtime i produkcyjnym rejestrze modułów.

**Weryfikacja:** pełne testy repozytorium, lint PHP i JavaScript, kontrola CSS/HTML,
manifestów, danych JSON-LD oraz `git diff --check`. Produkcyjny smoke test zwrócił
200 dla `/` z kompletem metadanych oraz 404 z `noindex, nofollow` i bez canonical
dla nieistniejącej trasy.

### Sesja: 2026-06-20 - Przezroczyste logo panelu, favicony HD i naprawa 500

**Faza i krok specyfikacji:** Krok 2/3 - dopracowanie zasobów marki obu motywów
oraz kontrola konfiguracji produkcyjnej.

**Wykonano:**
- ustalono, że kod 500 powodował błędny INI: nieucieczony cudzysłów w
  `SITE_DEFAULT_TITLE` i wieloliniowy `SITE_META_DESCRIPTION`,
- naprawiono plik atomowo po walidacji w pamięci; kopię zapisano jako
  `/etc/miniportal/miniportal.env.bak-20260620-093012`,
- panel i logowanie korzystają z osobnego `admin-logo.png`, wyciętego z
  `SyntaxDevTeam_logo.no_bg.png`, bez kwadratowego tła,
- publiczne logo strony głównej pozostało niezmienione,
- favicony są generowane z czystszego sygnetu w rozmiarach 16, 32, 48, 64, 96,
  128 i 256 px z wielopróbkowym filtrowaniem; ICO zawiera sześć rozdzielczości,
- dokumentacja konfiguracji ostrzega przed wieloliniowymi wartościami INI i
  opisuje uciekanie wewnętrznych cudzysłowów.

**Weryfikacja:** parser INI zaakceptował 50 kluczy, strona po naprawie zwraca 200,
generator odtwarza komplet ikon, testy repozytorium i lint PHP/JS przechodzą.

### Sesja: 2026-06-21 - Wyrównanie przycisków i dystrybucja instalacyjna

**Faza i krok specyfikacji:** Krok 2/3 oraz Krok 7 - korekta komponentów obu
motywów i przygotowanie instalacji zerowej konfiguracji.

**Wykonano:**
- przyciski obu motywów wyśrodkowano przez `inline-flex`, wspólną wysokość, linię
  tekstu i kontenery akcji tabel łączące linki z formularzami,
- dodano powtarzalny generator `bin/build-cms-distribution.php` oraz czysty folder
  `install/cms` bez produkcyjnego środowiska, blokady i danych cache,
- kreator WWW sprawdza PHP i rozszerzenia, przyjmuje ustawienia strony, MySQL,
  GitHub OAuth, opcjonalnych providerów oraz wybór modułów,
- instalator odtwarza aktualne schematy w kolejności zależności, zapisuje SHA-256
  stanu migracji, tworzy pierwszego Ownera na podstawie numerycznego ID GitHub,
  zapisuje konfigurację atomowo i blokuje ponowną instalację,
- lokalny `config/installed.env` ma pierwszeństwo przed globalnym plikiem `/etc`,
  natomiast jawny `MINIPORTAL_ENV_FILE` nadal ma najwyższy priorytet,
- dodano test katalogu modułów, kompletności dystrybucji oraz integracyjny test
  instalatora na odseparowanej bazie.

**Weryfikacja:** pełne testy repozytorium; integracyjna instalacja 11 modułów i
jednego Ownera na czystej MariaDB; smoke test HTTP kreatora z kodem 200, CSP,
CSRF i `noindex`; pełny lint PHP oraz kontrola `git diff --check`.

### Sesja: 2026-06-21 - Akrostych Hero / Split

**Faza i krok specyfikacji:** Krok 5C - rozszerzenie danych sekcji strony głównej
i ich prezentacji przez oba aktywne motywy.

**Wykonano:**
- dodano opcjonalne pole `Akrostych hero`, przyjmujące wyrazy rozdzielone spacją
  albo nową linią i normalizujące je do jednego wyrazu na wiersz,
- ograniczono wartość do 12 wyrazów, 60 znaków na wyraz i 500 znaków łącznie,
- dla `hero` w układzie `split` oba motywy pokazują wyrazy pionowo jako główny
  nagłówek `h1`, z subtelnie podświetloną pierwszą literą; terminal pozostaje obok,
- dodano migrację `20260621_homepage_hero_acrostic.sql`, zaktualizowano pełny
  `install.sql` i podniesiono `core_pages` z 1.0.0 do 1.1.0,
- migrację wykonano na produkcyjnej bazie, a schemat świeżej dystrybucji pozostaje
  zgodny z aktualnym stanem modułu.

**Weryfikacja:** test regresji HTML potwierdza pionowe `SYNTAX`, pełne testy i lint
PHP przechodzą, migracja produkcyjna utworzyła `acrostic_words VARCHAR(500)`.

### Sesja: 2026-06-21 - Podpis Powered by w stopce

**Faza i krok specyfikacji:** Krok 2/3 - spójność brandingu obu aktywnych motywów.

**Wykonano:** podpis stopki zmieniono na dynamiczne
`© {rok} Powered by miniPORTAL by {nazwa strony}` w obu motywach i prototypach.

**Weryfikacja:** test regresji HTML, lint PHP i przebudowa czystej dystrybucji.

### Sesja: 2026-06-21 - Podstrona projektu miniPORTAL i linki stopki

**Faza i krok specyfikacji:** Krok 3/5C - publiczna treść projektowa i wspólny
layout obu aktywnych motywów.

**Wykonano:**
- opublikowano `/p/miniportal` jako dokument projektowy Markdown z opisem funkcji,
  architektury, zabezpieczeń, modułów, instalatora i technologii,
- dodano opis SEO, podsumowanie, nadtytuł oraz odnośniki do repozytorium i zespołu,
- `miniPORTAL` w stopce prowadzi do `https://syntaxdevteam.pl/p/miniportal`, a
  `SyntaxDevTeam` do `https://syntaxdevteam.pl` w obu motywach i prototypach,
- identyczny konfigurowalny tekst `Powered by miniPORTAL by SyntaxDevTeam` jest
  pomijany, aby podpis nie był zdublowany; inne teksty stopki pozostają widoczne,
- dodano idempotentną migrację danych i podniesiono `core_pages` do 1.2.0.

**Weryfikacja:** migracja produkcyjna, HTTP 200 dla `/p/miniportal`, poprawny
canonical i opis SEO, testy linków stopki, pełny lint oraz przebudowa dystrybucji.

### Sesja: 2026-06-21 - Zbalansowany układ Ustawień

**Faza i krok specyfikacji:** Krok 3/5A - rozszerzenie ogólnych komponentów Theme
i uporządkowanie responsywnego widoku administracyjnego.

**Wykonano:**
- dodano do `ThemeInterface` ogólny komponent kolumny paneli i zaimplementowano
  go w obu aktywnych motywach,
- Branding, Szablon i Cache tworzą lewy stos, a wysokie SEO prawą kolumnę,
- publiczna nawigacja modułów zajmuje pełną szerokość w kolejnym rzędzie,
- pojedynczy panel siatki automatycznie rozciąga się na wszystkie kolumny,
- poniżej 1200 px układ ustawień przechodzi do jednej kolumny,
- podniesiono `system_admin` do 1.6.1 bez migracji schematu bazy.

**Weryfikacja:** test HTML dwóch kolumn paneli, pełne testy repozytorium, lint PHP,
kontrola CSS obu motywów i przebudowa czystej dystrybucji.

### Sesja: 2026-06-21 - Wyszukiwanie, nawigacja i hooki Dashboardu

**Faza i krok specyfikacji:** Krok 5A/5B i Krok 6 - funkcjonalny panel oraz
rozszerzalne kontrakty modułów bez zależności od HTML.

**Wykonano:**
- dodano `AdminSearchRegistry` i `AdminSearchProviderInterface`; menu jest
  indeksowane automatycznie, moduły dopisują akcje i słowa kluczowe, a ACL filtruje
  wpisy przed przekazaniem ich do motywu,
- oba motywy renderują dostępne wyniki topbara, a JavaScript obsługuje filtrowanie,
  pusty wynik, limit, Escape, strzałki i Enter,
- publiczna nawigacja zapisuje i stosuje kolejność obok etykiety oraz miejsc,
- dodano `DashboardRegistry` i `DashboardProviderInterface` dla metryk oraz tabel;
  widoczność zgłoszeń konfiguruje się w Ustawieniach,
- usunięto `Stan architektury`; Team, Projekty i Build Explorer dodają statystyki,
- `/admin/team/create` potwierdzono kodem 200 w rzeczywistej sesji Ownera, a wyjątek
  listy użytkowników jest prezentowany jako kontrolowany stan panelu,
- poprawiono akrostych: zastępuje `h1` hero po lewej i nie usuwa terminala statusu,
- wersje: `system_admin` 1.7.0, `team` 1.1.0, `projects` 1.2.0,
  `build_explorer` 1.3.0.

**Weryfikacja:** testy rejestrów, kolejności, ACL i HTML; smoke testy Ownera dla
Dashboardu, Ustawień i Team zwracają 200; pełny lint, walidacja CSS/JS i świeża
instalacja dystrybucji.

### Sesja: 2026-06-21 - Korekta typografii akrostychu

**Faza i krok specyfikacji:** Krok 2/3 i 5C - responsywna prezentacja danych Hero.

**Wykonano:** usunięto osobną szerokość pierwszej litery i dekoracyjną linię,
zmniejszono responsywną skalę nagłówka oraz zabezpieczono każdy wyraz przed
łamaniem. Wyróżniona litera tworzy teraz ciągły wyraz z pozostałą częścią.

**Weryfikacja:** parser CSS obu motywów, test HTML Hero, kontrola publicznej strony
z akrostychem i terminalem oraz pełne testy repozytorium.

### Sesja: 2026-06-21 - Diagnostyka cache i generator favicon

**Faza i krok specyfikacji:** Krok 3/5A oraz Krok 6 - ustawienia prezentacji,
cache szablonów i bezpieczne zasoby publiczne.

**Wykonano:**
- ustalono, że zerowy licznik wynikał z omijania cache przez zalogowaną sesję,
  unieważniania wpisów i braku późniejszego anonimowego żądania,
- statystyki pokazują ważne i wygasłe wpisy, rozmiar HTML, TTL, możliwość zapisu
  i katalog; zapis ustawień Dashboardu nie czyści już publicznych szablonów,
- Branding generuje z PNG komplet favicon, Apple Touch Icon, ikony aplikacji,
  ICO i manifest w chronionym `uploads/branding`,
- oba motywy używają wspólnego zestawu z wersją unieważniającą cache przeglądarki,
- dystrybucja zawiera generator i instrukcję praw zapisu; produkcyjny
  `system_admin` podniesiono do 1.8.0.

**Weryfikacja:** lint PHP, pełne testy, rzeczywiste generowanie wariantów 16-512
px, przebudowa dystrybucji i anonimowy smoke test HTTP 200. Po rozgrzaniu cache
raportował 1 wpis oraz 18 048 B.

### Sesja: 2026-06-21 - Alternatywny motyw Future

**Faza i krok specyfikacji:** Krok 2/3 - prototyp wizualny i pełne odwzorowanie
wymiennego motywu w `ThemeInterface`.

**Wykonano:**
- przeanalizowano wyłącznie publiczny HTML oraz CSS starego projektu z
  `inspiration_sources/oldcms`; jego PHP, bazę, autoryzację i zależności odrzucono,
- dodano kompletny `templates/future` obsługujący aktualną stronę główną,
  podstrony, formularze, tabele, błędy, logowanie i panel administracyjny,
- odtworzono grafitową siatkę, paletę cyan/lime/magenta, duży hero, neonowe linie,
  zwarte panele, proste karty i gradientowe obramowania,
- zachowano aktualne SEO, favicony, publiczną nawigację, responsywność, dostępność,
  redukcję ruchu oraz wszystkie komponenty modułów,
- motyw `Future` dodano do kreatora instalacji; silnik wykrywa go automatycznie
  także na istniejącej instalacji.

**Weryfikacja:** lint PHP i JavaScript, kontrola składni i bilansu CSS, test
wykrywania oraz renderowania własnych assetów Future, pełne testy repozytorium i
przebudowa czystej dystrybucji.

### Sesja: 2026-06-21 - Dedykowany moduł Econizer Control Center

**Faza i krok specyfikacji:** Krok 6A - samodzielny moduł rozszerzenia z pełnym
podziałem Core -> Module -> Theme i izolacją tenantów Discord.

**Wykonano:**
- dodano `econizer` 1.0.0 z 11 tabelami domenowymi: funkcje, ustawienia platformy,
  serwery, członkostwa, portfele, transakcje, sklep, zamówienia, aktywa, notowania
  i udziały graczy,
- Owner platformy zarządza funkcjami, językiem PL, domyślnym `/daily` i `/work`,
  limitem Freemium, planami oraz tenantami,
- właściciel lub administrator serwera konfiguruje walutę, podatek, VIP daily,
  członków, sklep i rynek wyłącznie w tenantcie wynikającym z członkostwa,
- gracz otrzymał pulpit salda, EXP, poziomu i historii, sklep z atomowym zakupem
  oraz giełdę z dostępnym wykresem SVG, notowaniami, udziałami i atomowym
  kupnem/sprzedażą; ogólny wykres działa przez `ThemeInterface` we wszystkich
  trzech motywach bez biblioteki JavaScript,
- plan Freemium egzekwuje konfigurowalny limit katalogu, a Premium nie ma limitu,
- endpoint `/api/econizer/events` wymaga osobnego tokenu nagłówka, waliduje JSON,
  mapuje Discord Guild/User ID na lokalne konto i używa `event_id` do idempotencji,
- dodano README integracji bota, konfigurację `ECONIZER_API_TOKEN`, testy manifestu,
  schematu i zabezpieczeń oraz moduł do czystej dystrybucji instalacyjnej.

**Weryfikacja:** pełne testy repozytorium, lint PHP, walidacja JSON, `git diff
--check`, przebudowa dystrybucji, instalacja wszystkich 12 modułów na pustej
MariaDB oraz smoke test repozytorium potwierdzający idempotencję i atomowe saldo
po zakupie sklepowym i transakcji giełdowej.

### Sesja: 2026-06-21 - Izolowane środowisko modułu Econizer

**Faza i krok specyfikacji:** Krok 6A/7 - konfiguracja przenośnego rozszerzenia
i jej bezpieczne tworzenie w czystej dystrybucji.

**Wykonano:**
- wydzielono sekrety Econizer z globalnego środowiska miniPORTAL do
  `modules/Econizer/.env`, ignorowanego przez Git i blokowanego publicznie,
- dodano `EconizerConfig` z priorytetem `ECONIZER_ENV_FILE` -> lokalny `.env` ->
  zmienne procesu dla zgodności wstecznej,
- szablon modułu obejmuje token endpointu, token bota, Client ID, Client Secret,
  callback instalacji oraz minimalną maskę uprawnień Discord,
- panel Ownera raportuje wyłącznie stan konfiguracji bez ujawniania sekretów,
- instalator tworzy osobny plik Econizer atomowo z prawami `0600`, tylko gdy moduł
  został wybrany; czysta dystrybucja zawiera wyłącznie `.env.example`.

**Weryfikacja:** test izolowanego pliku wskazanego przez `ECONIZER_ENV_FILE`, test
braku sekretów w dystrybucji, integracyjna kontrola wygenerowanego tokenu i praw
`0600`, lint PHP, pełne testy oraz `git diff --check`.

### Sesja: 2026-06-22 - Kontrolowany dostęp niezalogowany do Econizer

**Faza i krok specyfikacji:** Krok 5B/6A - ACL, audit log i odporność modułu na
niezalogowane żądania panelowe.

**Wykonano:** naprawiono `guard()` modułu Econizer, który przekazywał liczbową
decyzję HTTP `401`/`403` do tekstowego pola wyniku `AuditLogService`, powodując
`TypeError` i odpowiedź 500. Decyzje są teraz mapowane na `unauthenticated` albo
`forbidden`, natomiast kod HTTP pozostaje odpowiednio 401 lub 403.

**Weryfikacja:** test regresji źródła, lint PHP, pełne testy repozytorium,
przebudowa dystrybucji oraz anonimowy smoke test rzeczywistej trasy Econizer.

### Sesja: 2026-06-22 - Warstwa menu profilu panelu

**Faza i krok specyfikacji:** Krok 3/5A - ogólny layout panelu i działanie
nawigacji użytkownika we wszystkich motywach.

**Wykonano:** jawnie ustawiono stacking context topbara i menu użytkownika ponad
treścią panelu. Naprawia to karty Dashboardu przykrywające dolną część dropdownu,
w tym przycisk `Wyloguj`, bez zmiany struktury HTML ani priorytetów kart.

**Weryfikacja:** test regresji CSS dla motywów Default, Glassnight i Future,
kontrola bilansu CSS, pełne testy repozytorium, przebudowa dystrybucji oraz
kontrola działającego panelu.

Podczas weryfikacji test dystrybucji wykrył, że generator kopiował ignorowany
przez Git lokalny `modules/Econizer/.env`. Generator pomija teraz `.env` oraz
warianty `.env.*` z wyjątkiem publicznego `.env.example`; dystrybucję odtworzono,
usuwając z niej lokalny sekret.

### Sesja: 2026-06-22 - OAuth serwerów i instalacja bota Econizer

**Faza i krok specyfikacji:** Krok 6A - rozwinięcie bezpiecznego onboardingu
dedykowanego modułu bez ręcznego zaufania do danych użytkownika.

**Wykonano:**
- `Konfiguracja integracji` wyróżnia poprawne elementy zieloną ramką, a po
  skompletowaniu wszystkich ustawień zwija się do subtelnego komunikatu sukcesu,
- serwery aktywne w Econizer znajdują się w lewej kolumnie, a prawa pokazuje
  kafelki serwerów Discord zarządzanych przez bieżącego użytkownika,
- usunięto mylący formularz ręcznego tworzenia serwera na podstawie Guild ID,
- dodano osobny Authorization Code + PKCE z `identify guilds`, filtrowanie bitów
  Owner/Administrator/Manage Guild oraz 10-minutowy cache listy w sesji,
- token użytkownika Discord jest używany wyłącznie do profilu i listy guildów;
  nie trafia do bazy, konfiguracji ani sesji,
- szczegóły zweryfikowanego serwera pozwalają utworzyć tenant Freemium, przypisać
  lokalne konto jako `guild_owner`/`guild_admin`, sprawdzić obecność bota i użyć
  przypiętego zaproszenia `bot applications.commands`,
- podniesiono `econizer` do 1.1.0 bez zmiany schematu bazy.
- lokalny plik developerski ma tryb `0640` i grupę `www-data`, ponieważ jego
  właścicielem jest administrator; instalator zachowuje `0600`, gdy zapisuje go
  bezpośrednio jako użytkownik procesu WWW.

**Weryfikacja:** test symulowanego OAuth Discord z odrzuceniem zwykłego członka,
kontrola braku tokenu Bearer w sesji, test URL instalacji, test wariantów Theme,
lint PHP, pełne testy repozytorium, przebudowa dystrybucji i `git diff --check`.

### Sesja: 2026-06-22 - Interaktywny terminal strony głównej

**Faza i krok specyfikacji:** Krok 2/3 - rozwinięcie prototypu Hero i jego
odwzorowania w wymiennych motywach.

**Wykonano:** statyczny podgląd `./workspace status` zastąpiono bezpiecznym
symulatorem terminala w motywach Default, Glassnight i Future. Sekwencja startowa
pokazuje stan CoreAuth, CorePages, ThemeEngine i SyntaxCrudApp, a prompt obsługuje
pomoc, status, listę obszarów, historię klawiszami oraz kontrolowaną nawigację do
logowania, projektów, buildów, Wiki, zespołu i strony miniPORTAL. Symulator działa
wyłącznie w JavaScript i nie wykonuje poleceń ani procesów na serwerze. Korekta
prezentacji usuwa prefiksy `[ OK ]`, wyróżnia na zielono wyłącznie wartości stanu,
nieznacznie zwiększa wysokość terminala i pokazuje powitanie
`SyntaxDevTerminal 0.1.5`.

**Weryfikacja:** test renderowania kontraktu terminala, lint PHP i JavaScript,
pełne testy repozytorium, przebudowa czystej dystrybucji oraz `git diff --check`.

### Sesja: 2026-06-21 - Ręczne łamanie nagłówków sekcji

**Faza i krok specyfikacji:** Krok 3/5C - bezpieczne dane prezentacyjne sekcji
strony głównej i ich renderowanie przez wymienne motywy.

**Wykonano:** pole nagłówka sekcji jest teraz trzywierszowym `textarea`. Enter
wyznacza kontrolowany podział wiersza, zapis normalizuje odstępy i dopuszcza
maksymalnie cztery niepuste linie. `default`, `glassnight` i `future` kodują każdą
linię przed połączeniem przez `<br>`, a etykieta menu usuwa podziały. Moduł
`core_pages` podniesiono do 1.3.0 bez zmiany schematu bazy.

**Weryfikacja:** test zamierzonego podziału i kodowania potencjalnego HTML, test
manifestu, pełne testy repozytorium, lint PHP i przebudowa dystrybucji.

### Sesja: 2026-06-22 - Fundament i18n PL/EN/DE i tłumaczenia treści

**Faza i krok specyfikacji:** Krok 8.1 - internacjonalizacja Core, Theme oraz
modułów treści bez naruszania granic Core -> Modules -> Templates.

**Wykonano:**
- dodano `LocaleResolver`, `LocaleContext`, `TranslatorInterface`, plikowy katalog
  tłumaczeń z fallbackiem PL i prefiksy publiczne `/pl`, `/en`, `/de`,
- Router nadal otrzymuje dotychczasowe ścieżki modułów; `/admin/*` i `/api/*` nie
  są lokalizowane,
- wszystkie motywy generują `lang`, przełącznik języka, canonical i `hreflang`,
  a cache homepage, stron i artykułów jest rozdzielony locale,
- `core_pages` 1.4.0 dodaje `core_page_translations`, formularze EN/DE, osobną
  publikację i wykrywanie zmiany polskiego źródła,
- `articles` 1.1.0 dodaje analogiczny kontrakt `article_translations`,
- opcjonalny `GoogleCloudTranslationService` korzysta z serwerowego klucza,
  zapisuje wyłącznie szkice i nie jest uruchamiany podczas publicznych żądań,
- brak opublikowanej wersji językowej zwraca 404 zamiast mieszać języki.

**Weryfikacja:** pełne testy repozytorium, aktualizacja obu modułów przez
`ModuleInstaller` z kontrolą SHA-256, transakcyjny test zapisu/publikacji strony i
artykułu, integracyjna instalacja wszystkich 12 modułów na pustej bazie, smoke
testy `/pl`, `/en`, `/de`, lint PHP, przebudowa dystrybucji i `git diff --check`.

### Sesja: 2026-06-22 - Hooks API i parametryczny Slug Router

**Faza i krok specyfikacji:** punkt 5.3 oraz Krok 4/6 - rozszerzalny Core i trasy
aktywnych modułów bez zależności między ich źródłami.

**Wykonano:** dodano `HookRegistry` z akcjami, filtrami, priorytetami i stabilną
kolejnością oraz opcjonalny `HookProviderInterface` uruchamiany przez
`ModuleRegistry`. Pierwszy filtr `homepage.sections` pozwala modułowi widgetów
dostarczyć strukturalne dane sekcji przed renderowaniem przez aktywny Theme; hook
nie przenosi HTML do modułu. Router obsługuje teraz parametry `{slug}` przekazywane
przez filtrowany `Request`, zachowuje pierwszeństwo tras statycznych, 405 i odrzuca
zakodowane separatory. Core Pages, Articles, Wikipedia, Team, Projects i Build
Explorer nie generują już tras na podstawie list pobieranych z bazy przy starcie.

**Weryfikacja:** lint zmienionych plików PHP oraz testy akcji, filtrów, rejestracji
providera, priorytetów, parametrów URL, dekodowania, pierwszeństwa tras i 405.

### Sesja: 2026-06-22 - Tłumaczenia sekcji strony głównej

**Faza i krok specyfikacji:** Krok 8.2 - internacjonalizacja ręcznie zarządzanej
treści homepage przy zachowaniu granic Core -> Modules -> Templates.

**Wykonano:** `core_pages` 1.5.0 dodaje tabele tłumaczeń sekcji oraz ich elementów,
formularze EN/DE, niezależne szkice i publikacje, wykrywanie zmiany polskiego
źródła oraz opcjonalne generowanie szkicu przez Google Cloud Translation. Pola
tekstowe są lokalizowane, natomiast typy, układ, warianty, kolejność, adresy i
powiązania pozostają wspólne. Publiczny odczyt wybiera wyłącznie opublikowane
rekordy danego locale, lokalizuje wewnętrzne linki i pokazuje przetłumaczony stan
pusty, gdy wersja językowa nie ma jeszcze sekcji. Poprawiono też zapis wszystkich
tłumaczeń z kluczem złożonym: wynik `INSERT` nie zależy już od `lastInsertId()`.

**Weryfikacja:** aktualizacja produkcyjna przez `ModuleInstaller`, transakcyjny
cykl zapisu, publikacji i odczytu sekcji oraz karty, lint PHP, pełne testy
repozytorium, integracyjna instalacja na pustej bazie, przebudowa dystrybucji,
smoke testy PL/EN/DE i `git diff --check`.

### Sesja: 2026-06-22 - Wycofanie publicznego i18n

**Faza i krok specyfikacji:** decyzja projektowa wycofująca Kroki 8.1/8.2
internacjonalizacji, z zachowaniem niezależnych elementów Kroku 4/6.

**Wykonano:** usunięto resolver locale, katalogi PL/EN/DE, prefiksy językowe,
przełącznik i `hreflang`, integrację Google Cloud Translation, formularze
tłumaczeń oraz tabele i migracje tłumaczeń stron, artykułów, sekcji i kart.
Przywrócono pojedynczy polski obieg treści i wersje modułów sprzed i18n.
Zachowano niezależne `HookRegistry`, `HookProviderInterface` i parametryczny Router,
które znalazły się w tym samym historycznym commicie, ale nie należą do i18n.

**Weryfikacja:** pełne testy repozytorium, lint PHP, przebudowa czystej
dystrybucji, wyszukiwanie pozostałości i18n oraz `git diff --check`.

### Sesja: 2026-06-22 - Usunięcie dodatkowego przycisku panelu z Hero

**Faza i krok specyfikacji:** Krok 2/3 - korekta prototypu strony głównej i jego
odwzorowania przez wymienne motywy.

**Wykonano:** usunięto z Hero dodatkowy przycisk `Panel administracyjny` /
`Przejdź do panelu` we wszystkich aktywnych motywach. Pozostawiono główny,
konfigurowalny przycisk sekcji oraz standardowe wejście do logowania lub panelu
w nawigacji publicznej.

**Weryfikacja:** test regresji renderowania Hero, lint PHP, pełne testy,
przebudowa dystrybucji i `git diff --check`.
### Sesja: 2026-06-22 - Moduł widgetów i terminal Hero

**Faza i krok specyfikacji:** Krok 5C/6 - moduł treści prezentacyjnej korzystający
z Hooks API bez zależności od HTML konkretnego motywu.

**Wykonano:** dodano `widgets` 1.0.0 z CRUD, ACL `widgets.manage`, CSRF, audytem,
cache invalidation, metryką Dashboardu i wyszukiwarką panelu. Widgety typu terminal
albo karta można przypisać do wszystkich motywów lub jednego motywu oraz do slotów
na początku strony, obok Hero, po Hero, przed/po wskazanej sekcji i przed stopką.
Istniejący SyntaxDevTerminal stał się rekordem instalacyjnym modułu. Default,
Glassnight i Future renderują dane widgetów samodzielnie; brak widgetu rozszerza
Hero na pełną szerokość. Symulator obsługuje wiele instancji i nadal nie wykonuje
poleceń serwera.

**Weryfikacja:** testy układu hooków, manifestu, bezpiecznego renderowania karty,
braku zaszytego terminala, wielu terminali JS, pełny lint PHP/JS, testy repozytorium,
przebudowa czystej dystrybucji, instalacja przez `ModuleInstaller` i smoke test `/`.

### Sesja: 2026-06-23 - Kontakt na końcu publicznego menu

**Faza i krok specyfikacji:** Krok 3/7 - wspólna publiczna nawigacja aktywnych
motywów oraz kolejność linków publicznych modułów.

**Wykonano:** Default, Glassnight i Future odkładają link do sekcji `Kontakt`
podczas renderowania sekcji strony głównej i wypisują go dopiero po linkach
stron oraz modułów w menu głównym. Dzięki temu `Kontakt` pozostaje ostatnim
zwykłym linkiem nawigacyjnym niezależnie od liczby modułów dodających własne
pozycje; przycisk logowania/panelu pozostaje osobną akcją po nim.

**Weryfikacja:** dodano test regresji kolejności linków publicznej nawigacji,
uruchomiono lint zmienionych plików PHP, pełne testy repozytorium, pełny lint PHP,
przebudowę czystej dystrybucji, migracje Core oraz `git diff --check`.

### Sesja: 2026-06-23 - Dokumentacja modułu Econizer

**Faza i krok specyfikacji:** Krok 8 - moduł dedykowany Econizer, dokumentacja
części publicznej oraz panelu administracyjnego.

**Wykonano:** dodano `docs/ECONIZER_MODULE.md` z opisem celu modułu, poziomów
dostępu, widoków `/econizer`, `/econizer/shop`, `/econizer/market`,
`/econizer/server`, panelu `/admin/econizer`, onboardingu Discord, endpointu bota,
pliku `.env`, tabel bazy oraz granic bezpieczeństwa. README wskazuje nowy dokument
w mapie dokumentacji projektu.

**Weryfikacja:** porównano dokument z aktualnym `EconizerModule`,
`EconizerRepository`, `EconizerConfig`, `EconizerDiscordGateway`, `info.json` oraz
`install.sql`; uruchomiono kontrolę spójności Markdown przez podgląd diffu.

### Sesja: 2026-06-23 - Bot-first onboarding serwerów Econizer

**Faza i krok specyfikacji:** Krok 8 - moduł dedykowany Econizer, rozdzielenie
zarządzania serwerem Discord od konta gracza.

**Wykonano:** podniesiono `econizer` do 1.2.0. Dodano publiczny flow
`/econizer/servers` dla właścicieli i administratorów Discord, którzy pobierają
zarządzane serwery przez OAuth `identify guilds`. Portal nie tworzy już tenantów
przez ręczne aktywowanie Guild ID; nowy endpoint `/api/econizer/guilds` przyjmuje
zgłoszenie bota `installed`/`removed` i dopiero taki rekord można połączyć z
lokalnym kontem jako `guild_owner` albo `guild_admin`. `owner_user_id` w
`econizer_guilds` jest opcjonalny, bo bot może zgłosić serwer przed połączeniem
lokalnego właściciela. Sklep, giełda, portfele i transakcje pozostają przypisane
do konkretnego serwera Discord.

**Weryfikacja:** lint zmienionych plików PHP, pełne testy repozytorium, pełny
lint PHP, przebudowa dystrybucji, lint plików Econizer w `install/cms`, migracje
Core, `git diff --check` oraz kontrolowana aktualizacja produkcyjnego stanu
`econizer` z 1.1.0 do 1.2.0 przez `ModuleInstaller`.

### Sesja: 2026-06-23 - Usunięcie ręcznego wiązania użytkowników Econizer

**Faza i krok specyfikacji:** Krok 8 - moduł dedykowany Econizer, separacja graczy
Discord od administracji serwera Discord.

**Wykonano:** podniesiono `econizer` do 1.2.1. Usunięto z ustawień serwera kartę
`Powiąż użytkownika`, trasę `/econizer/server/member` oraz administracyjne trasy
Discord OAuth z `/admin/econizer/*`. Panel administracyjny Econizer jest teraz
diagnostyką platformy i listą tenantów zgłoszonych przez bota, bez flow zapraszania
bota z panelu. Właściciel lub administrator Discord korzysta z publicznego
`/econizer/servers`, a gracz jest przypisywany automatycznie po zdarzeniu bota,
jeśli jego lokalne konto miniPORTAL ma tożsamość Discord zgodną z `discord_user_id`.

**Weryfikacja:** lint zmienionych plików PHP, pełne testy repozytorium, pełny
lint PHP, przebudowa dystrybucji, lint plików Econizer w `install/cms`, kontrolowana
aktualizacja produkcyjnego stanu `econizer` z 1.2.0 do 1.2.1 bez migracji, migracje
Core oraz `git diff --check`.

### Sesja: 2026-06-23 - Ikona Econizer w stylu Future

**Faza i krok specyfikacji:** Krok 8 - identyfikacja wizualna dedykowanego modułu
Econizer, zgodna z motywem Future.

**Wykonano:** wygenerowano dużą bitmapową ikonę Econizer 1254x1254 px z grafitowym
tłem, neonowym cyan/lime/magenta, motywem tokena ekonomii, bota i wykresu wzrostu.
Asset zapisano jako `modules/Econizer/assets/brand/econizer-icon-future-1254.png`
i przebudowano czystą dystrybucję, aby trafił również do `install/cms`.
Po ocenie jako zbyt modułowo-dashboardowej wygenerowano drugą, prostszą wersję
avatarową dla Discorda: `modules/Econizer/assets/brand/econizer-discord-bot-icon-v2.png`.

**Weryfikacja:** sprawdzono rozdzielczość pliku i podgląd wizualny wygenerowanej
ikony; lokalne narzędzia do automatycznego skalowania PNG nie są dostępne w
środowisku.
### Sesja: 2026-06-24 - Produkcyjny rebranding Econizer

**Faza i krok specyfikacji:** Krok 8 - moduł dedykowany Econizer, aktualizacja
ukończonego modułu i jego kontraktu produkcyjnego.

**Wykonano:** zmieniono markę bota i modułu na Econizer we wszystkich bieżących
widokach, treściach startowych, dokumentacji, testach i assetach. Kontrakt runtime
używa teraz katalogu `modules/Econizer`, klas `Econizer*`, identyfikatora
`econizer`, tras `/econizer`, endpointów `/api/econizer/*`, zmiennych
`ECONIZER_*`, nagłówka `X-Econizer-Token` oraz tabel `econizer_*`. Dodano migrację
stanu managera modułów, migrację danych domenowych i uprawnień, migrację nazw
ograniczeń oraz indeksów i migrację treści `core_pages`. Podniesiono wersje
`econizer` i `core_pages` do 1.3.1. Historyczna migracja z 2026-06-23 zachowała
niezmienioną treść i SHA-256.

Dedykowane bitmapy nie zawierały tekstu marki, dlatego zachowano ich piksele i
bezstratnie zmieniono nazwy plików oraz odwołania na warianty `econizer-*`.

**Weryfikacja:** pełne `php tests/run.php`, lint 254 plików PHP, `node --check`
dla plików JavaScript, przebudowa `install/cms`, `git diff --check`, kontrola
manifestów JSON, ponowne uruchomienie migracji bez oczekujących zmian oraz smoke
test tras `/econizer`, `/econizer/servers` i `/admin/econizer` na izolowanym
serwerze PHP. Produkcyjna baza ma aktywny `econizer` 1.3.1, 11 tabel
`econizer_*`, nowe uprawnienia oraz zero starych nazw tabel, indeksów, ograniczeń
i treści CMS.

### Sesja: 2026-06-24 - Czysty schemat instalacyjny bez migracji historycznych

**Faza i krok specyfikacji:** Krok 7 - integracyjna instalacja wszystkich modułów
na czystej bazie.

**Wykonano:** dodano bieżący `core/install.sql`, a kreator przestał wykonywać
historyczne migracje Core na pustej bazie. Generator dystrybucji pomija wszystkie
katalogi `migrations/` Core i modułów. Zamiast kopiować historyczny SQL, generuje
manifest nazw i SHA-256 migracji już zawartych w aktualnych plikach `install.sql`;
kreator zapisuje ten stan w `core_migrations` i `module_migrations`, dzięki czemu
przyszłe aktualizacje nie próbują uruchamiać starych zmian ponownie.

**Weryfikacja:** dodano test bieżącego schematu Core, braku katalogów migracji
w dystrybucji oraz obecności manifestu stanu bazowego; uruchomiono testy
repozytorium, lint PHP, przebudowę czystej dystrybucji i kontrolę diffu.

### Sesja: 2026-06-24 - Zapisywalna konfiguracja Econizer w kreatorze

**Faza i krok specyfikacji:** Krok 7 - kreator instalacji i atomowy zapis
konfiguracji; Krok 8 - izolowany plik środowiska Econizer.

**Wykonano:** instalator zapisuje sekrety Econizer w chronionym
`config/modules/econizer.env` zamiast w katalogu kodu `modules/Econizer`.
`config/modules` jest wymaganym katalogiem zapisywalnym i powstaje w czystej
dystrybucji. Loader preferuje nową lokalizację, zachowuje zgodność ze starszym
`modules/Econizer/.env` i nadal pozwala jawnie użyć `ECONIZER_ENV_FILE`.

**Weryfikacja:** zaktualizowano test integracyjny instalatora i test zawartości
dystrybucji; uruchomiono pełne testy, lint PHP, generator pakietu oraz kontrolę
uprawnień i diffu.

### Sesja: 2026-06-24 - Ekran konta oczekującego po pierwszym OAuth

**Faza i krok specyfikacji:** Krok 5B - tworzenie i akceptacja kont oczekujących.

**Wykonano:** `core_auth` 1.5.1 dodaje trasę `/admin/account-pending` z informacją,
że konto zostało utworzone, ale wymaga aktywacji przez administratora. Callback
OAuth kieruje tam konto `pending`, gdy docelowym adresem był panel, zachowując
jednocześnie powrót do dozwolonych funkcji publicznych. Ręczne wejście takiego
użytkownika na dashboard również przekierowuje przez `system_admin` 1.8.1 na ekran
oczekiwania zamiast pokazywać ogólny błąd 403.

**Weryfikacja:** dodano kontrole manifestu, trasy i komunikatu; uruchomiono pełne
testy, lint PHP, przebudowę czystej dystrybucji i kontrolę diffu.

### Sesja: 2026-06-24 - Zgodność historycznej instancji bez installed.lock

**Faza i krok specyfikacji:** Krok 7 - blokada ponownej instalacji i zgodność
istniejących wdrożeń.

**Wykonano:** wykrywanie instalacji nie opiera się już wyłącznie na
`config/installed.lock`. Wspólny `InstallationState` rozpoznaje także poprawną
konfigurację bazodanową z jawnego `MINIPORTAL_ENV_FILE`, lokalnego
`config/installed.env` albo zgodnościowego `/etc/miniportal/miniportal.env`.
Przywrócono kolejność źródeł konfiguracji używaną przez instancję, na której
projekt był rozwijany. Wymóg zapisu `config/modules` pozostał częścią preflightu
kreatora, ale nie blokuje uruchamiania już działającego portalu.

**Weryfikacja:** dodano test instancji bez blokady korzystającej z zewnętrznego
pliku środowiska; uruchomiono pełne testy, lint PHP, przebudowę dystrybucji
i kontrolę diffu.

### Sesja: 2026-06-24 - Dystrybucyjne aktualizacje modułów chronionych

**Faza i krok specyfikacji:** Krok 6 - system modułów, aktualizacja i dystrybucja
pakietów; Krok 7 - czysta dystrybucja instalacyjna.

**Wykonano:** manager modułów eksportuje teraz każdy zainstalowany pakiet, również
chronione moduły `core` i `system`. Import wyższej wersji istniejącego modułu
wymaga zachowania identyfikatora, katalogu, typu, ochrony, autora i pochodzenia.
Rozszerzenia nadal wymagają podpisu zaufanego wydawcy, a chronione pakiety
wbudowane korzystają z kontrolowanego kanału `origin.type=bundled`.

Zatwierdzenie aktualizacji tworzy kopię poprzedniego katalogu, atomowo podmienia
kod i od razu uruchamia standardowy preflight oraz migracje `ModuleInstaller`.
Błąd walidacji lub aktualizacji przywraca poprzednią wersję plików. Standardowe
ograniczenie MySQL/MariaDB pozostaje jawne: wykonane DDL może zostać automatycznie
zatwierdzone i nie jest obiecywany rollback bazy. Ochrona modułów przed wyłączeniem
i odinstalowaniem nie została osłabiona. `system_admin` podniesiono do 1.9.0.

**Weryfikacja:** dodano test poprawnej aktualizacji chronionego pakietu i rollbacku
kodu po symulowanym błędzie migracji. Uruchomiono dwukrotnie pełne
`php tests/run.php`, pełny lint PHP, `node --check`, `php bin/migrate-core.php`,
`git diff --check` oraz przebudowę `install/cms`. Wygenerowana dystrybucja zawiera
`system_admin` 1.9.0 i nowy przepływ importu chronionych aktualizacji.

### Sesja: 2026-06-24 - Bezpieczny eksport konfiguracji przykładowej modułu

**Faza i krok specyfikacji:** Krok 6 - system modułów, eksport pakietów; Krok 8 -
izolowana konfiguracja Econizer.

**Wykonano:** naprawiono eksport Econizera blokowany przez `.env.example`.
Eksporter pomija teraz lokalny `.env` bez umieszczania sekretów w archiwum,
dopuszcza wyłącznie bezpieczny plik wzorcowy `.env.example` i nadal odrzuca
pozostałe ukryte pliki, ukryte katalogi, znaki kontrolne oraz dowiązania
symboliczne. Importer i weryfikator podpisów stosują tę samą regułę, dzięki czemu
wyeksportowany pakiet może przejść pełny import do kwarantanny.

**Weryfikacja:** wykonano rzeczywisty eksport `econizer` 1.3.1 i sprawdzono listę
ZIP: zawiera `Econizer/.env.example`, ale nie zawiera `Econizer/.env`. Dodano test
regresji eksportu i ponownego importu, uruchomiono pełne `php tests/run.php`, lint
zmienionych plików, `git diff --check` oraz przebudowę `install/cms`.

### Sesja: 2026-06-24 - Katalog wydań i aktualizator platformy 0.2.0

**Faza i krok specyfikacji:** Krok 6 - lifecycle modułów chronionych; Krok 7 -
dystrybucja, wersjonowanie i aktualizacja istniejącej instalacji.

**Wykonano:** oddzielono aktualizację całego miniPORTALu od managera modułów.
Dodano chroniony katalog `releases/`, walidowany `catalog.json`, generator
`bin/build-platform-release.php`, manifest plików i archiwum wydania
`miniportal-0.2.0.zip`. Wersja aplikacji została podniesiona do 0.2.0, a
`system_admin` do 2.0.0.

Dashboard pokazuje dostępność nowszego zgodnego wydania. Nowy panel
`/admin/system-updates` prezentuje bieżącą wersję, wymaganie wersji bazowej,
historię wydań i listę zmian. Aktualizacja wymaga ACL `settings.manage`, CSRF i
jest audytowana. Proces sprawdza SHA-256 archiwum i wszystkich plików, rozpakowuje
pakiet do stagingu, wykonuje backup, podmienia wyłącznie kontrolowany runtime,
uruchamia migracje Core i aktualizacje zainstalowanych modułów, a przy błędzie
przywraca poprzedni kod.

Pakiet nie zawiera bazy, uploadów, cache, `config/installed.env`,
`config/installed.lock`, `config/modules/` ani lokalnych `.env`. Czysta
dystrybucja ma pusty, chroniony katalog wydań i zapisywalny
`cache/platform-updates`. Kanał może działać lokalnie albo pobierać centralny
`catalog.json` i ZIP przez skonfigurowany HTTPS bez przekierowań; archiwum trafia
do chronionego cache i przed użyciem musi odpowiadać SHA-256 katalogu.

**Weryfikacja:** dodano test wyboru zgodnej wersji oraz test zachowania lokalnych
danych i rollbacku plików. Wykonano pełne testy repozytorium, pełny lint PHP,
`node --check`, `git diff --check`, przebudowę `install/cms` oraz rzeczywisty smoke
test aktualizacji kopii czystej instalacji z 0.1.0 do 0.2.0. Zaktualizowano 275
plików, zachowując `config/installed.env` i plik użytkownika w `uploads/branding`.

### Sesja: 2026-06-24 - Automatyczny podpis eksportowanych modułów

**Faza i krok specyfikacji:** Krok 6 - system modułów, pochodzenie, podpisy
RSA-SHA256 oraz dystrybucja aktualizacji pomiędzy instalacjami.

**Wykonano:** dodano `ModulePackageSigner` jako wspólną implementację podpisu dla
panelu i CLI. Po skonfigurowaniu `MODULE_SIGNING_*` akcja `Eksportuj ZIP`
automatycznie tworzy kopię roboczą modułu, dodaje do niej deklarację i dokument
podpisu, pakuje ZIP, a następnie usuwa staging. Źródłowy `info.json` i katalog
modułu nie są zmieniane, `.env` nadal nie trafia do archiwum, a klucz prywatny
pozostaje poza projektem.

Dodano `bin/setup-module-signing.php`, który generuje parę RSA 4096 i wypisuje
gotowe zmienne środowiskowe. `bin/sign-module.php` pozostaje dostępny dla CI oraz
ręcznych wydań, ale używa tej samej klasy co panel. Instalacja odbierająca pakiet
potrzebuje wyłącznie zgodnego identyfikatora i klucza publicznego.

**Weryfikacja:** dodano test automatycznie podpisanego eksportu, ponownego importu
ze statusem `verified` oraz braku zmian w źródłowym module. Uruchomiono lint
zmienionych plików PHP, pełne testy repozytorium i `git diff --check`.

### Sesja: 2026-06-24 - Kotwica zaufania w czystym instalatorze

**Faza i krok specyfikacji:** Krok 6 - pochodzenie i podpisy pakietów; Krok 7 -
bezpieczna czysta dystrybucja oraz preflight kreatora.

**Wykonano:** oficjalny publiczny klucz `syntaxdevteam-modules-2026` dodano do
wersjonowanego `config/keys/` i aktywnego rejestru `module_publishers.php`.
Generator czystej dystrybucji przenosi klucz razem z konfiguracją, więc nowa
instalacja zna wydawcę przed pierwszym importem modułu. Kreator sprawdza, czy klucz
jest czytelny i poprawnie parsowany przez OpenSSL; brak albo podmiana na błędny PEM
blokuje rozpoczęcie instalacji jako niespełnione wymaganie środowiska.

Klucz prywatny nadal istnieje wyłącznie poza projektem macierzystym i służy do
automatycznego podpisywania eksportów. Archiwum modułu nie może dostarczyć własnej
kotwicy zaufania ani zastąpić klucza publicznego instalacji.

**Weryfikacja:** rozszerzono test czystej dystrybucji o obecność klucza i wpisu
wydawcy, dodano test kryptograficznej poprawności PEM oraz preflight instalatora,
przebudowano `install/cms`, uruchomiono pełne testy i lint PHP.

### Sesja: 2026-06-24 - Utwardzenie produkcyjnego lifecycle modułów

**Faza i krok specyfikacji:** Krok 6 - dystrybucja, kwarantanna i lifecycle
modułów; Krok 7 - wymagania czystej instalacji.

**Wykonano:** eksport modułów ograniczono do instancji wydawniczej posiadającej
skonfigurowany prywatny klucz. Na zwykłej instalacji produkcyjnej przycisk nie jest
renderowany, a bezpośrednie żądanie endpointu zostaje odrzucone i audytowane.

Każdy import ma akcję trwałego usunięcia z kwarantanny. Dodano także audytowane
czyszczenie wpisów starszych niż 1-365 dni, domyślnie 7 dni przez
`MODULE_QUARANTINE_RETENTION_DAYS`. Operacje zachowują ACL `modules.install`,
CSRF i walidację identyfikatora katalogu importu.

Manager modułów pokazuje przed zatwierdzeniem, że nadrzędny `modules/` nie jest
zapisywalny, wraz z poleceniami `chgrp`, `chmod 2775` i testem jako `www-data`.
Ten sam katalog dołączono do preflight czystego instalatora, aby błąd nie pojawiał
się dopiero podczas pierwszej aktualizacji. `system_admin` podniesiono do 2.0.1.

**Weryfikacja:** dodano test indywidualnego usuwania i retencji kwarantanny,
kontrole blokady eksportu oraz instrukcji uprawnień, przebudowano czystą
dystrybucję, uruchomiono pełne testy, pełny lint PHP i `git diff --check`.

### Sesja: 2026-06-24 - Publikowanie release z panelu Ownera

**Faza i krok specyfikacji:** Krok 7 - dystrybucja i katalog wydań platformy.

**Wykonano:** instalacja macierzysta pokazuje Ownerowi w
`/admin/system-updates` formularz budowy własnego wydania. Formularz przyjmuje
wersję SemVer, minimalną obsługiwaną wersję bazową i listę zmian po jednej pozycji
w wierszu. Może przebudować bieżącą wersję albo opublikować wyższą.

Panel korzysta z tej samej implementacji co CLI:
`bin/build-platform-release.php`. Przed budową aktualizuje wersję w
`config/config.php` i źródle instalatora; przy błędzie przywraca poprzednie pliki.
Operacja wymaga wildcarda Ownera, CSRF, jest audytowana i pozostaje niewidoczna w
czystej dystrybucji, z której generator jest usuwany.

**Weryfikacja:** dodano test delegowania do generatora, walidacji wersji bazowej
i trwałej aktualizacji wersji źródłowej po sukcesie. Uruchomiono pełne testy,
lint zmienionych plików i `git diff --check`.

### Sesja: 2026-06-24 - Poprawka panelowego generatora release

**Faza i krok specyfikacji:** Krok 7 - panel publikacji wydań platformy.

**Wykonano:** usunięto zależność publikatora od wartości `PHP_BINARY` dostępnej
w PHP-FPM. Pusta wartość prowadziła do wykonania pustej komendy przez `/bin/sh`
i błędu `sh: 1: : Permission denied`. Publikator wybiera teraz jawnie wykonywalne
PHP CLI i przekazuje `proc_open()` tablicę argumentów bez udziału powłoki.

Przycisk formularza nie zakłada już wersji przed zatwierdzeniem pól. Neutralny
tekst `Zbuduj wskazany release` odpowiada numerowi wpisanemu w polu
`Publikowana wersja`, a komunikat wyjaśnia możliwość przebudowy bieżącej wersji.

**Weryfikacja:** lint zmienionych plików, `git diff --check` oraz rzeczywiste
zbudowanie wydania 0.2.0 przez `PlatformReleasePublisher` uruchomiony jako
użytkownik `www-data`.

### Sesja: 2026-06-24 - Centralny kanał wydań 0.2.2

**Faza i krok specyfikacji:** Krok 7 - dystrybucja wydań pomiędzy niezależnymi
instalacjami miniPORTAL.

**Wykonano:** naprawiono sytuację, w której publiczna instancja z pustym lokalnym
`releases/catalog.json` zgłaszała najnowszą wersję mimo wydań istniejących w
projekcie macierzystym. Dodano read-only endpointy
`/api/platform-releases/catalog` oraz `/api/platform-releases/{filename}`.
Archiwum jest dostępne tylko wtedy, gdy jego nazwa znajduje się w zwalidowanym
katalogu; fizyczny katalog `releases/` nadal zwraca 403.

Instancja Econizera została skonfigurowana przez
`PLATFORM_RELEASE_CATALOG_URL=https://new.syntaxdevteam.pl/api/platform-releases/catalog`.
Kreator nowych instalacji zapisuje oficjalny URL automatycznie. Pusty kanał jest
teraz raportowany jako brak konfiguracji lub brak wydań, nie jako aktualny system.
Zbudowano wydanie miniPORTAL 0.2.2.

**Weryfikacja:** centralny endpoint zwraca wersje 0.2.2, 0.2.1 i 0.2.0.
Repozytorium aktualizacji Econizera wykrywa przejście 0.2.0 → 0.2.2. Pobrany przez
HTTPS ZIP 0.2.2 ma SHA-256 zgodne z katalogiem.

### Sesja: 2026-06-25 - Preflight praw aktualizacji platformy 0.2.3

**Faza i krok specyfikacji:** Krok 7 - bezpieczna aktualizacja istniejącej
instalacji.

**Wykonano:** instancja Econizera nie mogła podmienić `.htaccess`, ponieważ plik
i katalog główny nie były zapisywalne dla `www-data`. Atomowa podmiana tworzy
plik tymczasowy obok celu, dlatego samo prawo zapisu do pliku nie wystarcza.
Przygotowano grupowe prawa zapisu dla katalogu głównego i zarządzanego runtime,
bez zmiany `config/installed.env`, `config/installed.lock`, `config/modules/`,
uploadów ani danych.

Dodano `FilesystemPermissions::platformUpdateIssues()` i kompletne polecenie
naprawcze. Panel blokuje przycisk aktualizacji i pokazuje wszystkie wykryte
problemy przed podmianą. Kreator sprawdza gotowość runtime do przyszłych
aktualizacji. `system_admin` podniesiono do 2.0.3.

**Weryfikacja:** wykonano aktualizację produkcyjnego Econizera jako `www-data`
z 0.2.0 do 0.2.2: podmieniono 279 plików, zapisano backup, zaktualizowano
`system_admin` i uzyskano HTTP 200. Dodano test preflightu oraz uruchomiono pełne
testy repozytorium i lint zmienionych plików.
