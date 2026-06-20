# Wynik testów - obserwacje

## System modułów
1. Usunięto z działającego panelu martwą sekcję `Wzorce UI`, trasę
   `/admin/design-system` i globalny przycisk `Admin stylebook`; prototypy pozostają
   materiałem developerskim w `templates/`. (gotowe)
2. Role systemowe tworzą hierarchię Owner, Administrator, Maintainer, Redaktor,
   Audytor, Support i Użytkownik z ochroną ostatniego Ownera oraz blokadą eskalacji
   ról uprzywilejowanych. (gotowe)

3. ~~Obecnie głownym założeniem modulacji jest separacja pozwalajaca na "wrzuć -> zainstaluj -> używaj". Chciałbym aby moduły "Rozszerzenie" dodawały do sekcji ustawień rozszerzone możliwości ustawień dla linków. W tej chwili taką mozliwość ma wyłacznie "Dokumentacja" w dodatku z wyborem Menu głowne lub stopka (tylko na stronie głównej) bez opcji ustawienia nazwy/etykiety linku anie zaznaczenia obu tych elementów. To trzeba zmienić aby każdy z modułów "Rozszerzenie" posiadał taką implementację oraz w niej bardziej zaaawansowane opcje.~~ (gotowe)
4. ~~Możliwość eksportu do zip już zainstalowanych modułów "Rozszerzenie"~~ (gotowe)

## Szablony

Publiczny formularz logowania nie pokazuje technicznych nazw zabezpieczeń OAuth
(`state`, PKCE, rotacja sesji). Mechanizmy pozostają aktywne w Core, natomiast
interfejs ogranicza się do informacji potrzebnych użytkownikowi. (gotowe)

Branding SyntaxDevTeam korzysta z właściwego sygnetu w publicznej nawigacji,
panelu i formularzu logowania. Oba motywy mają favikony 16/32/48 px, wielorozmiarowe
ICO, Apple Touch Icon, ikony aplikacji 192/512 px, wariant maskowalny, manifest oraz
metadane Open Graph, Twitter Card i schema.org. Panel oraz prototypy developerskie
pozostają wyłączone z indeksowania. (gotowe)

Panel rozdziela teraz prosty branding od pełnych ustawień SEO i udostępniania:
bazowego URL, domyślnego tytułu, autora, robots, locale, obrazu social media wraz
z opisem, konta X/Twitter, koloru urządzenia i tokenów weryfikacyjnych. Publiczne
widoki generują canonical, Open Graph, Twitter Card oraz Organization/WebSite
JSON-LD, a błędy zawsze mają `noindex`. Szablony mają semantyczną stopkę, aktywną
pozycję nawigacji, większe cele dotykowe, mocny fokus i redukcję ruchu. (gotowe)

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
 (gotowe: dropdown użytkownika, osobny moduł `user_profile`, widok profilu, edycja
 danych, ustawienia avatara, bezpieczeństwo i wejście do połączonych kont poza
 sidebarem; operacje OAuth pozostają w chronionym `core_auth`)

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

Etap 9 gotowy: dodano CRUD rekordów tabel. Manager SQL pozwala dodawać rekordy,
a dla tabel z dokładnie jednym kluczem głównym również edytować i usuwać wiersze.
Formularze są budowane z metadanych kolumn, korzystają z `Request`, CSRF, ACL
`database.manage`, audit logu i historii modułu.

### Translator pluginów
Autorska biblioteka MessageHandler używana w pluginach SyntaxDevTeam korzysta z plików YAML z wiadomościami na zasadzie kategoria, klucz, treść. Chciałbym mieć moduł który pozwoli na załadowanie pliku .yml z komputera przez formularz (lub przeciągnij/upuść) i otworzy przedzielony na pół ekran z treściką oryginalna i formlarzem dla utworzenia nowego pliku w którym wpisuję własną wersję tłumaczenia i zapis z weryfikacją parsera YAML (możliwie pomocne użycie biblioteki `/core/libs/Spyc.php`)

Etap 1 gotowy: dodano osobny moduł rozszerzenia `plugin_translator`. Panel
`/admin/plugin-translator` pozwala wkleić albo wgrać `.yml/.yaml`, waliduje YAML
przez `Spyc`, pokazuje oryginalne wiadomości oraz generuje formularz nowego
tłumaczenia. Eksport `/admin/plugin-translator/export` buduje `translation.yml`,
waliduje wynik przed pobraniem, wymaga CSRF i ACL `plugin_translator.use` oraz
zapisuje operacje do audit logu.

