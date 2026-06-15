# Plan panelu administracyjnego i logowania

Panel administracyjny jest stałą częścią miniPORTAL, ale jego funkcje pozostają
podzielone na moduły. Warstwa panelu dostarcza układ, nawigację, kontrolę dostępu
i wspólne komponenty; moduły rejestrują własne pozycje menu, trasy i uprawnienia.

## 1. Założenia

- panel działa pod trasami `/admin/*`,
- dostęp wymaga aktywnej sesji użytkownika i odpowiedniego uprawnienia,
- GitHub, Discord i Google są adapterami jednego kontraktu logowania,
- konto użytkownika jest oddzielone od zewnętrznej tożsamości,
- jedna osoba może połączyć kilka tożsamości z jednym kontem,
- adres e-mail nie jest samodzielnym identyfikatorem konta,
- sekrety i tokeny dostawców nie trafiają do repozytorium ani logów,
- moduły nie implementują własnego logowania ani nie odczytują globalnych danych wejściowych.

## 2. Model danych

### `users`

- `id`
- `display_name`
- `email` - pomocniczy adres kontaktowy, nie klucz tożsamości
- `avatar_url`
- `status` - `active`, `blocked`, `pending`
- `last_login_at`
- `created_at`, `updated_at`

### `user_identities`

- `id`
- `user_id`
- `provider` - `github`, `discord`, `google`, opcjonalnie `local`
- `provider_subject` - niezmienny identyfikator użytkownika u dostawcy
- `provider_login`
- `provider_email`
- `email_verified`
- `linked_at`, `last_used_at`
- unikalny indeks: `(provider, provider_subject)`

### ACL

- `roles`
- `permissions`
- `user_roles`
- `role_permissions`

Role startowe:

- `administrator`
- `editor`
- `user`

Przykładowe uprawnienia:

- `admin.access`
- `pages.view`, `pages.create`, `pages.edit`, `pages.delete`, `pages.publish`
- `articles.*`
- `users.view`, `users.manage`
- `modules.view`, `modules.install`, `modules.toggle`, `modules.remove`
- `settings.manage`

### Rejestr bezpieczeństwa

Tabela `auth_events` zapisuje co najmniej:

- próbę logowania i dostawcę,
- wynik operacji,
- skrócony lub zanonimizowany adres IP,
- user agent,
- czas zdarzenia,
- identyfikator użytkownika, jeśli został rozpoznany.

Nie zapisuje kodów autoryzacyjnych, access tokenów, refresh tokenów ani sekretów.

## 3. Kontrakt dostawcy tożsamości

Każdy adapter implementuje wspólny kontrakt:

```php
interface IdentityProviderInterface
{
    public function name(): string;

    public function authorizationUrl(string $state, string $codeChallenge): string;

    public function resolveIdentity(string $code, string $codeVerifier): ExternalIdentity;
}
```

`ExternalIdentity` zawiera ujednolicone dane:

- provider,
- subject,
- login/display name,
- e-mail i informację o jego weryfikacji,
- avatar URL.

Provider nie nadaje roli. Po zalogowaniu lokalny `AuthService` znajduje konto,
łączy tożsamość i dopiero z lokalnej bazy odczytuje role oraz uprawnienia.

## 4. Wymagania bezpieczeństwa OAuth/OIDC

- Authorization Code flow wykonywany po stronie serwera.
- Losowy, jednorazowy `state` przypisany do sesji i sprawdzany w callbacku.
- PKCE tam, gdzie wspiera go dostawca; kontrakt przechowuje verifier po stronie sesji.
- Dla Google: OpenID Connect, walidacja ID tokenu, `nonce`, issuer, audience i czasu ważności.
- Dokładne dopasowanie redirect URI.
- Minimalne scope potrzebne wyłącznie do identyfikacji.
- Token dostawcy usuwany po pobraniu profilu, jeśli nie jest potrzebny do późniejszego API.
- Rotacja identyfikatora sesji po poprawnym logowaniu i wylogowaniu.
- Ograniczenie liczby prób callbacku i logowania.
- Brak automatycznego łączenia kont wyłącznie na podstawie zgodnego e-maila.
- Pierwszy administrator tworzony przez kontrolowaną allowlistę lub komendę bootstrapującą.

Minimalne dane:

