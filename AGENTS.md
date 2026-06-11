<<<<<<< ours
# Instrukcje dla agentów AI — miniPORTAL

Ten plik określa, jak agenci AI mają pracować nad projektem miniPORTAL. **Plan projektu jest źródłem prawdy** — zawsze zaczynaj od dokumentacji w katalogu `docs/`.

---

## 1. Źródło prawdy

| Dokument | Zawartość |
|----------|-----------|
| [docs/TECHNICAL_SPECIFICATION.md](docs/TECHNICAL_SPECIFICATION.md) | Architektura, fazy rozwoju, plan wykonawczy, zasady bezpieczeństwa |

Przed rozpoczęciem pracy:

1. Przeczytaj odpowiednie sekcje specyfikacji technicznej.
2. Sprawdź sekcję **„Status realizacji planu”** poniżej — nie duplikuj ukończonych zadań.
3. Wykonuj **następny niewykonany krok** zgodnie z kolejnością faz (Outside-In: najpierw frontend, potem backend).

Jeśli w repozytorium istnieje kod sprzeczny ze specyfikacją (np. `theme/` zamiast `templates/`), **nowa praca musi iść zgodnie ze specyfikacją**. Istniejący kod traktuj jako materiał do migracji lub refaktoryzacji — nie rozbudowuj go w sprzecznej strukturze bez uzasadnienia w specyfikacji.

---

## 2. Zasady pracy agenta

### Podejście Outside-In

1. **Faza 1** — prototyp wizualny (HTML/stylebook, Bootstrap 5)
2. **Faza 2** — abstrakcja szablonu (`ThemeInterface`, klasa `Theme`)
3. **Faza 3** — rdzeń systemu (autoloader, router, baza, security, bootstrap)
4. **Faza 4** — stałe moduły (`core_auth`, `core_pages`)
5. **Faza 5** — manager modułów (system „Lego”)

Nie przeskakuj faz. Nie implementuj backendu, zanim nie ma gotowego stylebooka i interfejsu szablonu.

### Architektura

- Trzy warstwy: **Core** → **Modules** → **Templates**
- Moduły nie znają szczegółów HTML — korzystają z metod szablonu
- PSR-4 autoloader dla `core/` i `modules/`
- Bezpieczeństwo: `htmlspecialchars`, CSRF, prepared statements, brak bezpośredniego dostępu do `$_GET`/`$_POST` bez filtrowania

### Zakres zmian

- Minimalizuj diff — tylko to, co wynika z bieżącego kroku planu
- Dopasuj się do konwencji istniejącego kodu w obrębie tej samej warstwy
- Nie dodawaj testów, komentarzy ani plików pomocniczych, o ile użytkownik tego nie prosi

### Po zakończeniu sesji — obowiązkowa aktualizacja statusu

Na końcu każdej sesji pracy agent **musi** zaktualizować sekcję 3 tego pliku:

1. Oznacz ukończone zadania jako `[x]`.
2. Uzupełnij **„Ostatnia aktualizacja”** (data + krótki opis).
3. W **„Następne kroki”** wpisz 1–3 konkretne zadania na kolejną sesję.
4. Jeśli odkryjesz rozbieżność między kodem a specyfikacją, dodaj wpis w **„Uwagi / blokery”**.

---

## 3. Status realizacji planu

> **Ostatnia aktualizacja:** 2026-06-10 — utworzenie pliku AGENTS.md; projekt na wczesnym etapie, przed realizacją planu ze specyfikacji.

### Faza 0 — dokumentacja i fundament repozytorium

| Status | Zadanie |
|--------|---------|
| [x] | Specyfikacja techniczna (`docs/TECHNICAL_SPECIFICATION.md`) |
| [x] | README z linkiem do dokumentacji |
| [x] | Instrukcje dla agentów AI (`AGENTS.md`) |
| [ ] | Struktura katalogów zgodna z sekcją 2 specyfikacji |
| [ ] | `config/config.php` |
| [ ] | Punkt wejścia `index.php` |