Etap 2 gotowy: translator ma publiczną stronę `/translations`, na której użytkownik
może wgrać albo wkleić YAML, uzupełnić tłumaczenie i zapisać je jako szkic lub
zgłoszenie gotowe do sprawdzenia. Dodano trwałą tabelę
`plugin_translation_submissions` z autorem, źródłem, wartościami tłumaczenia,
wygenerowanym YAML, postępem i statusem `draft`, `ready_for_review`, `approved`
albo `rejected`. Panel `/admin/plugin-translator` jest kolejką prac z podglądem
różnic, statusem ukończenia oraz akcjami zatwierdzenia, odrzucenia i pobrania YAML.

Etap 3 gotowy: formularz publiczny przyjmuje plik YAML przez pole
przeciągnij/upuść i wymaga wyboru języka docelowego z listy kodów `XX`. Edytor
pokazuje `Oryginał` i `Twoje tłumaczenie` w jednym oknie, linijka w linijkę, a
domyślny `Status zapisu` to `Kopia robocza`. Wprowadzanie i zapis wymagają
logowania; rozpoczęta praca jest zachowywana w sesji i wznawiana po logowaniu,
również dla kont oczekujących, które mogą tłumaczyć publicznie bez dostępu do
panelu admina. Liczniki używają określenia `linijki tekstu`, a przycisk `Sprawdź
formatowanie` pokazuje podgląd Minecraft legacy, RGB i MiniMessage bez zapisu.

Etap 4 gotowy: dodano widok `/translations/mine`, w którym zalogowany użytkownik
wraca do własnych szkiców, prac gotowych do sprawdzenia i odrzuconych zgłoszeń.
Kontynuacja edycji aktualizuje istniejący rekord zamiast tworzyć kolejne kopie, a
zatwierdzone tłumaczenia są zablokowane przed zmianą. Podgląd formatowania renderuje
wynik do HTML, pokazuje zmienne typu `<player>` jako zwykłe placeholdery i zgłasza
błędy MiniMessage, np. brak zamknięcia tagu lub błędny kolor HEX.

Etap 5 gotowy: translator jest także managerem zaakceptowanych plików językowych.
Dodano katalog `plugin_translation_projects`, przypisanie zgłoszeń do pluginu,
opcjonalną wersję pluginu oraz rozróżnienie pracy z edytora i gotowego uploadu.
Zalogowany użytkownik może przesłać ukończony YAML przez
`/translations/upload-ready`; plik trafia do kolejki `ready_for_review`, a po
akceptacji jest widoczny i możliwy do pobrania w publicznym katalogu pluginu.
Panel `/admin/plugin-translator/plugins` pozwala dodawać pluginy i zmieniać ich
widoczność, a główna kolejka pokazuje plugin, wersję i rodzaj zgłoszenia.

Etap 6 gotowy: formularz `Dodaj plugin` nie przechowuje opisu ani ręcznego URL.
Administrator wybiera opcjonalną, już opublikowaną stronę `core_pages`, np.
`/p/punisherx`. Katalog pokazuje powiązaną stronę i pozwala usunąć plugin bez
zgłoszeń. Główna kolejka managera ma bezpośrednie akcje `Zatwierdź`, `Odrzuć` i
`Usuń` obok podglądu oraz pobierania; akcje wymagają CSRF, potwierdzeń dla operacji
ryzykownych i zapisują audit log.

Etap 7 gotowy: pobierane tłumaczenia mają zawsze nazwę `messages_xx.yml`, gdzie
`xx` jest kodem języka, np. `messages_en.yml`, `messages_pl.yml` lub
`messages_de.yml`. Dotychczasowe `Narzędzie eksportu YAML` nazywa się teraz
`Edytor pliku YAML`; po wgraniu i edycji zapisuje wynik pod bezpieczną wersją
oryginalnej nazwy pliku zamiast stałego `translation.yml`.

Etap 8 gotowy: obszar `Pluginy translatora` nazywa się `Kategorie tłumaczeń`, bo
pozycja może reprezentować plugin, bota albo inny projekt. Każdą zwykłą kategorię
można edytować: zmienić nazwę, slug i powiązaną stronę. Usunięcie kategorii nie
kasuje prac użytkowników; w jednej transakcji przenosi zgłoszenia do chronionej
kategorii `Nieprzypisane`, a następnie usuwa wybraną pozycję.

Etap 9 gotowy: publiczne narzędzia translatora połączono w jedno centrum
`/translations` z zakładkami `Rozpocznij tłumaczenie`, `Moje wersje robocze` i
`Wyślij gotowy plik`. Katalog kategorii znajduje się pod nim na pełnej szerokości,
a nazwy kategorii są bezpośrednimi linkami zamiast osobnych przycisków pod tabelą.
Przy zaakceptowanych plikach dodano akcję `Zaproponuj poprawkę`, która otwiera
zawartość w edytorze jako nowy szkic bez modyfikowania zatwierdzonej wersji.

