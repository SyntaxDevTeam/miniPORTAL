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

#### 4.4 Moduł narzędziowy `plugin_translator`
- niezależne rozszerzenie do tłumaczenia plików YAML używanych przez pluginy
  SyntaxDevTeam oraz zarządzania zatwierdzonymi plikami językowymi,
- `plugin_translation_projects` przechowuje kategorie tłumaczeń dla pluginów,
  botów i innych projektów z nazwą, slugiem,
  opcjonalnym `page_id` do opublikowanej `core_pages` i widocznością; każde
  zgłoszenie wskazuje `project_id`,
- zgłoszenie przechowuje opcjonalną wersję pluginu oraz rodzaj `editor` albo
  `completed_upload`,
- publiczna strona `/translations` przyjmuje upload `.yml/.yaml` metodą
  przeciągnij/upuść; upload przechodzi wyłącznie przez `Request::file()`,
- formularz startowy wymaga wyboru języka docelowego z listy kodów ISO 639-1 w
  formacie `XX`, a wybrany kod jest zapisywany przy zgłoszeniu,
- parser i eksporter korzystają z lokalnej biblioteki `core/libs/Spyc.php`,
- struktura YAML jest spłaszczana do pól tłumaczenia w modelu
  `kategoria -> klucz -> treść`, z obsługą głębszych zagnieżdżeń przez ścieżki,
- edytor pokazuje `Oryginał` i `Twoje tłumaczenie` w jednym oknie, wyrównując
  każdą linijkę tekstu z odpowiadającym jej polem formularza,
- użytkownik może zapisać tłumaczenie jako `draft` albo `ready_for_review`;
  domyślnym statusem jest `draft`, a gotowe zgłoszenie wymaga uzupełnienia
  wszystkich pozycji,
- zgłoszenia są przechowywane w `plugin_translation_submissions` wraz z autorem,
  źródłowym YAML, językiem docelowym, wartościami tłumaczenia, wygenerowanym YAML,
  postępem i statusem,
- statusy robocze rozróżniają `draft`, `ready_for_review`, `approved` i `rejected`,
- wprowadzanie tłumaczeń i zapis wymagają zalogowanego użytkownika; próba zapisu
  bez sesji odkłada źródło, język i dotychczasowe pola tłumaczenia w sesji oraz
  kieruje do OAuth z powrotem przez `/translations/resume`,
- publiczne centrum `/translations` ma trzy zakładki: rozpoczęcie tłumaczenia,
  własne wersje robocze oraz wysłanie gotowego pliku; starsze adresy
  `/translations/mine` i `/translations/upload-ready` pozostają wejściami
  kompatybilnymi do odpowiednich zakładek,
- zakładka wersji roboczych pozwala wznowić szkice, zgłoszenia gotowe do sprawdzenia
  oraz prace odrzucone, a zakładka uploadu przyjmuje poprawny składniowo YAML i
  tworzy zgłoszenie `ready_for_review`,
- pełnoszeroki katalog pod centrum pracy grupuje zaakceptowane pliki według
  kategorii, języka i opcjonalnej wersji; nazwa kategorii jest linkiem do
  `/translations/project`,
- zaakceptowany plik można pobrać albo otworzyć przez `Zaproponuj poprawkę`;
  operacja tworzy nowy szkic użytkownika i nigdy nie modyfikuje zatwierdzonego
  zgłoszenia,
- konto lokalne w statusie `pending` może pracować nad publicznymi tłumaczeniami,
  ale nie dostaje uprawnień panelu administracyjnego,
- akcja `Sprawdź formatowanie` renderuje bez zapisu podgląd HTML dla kodów
  Minecraft legacy, RGB i MiniMessage, rozpoznaje zmienne tekstowe typu `<player>`
  i zgłasza błędy składni tagów formatujących, np. brak zamknięcia lub niepoprawny
  kolor HEX,
- panel administracyjny `/admin/plugin-translator` prezentuje listę prac, procent
  ukończenia, plugin, wersję, rodzaj zgłoszenia, podgląd różnic oraz akcje
  akceptacji lub odrzucenia,
