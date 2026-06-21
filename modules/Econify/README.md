# Econify Control Center

Dedykowany moduł miniPORTAL dla wieloserwerowego bota ekonomicznego Econify.
Wersja 1.0.0 obejmuje trzy niezależne poziomy dostępu:

- Owner/Administrator miniPORTAL: funkcje platformy, wartości domyślne ekonomii,
  język, limit Freemium, plany i tworzenie tenantów Discord.
- Właściciel lub administrator serwera Discord: waluta, podatek, VIP daily,
  członkowie, katalog sklepu i aktywa rynku wyłącznie własnego serwera.
- Gracz: saldo, EXP, poziom, historia, zakupy oraz portfel giełdowy wyłącznie
  w serwerach, z którymi jego konto zostało jawnie powiązane.

## Integracja bota

Token endpointu należy ustawić poza repozytorium jako `ECONIFY_API_TOKEN`, a w
`config/config.php` odwzorować go na `modules.econify_api_token`. Bot wysyła:

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

Sekretnych kodów sklepu nie należy przechowywać w miniPORTAL. Pole referencji
wskazuje identyfikator roli albo bezpieczny klucz rekordu należącego do bota.

## Granice modułu

Sklep i giełda są częścią jednego modułu, ponieważ zakup, saldo i historia muszą
być rozliczane w jednej transakcji bazodanowej. Tabele mają osobne prefiksy
`econify_shop_*` i `econify_market_*`, więc późniejsze wydzielenie interfejsów lub
osobnego procesu wyceny nie wymaga zmiany danych gracza.
