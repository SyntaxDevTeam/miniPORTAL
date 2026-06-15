# Instrukcja pisania modułów

## 1. Kontrakt obowiązkowy

Każdy moduł wykonawczy:

1. implementuje `ModuleInterface`,
2. ma poprawny `info.json`,
3. ma identyczne `id`, `version`, zależności i flagę ochrony w kodzie i manifeście,
4. jest tworzony przez fabrykę Core albo własny `factory.php` zadeklarowany w manifeście,
5. rejestruje trasy i menu, ale nie generuje HTML poza `ThemeInterface`,
6. używa `Request`, ACL, CSRF i audit logu,
7. korzysta z `CrudApp` jako fasady bazy.

## 2. Manifest

Dozwolony identyfikator to małe litery, cyfry i podkreślenia. Wersja używa
SemVer. `requires.modules` określa kolejność uruchamiania i blokuje usunięcie
zależności.

```json
{
  "id": "learning_module",
  "version": "1.1.0",
  "protected": false,
  "factory": "factory.php",
  "install": "install.sql",
  "uninstall": "uninstall.sql"
}
```

Brak `uninstall.sql` blokuje trwałe usunięcie danych, ale nadal pozwala
odinstalować moduł z ich zachowaniem.

Fabryka pakietu nie jest wykonywana podczas skanowania katalogu. Administrator
najpierw zatwierdza instalację, a Core ładuje `factory.php` dopiero dla modułu
zainstalowanego i aktywnego.

## 3. Bezpieczeństwo

- Nie używaj `$_GET`, `$_POST`, `$_SERVER` ani `$_SESSION`.
- Każde POST musi walidować CSRF.
- Każda trasa panelu musi przejść przez `AdminAccessGate`.
- Nie zapisuj sekretów, tokenów ani pełnych adresów IP.
- Nie umieszczaj HTML, klas CSS ani JavaScript w logice modułu.
- Ogranicz długości danych przed zapisem.
- Uprawnienia twórz w `install.sql` i usuwaj w `uninstall.sql`.

## 4. Aktualizacja

Aktualizacja jest dozwolona wyłącznie, gdy wersja manifestu jest wyższa od
wersji zapisanej w `modules_config`.

Przykład przejścia z `1.1.0` do `1.2.0`:

1. zmień wersję w `info.json` i metodzie `version()`,
2. dodaj nowy, niezmienny plik `migrations/20260701_feature.sql`,
3. zaktualizuj `install.sql`, aby świeża instalacja zawierała już nowy schemat,
4. nie edytuj wykonanych migracji – ich SHA-256 jest zapisane w bazie,
5. uruchom akcję „Aktualizuj” w managerze.

Manager sprawdza wszystkie sumy przed pierwszym SQL. MySQL może zatwierdzać DDL
automatycznie, więc jedna migracja powinna być mała i niezależnie weryfikowalna.

## 5. Odinstalowanie

Moduł musi być najpierw wyłączony. Dostępne są dwa tryby:

- zachowanie danych – kod, trasy i menu pozostają nieaktywne, tabele zostają,
- trwałe usunięcie – wykonywany jest jawny `uninstall.sql`.

Przywrócenie zachowanych danych wymaga manifestu w tej samej lub wyższej wersji.
Manager blokuje niejawny downgrade schematu.

Nie usuwaj katalogu modułu przed zakończeniem operacji w managerze. Moduły
chronione i zależności innych zainstalowanych modułów są blokowane.

## 6. Weryfikacja

```bash
php -l modules/LearningModule/LearningModule.php
php -l modules/LearningModule/LearningRepository.php
php -l modules/LearningModule/LearningEntry.php
php tests/run.php
```

Sprawdź dodatkowo instalację, wyłączenie, przywrócenie z danymi i trwałe
odinstalowanie na bazie testowej.
## Pochodzenie i podpis pakietu

Zewnętrzny moduł z własną fabryką deklaruje w `info.json` pola `origin` oraz
`signature`. Plik podpisu zawiera mapę SHA-256 całej zawartości i podpis RSA-SHA256.
Klucz publiczny wydawcy musi być jawnie dodany do `config/module_publishers.php`.

Pakiet podpisuje się poza serwerem produkcyjnym:

```bash
php bin/sign-module.php install/mod/LearningModule /bezpieczna/sciezka/private.pem syntaxdevteam-learning-2026
```

Prywatnego klucza nie wolno umieszczać w module, repozytorium ani katalogu WWW.
