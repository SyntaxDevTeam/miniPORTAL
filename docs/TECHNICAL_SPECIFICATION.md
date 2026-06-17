# SPECYFIKACJA TECHNICZNA I PLAN ROZWOJU: miniPORTAL

## 1. Cel projektu

miniPORTAL to modułowy system typu mini-CMS, zbudowany w czystym PHP 8.4 lub nowszym bez frameworków, zgodny z zasadą Outside-In: najpierw tworzymy i testujemy wizualne abstrakcje frontendowe, a dopiero potem implementujemy logikę backendową i mechanizmy systemowe.

Główne założenia projektu:
- modularna architektura „Lego”
- oddzielenie warstwy prezentacji od logiki aplikacyjnej
- pełna kontrola nad kodem i konfiguracją
- bezpieczeństwo jako element projektowy, nie dodatku
- skalowalność poprzez moduły, nie przez „monolit”

---

## 2. Architektura systemu i struktura katalogów

Projekt opiera się na logicznym podziale na trzy warstwy:
1. Core – niezmienny rdzeń systemu
2. Modules – dynamiczne moduły „klocków Lego”
3. Templates – warstwa prezentacji i szablonów

Proponowana struktura katalogów:

```text
miniPORTAL/
├── config/                  # Globalna konfiguracja (baza danych, ścieżki, klucze)
│   └── config.php
├── core/                    # Rdzeń systemu (Engine)
│   ├── Bootstrap.php        # Inicjalizacja systemu, sesji, bezpieczeństwa
│   ├── database/
│   │   └── CrudApp.class.php # Główna fasada CRUD oparta na Medoo
│   ├── Router.php           # Proste trasowanie URL
│   ├── Request.php          # Filtrowany i normalizowany dostęp do żądania
│   ├── Security.php         # Filtrowanie, CSRF, XSS, sesje
│   ├── ModuleInterface.php  # Kontrakt rejestracji tras, menu i uprawnień
│   ├── AdminMenuRegistry.php # Menu panelu filtrowane przez ACL
│   └── ThemeEngine.php      # Menedżer warstw szablonu
├── modules/                 # Moduły systemu
│   ├── core_pages/          # Stały moduł: strony statyczne
│   ├── CoreAuth/            # Stały moduł: logowanie i uprawnienia
│   └── articles/            # Przykładowy moduł rozszerzeń
│       ├── info.json
│       ├── install.sql
│       ├── Admin.php
│       └── Site.php
├── templates/               # Szablony i warstwa prezentacji
│   └── default/
│       ├── theme.php
│       ├── assets/
│       └── views/
├── cache/                   # Skompilowane elementy i cache wyników
└── index.php                # Punkt wejścia Front Controller
```

### Zasady architektoniczne
- PSR-4 autoloader dla klas z katalogów core/ i modules/
- każda warstwa ma jasno określone zadania
- moduły nie powinny zależeć od konkretnej implementacji szablonu
- szablony są wymienialne bez zmian w logice modułów
- rdzeń korzysta z `CrudApp` jako preferowanej warstwy pośredniczącej nad Medoo; bezpośredni dostęp do Medoo jest ograniczony do tej fasady i uzasadnionych operacji specjalistycznych
- moduł rejestruje trasy i pozycje menu przez kontrakty Core, ale nie generuje HTML
- `ModuleRegistry` jest pojedynczym punktem rejestracji i uruchamiania modułów;
  ręczne wywołania `registerRoutes()` i `registerAdminMenu()` poza rejestrem są zabronione
- `ThemeInterface` udostępnia komponenty ogólne, a nie metody nazwane według modułów;
  moduły składają widoki z layoutu, formularzy, alertów i tabel akcji
- widoczność pozycji panelu wynika z lokalnych uprawnień przekazanych do `AdminMenuRegistry`
- `CoreAuth` przechowuje konto lokalnie, a dostawców GitHub, Discord i Google traktuje
  jako zewnętrzne tożsamości przypięte przez parę `(provider, provider_subject)`
