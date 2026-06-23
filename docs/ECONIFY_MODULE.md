# Econify - opis modułu

Dokument opisuje moduł `econify` od strony widocznej dla użytkowników portalu oraz
od strony panelu administracyjnego. Stan odpowiada modułowi `Econify Control Center`
w wersji `1.2.0`.

## Cel modułu

`econify` jest dedykowanym centrum zarządzania botem ekonomicznym Discord. Moduł
obsługuje wiele serwerów Discord jako osobne tenanty, czyli każdy serwer ma własną
walutę, ustawienia ekonomii, sklep, giełdę, portfele graczy i historię transakcji.

Moduł nie jest ogólnym systemem płatności. Operuje na wirtualnej ekonomii bota:
saldo, EXP, poziomy, nagrody, zakupy w sklepie i wirtualne aktywa giełdowe.

## Poziomy dostępu

### Administrator platformy miniPORTAL

To konto z uprawnieniami `econify.view` oraz opcjonalnie
`econify.platform.manage`. W praktyce są to role Owner lub Administrator.

Administrator platformy widzi panel `/admin/econify`, zarządza globalnymi
funkcjami bota, domyślną ekonomią i diagnostyką serwerów Discord. Ten poziom
nie służy do codziennej gry, tylko do utrzymania usługi Econify jako całości.

### Właściciel lub administrator serwera Discord

To użytkownik przypisany w `econify_memberships` do konkretnego serwera z rolą
`guild_owner` albo `guild_admin`. Może zarządzać tylko swoim serwerem Econify.

Ma dostęp do ustawień ekonomii serwera pod `/econify/server`, może powiązywać
użytkowników miniPORTAL z Discord User ID, dodawać przedmioty sklepu, tworzyć
aktywa giełdowe i aktualizować notowania.

### Gracz

To użytkownik z rolą `player` w obrębie konkretnego serwera Econify. Widzi własny
portfel, historię, sklep i giełdę wyłącznie na serwerach, z którymi jego konto
zostało powiązane.

Gracz nie widzi ustawień platformy ani ustawień serwera.

## Część publiczna / użytkownika

Publiczne widoki Econify są dostępne pod linkiem `Econify` w menu głównym, który
prowadzi do `/econify`. Publiczne nie oznacza anonimowe: portfel jest powiązany
z lokalnym kontem miniPORTAL, więc użytkownik musi być zalogowany.

Właściciel lub administrator Discord nie musi zaczynać od panelu miniPORTAL. Jego
punktem wejścia jest `/econify/servers`, gdzie pobiera listę zarządzanych serwerów
Discord i widzi, czy Econify został już zgłoszony przez bota.

### `/econify` - centrum gracza

To główny ekran użytkownika. Pokazuje wybrany serwer Econify i podstawowe dane
portfela:

- saldo w walucie serwera,
- poziom gracza,
- aktualny EXP i próg następnego poziomu,
- szybkie linki do sklepu i giełdy,
- szybki link do listy zarządzanych serwerów Discord,
- link `Zarządzaj serwerem`, jeśli użytkownik ma rolę `guild_owner` lub
  `guild_admin`,
- ostatnie transakcje użytkownika na danym serwerze.

Jeśli użytkownik nie ma żadnego powiązanego serwera, zobaczy komunikat, że konto
nie jest jeszcze połączone z żadnym serwerem Econify oraz link do pobrania listy
serwerów Discord, którymi zarządza.

### `/econify/servers` - moje serwery Discord

Ten widok pobiera i pokazuje tylko serwery Discord, na których bieżący użytkownik
ma Owner, Administrator albo Manage Guild. Lista pochodzi z Discord OAuth
`identify guilds`, a token użytkownika nie jest zapisywany.

Dla każdego serwera użytkownik widzi:

- Discord Guild ID,
- poziom zweryfikowanego dostępu,
- informację, czy bot Econify zgłosił już serwer do miniPORTAL,
- przejście do szczegółów serwera.

