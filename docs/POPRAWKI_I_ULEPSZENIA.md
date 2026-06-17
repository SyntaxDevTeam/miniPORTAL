# Wynik testów - obserwacje

## System modułów
1. ~~Obecnie głownym założeniem modulacji jest separacja pozwalajaca na "wrzuć -> zainstaluj -> używaj". Chciałbym aby moduły "Rozszerzenie" dodawały do sekcji ustawień rozszerzone możliwości ustawień dla linków. W tej chwili taką mozliwość ma wyłacznie "Dokumentacja" w dodatku z wyborem Menu głowne lub stopka (tylko na stronie głównej) bez opcji ustawienia nazwy/etykiety linku anie zaznaczenia obu tych elementów. To trzeba zmienić aby każdy z modułów "Rozszerzenie" posiadał taką implementację oraz w niej bardziej zaaawansowane opcje.~~ (gotowe)
2. ~~Możliwość eksportu do zip już zainstalowanych modułów "Rozszerzenie"~~ (gotowe)

## Szablony

Szablon strony głównej a szablon pozostałych elementów to to 2 różne bajki zarówno dla menu i stopki co jest zgodne z założeniami i samą kwestią zawartości, jednakże pewne elelmenty powinny być współne:
1. ~~Nazwa strony~~ (gotowe)
2. ~~Stopka~~ (gotowe)
3. ~~Menu główne - część elementów dostępna wszedzie np.~~ (gotowe)
   -  ~~"Home" - strona główna, dostępna powinna być wszędzie po za samą stroną główną~~
   - ~~Linki do modułów ustawionych w panelu admina w "Ustawienia"~~
   - ~~Zaloguj/Panel~~ (gotowe)
   - ~~Kontakt - aby z każdego miejsca uzytkwonik mógł wejść i sprawdzić jak się skontaktować z zespołem.~~
4. ~~Panel admina -> Ustawienia i Dashboard - obecnie to szerokie na całą stronę ilości informacji z nie wielką doża realnych ustawień.~~ (gotowe - dodano responsywne siatki paneli)
   - ~~Brak możliwości edycji stopki - trzeba koniecznie to zmienić.~~ (gotowe)
   - ~~Szablon i branding można rozdzielic na dwa osobne elementy (będące responsywnie obok siebie w jednej lini)"Branding i SEO" oraz Szablon. Branding i SEO pozwalały na ustawienie więcej niż nazwy strony jej domyślny "nadtytuł" (czymkolwiek to jest) ale na realne wpisy w meta strony takiej jak słowa kluczowe, opis i całą resztę.~~ (gotowe)
   - ~~Wiele podobnych zabiegów można zrobić z informacjami o statusie ustawień gdzie nie ma możliwości konfiguracji z poziomu strony.~~ (gotowe w Dashboardzie i Ustawieniach)
   - ~~Porozrzucane przyciski funkcjonalne w panelach modułów.~~ (gotowe - główne akcje
     modułu trafiają do pełnoszerokiego paska pod nagłówkiem panelu)
5. Obsługa wszystkich stron błędu.
   - ~~Przykładowo obecnie brak ścieżki czy popularne 404 wygląda wizualnie jak niewiadomo jaki błąd a buton "Wróć do dashboardu" nie wiele mówi zwykłemu użytkownikowi~~ (publiczne 404/405 gotowe) ![alt text](image.png)
   - Przyjazne strony błędów znacznei uatrakcyjnią samą stronę
6. Przyjazne linki  - mode_rewrite (gotowe)

## Dashboard
~~Więcej elementów statystyk i informacji o modułach, aktywności użytkowników itp. Dashboard musi byc centrum informacji o stronie gdzie padają decyzję co dalej 😉.~~ (gotowe)

## Przyszłę moduły i pomysły
### Profil użytkownika

~~Obecnie istnieje jakaś namiastka w panelu admina o nazwie "Profil" gdzie występuej tylko "Połączone konta" całość można by zamknąć w osobnym module który by rozszerzał możliwości i przenosił opcje z "Profil" do menu po kliknięciu w nazwę użytkownika~~
![alt text](image-1.png) ~~gdzie możnaby utworzyć rozwijane menu z kilkoma opcjami takimi jak "Pokaż profil", "Edytuj dane", "Połączone konta", "Ustawienia avatara", "Bezpieczeństwo", "Wyloguj" itp.~~
 (gotowe: dropdown użytkownika, widok profilu, edycja danych, ustawienia avatara,
 bezpieczeństwo i przeniesienie połączonych kont poza sidebar)