**Stan obecny:** istnieją `config/app.php`, `core/database/`, `core/libs/Medoo.php`, katalog `theme/default/` — to nie jest jeszcze docelowa struktura ze specyfikacji (`templates/`, `modules/`, `cache/` itd.).

### Krok 1 — przygotowanie fundamentu projektu

| Status | Zadanie |
|--------|---------|
| [ ] | Utworzenie struktury katalogów (sekcja 2 specyfikacji) |
| [ ] | Przygotowanie `config/config.php` |
| [ ] | Stworzenie punktu wejścia `index.php` |

### Krok 2 — prototyp wizualny i stylebook

| Status | Zadanie |
|--------|---------|
| [ ] | Plik `templates/default/stylebook.html` |
| [ ] | Komponenty Bootstrap 5: navbar, cards, tables, forms, buttons, alerts, footers |
| [ ] | Dopracowanie CSS i animacji |
| [ ] | Wersja 1 strony głównej SyntaxDevTeam.pl (Faza 1, pkt 3) |

**Stan obecny:** istnieje `theme/default/index.html` i pliki CSS/JS — wymaga migracji do `templates/default/stylebook.html` lub przepisania zgodnie ze specyfikacją.

### Krok 3 — odwzorowanie prototypu w PHP

| Status | Zadanie |
|--------|---------|
| [ ] | Definicja `ThemeInterface` |
| [ ] | Implementacja klasy `Theme` w `templates/default/theme.php` |
| [ ] | Metody: `start_card/end_card`, `render_button`, `render_alert`, `render_form`, `render_table`, `csrf_field` |
| [ ] | Weryfikacja użycia z poziomu modułu bez zależności od HTML |

### Krok 4 — implementacja rdzenia systemu

| Status | Zadanie |
|--------|---------|
| [ ] | Autoloader PSR-4 |
| [ ] | `Router.php` |
| [ ] | `Database.php` (wrapper PDO/Medoo) |
| [ ] | `Security.php` (filtrowanie, CSRF, XSS, sesje) |
| [ ] | `Bootstrap.php` |
| [ ] | `ThemeEngine.php` |

**Stan obecny:** `core/libs/Medoo.php` i `core/database/CrudApp.class.php` — materiał do integracji lub refaktoryzacji w docelową warstwę `core/`.

### Krok 5 — pierwsze moduły

| Status | Zadanie |
|--------|---------|
| [ ] | Moduł stron statycznych (`modules/core_pages/`) |
| [ ] | Moduł autoryzacji i ról (`modules/core_auth/`) — Argon2id, ACL |
| [ ] | Moduł artykułów (`modules/articles/`) jako przykład rozbudowy |

### Krok 6 — system modularny (Lego)

| Status | Zadanie |
|--------|---------|
| [ ] | Manager modułów (skan `modules/`, odczyt `info.json`) |
| [ ] | Instalator (`install.sql`, tabela `modules_config`) |
| [ ] | Aktywacja / deaktywacja modułów przez router |

### Przyszły rozwój (poza MVP)

| Status | Zadanie |
|--------|---------|
| [ ] | Hooks API |
| [ ] | Slug Router (przyjazne URL) |
| [ ] | Audit Log |

---

## 4. Następne kroki (priorytet)

1. **Utworzyć docelową strukturę katalogów** zgodnie z sekcją 2 specyfikacji (`config/`, `core/`, `modules/`, `templates/`, `cache/`, `index.php`).
2. **Przenieść lub przepisać** istniejący prototyp z `theme/default/` do `templates/default/stylebook.html`.
3. **Przygotować `config/config.php`** i minimalny `index.php` jako front controller.

---

## 5. Uwagi / blokery

| Data | Opis |
|------|------|
| 2026-06-10 | Struktura katalogów w repozytorium (`theme/`, `config/app.php`) nie odpowiada jeszcze specyfikacji (`templates/`, `config/config.php`). Wymagana migracja przed dalszym rozwojem. |

---

## 6. Szablon wpisu po sesji agenta