Jeśli bot nie zgłosił jeszcze serwera, użytkownik dostaje link `Zaproś Econify na
serwer`. Jeśli bot już zgłosił serwer, użytkownik może połączyć swoje lokalne
konto z tenantem i wejść do ustawień Econify dla tej gildii.

### `/econify/shop` - sklep serwerowy

Sklep pokazuje aktywne przedmioty dla wybranego serwera. Użytkownik widzi saldo,
nazwę przedmiotu, opis, cenę i przycisk zakupu.

Zakup jest rozliczany transakcyjnie:

- sprawdzany jest aktywny przedmiot,
- sprawdzany jest portfel gracza,
- saldo jest pomniejszane o cenę,
- stan magazynowy jest zmniejszany, jeśli przedmiot ma limit,
- tworzone jest zamówienie,
- zapisywana jest transakcja `shop_purchase`.

Typ realizacji przedmiotu może być:

- `discord_role` - np. rola Discord,
- `code` - bezpieczna referencja do kodu obsługiwanego po stronie bota,
- `manual` - ręczna realizacja.

W miniPORTAL nie należy przechowywać sekretnych kodów. Pole referencji ma być
identyfikatorem roli albo bezpiecznym kluczem rekordu obsługiwanym przez bota.

### `/econify/market` - giełda

Giełda pokazuje aktywa dostępne na danym serwerze. Dla każdego aktywa użytkownik
widzi:

- symbol i nazwę,
- aktualną cenę,
- liczbę posiadanych jednostek,
- średnią cenę zakupu,
- wartość portfela dla tego aktywa,
- wykres i tabelę ostatnich notowań,
- formularz kupna lub sprzedaży.

Kupno i sprzedaż są rozliczane na tym samym portfelu co sklep. Przy kupnie system
sprawdza saldo, przy sprzedaży liczbę posiadanych jednostek. Transakcje trafiają
do historii jako `market_buy` albo `market_sell`.

### `/econify/server` - ustawienia serwera

Ten widok jest publiczną częścią modułu, ale wymaga roli `guild_owner` lub
`guild_admin` dla wybranego serwera. Nie jest to globalny panel miniPORTAL.

Właściciel lub administrator serwera może tu ustawić:

- nazwę waluty,
- nagrodę `/daily`,
- minimalną i maksymalną nagrodę `/work`,
- podatek od przelewów w procentach,
- Discord Role ID dla VIP,
- kwotę `VIP daily`,
- włączenie lub wyłączenie sklepu,
- włączenie lub wyłączenie giełdy.

Ten sam widok pozwala też:

- powiązać konto miniPORTAL z Discord User ID,
- nadać poziom dostępu `player`, `guild_admin` albo `guild_owner`,
- dodać przedmiot do sklepu,
- dodać aktywo giełdowe,
- dopisać nowe notowanie aktywa,
- podejrzeć katalog sklepu serwera.

Plan `freemium` ma limit aktywnych pozycji sklepu określony globalnie w ustawieniach
platformy.

## Panel administracyjny

### `/admin/econify` - Econify Control Center

Panel administracyjny modułu jest dostępny w sekcji `Dedykowane` jako `Econify`.
Wymaga uprawnienia `econify.view`.

Panel pokazuje metryki platformy:

- liczbę aktywnych serwerów,
- liczbę powiązanych graczy,
- liczbę zamówień.

Pokazuje też diagnostykę integracji:

- czy plik `modules/Econify/.env` albo `ECONIFY_ENV_FILE` jest czytelny,
- czy skonfigurowano `ECONIFY_API_TOKEN`,
- czy skonfigurowano aplikację Discord,
- czy ustawiono token bota.

Sekrety nie są wyświetlane w HTML. Panel pokazuje tylko stan kompletności
konfiguracji.

### Lista serwerów Econify

Panel zawiera tabelę aktywowanych tenantów Discord. Dla każdego serwera widać:

