# Instrukcje pracy nad miniPORTAL

> **Ostatnia aktualizacja:** 2026-06-25 - providerzy OAuth i sekcje menu panelu.

Plan projektu jest źródłem prawdy. Przed rozpoczęciem każdego etapu przeczytaj:

1. `README.md` - mapę dokumentacji projektu.
2. `docs/SZKIC.md` - pierwotne założenia i wymaganie maksymalnej modularności.
3. `docs/TECHNICAL_SPECIFICATION.md` - architekturę, bezpieczeństwo i plan wykonawczy.
4. `docs/POPRAWKI_I_ULEPSZENIA.md` - Spostrzeżenia użytkownika na końcowym etapie prac wymagane do poprawy projektu.



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
- Zachowuj zgodność z PHP 8.4 lub nowszym i nie wprowadzaj frameworka aplikacyjnego bez zmiany dokumentacji.
- Minimalizuj diff i nie rozbudowuj starego katalogu `theme/`; nowa prezentacja trafia do `templates/`.
- Ukończone etapy prac odznaczaj by było jasne że dany element jest już skończony i nie potrzeba go więcej modyfikować.

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
| [x] | Logo SyntaxDevTeam, favikony, Apple Touch Icon, manifest i metadane SEO |
| [x] | Osobny przezroczysty sygnet panelu i wygładzone favicony 16-256 px |
| [x] | Canonical, social cards, JSON-LD oraz zgodność dostępności publicznych layoutów |
| [x] | Alternatywny motyw `future` inspirowany warstwą HTML/CSS dawnego CMS-a |
| [x] | Interaktywny, bezpieczny symulator terminala Hero z komendami nawigacyjnymi |

### Krok 3 - odwzorowanie prototypu w PHP

