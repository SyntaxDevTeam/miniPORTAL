# Econify Control Center

Dedykowany moduł miniPORTAL dla wieloserwerowego bota ekonomicznego Econify.
Wersja 1.2.0 obejmuje trzy niezależne poziomy dostępu:

- Owner/Administrator miniPORTAL: funkcje platformy, wartości domyślne ekonomii,
  język, limit Freemium, plany i tworzenie tenantów Discord.
- Właściciel lub administrator serwera Discord: waluta, podatek, VIP daily,
  członkowie, katalog sklepu i aktywa rynku wyłącznie własnego serwera.
- Gracz: saldo, EXP, poziom, historia, zakupy oraz portfel giełdowy wyłącznie
  w serwerach, z którymi jego konto zostało jawnie powiązane.

## Integracja bota

Moduł czyta własny plik `modules/Econify/.env`, niezależny od
`config/installed.env` i `/etc/miniportal/miniportal.env`. Zacznij od
`.env.example`, zapisz wynik jako `.env` i ustaw prawa `0600`, gdy plik należy do
procesu WWW, albo `0640` z grupą serwera WWW w instalacji zarządzanej przez
administratora systemu. W testach lub CI
możesz wskazać inną lokalizację zmienną procesu `ECONIFY_ENV_FILE`; ma ona
najwyższy priorytet. Jeśli lokalny plik nie istnieje, loader zachowuje zgodność
wsteczną i odczytuje zmienne procesu.

Plik zawiera:

- `ECONIFY_API_TOKEN` - sekret endpointu zdarzeń,
- `ECONIFY_DISCORD_CLIENT_ID` i `ECONIFY_DISCORD_CLIENT_SECRET` - dedykowaną
  aplikację Discord używaną przez przyszły onboarding serwerów,
- `ECONIFY_DISCORD_BOT_TOKEN` - opcjonalny token runtime bota; portal nie
  wyświetla go ani nie zapisuje w bazie,
- `ECONIFY_DISCORD_CALLBACK_URL` - callback instalacji Econify,
- `ECONIFY_DISCORD_BOT_PERMISSIONS` - minimalną liczbową maskę uprawnień.

Bot wysyła:

```http
POST /api/econify/events
X-Econify-Token: <sekret>
Content-Type: application/json
```

```json
{
  "event_id": "discord-interaction-unikalne-id",
  "guild_id": "123456789012345678",
  "user_id": "234567890123456789",
  "type": "daily",
  "amount": 250,
  "experience": 1200,
  "level": 3,
  "description": "Nagroda /daily"
}
```

`event_id` jest kluczem idempotencji. Powtórne wysłanie tego samego zdarzenia nie
zmienia salda drugi raz. Obsługiwane typy to `daily`, `work`, `vip_daily`,
`transfer_in`, `transfer_out` i `adjustment`.

Panel Ownera pokazuje jedynie stan kompletności ustawień. Nie zwraca wartości
tokenów, sekretu klienta ani ścieżki wskazanej dla środowiska testowego.

Bot zgłasza swoją obecność na serwerze osobnym endpointem:

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

`action=installed` tworzy lub reaktywuje tenant serwera. `action=removed` oznacza
tenant jako nieaktywny. Dopiero po takim zgłoszeniu zweryfikowany właściciel albo
administrator Discord może połączyć konto miniPORTAL z serwerem i przejść do
ustawień Econify.

## Onboarding serwera Discord

Panel nie przyjmuje ręcznie Guild ID i nie tworzy tenantów z formularza.
`Pobierz moje serwery Discord` uruchamia osobny Authorization Code + PKCE z
zakresami `identify guilds`. Moduł pobiera profil i listę `/users/@me/guilds`,
zachowuje tylko serwery z Owner, Administrator albo Manage Guild, a token
użytkownika natychmiast odrzuca.

Lista jest przechowywana w sesji przez 10 minut. Dla serwera bez zgłoszenia bota
użytkownik widzi link `Zaproś Econify na serwer`. Dla serwera już zgłoszonego
przez bota może połączyć konto z tenantem jako `guild_owner` albo `guild_admin`
i przejść do ustawień ekonomii. Przycisk zaproszenia używa oficjalnego flow
Discord `bot applications.commands`, blokuje wybór do zweryfikowanego Guild ID
i prosi wyłącznie o maskę `ECONIFY_DISCORD_BOT_PERMISSIONS`.

W Discord Developer Portal trzeba dodać dokładną wartość
`ECONIFY_DISCORD_CALLBACK_URL` do Redirects aplikacji Econify.

Sekretnych kodów sklepu nie należy przechowywać w miniPORTAL. Pole referencji
wskazuje identyfikator roli albo bezpieczny klucz rekordu należącego do bota.

## Granice modułu

Sklep i giełda są częścią jednego modułu, ponieważ zakup, saldo i historia muszą
być rozliczane w jednej transakcji bazodanowej. Tabele mają osobne prefiksy
`econify_shop_*` i `econify_market_*`, więc późniejsze wydzielenie interfejsów lub
osobnego procesu wyceny nie wymaga zmiany danych gracza.