Skopiuj i uzupełnij na końcu pracy:

```markdown
### Sesja: YYYY-MM-DD

**Wykonano:**
- ...

**Zaktualizowano status:** (lista checkboxów zmienionych z [ ] na [x])

**Następne kroki:**
1. ...
2. ...

**Uwagi:** (opcjonalnie)
```

Wpis dodaj nad sekcją „Następne kroki” lub w dedykowanej sekcji historii sesji, jeśli powstanie.
=======
# Instrukcje pracy nad miniPORTAL

## Dokumentacja jest źródłem wymagań

Przed rozpoczęciem każdego etapu pracy oraz przed zmianą zakresu przeczytaj:

1. `README.md` — mapa dokumentacji projektu.
2. `docs/SZKIC.md` — pierwotne założenia produktu i wymaganie maksymalnej modularności.
3. `docs/TECHNICAL_SPECIFICATION.md` — obowiązująca architektura, standardy bezpieczeństwa i plan wykonawczy.

Nie opieraj decyzji wyłącznie na aktualnym kodzie. Jeśli kod, szkic i specyfikacja są niespójne, zatrzymaj implementację, nazwij rozbieżność i wybierz rozwiązanie zgodne ze specyfikacją techniczną albo najpierw zaktualizuj dokumentację.

## Praca etapami

Dla każdego zadania:

1. Wskaż fazę i krok z `docs/TECHNICAL_SPECIFICATION.md`, które realizuje zmiana.
2. Sprawdź zależności i kryteria poprzedniego kroku. Nie rozpoczynaj backendu, jeśli wymagany komponent wizualny lub kontrakt warstwy prezentacji nie został wcześniej przygotowany.
3. Ogranicz zmianę do najmniejszego kompletnego „klocka Lego”, który można niezależnie sprawdzić.
4. Po implementacji porównaj rezultat ponownie z dokumentacją, zwłaszcza z sekcjami dotyczącymi separacji prezentacji, bezpieczeństwa i zasad pracy zespołowej.
5. Jeżeli ukończenie zadania zmienia stan roadmapy, zakres lub decyzję architektoniczną, zaktualizuj odpowiedni plik w `docs/` w tym samym zestawie zmian.

## Reguły architektoniczne

- Stosuj podejście Outside-In: najpierw prototyp i komponent wizualny, potem `ThemeInterface`, a dopiero później logika rdzenia i modułów.
- Oddzielaj HTML i szczegóły motywu od logiki PHP. Moduły nie mogą zależeć od konkretnego frameworka CSS ani implementacji szablonu.
- Traktuj `core/` jako stabilny rdzeń, a nowe funkcje projektuj jako niezależne moduły z jawnymi zależnościami.
- Nie omijaj warstw `Theme`, `Database` i `Security` bez udokumentowanego powodu.
- Nie odczytuj bezpośrednio danych `$_GET`, `$_POST` ani innych danych wejściowych w modułach; najpierw je waliduj i normalizuj.
- Domyślnie koduj dane wyświetlane w HTML za pomocą `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`, używaj przygotowanych zapytań i zabezpieczaj formularze zmieniające stan tokenami CSRF.
- Zachowuj zgodność z PHP 8.5 i nie wprowadzaj frameworka aplikacyjnego ani automatyzacji sprzecznej z założeniami projektu bez zmiany dokumentacji.

## Weryfikacja

- Przed zakończeniem pracy uruchom wszystkie testy i kontrole dostępne w repozytorium.
- Jeżeli repozytorium zawiera `gradlew`, nadaj mu wykonywalność (`chmod +x gradlew`) i zawsze uruchom `./gradlew test --console=plain`.
- Dla zmienionych plików PHP uruchom co najmniej `php -l`; dla statycznego HTML/CSS/JavaScript użyj dostępnych walidatorów lub testu uruchomieniowego.
- Nie deklaruj ukończenia etapu, jeśli nie da się wykazać zgodności z jego kryteriami albo jasno opisać ograniczenia środowiska.
>>>>>>> theirs