- testowe repozytorium pamięciowe może działać wyłącznie po jawnym ustawieniu
  `AUTH_DEMO_ENABLED=1`; konfiguracja publiczna używa `AUTH_STORAGE=database`

---

## 3. Model separacji prezentacji (Template Interface)

Aby w pełni oddzielić logikę PHP od HTML/CSS/Bootstrap 5, należy zastosować wzorzec interfejsu szablonu. Każdy szablon musi implementować określony zestaw metod renderujących komponenty wizualne.

### Założenie
Moduły nie powinny „wiedzieć”, jak dokładnie wygląda nagłówek, tabela czy karta. Powinny wywoływać jedynie abstrakcyjne metody szablonu.

### Przykładowa koncepcja klasy szablonu

```php
class Theme implements ThemeInterface
{
    public static function start_header(string $cssClass = ''): void
    {
        echo '<div class="container my-4">';
        echo '<header class="pb-3 mb-4 border-bottom d-flex justify-content-between align-items-center ' . htmlspecialchars($cssClass) . '">';
    }

    public static function end_header(): void
    {
        echo '</header>';
        echo '</div>';
    }

    public static function render_table_row(array $data): void
    {
        echo '<tr>';
        foreach ($data as $cell) {
            echo '<td class="align-middle">' . htmlspecialchars((string) $cell) . '</td>';
        }
        echo '</tr>';
    }
}
```

### Zasada użycia w module

```php
Theme::start_header('text-primary');
echo '<h1>' . htmlspecialchars($article['title']) . '</h1>';
Theme::end_header();
```

### Korzyść architektoniczna
Zmiana motywu z Bootstrap na Tailwind lub inny system UI wymaga modyfikacji tylko pliku szablonu. Logika modułu pozostaje bez zmian.

---

## 4. Podejście Outside-In (od frontend do backendu)

### Faza 1: Prototypowanie wizualne (Frontend-First)

1. Utworzenie jednego centralnego pliku HTML jako „żywego repozytorium komponentów”.
2. Implementacja komponentów wizualnych:
   - nawigacja
   - stopka
   - karty artykułów
   - tabele
   - formularze logowania
   - komunikaty sukcesu / błędów
   - animacje wejścia i przejść
3. Opracowanie wersji 1 strony głównej dla SyntaxDevTeam.pl na bazie tych komponentów.

Cel tej fazy:
- zobaczyć finalny efekt wizualny przed napisaniem logiki PHP
- ustalić standard UX/UI jako fundament dla dalszego rozwoju

Stan integracji:
- aktywna trasa `/` odwzorowuje `templates/default/homepage.html` przez `ThemeInterface`,
- opublikowane treści `core_pages` pojawiają się dynamicznie na stronie głównej,
- prototypy pozostają referencyjnymi źródłami wyglądu dostępnymi z panelu pod
  `/admin/design-system`.

### Faza 2: Abstrakcja szablonu do PHP

1. Definicja interfejsu ThemeInterface.
2. Przeniesienie komponentów HTML z prototypu do metod klasy Theme.
3. Wprowadzenie metod typu:
   - start_card(), end_card()
   - render_button()
   - render_alert()
   - render_form()
   - render_table()
4. Oddzielenie „układu” od „treści” w module.

### Faza 3: Rdzeń systemu i bezpieczeństwo

1. Implementacja autoloadera PSR-4.
2. Integracja `CrudApp` jako warstwy pośredniczącej nad Medoo/PDO.
3. Bezpieczne przygotowanie zapytań (Prepared Statements).
4. Wprowadzenie komponentu Security:
   - filtrowanie danych wejściowych
   - walidacja i normalizacja
   - tokeny CSRF
   - zabezpieczenie sesji
   - nagłówki CSP, HSTS, frame protection i polityka uprawnień

5. Moduły otrzymują dane wejściowe wyłącznie przez obiekt `Request`; bezpośredni dostęp
   do `$_GET`, `$_POST` i `$_SERVER` pozostaje odpowiedzialnością warstwy Core.

