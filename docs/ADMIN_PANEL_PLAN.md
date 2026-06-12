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
- `AdminMenuRegistry` filtruje pozycje według uprawnień i odrzuca duplikaty tras,
- `ModuleInterface` deklaruje identyfikator, wymagane uprawnienia, menu i trasy,
- `DemoAdminModule` potwierdza separację modułu od HTML na trasach `/admin-demo/*`,
- właściwy `AdminLayout` i ochrona tras powstaną razem z `core_auth`.

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

### 5.4 Szkielet panelu administracyjnego

1. Trasy `/admin`, `/admin/login`, `/admin/logout`.
2. Dashboard ze stanem systemu i modułów.
3. Profil użytkownika i połączone konta.
4. Zarządzanie użytkownikami, rolami i uprawnieniami.
5. Audit log i komunikaty systemowe.
6. Ochrona każdej trasy konkretnym uprawnieniem.

### 5.5 Moduł `core_pages`

1. Lista, tworzenie, edycja, publikacja i usuwanie stron.
2. Slug, status, autor, daty publikacji i wersjonowanie.
3. WYSIWYG dopiero po działającym formularzu podstawowym.
4. Walidacja przez `Request`, CSRF przez `Security`, dane przez `CrudApp`.
5. Uprawnienia granularne `pages.*`.

### 5.6 Moduł `articles`

1. Kategorie, artykuły, status publikacji i autor.
2. Lista publiczna i widok artykułu.
3. Panel redakcyjny oparty na komponentach `core_pages`.
4. Demonstracja niezależnego modułu rejestrującego własne trasy i menu.

## 6. Manager modułów

Manager powstaje po działających modułach stałych, ponieważ ich kontrakt wyznaczy
rzeczywiste wymagania systemu modułów.

1. Schemat i walidacja `info.json`.
2. Odczyt zależności oraz zgodności wersji PHP i miniPORTAL.
3. Podgląd modułu przed instalacją.
4. Transakcyjne wykonanie `install.sql` i migracji.
5. Rejestr `modules_config`.
6. Aktywacja i deaktywacja tras oraz menu.
7. Aktualizacja z kontrolą wersji i możliwością przerwania operacji.
8. Odinstalowanie z osobnym potwierdzeniem usunięcia danych.
9. Blokada usunięcia modułów stałych `core_auth` i `core_pages`.
10. Uprawnienia administratora i zapis wszystkich operacji w audit logu.

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
