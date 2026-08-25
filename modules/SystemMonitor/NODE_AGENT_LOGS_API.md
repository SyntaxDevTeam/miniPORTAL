# SystemMonitor ↔ Node Agent — log maintenance contract

Ten dokument definiuje minimalny kontrakt potrzebny do bezpiecznego czyszczenia logów systemowych z poziomu miniPORTAL.

## Założenie bezpieczeństwa

miniPORTAL działa jako nieuprzywilejowany użytkownik procesu WWW i **nie może wykonywać operacji usuwania w `/var/log`**. Cała operacja uprzywilejowana należy do lokalnego Node Agenta komunikującego się z miniPORTAL przez istniejący Unix Socket.

Warstwa WWW przekazuje wyłącznie semantyczne parametry polityki. Nie wolno przyjmować z miniPORTAL:

- dowolnej ścieżki pliku lub katalogu,
- polecenia powłoki,
- argumentów `journalctl`, `find`, `rm`, `logrotate` itp.,
- nazwy użytkownika, pod którym ma wykonać się operacja.

Allowlista katalogów, reguły rozpoznawania rotowanych logów, limity i dozwolone operacje muszą należeć do konfiguracji Node Agenta.

## 1. Preview

### Request

```http
GET /v1/system/logs?retention_days=14
```

`retention_days`:

- typ: integer,
- minimum: `1`,
- maksimum: `3650`,
- agent musi walidować zakres niezależnie od miniPORTAL.

### Response 200

```json
{
  "retention_days": 14,
  "sampled_at": 1786982400,
  "candidate_files": 12,
  "candidate_bytes": 1048576000,
  "reclaimable_bytes": 1048576000,
  "protected_bytes": 0,
  "journal_bytes": 2147483648,
  "journal_reclaimable_bytes": 1073741824,
  "preview_truncated": false,
  "candidates": [
    {
      "label": "Apache2 access.log.4.gz",
      "path": "/var/log/apache2/access.log.4.gz",
      "bytes": 52428800,
      "modified_at": 1784000000,
      "deletable": true,
      "source": "rotated_file"
    }
  ],
  "policy": {
    "active_logs_preserved": true,
    "symlinks_preserved": true,
    "allowed_roots": ["/var/log"],
    "journal_supported": true
  }
}
```

`path` w odpowiedzi jest informacyjne. miniPORTAL nigdy nie odsyła go do operacji czyszczenia.

Agent może pominąć opcjonalne pola `journal_*`, jeżeli host nie używa systemd-journald.

## 2. Czyszczenie jako zadanie agenta

Operacja korzysta z istniejącego endpointu zadań:

```http
POST /v1/jobs
Content-Type: application/json
```

```json
{
  "request_id": "550e8400-e29b-41d4-a716-446655440000",
  "action": "system.logs.clean",
  "parameters": {
    "retention_days": 14,
    "include_journal": true
  }
}
```

Dla `system.logs.clean`:

- `server_id` musi być nieobecny,
- jedyne dozwolone parametry to `retention_days` i `include_journal`,
- `retention_days` musi być integerem `1..3650`,
- `include_journal` musi być booleanem,
- wszystkie inne pola muszą skutkować odrzuceniem żądania.

Standardowa odpowiedź job API pozostaje bez zmian:

```json
{
  "job_id": "job_0123456789abcdef0123456789abcdef",
  "status": "queued"
}
```

miniPORTAL monitoruje wynik przez istniejące:

```http
GET /v1/jobs/{job_id}
```

## 3. Polityka plików rotowanych

Agent powinien usuwać wyłącznie pliki znajdujące się w swojej lokalnej allowliście katalogów logów i spełniające wszystkie warunki:

- zwykły plik, nie katalog,
- brak symlinka na dowolnym etapie rozwiązywania ścieżki,
- plik znajduje się wewnątrz dozwolonego root po `realpath`,
- mtime starszy niż zadana retencja,
- nazwa jednoznacznie wskazuje rotację/archiwum logu, np. `.1`, `.2.gz`, `.old`, kontrolowany format daty,
- aktywny plik logu bez sufiksu rotacji jest zachowany,
- katalogi audit/private oraz inne lokalnie chronione ścieżki są wyłączone niezależnie od żądania klienta.

Agent powinien posiadać limity pojedynczej operacji, np. maksymalną liczbę plików i maksymalną sumę usuwanych bajtów. Limity są konfiguracją agenta i miniPORTAL nie może ich zwiększać.

## 4. systemd-journald

Jeżeli `include_journal=true` i host używa journald, agent może wykonać vacuum odpowiadający zatwierdzonej retencji, np. przez bezpośrednie wywołanie `/usr/bin/journalctl` z argumentem skonstruowanym **wyłącznie z uprzednio zwalidowanej liczby dni**.

Nie wolno przekazywać fragmentu komendy pochodzącego od klienta WWW do powłoki. Preferowane jest uruchomienie procesu bez shella (`execve`/odpowiednik języka agenta) z ustaloną ścieżką binarki i osobnymi argumentami.

## 5. Wynik i audit

Po zakończeniu job powinien przejść do `succeeded` lub `failed` zgodnie z obecnym protokołem agenta.

Jeżeli format job API obsługuje pole wyniku, zalecane dane to:

```json
{
  "deleted_files": 12,
  "deleted_bytes": 1048576000,
  "journal_vacuumed": true,
  "journal_reclaimed_bytes": 1073741824
}
```

Agent powinien zapisać zredagowane zdarzenie audytowe bez listy pełnych treści logów i bez danych poufnych.

## 6. Błędy

Zalecane kody błędów:

- `invalid_retention`
- `invalid_parameters`
- `operation_not_supported`
- `journal_not_supported`
- `cleanup_limit_exceeded`
- `cleanup_failed`

Brak nowego API w starszej wersji agenta musi być traktowany przez miniPORTAL jako stan niedostępny, a nie jako sygnał do fallbacku na lokalne `unlink()`.