### Faza 4: Stałe moduły rdzenia

#### 4.1 Panel administracyjny i moduł użytkowników

Szczegółowy plan znajduje się w `docs/ADMIN_PANEL_PLAN.md`.

- prototyp panelu zgodny z Outside-In
- wspólny model użytkownika i wielu zewnętrznych tożsamości
- logowanie GitHub, Discord i Google przez adaptery dostawców
- lokalne role i uprawnienia niezależne od dostawcy logowania
- konta oczekujące tworzone po pierwszej zweryfikowanej tożsamości i akceptowane
  przez administratora
- wiele ról na użytkownika oraz edytor mapowania ról do uprawnień
- sesje administratora, ochrona tras i audit log
- opcjonalne konto lokalne Argon2id wyłącznie jako mechanizm awaryjny

Aktualny kontrakt `CoreAuth` składa się z modeli `User` i `ExternalIdentity`,
repozytorium użytkowników, `AuthService`, `AuthorizationService` oraz
`AdminAccessGate`. Schemat SQL znajduje się w `modules/CoreAuth/install.sql`,
a dostęp produkcyjny do danych przechodzi przez `CrudAppUserRepository`. Dostawcy
tożsamości implementują `IdentityProviderInterface` i są rejestrowani przez
`IdentityProviderRegistry`; pierwszą implementacją jest adapter GitHub z ochroną
`state` i PKCE. Adapter Discord używa Authorization Code oraz zakresów
`identify email`. Pierwszy administrator jest tworzony kontrolowaną komendą CLI,
która zapisuje stały identyfikator dostawcy zamiast łączyć konto po e-mailu.
Google używa OpenID Connect z lokalną walidacją podpisu ID tokenu, `nonce`,
issuer, audience i czasu ważności. Łączenie providerów wymaga aktywnej sesji,
a operacje uwierzytelniania i ACL trafiają do `auth_events`. Próby rozpoczęcia
i callbacku OAuth są ograniczane osobno dla każdego providera i sesji.
Nieznana tożsamość tworzy nieaktywne konto `pending` z domyślną rolą `user`.
Aktywacja jest decyzją lokalnego administratora; system nadal nie łączy kont
automatycznie po zgodnym adresie e-mail.

#### 4.2 Moduł stron statycznych
- CRUD dla stron przez `CrudApp`
- unikalny slug i publiczna trasa opublikowanej strony
- stany `draft` i `published` oraz granularne uprawnienia `pages.*`
- typy dokumentów `standard`, `project` i `legal`
- skrót do kart, opis SEO, kolejność oraz publikacja w menu głównym lub stopce
- publiczne adresy `/p/slug` i katalog `/pages`
- opcjonalne powiązanie karty strony głównej z konkretną podstroną
- edytowalne sekcje strony głównej: typ, nagłówki, treść, układ, widoczność i kolejność
- sekcje kolumnowe składają się z niezależnych elementów/kart z etykietą, opisem,
  CTA, wariantem wizualnym i szerokością
- lokalny edytor z przełącznikiem WYSIWYG / Markdown i jawnym formatem źródłowym rekordu
- Markdown w stylu GitHub: nagłówki, listy, zadania, tabele, cytaty, linki,
  obrazy, kod liniowy i bloki kodu; surowy HTML jest kodowany
- renderowanie sekcji przez `ThemeInterface`, bez HTML i klas CSS w module `core_pages`
- wspólny renderer bezpiecznej treści dla sekcji homepage, kart, podstron i artykułów
- wspólny publiczny branding motywu oraz edytowalne nadtytuły podstron
- układ kontaktowy homepage z deklaratywnymi kanałami, osobami i ikonami
- podgląd roboczy obejmujący również ukryte sekcje i elementy
- autozapis formularzy treści do `localStorage`, oferowany do ręcznego przywrócenia
  bez nadpisywania danych z bazy i czyszczony po potwierdzonym zapisie

