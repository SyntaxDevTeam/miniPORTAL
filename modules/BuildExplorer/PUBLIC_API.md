[Otwórz interaktywną konsolę Swagger UI](/builds/api) · [Specyfikacja OpenAPI 3.1 — JSON](/api/v2/builds/openapi) · [Katalog projektów](/api/v2/builds/projects) · [Zbiorczy katalog wydań](/api/v2/builds/catalog)

## Jak korzystać z konsoli

Na stronie [Konsola API](/builds/api) rozwiń endpoint, kliknij **Try it out**, uzupełnij parametry i wybierz **Execute**. Poniżej zobaczysz adres zapytania, gotowe polecenie curl, status HTTP i rzeczywisty wynik JSON. Przykładowe odpowiedzi w sekcji Example Value są ilustracją — wynik bieżącego zapytania jest w Server response.

Zacznij od **Lista projektów** i odczytaj `slug` (np. `punisherx`). **Wersja projektu** oznacza numer pluginu, np. `1.8.0`, a `game_version` oznacza wersję Minecrafta, np. `26.3`. **Wydanie** to konkretna publikacja, która może zawierać kilka **plików** dla różnych platform. Aby dobrać plik, przejdź do **Sprawdź plik wybrany przyjaznym adresem** i podaj projekt, platformę oraz kanał. Pobieranie binarnego pliku jest osobnym endpointem **Pobierz najnowszy build — przyjazny adres**.

## Przyjazny adres: latest albo konkretny numer wersji

```text
GET /api/v2/builds/{project}/{platform}/{game_version}/{channel}/{version}
```

Ostatni segment `version` może mieć wartość **latest** albo numer pluginu, np. **1.7.3**. Wszystkie parametry są w ścieżce, bez znaku zapytania.

```sh
# Najwyższa dostępna wersja projektu i jej ostatni zgodny build
curl -fL 'https://syntaxdevteam.pl/api/v2/builds/punisherx/paper/26.3/release/latest' -o PunisherX.jar
# Ostatni build wyłącznie wersji 1.7.3 — o ile jest zgodny z Paper i MC 26.3
curl -fL 'https://syntaxdevteam.pl/api/v2/builds/punisherx/paper/26.3/release/1.7.3' -o PunisherX.jar
# Same metadane wybranego pliku, bez pobierania JAR-a
curl -fsS 'https://syntaxdevteam.pl/api/v2/builds/punisherx/paper/26.3/release/latest/info'
# Proxy bez zadeklarowanych wersji Minecrafta
curl -fL 'https://syntaxdevteam.pl/api/v2/builds/punisherx-proxy-bridge/velocity/any/release/latest' -o ProxyBridge.jar
```

`26.3` określa Minecrafta, a końcowe `1.7.3` określa wersję pluginu. `any` jawnie wyłącza filtrowanie Minecrafta; używaj go, gdy nie chcesz sprawdzać zgodności z konkretną wersją gry, np. dla proxy bez deklaracji MC. Kanał to `release`, `snapshot`, `dev` lub `wip`.

**Reguła latest:** najpierw filtrujemy projekty i pliki według publikacji, dostępu, platformy, MC i kanału. Dopiero wśród zgodnych plików wybieramy najwyższą wersję. Porównanie numerycznych części etykiet realizuje PHP `version_compare`: `1.10.0` jest wyższe niż `1.9.0`, a `2.0.0-R0.2-SNAPSHOT` niż `2.0.0-R0.1-SNAPSHOT`. Początkowe `v` oraz metadane `+build` nie zmieniają pierwszeństwa. To kontrakt dla etykiet wersji projektu, nie wymóg ścisłego formatu SemVer.