| Status | Zadanie |
|--------|---------|
| [x] | `ThemeInterface` |
| [x] | `templates/default/theme.php` |
| [x] | Metody kart, przycisków, alertów, formularzy, tabel i CSRF |
| [x] | Użycie metod motywu przez integracyjny `index.php` |
| [x] | Konfigurowalne SEO i semantyczny layout obu aktywnych motywów |

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
| [x] | Adapter Microsoft OAuth z PKCE i Microsoft Graph |
| [x] | Chroniona konfiguracja providerów z panelu bez edycji `.env` |
| [x] | Łączenie wielu tożsamości z jednym kontem |
| [x] | Lista użytkowników oraz zmiana lokalnego statusu i roli |
| [x] | Tworzenie i akceptacja kont oczekujących |
| [x] | Wiele ról użytkownika oraz edytor uprawnień ról |
| [x] | Grupowany wybór uprawnień bez klawisza Ctrl |
| [x] | Bootstrap pierwszego Ownera oraz hierarchia chronionych ról systemowych |
| [x] | Audit log logowań i operacji administracyjnych |
| [x] | Chroniony widok audit logu z paginacją |
| [x] | Kontrolowany eksport wyfiltrowanego audit logu do CSV |
| [x] | Chronione ustawienia motywu i zredagowana diagnostyka konfiguracji |
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
| [x] | Kontrolowane ręczne łamanie nagłówków sekcji strony głównej |
| [x] | Elementy sekcji kolumnowych: karty, CTA, wariant, szerokość i kolejność |
| [x] | Kontrolowany WYSIWYG strony głównej z sanitizacją po stronie serwera |
| [x] | Rozszerzenie WYSIWYG na zwykłe podstrony `core_pages` |
| [x] | Przełącznik WYSIWYG / Markdown dla sekcji, kart, podstron i artykułów |
| [x] | Podgląd roboczy i lokalny autozapis formularzy treści |
| [x] | `articles` jako niezależny moduł z kategoriami, własnymi trasami i menu |
| [x] | `wikipedia` jako niezależny moduł dokumentacji projektowej |
| [x] | `team` jako moduł prezentacji listy członków drużyny z możliwością wejścia w publiczny profil użytkownika. |
| [x] | `user_profile` jako osobny moduł edycji danych i avatara, przeglądu bezpieczeństwa oraz wejścia do połączonych kont; profil publiczny pozostaje prezentowany przez zależny moduł `team`. |
| [x] | `projects` jako katalog projektów z publicznymi trasami, stanem realizacji oraz powiązaniami do `core_pages` i `wikipedia`. |
| [x] | `build_explorer` Etap 1: publiczna lista buildów Release/Snapshot/Dev/WIP i panel CRUD metadanych powiązanych z `projects`. |
| [x] | `build_explorer` Etap 2: bezpośredni upload JAR, automatyczny rozmiar i SHA-256 oraz generowana, edytowalna nazwa pliku. |
| [x] | `projects` Etap 2: adaptacyjna siatka i powiązane zasoby bez duplikowanego opisu. |
| [x] | `build_explorer` Etap 3: hierarchia projekt/kanał/wersja/build oraz uwierzytelniony import DEV/WIP z CI. |
| [x] | `core_pages` 1.1.0: opcjonalny pionowy akrostych dla sekcji Hero / Split. |
| [x] | `core_pages` 1.2.0: publiczna podstrona projektu miniPORTAL i linkowany podpis stopki. |
| [x] | `widgets` 1.0.0: hookowe sloty strony głównej, CRUD i terminal wydzielony z motywów. |
| [x] | `widgets` 1.2.0: panel uptime z elementami monitoringu oraz szybkie włączanie i wyłączanie widgetów. |

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
| [x] | Aktualizacja i odinstalowanie modułu |
| [x] | Ochrona `core_auth`, `core_pages` i `system_admin` przed wyłączeniem i usunięciem |
| [x] | Uprawnienia managera i audit log operacji |
| [x] | Podgląd historii migracji i kontrola aktualnego SHA-256 |
| [x] | Filtry audit logu według zdarzenia, wyniku i zakresu dat |
| [x] | Pochodzenie i podpisy RSA-SHA256 zewnętrznych pakietów |
| [x] | Rotacja, okres ważności i unieważnianie kluczy wydawców |
| [x] | Tagowy kontrakt unieważniania cache szablonów |
| [x] | Hooks API z akcjami, filtrami i providerami aktywnych modułów |
| [x] | Parametryczny Slug Router bez budowania tras z rekordów bazy |
| [x] | Projekt zadeklarowany jako PHP 8.4 lub wyższy bez wymogu PHP 8.5 |
| [x] | Cache publicznych podstron i artykułów z granularnymi tagami |
| [x] | Kontrolowany import archiwum modułu do katalogu kwarantanny |
| [x] | Kontrolowane zatwierdzanie podpisanego pakietu z kwarantanny do `modules/` bez instalacji i wykonania kodu |
| [x] | Eksport oraz atomowa aktualizacja modułów chronionych z rollbackiem kodu |
| [x] | Automatyczne podpisywanie eksportowanych modułów skonfigurowanym kluczem wydawcy |
| [x] | Oficjalny publiczny klucz wydawcy w czystej dystrybucji i preflight instalatora |
| [x] | Produkcyjna blokada eksportu, retencja kwarantanny i diagnostyka zapisu `modules/` |
| [x] | Polityka retencji i archiwizacji audit logu |

### Krok 7 - dystrybucja i kreator instalacji

| Status | Zadanie |
|--------|---------|
| [x] | Wyrównane przyciski i grupy akcji obu motywów |
| [x] | Czysty pakiet runtime w `install/cms` bez lokalnych danych i sekretów |
| [x] | Kreator wymagań, strony, MySQL, OAuth, modułów i pierwszego Ownera |
| [x] | Atomowy zapis konfiguracji i blokada ponownej instalacji |
| [x] | Integracyjna instalacja wszystkich modułów na czystej bazie |
| [x] | Katalog wydań i jednoklikowa aktualizacja całego runtime miniPORTAL |
| [x] | Panel Ownera do budowania własnych wydań w instalacji macierzystej |
| [x] | Zbalansowany, responsywny układ paneli strony Ustawienia |
| [x] | Rozszerzalny indeks wyszukiwania panelu z ACL i słowami kluczowymi modułów |
| [x] | Rozszerzalne sekcje menu panelu: Core, Treść, Narzędzia, Dedykowane i System |
| [x] | Kolejność linków publicznej nawigacji modułów |
| [x] | DashboardRegistry z konfigurowalnymi metrykami i panelami modułów |
| [x] | Obsłużony stan błędu formularza Team bez HTTP 500 |

### Krok 8 - moduł dedykowany Econizer