#### 4.3 Moduł dokumentacji projektowej `wikipedia`
- niezależne rozszerzenie do tworzenia bazy wiedzy dla projektów,
- projekty dokumentacji mają własny slug, opis, kolejność i status publikacji,
- strony dokumentacji są przypisane do projektu, mają osobny slug w obrębie projektu,
  opis, treść `html`/`markdown`, kolejność i status publikacji,
- publiczne trasy `/wiki`, `/wiki/project/{slug}` i `/wiki/page/{project}/{slug}`
  pokazują wyłącznie opublikowane projekty i strony; starsze wejścia z query string
  pozostają kompatybilne, ale linki generowane przez CMS używają przyjaznych adresów,
- publiczna strona dokumentacji pokazuje na dole ogólny komponent nawigacji treści
  z poprzednią stroną, spisem projektu i następną stroną wraz z rzeczywistymi tytułami,
- panel `/admin/wikipedia` udostępnia CRUD projektów oraz stron przez `CrudApp`,
  CSRF, ACL `wikipedia.*`, audit log i komponenty `ThemeInterface`,
- breadcrumb formularzy Wiki pokazuje kontekst: panel, dokumentację, projekt,
  edytowaną stronę i bieżącą akcję,
- aktywne moduły mogą deklarować publiczne linki przez `PublicNavigationRegistry`,
  a `/admin/settings` pozwala zmienić ich etykietę oraz niezależnie przypisać je do
  głównego menu, stopki, obu obszarów albo ukryć,
- `ThemeInterface::set_public_navigation()` przekazuje wspólne menu i stopkę do
  wszystkich publicznych widoków modułów, nie tylko do strony głównej,
- publiczne widoki poza stroną główną pokazują wspólne pozycje `Home`, `Kontakt`,
  linki modułów przypięte w ustawieniach oraz przycisk logowania albo panelu,
- Front Controller renderuje publiczne 404 i 405 przez komponent motywu zamiast
  technicznego alertu z odnośnikiem do dashboardu,
- moduły `wikipedia` i `articles` deklarują publiczne linki startowe do `/wiki` oraz
  `/articles`, które administrator konfiguruje przez ten sam panel,
- moduł jest opcjonalny, instalowany przez manager modułów i nie rozszerza
  kontraktu motywu metodami specyficznymi dla dokumentacji.

### Faza 5: Manager modułów (Lego System)

1. Manager skanuje katalog /modules/.
2. Odczytuje plik info.json:
   - nazwa modułu
   - wersja
   - autor
   - wymagania
3. Instalacja modułu:
   - wykonanie install.sql
   - zapis statusu do tabeli modules_config
4. Dynamiczne ładowanie modułów:
   - router sprawdza, czy moduł jest aktywny
   - tylko aktywne moduły są uruchamiane

Stan managera:
- `modules_config` przechowuje wersję, stan
  `discovered/active/disabled/uninstalled`, ochronę i informację o zachowanych danych,
- `module_migrations` przechowuje wykonaną migrację wraz z sumą SHA-256,
- manager pokazuje historię wykonania, zapisany i aktualny SHA-256 oraz stan
  `Zgodna`, `Zmieniona` albo `Brak pliku`,
- aktualny `install.sql` stanowi pełny schemat nowej instalacji, więc istniejące
  migracje są przy instalacji oznaczane jako stan bazowy; każda zmiana schematu
  z `migrations/*.sql` musi być równolegle scalona do `install.sql`,
- aktualizacja wymaga wersji manifestu wyższej od zapisanej, sprawdza sumy wszystkich
  historycznych migracji przed pierwszym DDL i dopiero po powodzeniu zapisuje wersję,
- odinstalowanie wymaga wcześniejszego wyłączenia modułu i pozwala zachować dane albo
  wykonać jawny `uninstall.sql`; zachowane dane można następnie przywrócić,
- wyłączenie rozszerzenia usuwa jego trasy i menu od następnego żądania,
- zależności aktywnych modułów oraz moduły chronione nie mogą zostać wyłączone ani
  odinstalowane,