| Dostawca | Mechanizm | Minimalny cel |
|----------|-----------|---------------|
| GitHub | OAuth 2.0 / GitHub App user authorization | identyfikator, login, avatar, zweryfikowany e-mail jeśli wymagany |
| Discord | OAuth 2.0 Authorization Code | scope `identify`, opcjonalnie `email` |
| Google | OpenID Connect | scope `openid email profile`, walidowany ID token |

Przed implementacją GitHub należy podjąć decyzję: GitHub App albo klasyczna OAuth App.
GitHub rekomenduje GitHub Apps dla nowych integracji; do samego logowania oba warianty
mogą używać przepływu OAuth.

Przed implementacją adapterów należy zarejestrować aplikacje i dokładne callbacki:

- `/admin/auth/github/callback`,
- `/admin/auth/discord/callback`,
- `/admin/auth/google/callback`.

Rejestracja dostawców nie jest potrzebna do wykonania statycznego prototypu panelu.

## 5. Etapy realizacji

### 5.1 Prototyp panelu Outside-In

1. `templates/default/admin-stylebook.html`.
2. Widoki: logowanie, dashboard, sidebar, topbar, breadcrumb.
3. Tabele, filtry, formularze edycji, paginacja i modal potwierdzenia.
4. Stany: ładowanie, pusto, sukces, błąd, 403, 404.
5. Responsywna nawigacja i dostępność klawiaturą.
6. Przyciski logowania GitHub, Discord i Google z jednolitym komponentem.

### 5.2 Kontrakt panelu i modułów

1. Rozszerzenie `ThemeInterface` o komponenty panelu wynikające ze stylebooka.
2. `AdminLayout` bez logiki biznesowej.
3. `AdminMenuRegistry` z pozycjami menu zależnymi od uprawnień.
4. `ModuleInterface` z rejestracją tras, menu i wymaganych uprawnień.

Stan implementacji:

- `ThemeInterface` udostępnia układ panelu, breadcrumb, metryki, panele i tabelę,
- ogólna tabela akcji obsługuje linki i formularze POST z CSRF bez znajomości modułu,
- `AdminMenuRegistry` filtruje pozycje według uprawnień i odrzuca duplikaty tras,
- `ModuleInterface` deklaruje identyfikator, wymagane uprawnienia, menu i trasy,
- `ModuleRegistry` uruchamia rejestrację menu i tras wszystkich aktywnych modułów,
- `SystemAdminModule` dostarcza dashboard, manager modułów i zasoby systemowe,
- `CoreAuthModule` jest właścicielem menu i tras użytkowników oraz połączonych tożsamości,
- układ panelu pokazuje menu przefiltrowane według uprawnień bieżącego użytkownika.
- sekcja `/admin/design-system` łączy działający panel ze statycznym stylebookiem,
  prototypem homepage, prototypem panelu i testem warstw Core.

### 5.3 Moduł `core_auth`

1. Migracje tabel użytkowników, tożsamości i ACL.
2. `AuthService`, `AuthorizationService` i middleware ochrony tras.
3. Provider registry i interfejs adaptera.
4. Adapter GitHub.
5. Adapter Discord.
6. Adapter Google OIDC.
7. Łączenie i odłączanie dodatkowej tożsamości.
8. Wylogowanie, blokowanie konta i unieważnianie sesji.
9. Bootstrap pierwszego administratora.
10. Rejestr zdarzeń bezpieczeństwa.

Opcjonalne logowanie lokalne z Argon2id należy traktować jako konto awaryjne, nie
domyślną ścieżkę panelu. Wymaga osobnej decyzji i polityki odzyskiwania dostępu.

Stan implementacji:

- `install.sql` definiuje użytkowników, tożsamości, role, uprawnienia i `auth_events`,
- modele domenowe oddzielają konto od zewnętrznej tożsamości,
- `CrudAppUserRepository` odczytuje konto, role i uprawnienia przez fasadę `CrudApp`,
- pierwsze poprawne logowanie nieznanej tożsamości tworzy konto `pending` po kluczu
  `(provider, provider_subject)`; e-mail nie służy do automatycznego łączenia,
- administrator może utworzyć konto ręcznie, opcjonalnie podając niezmienny subject
  dostawcy, oraz zaakceptować konto oczekujące,