- nazwę serwera,
- właściciela,
- plan,
- liczbę członków,
- stan aktywności,
- przejście do ustawień ekonomii serwera.

### Twoje serwery Discord

Sekcja `Twoje serwery Discord` służy do bezpiecznej diagnostyki i odsyła do tego
samego publicznego flow `/econify/servers`.

Przycisk `Pobierz moje serwery Discord` uruchamia OAuth Discord z zakresami
`identify guilds`. Moduł pobiera tylko serwery, na których bieżący użytkownik ma:

- status właściciela,
- uprawnienie Administrator,
- albo uprawnienie Manage Guild.

Token użytkownika Discord nie jest zapisywany. Lista serwerów jest przechowywana
w sesji przez 10 minut.

### Szczegóły serwera Discord

Widok szczegółów serwera pokazuje:

- Discord Guild ID,
- czy tenant Econify już istnieje,
- czy bot jest potwierdzony na serwerze,
- plan serwera,
- link zaproszenia bota,
- informację, czy po zgłoszeniu bota można już połączyć konto z tenantem.

Link zaproszenia bota używa flow Discord `bot applications.commands`, przypina
konkretny `guild_id` i wyłącza wybór innego serwera.

Panel nie tworzy już tenantów przez ręczne aktywowanie Guild ID. Tenant powstaje
w bazie dopiero wtedy, gdy bot wyśle zgłoszenie instalacji do endpointu
`/api/econify/guilds`.

### Funkcje bota

Administrator z uprawnieniem `econify.platform.manage` może włączać i wyłączać
globalne funkcje:

- `economy` - komendy ekonomii, portfele i synchronizacja zdarzeń,
- `shop` - sklep serwerowy,
- `market` - giełda,
- `vip_daily` - premia dla roli VIP.

Wyłączenie funkcji globalnej wpływa na wszystkie serwery.

### Domyślna ekonomia bota

Administrator z `econify.platform.manage` może ustawić wartości startowe dla nowo
aktywowanych serwerów:

- język bota, obecnie tylko `pl`,
- domyślne `/daily`,
- domyślne minimum `/work`,
- domyślne maksimum `/work`,
- limit aktywnych pozycji sklepu w planie Freemium.

Zmiana tych wartości nie nadpisuje automatycznie ustawień już istniejących serwerów.

## API bota

Bot zgłasza dodanie lub usunięcie serwera przez:

```http
POST /api/econify/guilds
X-Econify-Token: <sekret>
Content-Type: application/json
```

```json
{
  "guild_id": "123456789012345678",
  "name": "Nazwa serwera",
  "action": "installed"
}
```

`action` może mieć wartość `installed` albo `removed`. Zgłoszenie `installed`
tworzy albo ponownie aktywuje tenant serwera. Zgłoszenie `removed` oznacza serwer
jako nieaktywny.

Zdarzenia ekonomii są synchronizowane osobnym endpointem:

```http
POST /api/econify/events
X-Econify-Token: <sekret>
Content-Type: application/json
```

Wymagany payload:

```json
{
  "event_id": "unikalny-identyfikator-zdarzenia",
  "guild_id": "123456789012345678",
  "user_id": "234567890123456789",
  "type": "daily",
  "amount": 250,
  "experience": 1200,
  "level": 3,
  "description": "Nagroda /daily"
}
```

Obsługiwane typy zdarzeń:

- `daily`,
- `work`,
- `vip_daily`,
- `transfer_in`,
- `transfer_out`,
- `adjustment`.

`event_id` jest kluczem idempotencji. Ponowne wysłanie tego samego zdarzenia dla
tego samego serwera nie zmieni salda drugi raz.

Możliwe odpowiedzi:

- `201` z `created: true` - zdarzenie zapisane,
- `200` z `created: false` - zdarzenie było już obsłużone,
- `401` - brak lub błędny token,
- `404` - nie znaleziono powiązania Discord Guild ID + Discord User ID,
- `422` - nieprawidłowy payload,
- `503` - globalna funkcja ekonomii jest wyłączona.