**Ostatni build w wybranej wersji:** decyduje czas publikacji (następnie CI/utworzenia), potem większe ID wydania, preferowana główna platforma i większe ID artefaktu. Nie porównujemy tekstowego pola build_number jako liczby. Dla numeru podanego w URL nie ma fallbacku do innej wersji. Brak zgodnego pliku daje 404, a nie pobranie innej wersji. Prywatny projekt wymaga sesji użytkownika; token CI nie daje dostępu do odczytu.

Pobieranie zwraca 302 do istniejącego handlera pliku, z ponowną kontrolą publikacji i uprawnień oraz nagłówkami private/no-store. W curl wymagane jest `-L`. `/info` zwraca standardową odpowiedź `api`, `data` z metadanymi artefaktu.

Starsze endpointy `/projects/{project}/latest` i `/latest/download` nadal używają kolejności dat publikacji dla zgodności istniejących integracji. **Aby dostać najwyższą zgodną wersję projektu, używaj nowej przyjaznej ścieżki.** Parametry query nie zmieniają wyboru nowej ścieżki.

## Szybki start

Baza API: `https://syntaxdevteam.pl/api/v2/builds`. Odczyt publicznych projektów nie wymaga klucza. Wszystkie adresy poniżej obsługują GET i HEAD. Endpointy zwracają JSON UTF-8, a pobieranie najnowszego pliku zwraca przekierowanie HTTP 302.

```sh
# Katalog nazw i identyfikatorów projektów
curl -fsS 'https://syntaxdevteam.pl/api/v2/builds/projects'
# Kompletny pakiet metadanych wydań, wraz ze wszystkimi plikami każdej platformy
curl -fsS 'https://syntaxdevteam.pl/api/v2/builds/catalog?per_page=100'
# Informacje o projekcie i zadeklarowanej zgodności
curl -fsS 'https://syntaxdevteam.pl/api/v2/builds/projects/punisherx'
# Pobierz najnowszy zgodny Release dla Paper i Minecraft 26.3
curl -fL 'https://syntaxdevteam.pl/api/v2/builds/punisherx/paper/26.3/release/latest' -o PunisherX.jar
```

Zastąp `punisherx` slugiem otrzymanym z katalogu. Pusty katalog lub brak zgodności to poprawny stan — przykład nie gwarantuje istnienia wskazanego wydania.

## Mapa endpointów

W tabeli `P` oznacza `/api/v2/builds/projects/{project}`. `{project}` to slug, `{version}` to dokładna wersja pluginu/aplikacji zakodowana jako segment URL, a identyfikatory wydań i plików to dodatnie liczby z odpowiedzi API.

| GET / HEAD | Zawartość |
| --- | --- |
| `/api/v2/builds` | Punkt wejścia i linki do katalogu oraz dokumentacji |
| `/api/v2/builds/{project}/{platform}/{game_version}/{channel}/{version}` | Przyjazne pobranie: version = latest albo numer pluginu |
| `/api/v2/builds/{project}/{platform}/{game_version}/{channel}/{version}/info` | Metadane pliku wybranego przyjaznym adresem |
| `/api/v2/builds/openapi` | Specyfikacja OpenAPI 3.1 do importu w narzędziach API |
| `/api/v2/builds/projects` | Stronicowana lista nazw, slugów, identyfikatorów, liczników wydań i linków projektów |
| `/api/v2/builds/catalog` | Zbiorczy pakiet: stronicowane wydania wszystkich dostępnych projektów, każde ze wszystkimi artefaktami |
| `P` | Pojedynczy projekt, nazwa, opis, ikona, stan realizacji, licznik wydań, dostępne kanały, platformy, rodziny, wersje MC i Java |
| `P/options` | Same dostępne platformy, wersje MC, kanały, rodziny i wersje Java |
| `P/versions` | Stronicowane wersje pluginu/aplikacji, liczba wydań i czas ostatniej publikacji |
| `P/versions/{version}` | Pełne wydania konkretnej wersji ze wszystkimi platformami, stronicowane |
| `P/releases` | Historia wydań projektu ze wszystkimi artefaktami |
| `P/releases/{release}` | Jedno kompletne wydanie: wersja, kanał, numer buildu, opis zmian, CI i artefakty |
| `P/artifacts` | Stronicowana lista plików z metadanymi zgodności i linkami pobierania |
| `P/artifacts/{artifact}` | Pojedynczy plik wraz z wersją, kanałem i numerem buildu |
| `P/latest` | Metadane najnowszego pliku spełniającego filtry; wymaga `platform` i `channel` |
| `P/latest/download` | Stały adres przekierowujący do pobrania najnowszego zgodnego pliku; wymaga `platform` i `channel` |
| `/builds/download/v2?artifact={artifact}` | Pobranie konkretnego artefaktu; ponownie sprawdza publikację i dostęp |