| Status | Zadanie |
|--------|---------|
| [x] | Wieloserwerowy model tenantów Discord i trzy poziomy dostępu |
| [x] | Globalne flagi funkcji, język PL i domyślna ekonomia bota |
| [x] | Ustawienia waluty, podatku i VIP daily właściciela serwera |
| [x] | Pulpit gracza z saldem, EXP, poziomem i historią |
| [x] | Sklep Freemium/Premium z transakcyjnymi zamówieniami |
| [x] | Giełda z notowaniami, portfelem oraz kupnem i sprzedażą |
| [x] | Idempotentny endpoint synchronizacji zdarzeń bota |
| [x] | Dedykowany `.env` modułu z niezależnymi danymi Discord i API |
| [x] | OAuth listy zarządzanych serwerów i przypięta instalacja bota |

### Wycofany kierunek - internacjonalizacja

Publiczne i18n PL/EN/DE zostało wycofane decyzją projektową 2026-06-22.
miniPORTAL pozostaje jednojęzyczny; nie planuje się prefiksów locale, katalogów
tekstu interfejsu ani osobnych tłumaczeń treści CMS.

## Następne kroki

1. Rozważyć osobny widok przeglądania `auth_events_archive`.
2. Dodać automatyczne zadanie retencji uruchamiane przez CLI/cron.

## Uwagi / blokery

### Aktywne blokery

Brak aktywnych blokerów.

### Uwagi architektoniczne

