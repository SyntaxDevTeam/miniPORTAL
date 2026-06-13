# SPECYFIKACJA TECHNICZNA I PLAN ROZWOJU: miniPORTAL

## 1. Cel projektu

miniPORTAL to modułowy system typu mini-CMS, zbudowany w czystym PHP 8.5 bez frameworków, zgodny z zasadą Outside-In: najpierw tworzymy i testujemy wizualne abstrakcje frontendowe, a dopiero potem implementujemy logikę backendową i mechanizmy systemowe.

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
a operacje uwierzytelniania i ACL trafiają do `auth_events`.

#### 4.2 Moduł stron statycznych
- CRUD dla stron przez `CrudApp`
- unikalny slug i publiczna trasa opublikowanej strony
- stany `draft` i `published` oraz granularne uprawnienia `pages.*`
- integracja z edytorem WYSIWYG (np. TinyMCE lub CKEditor 5)
- opcjonalny autozapis do localStorage / bazy danych

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

- cache szablonów przez output buffering (ob_start(), ob_get_contents())
- zapis statycznych fragmentów strony do katalogu cache/
- ograniczenie liczby zapytań do bazy danych
- indeksowanie kolumn takich jak slug, category_id, created_at
- FULLTEXT dla wyszukiwarki, jeśli zostanie zaimplementowana

### 5.3 Propozycje autorskie do przyszłego rozwoju

1. System haków i filtrów (Hooks API)
   - moduł może wstrzyknąć własne zachowanie do innego modułu bez modyfikacji jego źródeł

2. Przyjazne adresy URL (Slug Router)
   - zamiast index.php?module=articles&id=5
   - system ma mapować adresy typu /artykuly/nazwa-artykulu

3. Wbudowany moduł logów (Audit Log)
   - zapis nieudanych logowań, instalacji modułów, zmian konfiguracyjnych
   - logi do pliku lub osobnej tabeli SQL

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
9. `articles`: przykład niezależnego modułu.

### Krok 6: uruchomienie systemu modularnego
1. Stabilizacja `ModuleInterface` na podstawie działających modułów.
2. Walidacja `info.json`, zależności i wersji.
3. Instalator oraz migracje bazodanowe.
4. Konfiguracja modułów w `modules_config`.
5. Rejestracja i wyłączanie tras, menu oraz uprawnień.
6. Aktualizacja i odinstalowanie modułu.
7. Ochrona modułów stałych przed wyłączeniem i usunięciem.
8. Audit log wszystkich operacji managera.

---

## 7. Zasady pracy zespołowej i rozwoju

- każda funkcjonalność powinna być zaprojektowana najpierw jako komponent wizualny
- każda zmiana w szablonie musi być odzwierciedlona w metodach ThemeInterface
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