„Pełny pakiet” oznacza JSON całego logicznego wydania wraz z metadanymi wszystkich jego plików. Pliki binarne pobiera się osobno z `artifacts[].links.download`. Endpointy nie tworzą archiwum ZIP i nie kodują binariów w JSON. Pobranie wszystkich stron `/catalog` daje kompletny zbiór opublikowanych wydań v2.

## Filtry i stronicowanie

Listy wersji, wydań i artefaktów oraz katalog przyjmują:

| Parametr | Znaczenie / przykład |
| --- | --- |
| `project` | Dokładny slug; przydatny dla `/catalog`. Slug w ścieżce ma pierwszeństwo. |
| `version` | Dokładna wersja pluginu/aplikacji, np. `1.8.0`; niezależna od Minecrafta |
| `channel` | `release`, `snapshot`, `dev` lub `wip` |
| `platform` | Zadeklarowana zgodna platforma: np. `paper`, `spigot`, `folia`, `velocity`, `bungeecord`, `windows-x64` |
| `game_version` | Dokładna deklarowana wersja Minecrafta, np. `26.3` lub `1.21.11` |
| `platform_family` | `server`, `proxy` lub `other` (aplikacje) |
| `java_version` | Dokładna zadeklarowana wymagana Java, liczba 8–99; nie jest testem „maksymalnej wersji Java klienta” |
| `build_number` | Dokładny numer buildu jako tekst |
| `page` | Numer strony od 1; domyślnie 1; maksymalnie 1000000 |
| `per_page` | Rozmiar strony 1–100; domyślnie 25 |

`/projects` obsługuje paginację i dokładny filtr `project`; pozostałe filtry dotyczą zasobów wydań i plików. `P` i `P/options` opisują cały opublikowany projekt. Wszystkie filtry zgodności muszą pasować **do tego samego pliku**. W `/catalog` i listach wydań filtry kwalifikują wydanie, lecz odpowiedź nadal zawiera wszystkie jego platformy. `/artifacts` zwraca wyłącznie dopasowane pliki.

```json
{
  "api": "v2",
  "data": [],
  "pagination": {
    "page": 1,
    "per_page": 25,
    "total": 0,
    "next": null,
    "previous": null
  }
}
```

Pojedynczy zasób ma obiekt w `data` i nie ma `pagination`. Podążaj za `pagination.next`, aż osiągnie `null`. Linki zaczynające się od `/` są względne wobec źródła strony (np. `https://syntaxdevteam.pl`), nie względem katalogu bieżącego endpointu. Brak dopasowań listy zwraca 200 i pustą tablicę. Nieistniejący pojedynczy zasób, wersja lub `latest` zwraca 404. Zmiany katalogu podczas pobierania stron mogą przesuwać pozycje; identyfikatory pozwalają usunąć duplikaty.

## Metadane i zgodność

Wydanie zawiera `id`, `project` (`id`, `name`, `slug`), `version`, `channel`, `build_number`, `published_at`, `changelog`, `changelog_format`, `ci`, `artifacts` oraz `links`. Czas publikacji jest ISO 8601 w UTC (`Z`) albo `null`. `ci` obejmuje numer uruchomienia, czas, branch, SHA commita i listę commitów; ręczne wydania mogą nie mieć danych CI.

