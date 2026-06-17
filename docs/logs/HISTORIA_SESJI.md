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