- manifest bez deklaratywnej fabryki w `config/modules.php` jest widoczny, ale jego
  operacje wykonawcze są blokowane; rozszerzenie może zamiast tego jawnie zadeklarować
  własny plik `factory.php`,
- fabryka pakietu nie jest wykonywana podczas skanowania; przycisk instalacji wymaga
  świadomego potwierdzenia administratora, a kod fabryki jest ładowany dopiero dla
  modułu zainstalowanego i aktywnego,
- błąd pojedynczego pakietu jest izolowany w managerze i prezentowany jako
  „Błąd pakietu”; nie przerywa dashboardu ani obsługi pozostałych modułów,
- opcjonalny moduł z `config/modules.php` o niezgodnym manifeście jest pomijany bez
  uruchamiania fabryki; wyłącznie definicje Core z `required: true` zatrzymują start,
- `/admin/modules` wymaga ACL, CSRF i zapisuje wynik operacji do audit logu.
- zewnętrzny pakiet z własną fabryką wymaga jawnego `origin`, mapy SHA-256 wszystkich
  plików i podpisu RSA-SHA256 zweryfikowanego kluczem z lokalnego rejestru wydawców,
- podpis zawiera `signed_at`; rejestr rozróżnia klucze aktywne, wycofane po rotacji
  i unieważnione, a okres ważności jest sprawdzany względem czasu podpisania,
- klucz prywatny wydawcy pozostaje poza repozytorium i serwerem WWW; zmiana dowolnego
  pliku unieważnia pakiet przed instalacją albo uruchomieniem.
- import archiwum modułu przyjmuje `.tar`, `.tar.gz`, `.tgz` oraz `.zip`, rozpakowuje
  pakiet wyłącznie do `cache/module-quarantine`, waliduje manifest i podpis bez
  wykonywania fabryki oraz bez kopiowania plików do aktywnego katalogu `modules/`.
- manager pozwala eksportować zainstalowane moduły typu `extension` do ZIP z jednym
  top-level katalogiem pakietu; eksport wymaga ACL/CSRF, jest audytowany i blokuje
  dowiązania symboliczne oraz ukryte segmenty ścieżek.
- dashboard panelu pokazuje syntetyczne metryki modułów, rozszerzeń, migracji,
  aktywności dziennej, sygnały operacyjne i ostatnie zdarzenia audit logu.
- `ThemeInterface` udostępnia responsywną siatkę paneli administracyjnych, aby
  Dashboard i Ustawienia nie składały krótkich informacji jako pełnoszerokich bloków.

---

## 5. Bezpieczeństwo, wydajność i standardy jakości

### 5.1 Security-by-Design

- XSS: wszystkie dane tekstowe wyświetlane na ekranie powinny być filtrowane przez htmlspecialchars($str, ENT_QUOTES, 'UTF-8')
- CSRF: każda forma formularza powinna otrzymywać ukryty token poprzez Theme::csrf_field()
- HTTP headers:
  - Content-Security-Policy
  - X-Frame-Options: DENY
  - X-Content-Type-Options: nosniff
- bezpieczne zarządzanie sesją i ciasteczkami

### 5.2 Wydajność i optymalizacja

- cache szablonów przez output buffering i `TemplateCacheInterface`,
- zapis statycznych fragmentów do `cache/templates` z atomowym zapisem,
- tagowe unieważnianie zależności `homepage`, `pages`, `page:{slug}`,
  `articles`, `article:{slug}` i `theme`,
- cache wyłącznie dla odpowiedzi niezależnych od sesji; panel i widok administratora
  nie mogą korzystać ze wspólnego wpisu publicznego,
- ograniczenie liczby zapytań do bazy danych
- indeksowanie kolumn takich jak slug, category_id, created_at
- FULLTEXT dla wyszukiwarki, jeśli zostanie zaimplementowana