Artefakt zawiera `id`, `release_id`, `platform`, `platform_family`, `compatible_platforms`, `game_versions`, `java_version`, `filename`, `size` w bajtach, `sha256` oraz `links.self` i `links.download`. Pojedyncze artefakty i wyniki `latest` zawierają też `project`, `version`, `channel` i `build_number`. Rozmiar, SHA lub Java mogą być `null`, gdy nie zostały dostarczone. Weryfikuj SHA-256 po pobraniu.

`platform` jest główną platformą fizycznego pliku; `compatible_platforms` jest listą deklaracji zgodności tego samego pliku. Nie należy mnożyć JAR-ów na jej podstawie. `game_versions` dotyczy Minecrafta, a nie numeru wydania Velocity/BungeeCord. Portal nie zgaduje zgodności z nieprzekazanymi wersjami proxy ani nie rozszerza automatycznie deklaracji na inne wersje MC. Puste `game_versions` proxy lub aplikacji oznacza brak deklaracji; pomiń wtedy filtr Minecrafta. Jawne `game_version` wymaga dokładnego wpisu również dla proxy.

## Starsze endpointy query — ostatnia publikacja

**Nie musisz znać numeru pluginu ani buildu.** Podaj projekt w ścieżce oraz `platform`, `game_version` (Minecraft) i `channel`. **Pomiń `version` i `build_number`** — wtedy API przeszukuje wszystkie opublikowane wersje projektu w wybranym kanale i wybiera ostatnio opublikowany zgodny plik.

Gotowy adres dla PunisherX, Paper, Minecraft 26.3 i Release:

```text
https://syntaxdevteam.pl/api/v2/builds/projects/punisherx/latest/download?platform=paper&game_version=26.3&channel=release
```

```sh
curl -fL 'https://syntaxdevteam.pl/api/v2/builds/projects/punisherx/latest/download?platform=paper&game_version=26.3&channel=release' -o PunisherX.jar
```

`game_version=26.3` to wersja gry. `version=1.8.0` oznaczałoby wersję pluginu i ograniczyłoby wybór wyłącznie do niej. Dla innego kanału zmień `release` na `snapshot`, `dev` lub `wip`. Dla proxy bez deklarowanych wersji MC pomiń `game_version`.

## Sprawdzenie metadanych i przypięcie konkretnej wersji

```sh
# Najpierw sprawdź metadane
curl -fsS 'https://syntaxdevteam.pl/api/v2/builds/projects/punisherx/latest?platform=paper&channel=release&game_version=26.3'
# Przypnij wersję pluginu, pozostawiając automatyczny wybór jej najnowszego buildu
curl -fL 'https://syntaxdevteam.pl/api/v2/builds/projects/punisherx/latest/download?platform=paper&channel=release&version=1.8.0&game_version=26.3' -o PunisherX.jar
# Proxy bez deklaracji wersji MC
curl -fL 'https://syntaxdevteam.pl/api/v2/builds/projects/punisherx-proxy-bridge/latest/download?platform=velocity&channel=release' -o ProxyBridge.jar
```

„Najnowszy” oznacza najpóźniejszy czas publikacji (następnie czas CI, następnie utworzenia), a przy remisie największe ID logicznego wydania. To kolejność publikacji, nie sortowanie numerów semver. W jednym wydaniu preferowany jest artefakt o głównej platformie zgodnej z filtrem, następnie największe ID artefaktu. Kanał i platforma są obowiązkowe, by nie pobrać przypadkowo WIP lub pliku innego środowiska. Pozostałe filtry są opcjonalne i nie mają automatycznych fallbacków. Nie ma zgodnego pliku — 404, bez przekierowania do innej wersji lub kanału.