### Team
Moduł prezentacji listy członków drużyny z możliwością wejścia w publiczny profil użytkownika (zależność z z sekcją strony głównej `Kontakt`).

Etap 1 gotowy: dodano osobny moduł rozszerzenia `team`. Moduł ma tabelę
`team_members`, publiczną listę `/team`, publiczne profile `/team/member/{slug}`
oraz panel `/admin/team` do zarządzania widocznością, opisem, rolą, linkiem
kontaktowym i kolejnością członków zespołu. Profil publiczny jest powiązany z
lokalnym kontem użytkownika i korzysta z jego avatara. Dodano ogólny komponent
motywu `render_avatar()`.

### Wiki
Gotowe

### Build Explorer
(Nie mam dokładnie pomysłu) Moduł pozwalający na wyświetlenie listy plików do pobrania dla wszystkich dodanych projektów (współpraca z modułem Projekty) dla wersji Release/Snapshot/Dev/WIP Przykład ze strony innej ekipy ![alt text](image-2.png)

Etap 1 gotowy: dodano osobny moduł `build_explorer` zależny od `projects`.
Każdy wpis należy do projektu i przechowuje wersję, kanał `Release`, `Snapshot`,
`Dev` albo `WIP`, nazwę pliku, zewnętrzny adres HTTPS, opcjonalny rozmiar i SHA-256,
opis zmian oraz stan publikacji. Publiczne `/builds` i
`/builds/project/{slug}` pokazują wyłącznie buildy opublikowanych projektów.
Panel `/admin/builds` udostępnia CRUD metadanych z ACL, CSRF i audit logiem.
Pierwszy etap celowo nie przyjmuje binarnych uploadów na serwer. Link `Pliki do
pobrania` jest domyślnie widoczny w menu głównym i pozostaje konfigurowalny w
ustawieniach nawigacji.

Etap 2 gotowy: panel przyjmuje bezpośredni upload `.jar` do chronionego katalogu
`cache/build-artifacts`. Rozmiar w bajtach i SHA-256 są obliczane po zapisie.
Domyślna nazwa powstaje jako
`<projekt>-<serwer>-<wersja>-<typ wersji>-<nr buildu>.jar`, np.
`PunisherX-Spigot-1.7.3-DEV-14c0e44.jar`, lecz pozostaje edytowalna. Publiczne
pobieranie przechodzi przez kontrolowaną trasę, która wymaga opublikowanego buildu
i projektu. Podmiana oraz usunięcie rekordu sprzątają poprzedni artefakt.

Etap 3 gotowy: `/builds` prowadzi kolejno przez projekt, kanał Release/Snapshot/
Dev/WIP, tabelę wersji i historię buildów. Tabela wersji pobiera zawsze najnowszy
build, a historia DEV/WIP pokazuje uruchomienia CI i ich commity. Endpoint
`POST /api/builds/ci/{slug-projektu}` przyjmuje JSON z GitHub Actions, weryfikuje
sekret z nagłówka i idempotentnie zapisuje artefakty według projektu, kanału,
platformy oraz ID joba. Release i Snapshot nie wymagają numeru buildu; rewizja
Snapshot może być częścią wersji, np. `1.7.3-R0.1`.

### Projekty
(Taki pomysł ale trzeba mocno się zastanowić czy to ma sens przy już istniejacych modułach.) Moduł lub modyfikacja instniejących elementów CMSa gdzie można dodawać Projekty które są już publiczne lub w trakcie tworzenia, współpraca z podstonami (powiązanie) i modułem Wiki.

Etap 1 gotowy: osobny moduł `projects` pełni rolę katalogu agregującego, zamiast
powielać treści istniejących modułów. Przechowuje nazwę, slug, stan
`planowany` / `w trakcie tworzenia` / `wydany` / `wstrzymany`, kolejność i
publikację. Projekt może wskazywać istniejącą podstronę `core_pages` oraz projekt
`wikipedia`. Publiczne `/projects` i `/projects/{slug}` pokazują wyłącznie
opublikowane wpisy, a panel `/admin/projects` udostępnia pełny CRUD z ACL, CSRF i
audit logiem.
Link `Projekty` jest domyślnie widoczny w menu głównym i może zostać przeniesiony,
powielony w stopce albo ukryty przez ustawienia publicznej nawigacji.

Etap 2 gotowy: publiczny katalog układa jeden projekt na pełnej szerokości, dwa
i cztery w dwóch kolumnach, a trzy w trzech kolumnach. Karta nie duplikuje opisu;
prezentuje odnośniki do powiązanej strony, dokumentacji i Build Explorera.
