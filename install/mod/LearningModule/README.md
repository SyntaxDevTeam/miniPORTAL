# Moduł edukacyjny miniPORTAL

Ten pakiet pokazuje kompletny, minimalny moduł zgodny z architekturą projektu.
Nie jest ładowany automatycznie, ponieważ znajduje się poza `modules/`.

## Wymagania

- PHP 8.4 lub nowszy; docelowe środowisko projektu to PHP 8.5,
- aktywna baza MySQL/MariaDB przez `CrudApp`,
- zainstalowany i aktywny `core_auth`,
- administrator z uprawnieniami `modules.install`, `modules.toggle`,
  `modules.remove`, `learning.view` i `learning.manage`,
- jawny plik `factory.php` zadeklarowany w manifeście,
- pochodzenie pakietu i podpis RSA-SHA256 zaufanego wydawcy.

## Instalacja ćwiczeniowa

1. Skopiuj katalog `LearningModule` do `modules/LearningModule`.
2. Otwórz `/admin/modules`.
3. Sprawdź manifest, zależności i stan „Wykryty”.
4. Sprawdź status „podpis zweryfikowany”.
5. Kliknij „Zainstaluj”.
6. Otwórz `/admin/learning` i utwórz przykładowy wpis.

Manager wykona `install.sql`, zapisze moduł w `modules_config`, a istniejące pliki
z `migrations/` oznaczy jako stan bazowy. Dzięki temu pełny `install.sql` zawsze
opisuje najnowszy schemat świeżej instalacji.

## Struktura

- `info.json` – identyfikator, wersja, wymagania i pliki cyklu życia,
- `signature.json` – podpis oraz SHA-256 wszystkich plików pakietu,
- `LearningModule.php` – trasy, ACL, CSRF, menu i składanie widoku,
- `LearningRepository.php` – operacje bazodanowe przez `CrudApp`,
- `LearningEntry.php` – niezmienny model danych,
- `install.sql` – pełny schemat aktualnej wersji,
- `migrations/` – zmiany dla już zainstalowanych starszych wersji,
- `uninstall.sql` – świadome usunięcie tabel i uprawnień,
- `factory.php` – deklaratywna fabryka wykonywana dopiero po aktywacji,
- `MODULE_GUIDE.md` – zasady projektowania, aktualizacji i bezpieczeństwa.

## Test cyklu życia

1. Wyłącz moduł w managerze.
2. Wybierz „Odinstaluj, zachowaj dane”.
3. Przywróć moduł – `install.sql` nie zostanie wykonany ponownie.
4. Ponownie wyłącz moduł.
5. Wybierz „Odinstaluj i usuń dane” – manager wykona `uninstall.sql`.

Moduł chroniony albo wymagany przez inny zainstalowany moduł nie może zostać
odinstalowany.

Po każdej zmianie zawartości pakiet trzeba ponownie podpisać prywatnym kluczem
wydawcy. Core przechowuje wyłącznie klucz publiczny i nie może samodzielnie tworzyć
ważnych wydań.

Podpis zawiera czas `signed_at`, dzięki czemu rotacja klucza zachowuje możliwość
weryfikacji starszych wydań. Klucz oznaczony jako `revoked` blokuje pakiet niezależnie
od poprawności kryptograficznej podpisu.
