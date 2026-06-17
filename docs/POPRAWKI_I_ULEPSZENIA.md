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
 (gotowe jako pierwszy etap: dropdown użytkownika, widok profilu i przeniesienie połączonych kont poza sidebar)

### Manager SQL
Prosty manager bazy danych al`a mikro-PHPMyAdmin pokazujący baze danych, tabele, kolumny, strukture i dane, przyciski akcji takie jak optymalizacja, wstaw, sql, export/import, usuwanie kolumna tabel, opróżnianie itd.

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