Pierwszym konsumentem kontraktu jest anonimowa strona główna. Operacje zapisu
`core_pages` unieważniają treść publiczną, publiczne podstrony i artykuły korzystają
z osobnych wpisów cache, zmiana motywu unieważnia tag `theme`, a panel systemowy
pozwala wykonać kontrolowane pełne czyszczenie z audytem.

### 5.3 Propozycje autorskie do przyszłego rozwoju

1. System haków i filtrów (Hooks API)
   - moduł może wstrzyknąć własne zachowanie do innego modułu bez modyfikacji jego źródeł

2. Przyjazne adresy URL (Slug Router)
   - zamiast index.php?module=articles&id=5
   - system ma mapować adresy typu /artykuly/nazwa-artykulu

3. Wbudowany moduł logów (Audit Log) [wdrożony]
   - zapis nieudanych logowań, instalacji modułów i zmian konfiguracyjnych w `auth_events`
   - paginowany, chroniony widok `/admin/logs` bez tokenów i pełnych adresów IP
   - rutynowe poprawne odczyty panelu nie są zdarzeniami audytowymi; zapisywane są
     odmowy dostępu i operacje zmieniające stan
   - polityka retencji archiwizuje starsze wpisy do `auth_events_archive` przed
     usunięciem ich z aktywnego dziennika; operacja wymaga ACL, CSRF i sama trafia
     do audytu

4. Chronione ustawienia systemowe [wdrożone]
   - `SystemAdminModule` zarządza bezpiecznymi ustawieniami motywu i brandingu,
   - sekrety pozostają wyłącznie w pliku środowiskowym poza katalogiem publicznym,
   - panel pokazuje jedynie zredagowany stan konfiguracji bazy, sesji i OAuth,
   - usunięcie motywu wskazanego w bazie uruchamia kontrolowany fallback do konfiguracji
     środowiskowej albo motywu `default`.

---

## 6. Plan wykonawczy – krok po kroku

### Krok 1: przygotowanie fundamentu projektu
1. Utworzenie struktury katalogów opisanej w sekcji 2.
2. Przygotowanie pliku config/config.php.
3. Stworzenie punktu wejścia index.php.

### Krok 2: prototyp wizualny i stylebook
1. Utworzenie pliku templates/default/stylebook.html.
2. Implementacja komponentów Bootstrap 5:
   - navbar
   - cards
   - tables
   - forms
   - buttons
   - alerts
   - footers
3. Dopracowanie CSS i animacji.

### Krok 3: odwzorowanie prototypu w PHP
1. Utworzenie ThemeInterface.
2. Implementacja klasy Theme w templates/default/theme.php.
3. Weryfikacja, że moduły mogą korzystać z abstrakcyjnych metod bez zależności od konkretnego HTML.

### Krok 4: implementacja rdzenia systemu
1. Autoloader
2. Router oraz filtrowany obiekt Request
3. Integracja fasady `CrudApp`/Medoo
4. Security
5. Bootstrap

### Krok 5: wdrożenie pierwszych modułów
1. Prototyp panelu i ekranu logowania.
2. Kontrakt panelu, menu i rejestracji modułów.
3. `core_auth`: użytkownicy, tożsamości, ACL i ochrona tras.
4. Adapter logowania GitHub.
5. Adapter logowania Discord.
6. Adapter logowania Google OpenID Connect.
7. Szkielet panelu: dashboard, profil, użytkownicy, role i audit log.
8. `core_pages`: CRUD stron przez `CrudApp`.
9. `core_pages`: edytor sekcji strony głównej i kontrolowany WYSIWYG.
10. `wikipedia`: projektowa baza wiedzy z projektami i stronami dokumentacji.
11. `articles`: przykład niezależnego modułu.

Stan Kroku 5:

- `core_pages` i `articles` są rzeczywistymi, odrębnymi modułami treści,
- `core_pages` zarządza sekcjami strony głównej, ich kolejnością i układem,
- elementy sekcji są danymi modułu, natomiast siatka, kolory wariantów i wygląd kart
  pozostają wyłączną odpowiedzialnością aktywnego motywu,