- `AuthService`, `AuthorizationService` i `AdminAccessGate` chronią trasy `/admin/*`,
- logowanie i wylogowanie wymagają CSRF, a identyfikator sesji jest rotowany,
- `IdentityProviderRegistry` udostępnia wspólny kontrakt adapterów,
- adapter GitHub realizuje Authorization Code, `state`, PKCE i mapowanie profilu,
- adapter Discord realizuje Authorization Code, `state` i zakresy `identify email`,
- adapter Google realizuje OIDC z `nonce`, PKCE i walidacją podpisanego ID tokenu,
- `FirstAdminBootstrapper` atomowo tworzy pierwsze konto wyłącznie w pustej bazie,
- profil pozwala łączyć i odłączać providerów bez automatycznego scalania po e-mailu,
- `AuditLogService` zapisuje logowania, wylogowania, callbacki, ACL i zmiany tożsamości,
- `/admin/users` pokazuje lokalne konta, role, statusy i podłączonych providerów,
- `users.manage` pozwala atomowo zmienić status i jedną rolę startową; operacja
  chroni własne konto oraz ostatniego aktywnego administratora,
- sesyjny limiter ogranicza rozpoczęcia i callbacki osobno dla każdego providera,
- repozytorium pamięciowe służy wyłącznie do testów po ustawieniu `AUTH_DEMO_ENABLED=1`.

Migrację `modules/CoreAuth/install.sql` wykonano i zweryfikowano na skonfigurowanej
bazie. GitHub, Discord i Google są skonfigurowane i zostały sprawdzone w pełnym
przepływie. Pierwszy aktywny administrator ma połączone wszystkie trzy tożsamości.

### 5.4 Szkielet panelu administracyjnego

1. Trasy `/admin`, `/admin/login`, `/admin/logout`.
2. Dashboard ze stanem systemu i modułów.
3. Profil użytkownika i połączone konta.
4. Zarządzanie użytkownikami, rolami i uprawnieniami.
5. Audit log i komunikaty systemowe.
6. Ochrona każdej trasy konkretnym uprawnieniem.

Stan implementacji:

- dashboard pokazuje rzeczywistą liczbę aktywnych modułów i oczekujących migracji,
- lista użytkowników obsługuje tworzenie, akceptację, status i wiele lokalnych ról,
- `/admin/roles` obsługuje role niestandardowe i przypisane uprawnienia,
- edytor roli grupuje uprawnienia według przestrzeni nazw modułów i pozwala
  zaznaczać pojedyncze prawa lub całą grupę bez używania klawisza Ctrl,
- role systemowe zachowują stałe identyfikatory, rola administratora zawsze otrzymuje
  pełny aktualny zestaw uprawnień, a używane role nie mogą zostać usunięte,
- osobny widok przeglądania audit logu pozostaje do wdrożenia.

### 5.5 Moduł `core_pages`

1. Lista, tworzenie, edycja, publikacja i usuwanie stron.
2. Slug, status, autor, daty publikacji i wersjonowanie.
3. Kontrolowany WYSIWYG po działającym formularzu podstawowym.
4. Walidacja przez `Request`, CSRF przez `Security`, dane przez `CrudApp`.
5. Uprawnienia granularne `pages.*`.
6. Typ dokumentu: informacyjny, projektowy lub prawny.
7. Skrót, opis SEO, miejsce w nawigacji i kolejność.
8. Powiązanie elementów homepage z opublikowaną podstroną.

Stan implementacji:

- tabela `core_pages` zawiera slug, status, autora i datę publikacji,
- `PageRepository` wykonuje operacje przez fasadę `CrudApp`,
- panel obsługuje tworzenie, edycję, publikację, cofnięcie publikacji i usuwanie,
- każda trasa wymaga osobnego uprawnienia `pages.*` oraz poprawnego CSRF,
- publiczna trasa `/page?slug=...` pokazuje tylko opublikowane rekordy,
- czytelna trasa `/p/slug` i katalog `/pages` działają przez Front Controller,
- strony mogą automatycznie pojawiać się w menu głównym albo stopce,
- karty projektów mogą wskazywać wybraną podstronę przez `page_id`,
- podstrony i sekcje strony głównej korzystają z kontrolowanego WYSIWYG,
- `RichTextSanitizer` usuwa kod wykonywalny i atrybuty HTML przed zapisem,
- formularze treści mają lokalny autozapis, a homepage udostępnia podgląd roboczy.