### Manager SQL
Prosty manager bazy danych al`a mikro-PHPMyAdmin pokazujący baze danych, tabele, kolumny, strukture i dane, przyciski akcji takie jak optymalizacja, wstaw, sql, export/import, usuwanie kolumna tabel, opróżnianie itd.

Korekta architektoniczna gotowa: Manager SQL jest osobnym modułem rozszerzenia
`database_manager`, a nie częścią `system_admin`. Moduł zachowuje adres
`/admin/database`, ma własny manifest, `install.sql` i tabelę historii operacji.

Etap 1 gotowy: bezpieczny podgląd read-only `/admin/database` pokazuje bazę,
listę tabel, rozmiary, przybliżoną liczbę wierszy oraz strukturę kolumn. Operacje
zapisu, SQL, import/export i akcje destrukcyjne wymagają kolejnych etapów z ACL,
CSRF, potwierdzeniami i audytem.

Etap 2 gotowy: widok wybranej tabeli pokazuje również dane rekordów w trybie
read-only z limitem 10-50 wierszy na stronę i prostą paginacją.

Etap 3 gotowy: wybraną tabelę można wyeksportować do CSV z limitem 10 000
rekordów, audytem operacji i neutralizacją formuł arkusza.

Etap 4 gotowy: wybraną tabelę można wyeksportować do pliku SQL zawierającego
`DROP TABLE IF EXISTS`, `CREATE TABLE` i paczkowane `INSERT`; SQL jest domyślnym
formatem eksportu, a CSV zostaje formatem pomocniczym.

Etap 5 gotowy: konsola `/admin/database/query` wykonuje pojedyncze zapytania
read-only `SELECT`, `SHOW`, `DESCRIBE`, `DESC` i `EXPLAIN` z CSRF, audytem oraz
limitem 100 wierszy wyniku.

Etap 6 gotowy: `/admin/database/history` pokazuje paginowaną historię operacji
zapisaną w tabeli `database_manager_history`, a główne akcje Managera SQL są
zebrane w górnym pasku modułu.

Etap 7 gotowy: Manager SQL nie jest już wyłącznie read-only. Dodano ACL
`database.manage`, operacje tabeli `OPTIMIZE`, `CHECK`, `ANALYZE`, `REPAIR`,
`TRUNCATE` i `DROP` z CSRF, audytem, historią i potwierdzeniem dla akcji
destrukcyjnych. Dodano też konsolę zapytań zapisowych z whitelistą pojedynczych
instrukcji i potwierdzeniem `WRITE`.

Etap 8 gotowy: dodano kontrolowany import SQL przez `/admin/database/import`.
Import przyjmuje plik `.sql` albo treść formularza, ma limit 2 MB, wymaga ACL
`database.manage`, CSRF, potwierdzenia `IMPORT`, audit logu i wpisu w historii modułu.

### Translator pluginów
Autorska biblioteka MessageHandler używana w pluginach SyntaxDevTeam korzysta z plików YAML z wiadomościami na zasadzie kategoria, klucz, treść. Chciałbym mieć moduł który pozwoli na załadowanie pliku .yml z komputera przez formularz (lub przeciągnij/upuść) i otworzy przedzielony na pół ekran z treściką oryginalna i formlarzem dla utworzenia nowego pliku w którym wpisuję własną wersję tłumaczenia i zapis z weryfikacją parsera YAML (możliwie pomocne użycie biblioteki `/core/libs/Spyc.php`)

### Team
Moduł prezentacji listy członków drużyny z możliwością wejścia w publiczny profil użytkownika (zależność z z sekcją strony głóœnej `Kontakt`).

### Projekty
Moduł lub modyfikacja instniejących elementów CMSa gdzie można dodawać Projekty które są już publiczne lub w trakcie tworzenia, współpraca z podstonami (powiązanie) i modułem Wiki.

### Wiki
Gotowe

### Build Explorer
(Nie mam dokładnie pomysłu) Moduł pozwalający na wyświetlenie listy plików do pobrania dla wszystkich dodanych projektów (współpraca z modułem Projekty) dla wersji Release/Snapshot/Dev/WIP Przykład ze strony innej ekipy ![alt text](image-2.png)