- oba rejestrują menu i trasy przez `ModuleRegistry`,
- oba składają panel z ogólnych komponentów `ThemeInterface`,
- oba przechowują źródłową treść wraz z formatem `html` albo `markdown`,
- `articles` dostarcza pierwszy plik `info.json`, który wyznacza wejście do Kroku 6.

### Krok 6: uruchomienie systemu modularnego
1. Stabilizacja `ModuleInterface` i `ModuleRegistry` na podstawie działających modułów.
2. Walidacja `info.json`, zależności i wersji.
3. Instalator oraz migracje bazodanowe.
4. Konfiguracja modułów w `modules_config`.
5. Rejestracja i wyłączanie tras, menu oraz uprawnień.
6. Aktualizacja i odinstalowanie modułu.
7. Ochrona modułów stałych przed wyłączeniem i usunięciem.
8. Audit log wszystkich operacji managera.

Stan fundamentu Kroku 6:
- `ModuleInterface` deklaruje identyfikator, wersję, zależności, ochronę i uprawnienia,
- `ModuleManifestValidator` sprawdza schemat, SemVer, wymagania runtime i plik SQL,
- `ModuleRegistry` porządkuje moduły według zależności i wykrywa cykle,
- `ModuleBootstrapper` ładuje deklaracje z `config/modules.php`,
- rozszerzenia mogą być uruchamiane bez modyfikacji konfiguracji Core przez
  zwracający `callable` plik fabryki wskazany w zweryfikowanym `info.json`,
- Front Controller otrzymuje gotowy rejestr bez znajomości konstruktorów modułów,
- `ModuleStateRepository` i `ModuleInstaller` zapewniają trwały stan i historię SQL,
- `SystemAdminModule` udostępnia dashboard, zasoby systemowe oraz manager instalacji,
  migracji, aktualizacji, aktywacji i odinstalowania, eksport audytu oraz diagnostykę
  cache i kluczy wydawców,
- `install/mod/LearningModule` dokumentuje pełny kontrakt modułu, fabrykę, CRUD przez
  `CrudApp`, ACL, CSRF, migrację i oba warianty odinstalowania,
- `CoreAuthModule` pozostaje właścicielem użytkowników, lokalnych ról i tożsamości;
  zmiana statusu i roli jest wykonywana atomowo przez repozytorium `CrudApp`.

---

## 7. Zasady pracy zespołowej i rozwoju

- każda funkcjonalność powinna być zaprojektowana najpierw jako komponent wizualny
- każda zmiana w szablonie musi być odzwierciedlona w metodach ThemeInterface
- funkcja specyficzna dla jednego modułu nie może rozszerzać `ThemeInterface`;
  moduł składa ją z istniejących komponentów albo proponuje nowy komponent ogólny
- wszystkie zapytania do bazy mają być przygotowane przez warstwę DB
- moduły nie powinny mieć bezpośredniego dostępu do danych $_GET / $_POST bez filtrowania
- każda nowa funkcja jest rozwijana najpierw jako „klocek” modularny

---

## 8. Wniosek i kierunek rozwoju

miniPORTAL powinien zostać zbudowany jako system modularny, bezpieczny, łatwy do utrzymania i gotowy na rozbudowę. Najważniejszym priorytetem jest zbudowanie najpierw spójnego i estetycznego modelu prezentacyjnego, a dopiero potem rozwijanie mechaniki systemu.

To podejście zapewnia:
- przejrzystość architektury
- elastyczność w zmianie motywów i interfejsu
- łatwość rozbudowy o kolejne moduły
- zgodność z nowoczesnymi standardami bezpieczeństwa

---

## 9. Zadanie na dzień pierwszy

1. Utworzyć strukturę katalogów zgodną z sekcją 2.
2. Utworzyć plik templates/default/stylebook.html z podstawowymi komponentami Bootstrap 5.
3. Dopracować wygląd dokumentacji wizualnej i komponentów.
4. Dopiero po tym rozpocząć implementację klasy Theme.php oraz rdzenia systemu.