### 5.6 Moduł `articles`

1. Kategorie, artykuły, status publikacji i autor.
2. Lista publiczna i widok artykułu.
3. Panel redakcyjny oparty na komponentach `core_pages`.
4. Demonstracja niezależnego modułu rejestrującego własne trasy i menu.

Stan implementacji:

- `article_categories` i `articles` przechowują kategorie, autora, status i daty publikacji,
- panel obsługuje kategorie oraz pełny CRUD artykułów z uprawnieniami `articles.*`,
- publiczne trasy `/articles` i `/article?slug=...` pokazują wyłącznie opublikowane treści,
- moduł korzysta z `CrudApp`, `Request`, `Security`, audit logu i ogólnych komponentów Theme,
- `modules/Articles/info.json` jest pierwszym rzeczywistym przykładem metadanych dla Kroku 6.

## 6. Manager modułów

Manager powstaje po działających modułach stałych, ponieważ ich kontrakt wyznaczy
rzeczywiste wymagania systemu modułów.

1. Schemat i walidacja `info.json`. [ukończone]
2. Odczyt zależności oraz zgodności wersji PHP i miniPORTAL. [ukończone]
3. Podgląd modułu przed instalacją. [ukończone]
4. Kontrolowane wykonanie `install.sql` i migracji z historią SHA-256. [ukończone]
5. Rejestr `modules_config`. [ukończone]
6. Aktywacja i deaktywacja tras oraz menu. [ukończone]
7. Aktualizacja z kontrolą wersji i preflightem sum migracji przed pierwszym DDL. [ukończone]
8. Odinstalowanie z osobnym potwierdzeniem zachowania albo usunięcia danych. [ukończone]
9. Blokada wyłączenia i usunięcia modułów stałych `core_auth` i `core_pages`. [ukończone]
10. Uprawnienia administratora i zapis wszystkich operacji w audit logu. [ukończone]

Manager korzysta ze stabilnego `ModuleInterface`, walidacji manifestów,
topologicznego uruchamiania zależności, deklaratywnych fabryk w `config/modules.php`,
rejestru `modules_config` i historii `module_migrations`. DDL MySQL/MariaDB może
zatwierdzać się automatycznie, dlatego historia jest zapisywana dopiero po pełnym
powodzeniu pliku SQL i nie jest opisywana jako jedna transakcja.

Aktualizacja jest dostępna tylko dla wyższej wersji manifestu. Odinstalowanie wymaga
wyłączonego modułu; wariant zachowujący dane pozostawia historię migracji i pozwala
na przywrócenie, a wariant trwały wykonuje zadeklarowany `uninstall.sql`. Moduł
chroniony albo wymagany przez inne zainstalowane rozszerzenie nie może zostać
odinstalowany.

Samo znalezienie `info.json` nie uprawnia do wykonania kodu ani SQL. Dopóki moduł
nie ma znanej fabryki w `config/modules.php`, manager pokazuje stan „Brak fabryki”
i blokuje operacje. Rozszerzenie może zadeklarować lokalny `factory.php`; manager
pokazuje wtedy akcję instalacji, ale kod pakietu jest wykonywany dopiero po jawnym
potwierdzeniu instalacji oraz zapisaniu stanu aktywnego. Wadliwa fabryka rozszerzenia
jest izolowana i nie zatrzymuje Core.

## 7. Kryterium ukończenia

Panel można uznać za gotowy do rozbudowy, gdy:

- niezalogowany użytkownik nie otworzy żadnej trasy `/admin/*`,
- każdy z trzech dostawców przechodzi pełny login i callback,
- tożsamości są przypisane przez `(provider, subject)`,
- role działają niezależnie od dostawcy logowania,
- administrator widzi tylko akcje, do których ma uprawnienia,
- `core_pages` wykonuje pełny CRUD przez `CrudApp`,
- zdarzenia logowania i działania administracyjne trafiają do audit logu,
- testy obejmują CSRF, `state`, replay callbacku, 403 i blokadę konta.

Runner `php tests/run.php` obejmuje normalizację `Request`, CSRF, związanie `state`
z providerem, replay, limiter OAuth, brak uprawnienia i zablokowane konto. Pełne
testy integracyjne callbacków zewnętrznych pozostają testami operacyjnymi, ponieważ
wymagają interakcji z kontami dostawców.