- `/admin/plugin-translator/plugins` pozwala administratorowi tworzyć i edytować
  kategorie, wybierać istniejącą stronę `/p/{slug}`, sterować widocznością oraz
  usuwać pozycje; usuwanie atomowo przenosi przypisane zgłoszenia do chronionej
  kategorii `Nieprzypisane`,
- główna kolejka udostępnia szybkie akcje podglądu, pobrania, zatwierdzenia,
  odrzucenia i trwałego usunięcia zgłoszenia; operacje zapisowe wymagają CSRF i są
  rejestrowane w audit logu,
- zatwierdzone i przeglądane tłumaczenie można pobrać jako zweryfikowany YAML,
- plik tłumaczenia pobierany ze zgłoszenia lub katalogu ma zawsze nazwę
  `messages_xx.yml`, gdzie `xx` jest kodem języka zapisanym małymi literami,
- administracyjny edytor pliku YAML pozostaje dostępny pod
  `/admin/plugin-translator/tool`; po edycji zachowuje bezpieczną wersję oryginalnej
  nazwy wgranego pliku,
- publiczne formularze używają `Request`, CSRF, walidacji rozmiaru i parsera `Spyc`;
  panel administracyjny wymaga ACL `plugin_translator.review`, a edytor pliku YAML
  wymaga `plugin_translator.use`,
- moduł jest opcjonalny, instalowany przez manager modułów i nie rozszerza
  `ThemeInterface` metodami specyficznymi dla translatora.

#### 4.5 Moduł prezentacji zespołu `team`
- niezależne rozszerzenie prezentujące publiczną listę członków drużyny,
- tabela `team_members` wiąże publiczny profil z lokalnym kontem `users`, dzięki
  czemu nazwa systemowa, status i avatar pozostają własnością profilu użytkownika,
- moduł przechowuje dane prezentacyjne: slug, nazwę publiczną, rolę w zespole,
  opis, opcjonalny link kontaktowy, kolejność i widoczność publiczną,
- publiczne `/team` pokazuje wyłącznie widocznych członków powiązanych z aktywnymi
  użytkownikami,
- publiczne `/team/member/{slug}` prezentuje profil członka zespołu z avatarem,
  opisem i linkiem profilu,
- panel `/admin/team` pozwala dodawać, edytować, ukrywać i usuwać publiczne profile
  zespołu; wymaga ACL `team.manage`, CSRF i zapisuje audit log operacji,
- aktywny moduł deklaruje link publiczny `Zespół` przez `PublicNavigationRegistry`,
- `ThemeInterface::render_avatar()` jest ogólnym komponentem prezentacji avatara
  lub inicjałów i nie jest związany wyłącznie z modułem `team`.

#### 4.6 Moduł profilu użytkownika `user_profile`
- niezależne rozszerzenie zależne od chronionego modułu `core_auth`,
- przejmuje trasy `/admin/profile`, `/admin/profile/edit`,
  `/admin/profile/avatar` i `/admin/profile/security`, zachowując publiczny kontrakt
  linków dropdownu użytkownika,
- pozwala użytkownikowi edytować nazwę wyświetlaną, kontaktowy adres e-mail i avatar
  oraz przeglądać role, uprawnienia i stan bezpieczeństwa,
- zapis przechodzi przez `AuthService` i repozytorium użytkowników `CoreAuth`, a
  formularze korzystają z `Request`, CSRF i audit logu,
- łączenie i odłączanie zewnętrznych tożsamości pozostaje w `core_auth`, ponieważ
  jest częścią granicy uwierzytelniania; profil tylko prowadzi do tych operacji,
- publiczne dane członka zespołu, slug i widoczność pozostają własnością `team`,
  który wiąże je z lokalnym użytkownikiem oraz jego avatarem.

#### 4.7 Moduł katalogu projektów `projects`
- niezależne rozszerzenie zależne od `core_auth`, `core_pages` i `wikipedia`,
- przechowuje wyłącznie metadane katalogowe projektu: nazwę, slug, stan
  lifecycle, kolejność i publikację,