Adres `latest/download` jest zmienny i zwraca `302` z `Cache-Control: private, no-store`. Klient musi obsługiwać przekierowania (`curl -L`). Docelowy adres ponownie weryfikuje uprawnienia i obecność pliku. Wycofanie wydania lub retencja może spowodować 404 między odczytem metadanych a pobieraniem. Pobieranie nie wykonuje plików.

## Opisy zmian Release

Opis jest wspólny dla całego wydania i widoczny na stronie artefaktów oraz w JSON `changelog` (`changelog_format: text`). Zwykły tekst zachowuje podziały wierszy; HTML jest wyświetlany jako tekst.

W panelu Build Explorer wybierz projekt, a przy wydaniu **Opis zmian**. Edytor obsługuje także wydania pochodzące z CI, wymaga `builds.manage`, CSRF i aktualnej rewizji. Release wymaga niepustego opisu podczas zapisu tym edytorem. Ręczny upload ma własne pole Opis zmian, wymagane przy publikacji Release. Limit wynosi 10000 znaków.

CI może przekazać jawne `changelog` w JSON `metadata`:

```json
{
  "changelog": "Nowości:\n- Dodano obsługę Minecraft 26.3.\nPoprawki:\n- Usprawniono synchronizację danych."
}
```

Jawne `changelog` kanału Release musi być niepuste. Pozostałe wymagane pola uploadu pozostają obowiązkowe. Dla zgodności dotychczasowe CI bez `changelog` używa opisów commitów; starsze wydania mogą mieć pusty opis. Ponowny upload tego samego wydania aktualizuje opis z metadanych CI — po ręcznej korekcie uwzględnij ją także w źródle CI. Poszczególne zadania matrix powinny wysyłać ten sam opis.

## Dostęp, błędy i integracje

API pokazuje tylko opublikowane projekty i wydania. Projekty wymagające logowania są dostępne przez istniejącą sesję WWW. Anonimowy klient dostaje identyczne 404 dla ukrytego i nieistniejącego zasobu; listy pomijają niedostępne rekordy. Token uploadu CI nie zastępuje logowania i nie nadaje prawa do odczytu prywatnych projektów. Nie przekazuj tokenów w URL. Odpowiedzi mają `private, no-store` i `Vary: Cookie`. API nie udostępnia poświadczeń, kluczy magazynu ani lokalnych ścieżek plików.

| Status | Znaczenie |
| --- | --- |
| 200 | JSON lub konkretne pobranie |
| 302 | Pobieranie latest: przekierowanie do konkretnego pliku |
| 400 | Błędny kanał, rodzina, platforma, liczba lub brak wymaganych filtrów latest |
| 404 | Brak dostępnego pojedynczego zasobu lub pasującego pliku |
| 500 | Tymczasowy błąd katalogu, bez szczegółów wewnętrznych |

```json
{"api":"v2","error":{"code":"not_found","message":"No accessible published resource matches the request."}}
```

Nowy kontrakt dotyczy modelu v2. Starsze buildy `project_builds` zachowują stare strony i pobieranie, ale nie są automatycznie kopiowane do katalogu v2. Dotychczasowe `/api/v2/builds/{project}/options`, `/resolve` i endpointy CI pozostają kompatybilne z dotychczasowymi klientami; ich format odpowiedzi jest historyczny. Nowe integracje powinny używać zasobów `projects` opisanych powyżej. Odczyt nie wymaga CSRF. API nie włącza CORS dla obcych domen — integracje serwerowe korzystają bezpośrednio z HTTPS.


## External public binary storage (2.10.0)

Download URLs returned by this API remain miniPORTAL URLs. They may redirect to
a verified GitHub Release asset. Follow redirects and verify the SHA-256/size
from the API as before. Release IDs, artifact IDs and latest/version resolution
are unchanged. Existing project ACL is evaluated by miniPORTAL before redirect.
Storage and migration contract: docs/GITHUB_BUILD_STORAGE.md in the source installation.