| Data | Opis |
|------|------|
| 2026-06-12 | Środowisko CLI działa na PHP 8.5.7 i spełnia wymaganie wersji PHP 8.4 lub nowszej. |
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
| 2026-06-13 | Pierwszy użytkownik jest aktywny, ma tożsamość GitHub; po migracji hierarchii pełni chronioną rolę `owner`, a bootstrap nie jest już dostępny. |
| 2026-06-13 | GitHub, Discord i Google są aktywne w środowisku, a `AUTH_AUDIT_HASH_KEY` pseudonimizuje IP przez HMAC. |
| 2026-06-13 | `core_pages` używa `CrudApp`, unikalnego slugu, stanów `draft/published`, CSRF i uprawnień `pages.*`. |
| 2026-06-13 | Publiczna trasa `/page?slug=...` pokazuje wyłącznie strony opublikowane i koduje treść przed HTML. |
| 2026-06-13 | Trasa `/` renderuje dynamiczną wersję prototypu homepage i automatycznie pokazuje opublikowane strony z `core_pages`. |
| 2026-06-13 | Prototypy HTML pozostają developerskimi źródłami wyglądu w `templates/`; od 2026-06-20 nie są eksponowane w działającym panelu. |
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
| 2026-06-14 | `CoreAuth` jest właścicielem tras i menu użytkowników oraz tożsamości; `SystemAdminModule` odpowiada za dashboard, manager modułów, ustawienia, diagnostykę i logi systemowe. |
| 2026-06-14 | Zmiana statusu i roli użytkownika jest atomowa oraz chroni własne konto i ostatniego aktywnego administratora. |
| 2026-06-14 | Manager nie wykonuje SQL modułu bez zarejestrowanej fabryki wykonawczej; stan „Brak fabryki” jest widoczny w panelu. |
| 2026-06-15 | Aktualizacja modułu wymaga wyższej wersji manifestu i weryfikuje SHA-256 wszystkich historycznych migracji przed pierwszym DDL. |
| 2026-06-15 | Odinstalowanie wymaga wyłączenia modułu; administrator może zachować dane do późniejszego przywrócenia albo wykonać jawny `uninstall.sql`. |
| 2026-06-15 | `install.sql` każdego modułu opisuje najnowszy stan świeżej instalacji i zawiera efekt wszystkich plików `migrations/*.sql`; wykonanych migracji nie wolno przepisywać. |
| 2026-06-15 | `install/mod/LearningModule` jest nieaktywnym pakietem edukacyjnym z pełnymi DocBlockami, instrukcją, migracją i bezpiecznym odinstalowaniem. |
| 2026-06-15 | Manager izoluje błędny manifest, wymagania runtime i błąd migracji pojedynczego pakietu; wadliwy moduł jest widoczny bez akcji i nie powoduje HTTP 500 całego panelu. |
| 2026-06-15 | Opcjonalna fabryka z `config/modules.php` nie jest uruchamiana przy błędnym manifeście; tylko jawne moduły Core z `required: true` pozostają krytyczne dla startu. |
| 2026-06-15 | Rozszerzenie może deklarować własny `factory.php`; manager udostępnia instalację jednym kliknięciem, lecz nie wykonuje kodu pakietu przed zatwierdzeniem instalacji i aktywacją. |
| 2026-06-15 | Pierwsza poprawna próba logowania nieznanej tożsamości tworzy konto `pending` z rolą `user`; administrator akceptuje je lokalnie, bez łączenia po e-mailu. |
| 2026-06-15 | Użytkownik może mieć wiele ról, a `/admin/roles` zarządza rolami niestandardowymi i mapowaniem uprawnień; administrator zachowuje komplet praw. |
| 2026-06-15 | `SystemAdminModule` udostępnia `/admin/settings` i `/admin/logs`; sekrety nie trafiają do HTML, a ustawienia bazy obejmują wyłącznie motyw i publiczny branding. |
| 2026-06-15 | Audit log nie zapisuje rutynowych `admin_access/allowed`; zachowuje odmowy dostępu i operacje zmieniające stan, a historyczny szum jest ukryty bez usuwania rekordów. |
| 2026-06-15 | Manager pokazuje historię migracji wraz z kontrolą aktualnego SHA-256, a audit log obsługuje filtry zdarzenia, wyniku i zakresu dat. |
| 2026-06-15 | Zewnętrzna fabryka wymaga jawnego pochodzenia i zweryfikowanego podpisu RSA-SHA256 obejmującego wszystkie pliki pakietu; prywatne klucze pozostają poza repozytorium. |
| 2026-06-15 | Audit log eksportuje bieżący filtr do CSV z limitem 10 000 rekordów, ACL, audytem operacji i neutralizacją formuł arkusza. |
| 2026-06-15 | Podpis pakietu zawiera `signed_at`; klucze wydawców mają stany `active`, `retired`, `revoked`, okres ważności i opcjonalnego następcę. |
| 2026-06-15 | `TemplateCacheInterface` definiuje zapis, tagowe unieważnianie, czyszczenie i statystyki; anonimowa strona główna korzysta z `FileTemplateCache`. |
| 2026-06-16 | Wymaganie runtime projektu to PHP 8.4 lub nowszy; PHP 8.5 nie jest konieczne dla handlera produkcyjnego. |
| 2026-06-16 | Publiczne podstrony, lista podstron, lista artykułów i pojedyncze artykuły korzystają z cache szablonów z tagami `page:{slug}`, `article:{slug}`, `pages:index`, `articles:index`, `pages`, `articles` i `theme`. |
| 2026-06-16 | `ModuleArchiveImporter` importuje `.tar`, `.tar.gz`, `.tgz` i `.zip` wyłącznie do `cache/module-quarantine`; manifest i podpis są sprawdzane bez wykonywania fabryki i bez kopiowania do `modules/`. |
| 2026-06-16 | `Request::file()` jest jedyną warstwą odczytu uploadów dla modułów; motywy obsługują pole formularza `file` z `multipart/form-data`. |
| 2026-06-16 | Audit log ma politykę retencji: panelowa operacja przenosi starsze wpisy do `auth_events_archive`, usuwa je z aktywnego `auth_events`, wymaga CSRF/ACL i zapisuje własny audit event. |
| 2026-06-16 | Stan produkcyjny modułów po zmianach: `articles` 1.0.2 oraz `system_admin` 1.4.0. |
| 2026-06-16 | `wikipedia` jest opcjonalnym modułem treści do dokumentacji projektów; używa tabel `wiki_projects` i `wiki_pages`, tras `/wiki`, `/wiki/project`, `/wiki/page`, ACL `wikipedia.*`, CSRF, audit logu i komponentów `ThemeInterface`. |
| 2026-06-16 | Stan produkcyjny modułu `wikipedia` po instalacji: wersja 1.0.0, status `active`. |
| 2026-06-16 | `ThemeInterface::render_content_navigation()` renderuje ogólną nawigację treści; moduł `wikipedia` używa jej na publicznej stronie dokumentacji do poprzedniej strony, spisu projektu i następnej strony z faktycznymi tytułami. Stan produkcyjny modułu `wikipedia`: wersja 1.0.1, status `active`. |
| 2026-06-16 | Kafle `previous` i `next` w `render_content_navigation()` mają dekoracyjne znaki `<` i `>` w tle, sterowane klasami motywu zamiast HTML w module. |
| 2026-06-16 | Breadcrumb panelu Wiki pokazuje kontekst projektu i edytowanej strony, np. `Panel / Dokumentacja / PunisherX / Konfiguracja / Edytuj stronę dokumentacji`. Stan produkcyjny modułu `wikipedia`: wersja 1.0.2, status `active`. |
| 2026-06-16 | `PublicNavigationRegistry` pozwala aktywnym modułom deklarować linki publiczne; `/admin/settings` przypisuje je do głównego menu, stopki albo ukrywa. `wikipedia` udostępnia link `Dokumentacja` do `/wiki`. Stan produkcyjny modułu `wikipedia`: wersja 1.0.3, status `active`. |
| 2026-06-16 | Publiczne widoki modułów używają wspólnej nawigacji i stopki przez `ThemeInterface::set_public_navigation()`. Wiki generuje `/wiki/project/{slug}` i `/wiki/page/{project}/{slug}` zamiast `index.php?route=...`; starsze wejścia pozostają kompatybilne. Stan modułów po zmianie: `wikipedia` 1.0.4 active, `articles` 1.0.3 disabled. |
| 2026-06-19 | `team` 1.0.0 realizuje publiczną listę i profile członków. Widoki oraz zapis danych profilu, avatara i przegląd bezpieczeństwa wydzielono z `CoreAuth` do opcjonalnego modułu `user_profile` 1.0.0 zależnego od `core_auth`; operacje OAuth pozostają własnością chronionego modułu uwierzytelniania. `core_auth` ma wersję 1.4.1. |
| 2026-06-19 | Manager może zatwierdzić podpisane rozszerzenie z kwarantanny do `modules/` po ponownej walidacji i kontroli konfliktów. Operacja wymaga ACL/CSRF, jest audytowana i nie instaluje ani nie wykonuje kodu pakietu. `system_admin` ma wersję 1.5.0. |
| 2026-06-19 | Produkcyjne katalogi lifecycle pakietów mają grupę `www-data`: `modules/` tryb `2775`, a `cache/module-quarantine` tryb `2770`. Pozwala to panelowi atomowo przenosić zatwierdzone pakiety bez nadawania zapisu do istniejących katalogów modułów. |
| 2026-06-19 | `projects` 1.0.1 jest katalogiem agregującym metadane projektu i opcjonalne relacje do `core_pages` oraz `wikipedia`; nie duplikuje treści tych modułów. Publiczne trasy to `/projects` i `/projects/{slug}`, a link domyślnie trafia do menu głównego. |
| 2026-06-19 | `build_explorer` 1.0.1 przechowuje metadane zewnętrznych plików HTTPS w kanałach Release/Snapshot/Dev/WIP i pokazuje je wyłącznie dla opublikowanych projektów oraz buildów; link domyślnie trafia do menu głównego. |
| 2026-06-19 | `build_explorer` 1.1.0 zapisuje uploady JAR w `cache/build-artifacts`, automatycznie wylicza rozmiar i SHA-256 oraz generuje edytowalną nazwę `<projekt>-<serwer>-<wersja>-<kanał>-<build>.jar`. |
| 2026-06-20 | `build_explorer` 1.1.1 naprawia sprzątanie starego artefaktu przy tworzeniu pierwszego buildu i nie usuwa nowego pliku po poprawnym zapisie bazy. |
| 2026-06-20 | `projects` 1.1.0 usuwa opis z katalogu, układa karty zależnie od ich liczby i prezentuje powiązane zasoby. `build_explorer` 1.2.0 dodaje hierarchię publicznych widoków, opcjonalny numer Release/Snapshot oraz tokenowy import JSON z CI wraz z commitami. |
| 2026-06-20 | CI monorepo `PunisherX` zawsze buduje całość, ale publikuje do osobnych projektów `punisherx`, `punisherx-bungeecord-bridge` i `punisherx-velocity-bridge` wyłącznie komponenty dotknięte zmianami. Pliki wspólne Gradle oznaczają wszystkie trzy komponenty. |
| 2026-06-20 | `core_auth` 1.5.0 wprowadza role Owner, Administrator, Maintainer, Redaktor, Audytor i Support. Owner ma wildcard i ochronę ostatniego aktywnego konta; `system_admin` 1.5.1 usuwa `Wzorce UI`, `/admin/design-system` i przycisk `Admin stylebook`. |
| 2026-06-20 | `system_admin` 1.6.0 rozdziela Branding od SEO i udostępniania. Motywy generują canonical, robots, pełne social cards i Organization/WebSite JSON-LD oraz wzmacniają fokus, landmarki, cele dotykowe i redukcję ruchu. |
| 2026-06-21 | `install/cms` jest czystą dystrybucją odtwarzaną przez generator. Kreator instaluje schemat i moduły na pustej bazie, tworzy pierwszego Ownera GitHub, generuje sekrety i zapisuje lokalną konfigurację bez ręcznej edycji. |
| 2026-06-21 | `core_pages` 1.1.0 dodaje tekstowy akrostych sekcji Hero / Split. Core normalizuje maksymalnie 12 wyrazów, a oba motywy renderują je pionowo z wyróżnionymi pierwszymi literami. |
| 2026-06-21 | `core_pages` 1.2.0 publikuje `/p/miniportal` jako opis systemu SyntaxDevTeam. Stopka linkuje nazwę miniPORTAL do tej strony, a SyntaxDevTeam do strony głównej zespołu. |
| 2026-06-21 | `system_admin` 1.6.1 układa Branding, Szablon i Cache w lewej kolumnie ustawień, SEO w prawej, a nawigację w pełnym rzędzie; Theme udostępnia ogólny komponent kolumn paneli. |
| 2026-06-21 | `system_admin` 1.7.0 dodaje indeks wyszukiwania panelu, kolejność nawigacji i konfigurowalne hooki Dashboardu. `team` 1.1.0, `projects` 1.2.0 i `build_explorer` 1.3.0 zgłaszają akcje lub statystyki. |
| 2026-06-21 | Akrostych Hero / Split zastępuje nagłówek po lewej jako `h1`; terminal statusu po prawej pozostaje bez zmian. |
| 2026-06-21 | `system_admin` 1.8.0 rozszerza diagnostykę cache i dodaje generator favicon z PNG do bezpiecznego `uploads/branding`. |
| 2026-06-21 | Motyw `future` przenosi wygląd starego projektu edukacyjnego na pełny `ThemeInterface`; nie korzysta z dawnej logiki CMS. |
| 2026-06-21 | `core_pages` 1.3.0 pozwala dzielić nagłówki sekcji Enterem; trzy motywy bezpiecznie renderują kontrolowane `<br>`. |
| 2026-06-21 | `econizer` 1.0.0 jest dedykowanym modułem innej instalacji miniPORTAL. Izoluje tenanty Discord, rozdziela Ownera platformy, administratora serwera i gracza oraz łączy ekonomię, sklep i giełdę przez transakcyjny portfel. Endpoint bota wymaga `ECONIZER_API_TOKEN` i idempotentnego `event_id`. |
| 2026-06-21 | Sekrety Econizer należą do `modules/Econizer/.env`, nie do środowiska miniPORTAL. `ECONIZER_ENV_FILE` pozwala wskazać niezależny plik testowy, a instalator generuje konfigurację modułu z trybem `0600`. |
| 2026-06-22 | `econizer` 1.1.0 zastępuje ręczne tworzenie tenantów zweryfikowanym OAuth Discord `identify guilds`. Token użytkownika jest jednorazowy, lista sesyjna obejmuje wyłącznie Owner/Administrator/Manage Guild, a instalacja bota używa przypiętego `bot applications.commands`. |
| 2026-06-22 | Terminal Hero jest symulatorem JavaScript bez dostępu do powłoki serwera; bootuje widok statusu i mapuje ograniczone komendy na bezpieczne trasy publiczne. |
| 2026-06-22 | Wycofano publiczne i18n PL/EN/DE wraz z tabelami tłumaczeń, prefiksami locale, przełącznikiem języka i integracją Google Cloud Translation; projekt pozostaje jednojęzyczny. |
| 2026-06-24 | Produkcyjna marka bota i modułu została zmieniona na Econizer. Bieżący kontrakt używa katalogu `modules/Econizer`, identyfikatora `econizer`, tras `/econizer`, endpointów `/api/econizer/*`, zmiennych `ECONIZER_*`, nagłówka `X-Econizer-Token` i tabel `econizer_*`; migracje zachowują dane istniejącej instalacji. |
| 2026-06-24 | Manager eksportuje także chronione moduły `core` i `system`. Import wyższej wersji istniejącego pakietu zachowuje jego tożsamość, atomowo podmienia katalog i wykonuje standardową aktualizację migracji; błąd przywraca poprzedni kod. Ochrona przed wyłączeniem i odinstalowaniem pozostaje niezależna od możliwości aktualizacji. |
| 2026-06-24 | Opcjonalna konfiguracja `MODULE_SIGNING_*` automatycznie podpisuje kopię pakietu podczas panelowego eksportu. Klucz prywatny pozostaje poza projektem, źródłowy katalog modułu nie jest modyfikowany, a `bin/sign-module.php` korzysta z tej samej implementacji dla CLI i CI. |
| 2026-06-24 | Czysta dystrybucja zawiera oficjalny publiczny klucz `syntaxdevteam-modules-2026`. Kreator waliduje go w preflight, a `module_publishers.php` rejestruje go jako aktywną kotwicę zaufania dla aktualizacji modułów; prywatny klucz nigdy nie trafia do paczki instalacyjnej. |
| 2026-06-24 | Eksport modułów jest funkcją wyłącznie instancji wydawniczej z prywatnym kluczem. Kwarantanna obsługuje audytowane usuwanie pojedynczych i starych importów, a manager oraz instalator diagnozują brak zapisu do nadrzędnego `modules/` i pokazują polecenia naprawcze. |
| 2026-06-24 | miniPORTAL 0.2.0 ma osobny lifecycle aktualizacji platformy. Chroniony katalog `releases/` przechowuje katalog wersji, SHA-256 i listę zmian; panel wykonuje staging, backup, podmianę runtime, migracje Core i aktualizacje modułów bez pakowania treści, uploadów ani sekretów. |
| 2026-06-24 | Owner instalacji macierzystej może zbudować release w `/admin/system-updates`. Formularz ustawia wersję SemVer, minimalną wersję bazową i changelog, po czym uruchamia wspólny generator CLI; funkcja nie występuje w czystej dystrybucji. |
| 2026-06-24 | Wydanie 0.2.2 dodaje centralny kanał read-only `/api/platform-releases/*`. Nowe instalacje otrzymują jego URL z kreatora, a pusty lokalny katalog bez kanału jest raportowany jako brak konfiguracji zamiast „najnowszej wersji”. |
| 2026-06-25 | Wydanie 0.2.3 dodaje pełny preflight praw zapisu platformy. Atomowa aktualizacja wymaga zapisu katalogu głównego oraz zarządzanego runtime; panel i kreator pokazują problem przed podmianą plików, bez zmiany praw sekretów. |
| 2026-06-25 | `core_auth` 1.6.0 dodaje Microsoft OAuth, panelową konfigurację providerów w chronionym `config/modules/auth-providers.env` oraz bootstrap Ownera przez pierwsze poprawne logowanie dowolnym providerem. `AdminMenuRegistry` ma stabilne i rozszerzalne sekcje; `system_admin` 2.1.0 udostępnia formularz wyłącznie Ownerowi. |
| 2026-06-29 | `widgets` 1.1.0 dodaje format treści kart (`html`/`markdown`), rich text w formularzu kart oraz w pełni edytowalny skrypt bootowania terminala Hero. Motywy renderują karty przez `ContentRenderer`, a JavaScript terminala używa linii zapisanych w rekordzie widgetu zamiast zaszytego tekstu startowego. |
| 2026-06-29 | `widgets` 1.2.0 dodaje typ `uptime` renderowany przez motywy jako panel monitoringu z kafelkami `Etykieta | Wartość | status`. Lista widgetów ma akcję `Włącz`/`Wyłącz`, a migracja rozszerza `widget_type` o `uptime`. |

## Historia sesji

Pełna historia sesji jest i powinna każdorazowo być zapisywana w `/docs/logs/HISTORIA_SESJI.md`.