- opcjonalne klucze obce wiążą projekt z istniejącą podstroną oraz projektem Wiki;
  moduł nie kopiuje ich treści,
- publiczne `/projects` i `/projects/{slug}` pokazują wyłącznie opublikowane wpisy;
  siatka zależy od liczby projektów, a karty prowadzą do opublikowanej strony,
  dokumentacji oraz Build Explorera bez duplikowania opisu,
- `/admin/projects` zapewnia CRUD przez `CrudApp`, `Request`, CSRF, ACL
  `projects.view` / `projects.manage` oraz audit log,
- moduł deklaruje konfigurowalny link publiczny `Projekty` przez
  `PublicNavigationRegistry`; domyślnym obszarem jest menu główne,

#### 4.8 Moduł plików projektów `build_explorer`
- niezależne rozszerzenie zależne od `core_auth` i `projects`,
- tabela `project_builds` przechowuje wersję, kanał `release` / `snapshot` / `dev`
  / `wip`, nazwę pliku, adres HTTPS, opcjonalny rozmiar i SHA-256, changelog,
  metadane CI, commity oraz publikację,
- publiczne `/builds` prowadzi przez projekt, kanał, wersję i historię buildów;
  tabela wersji wskazuje najnowszy plik, a historia DEV/WIP pokazuje commity,
- `POST /api/builds/ci/{slug}` przyjmuje JSON z CI, uwierzytelnia nagłówkiem
  `X-Build-Token` lub Bearer i idempotentnie zapisuje artefakty według ID joba,
- numer buildu jest opcjonalny dla Release i Snapshot; rewizję Snapshot zapisuje
  wersja, np. `1.7.3-R0.1`,
  należące do opublikowanych projektów,
- Etap 2 przyjmuje bezpośredni upload JAR do `cache/build-artifacts`, poza publicznym
  routingiem Apache; domyślny limit aplikacji i produkcyjnego PHP wynosi 20 MB,
- rozmiar i SHA-256 są wyliczane z zapisanego pliku, a nazwa domyślna ma wzór
  `<projekt>-<serwer>-<wersja>-<kanał>-<build>.jar`; administrator może podać
  własny bezpieczny basename `.jar`,
- publiczne pobieranie przechodzi przez `/builds/download?id=...`, ponownie sprawdza
  publikację projektu i buildu oraz wysyła `Content-Disposition` i `nosniff`,
- podmiana pliku zapisuje nowy artefakt przed aktualizacją bazy, a stary usuwa po
  sukcesie; usunięcie rekordu usuwa również lokalny plik,
- `/admin/builds` zapewnia CRUD przez `CrudApp`, `Request`, CSRF, ACL
  `builds.view` / `builds.manage` i audit log,
- moduł deklaruje konfigurowalny link `Pliki do pobrania` przez
  `PublicNavigationRegistry`; domyślnym obszarem jest menu główne.

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
- zatwierdzenie importu ponownie waliduje pakiet i wszystkie podpisane pliki,
  dopuszcza wyłącznie niechronione rozszerzenia z podpisem `verified` albo
  `verified_retired`, odrzuca konflikt identyfikatora lub katalogu i atomowo
  przenosi pakiet do `modules/`; nie instaluje modułu i nie wykonuje `factory.php`,
- `/admin/modules/approve` wymaga ACL `modules.install`, CSRF, jawnego potwierdzenia
  oraz zapisuje wynik operacji `module_archive_approve` w audit logu,
- manager pozwala eksportować zainstalowane moduły typu `extension` do ZIP z jednym
  top-level katalogiem pakietu; eksport wymaga ACL/CSRF, jest audytowany i blokuje
  dowiązania symboliczne oraz ukryte segmenty ścieżek.
- dashboard panelu pokazuje syntetyczne metryki modułów, rozszerzeń, migracji,
  aktywności dziennej, sygnały operacyjne i ostatnie zdarzenia audit logu.
- `ThemeInterface` udostępnia responsywną siatkę paneli administracyjnych, aby
  Dashboard i Ustawienia nie składały krótkich informacji jako pełnoszerokich bloków.