## Plik konfiguracyjny modułu

Econify używa własnego pliku:

```text
modules/Econify/.env
```

Można też wskazać inny plik przez zmienną procesu `ECONIFY_ENV_FILE`. To ułatwia
testy i oddzielenie konfiguracji modułu od konfiguracji miniPORTAL.

Najważniejsze zmienne:

- `ECONIFY_API_TOKEN` - token endpointu `/api/econify/events`,
- `ECONIFY_DISCORD_CLIENT_ID` - Client ID aplikacji Discord,
- `ECONIFY_DISCORD_CLIENT_SECRET` - sekret aplikacji Discord,
- `ECONIFY_DISCORD_CALLBACK_URL` - callback OAuth dla listy serwerów,
- `ECONIFY_DISCORD_BOT_TOKEN` - token bota, używany tylko do sprawdzenia obecności,
- `ECONIFY_DISCORD_BOT_PERMISSIONS` - maska uprawnień zaproszenia bota.

Plik `.env` nie trafia do czystej dystrybucji. W paczce pozostaje tylko
`.env.example`.

## Dane w bazie

Najważniejsze tabele modułu:

- `econify_features` - globalne przełączniki funkcji,
- `econify_platform_settings` - ustawienia domyślne platformy,
- `econify_guilds` - serwery Discord jako tenanty,
- `econify_memberships` - powiązania kont miniPORTAL z Discord User ID,
- `econify_wallets` - saldo, EXP i poziom użytkownika na serwerze,
- `econify_transactions` - historia zmian salda,
- `econify_shop_items` - katalog sklepu,
- `econify_shop_orders` - zamówienia,
- `econify_market_assets` - aktywa giełdowe,
- `econify_market_quotes` - historia notowań,
- `econify_market_holdings` - portfel aktywów użytkownika.

Sklep i giełda są w jednym module, ponieważ oba mechanizmy rozliczają się przez
ten sam portfel i historię transakcji.

## Bezpieczeństwo i granice

Moduł korzysta z lokalnego konta miniPORTAL oraz ról przypisanych do konkretnego
serwera Econify. Nie łączy kont automatycznie po adresie e-mail.

Najważniejsze zabezpieczenia:

- panel administracyjny przechodzi przez ACL `econify.view` i
  `econify.platform.manage`,
- formularze używają CSRF,
- dane wejściowe przechodzą przez `Request`,
- Discord OAuth używa `state` oraz PKCE `S256`,
- token użytkownika Discord nie jest zapisywany,
- sekrety integracyjne nie są renderowane w panelu,
- endpoint bota wymaga `X-Econify-Token`,
- zdarzenia bota są idempotentne przez `event_id`,
- zakupy i transakcje giełdowe są wykonywane transakcyjnie.

## Szybka mapa widoków

| Widok | Dla kogo | Zastosowanie |
|-------|----------|--------------|
| `/econify` | Gracz, admin serwera | Portfel, poziom, EXP, historia i szybkie akcje |
| `/econify/servers` | Właściciel/admin Discord | Lista zarządzanych serwerów, zaproszenie bota i połączenie konta |
| `/econify/shop` | Gracz | Zakup przedmiotów z katalogu serwera |
| `/econify/market` | Gracz | Kupno i sprzedaż aktywów giełdowych |
| `/econify/server` | `guild_owner`, `guild_admin` | Ustawienia ekonomii, członkowie, sklep i giełda serwera |
| `/admin/econify` | Administrator platformy | Diagnostyka, funkcje globalne, domyślna ekonomia i onboarding |
| `/api/econify/guilds` | Bot | Zgłoszenie dodania albo usunięcia bota na serwerze |
| `/api/econify/events` | Bot | Synchronizacja zdarzeń ekonomii |