- główne akcje modułu są renderowane przez motyw jako pełnoszeroki pasek pod
  nagłówkiem treści panelu; lokalnie w panelach pozostają tylko akcje kontekstowe
  i paginacja.
- `database_manager` jest osobnym modułem typu `extension`, a nie częścią
  `system_admin`; zachowuje adres `/admin/database`, własny manifest, instalator SQL
  i tabelę `database_manager_history` dla historii operacji.
- `/admin/database` udostępnia read-only podgląd aktywnej bazy, listy tabel,
  struktury kolumn i paginowanych danych tabeli przez `information_schema` oraz
  walidowaną nazwę tabeli, chroniony ACL `database.view`.
- `/admin/database/export` eksportuje walidowaną tabelę do CSV z limitem 10 000
  rekordów, audytem operacji i neutralizacją formuł arkusza.
- Ta sama trasa obsługuje `format=sql` jako domyślny eksport SQL z definicją tabeli
  i paczkowanymi `INSERT`; wartości są cytowane przez PDO aktywnego połączenia.
- `/admin/database/query` jest konsolą SQL wyłącznie read-only: akceptuje pojedyncze
  zapytania `SELECT`, `SHOW`, `DESCRIBE`, `DESC` i `EXPLAIN`, wymaga CSRF,
  zapisuje audit event `database_query` i pokazuje maksymalnie 100 wierszy wyniku.
- `/admin/database/history` pokazuje paginowaną historię operacji zapisaną w tabeli
  własnej modułu `database_manager_history`.
- `database_manager` rozdziela ACL na `database.view` dla podglądu i eksportu oraz
  `database.manage` dla operacji zmieniających stan.
- `/admin/database/table-operation` wykonuje kontrolowane operacje `OPTIMIZE`,
  `CHECK`, `ANALYZE`, `REPAIR`, `TRUNCATE` i `DROP` dla walidowanej tabeli; operacje
  destrukcyjne wymagają wpisania dokładnej nazwy tabeli.
- `/admin/database/query/manage` wykonuje pojedyncze zapytania zapisowe z whitelisty
  `INSERT`, `UPDATE`, `DELETE`, `REPLACE`, `CREATE`, `ALTER`, `DROP`, `TRUNCATE`,
  `OPTIMIZE`, `ANALYZE`, `CHECK` i `REPAIR`; wymaga CSRF, ACL `database.manage`,
  potwierdzenia `WRITE`, audit logu i historii modułu.
- `/admin/database/import` pozwala zaimportować plik `.sql` albo treść SQL z limitem
  2 MB; wymaga CSRF, ACL `database.manage`, potwierdzenia `IMPORT`, audit logu i
  historii modułu.
- `/admin/database/row/create`, `/admin/database/row/edit` i
  `/admin/database/row/delete` obsługują dodawanie, edycję i usuwanie rekordów;
  edycja oraz usuwanie wymagają tabeli z dokładnie jednym kluczem głównym, CSRF,
  ACL `database.manage`, audit logu i historii modułu.

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
12. `plugin_translator`: narzędzie do walidacji, tłumaczenia, zgłaszania i
    administracyjnego zatwierdzania plików YAML pluginów.
13. `team`: publiczna lista członków drużyny i profile zespołu powiązane z kontami
    użytkowników.
14. `user_profile`: wydzielony profil użytkownika, edycja danych i avatara oraz
    przegląd bezpieczeństwa z wejściem do tożsamości zarządzanych przez `core_auth`.
15. `projects`: publiczny katalog stanu projektów powiązany z podstronami i Wiki.
16. `build_explorer`: kanały i pliki wydań przypisane do katalogu projektów.

Stan Kroku 5:

- `core_pages` i `articles` są rzeczywistymi, odrębnymi modułami treści,
- `user_profile` udostępnia profil użytkownika przez dropdown w topbarze panelu;
  profil obejmuje podgląd, edycję danych, ustawienia avatara i bezpieczeństwo,
  natomiast `core_auth` zachowuje operacje na połączonych kontach,
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
