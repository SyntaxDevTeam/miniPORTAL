# Equestria — motyw konny SF

Nazwa robocza motywu dla serwera **MoonVale.pl**. Minecraft 26.2, paleta #D246CB,
#9C4DD1, #FF67E8, #692DA9. Motyw realizuje Fazę 1 / krok 2 (prototyp) oraz
Fazę 2 / krok 3 (ThemeInterface) specyfikacji miniPORTAL.

## Uruchomienie

W nowej instalacji skopiuj `templates/equestria/` obok `templates/default/`
i `templates/shared/`. W Ustawieniach wybierz motyw **Equestria**. ThemeEngine
odkrywa go automatycznie. Wypełnij Branding i SEO własną nazwą i danymi.
Nie zmieniano aktywnego motywu instalacji macierzystej.

`homepage.html` jest prototypem renderowanym przez tę samą klasę PHP. Otwieraj
`/templates/equestria/homepage.html` w instalacji, żeby działały lokalne assety.

Pusta lista sekcji uruchamia ofertowe treści startowe z `content.php`.
Jeśli CorePages ma sekcje, otrzymują one pełne pierwszeństwo: teksty, karty,
CTA i widgety pozostają pod kontrolą CMS. Treści przykładowe można przepisać
jako sekcje w edytorze. Nie zapisują się automatycznie do bazy.

## Efekty i zgodność

Oryginalne logo MoonVale, księżycowa mgławica, gwiazdy, świetlna oprawa,
animacje wejścia i podświetlane karty. Dla zastępczej ilustracji SVG działa
również paralaksa kursora. Efekty można wstrzymać;
`prefers-reduced-motion` wyłącza ruch. Obsługiwane mobilne layouty, fokus klawiatury,
HTMX, panel, formularze, CSRF, nawigacja modułów i zgoda analityczna.
CSS/JS i grafika są lokalne. Bazowe komponenty korzystają z assetów motywu default;
panel dziedziczy jego kontrakt i otrzymuje fioletową paletę.

Opis oraz oryginalny brief: `docs/SERWER_KONNY_BRIEF.md`.
Do uzupełnienia: adres serwera, Discord i zasady dołączania.
Nie prezentujemy fikcyjnych statystyk graczy ani statusu online.

## Oryginalne logo MoonVale

Umieść oryginał w `assets/img/moonvale-logo.webp` (alternatywnie `.png`,
`.jpg` lub `.jpeg`). Motyw automatycznie użyje go w Hero,
zamiast zastępczej ilustracji SVG. Oryginał dostarczony przez użytkownika jest już zapisany jako WebP. Logo jest wyświetlane w całości,
bez deformacji i animowania samego obrazu. Krawędzie zdjęcia są wygaszane
maską CSS, aby grafika płynnie łączyła się z tłem. Plik źródłowy pozostaje niezmieniony.
Ruchoma mgławica i gwiazdy należą do otoczenia grafiki.
Po dodaniu pliku odtwórz prototyp homepage.html z ThemeEngine, aby statyczny
podgląd również pokazywał nową grafikę. Runtime odczytuje ją automatycznie.

Oddzielny wariant miniatury `moonvale-icon.webp` dostarczony przez użytkownika
jest źródłem lokalnych ikon PNG 16–512 px, menu i favicon.ico.

## Nowa kompozycja (2026-10-08)

Hero z dużą sceną logo, szeryfową typografią, różowym akcentem,
wygaszonymi krawędziami zdjęcia i animowanym otoczeniem. Selektor
Hodowla / Zawody / Survival zmienia opis i wyróżnia odpowiednią kartę
startowych sekcji. Obsługuje mysz, dotyk oraz klawiaturę. Efekty i pauza
działają również przy podmianach HTMX i preferencji reduced motion.

## Tła i żywioły

Lokalna panorama `assets/img/moonvale-world-v2.webp` łączy blokowe stajnie,
arenę i dolinę survivalową z księżycową paletą MoonVale. Sekcje naprzemiennie
wykorzystują kolorowy kadr wygaszany w połowie szerokości oraz spokojne
tło bez obrazu. Krajobrazy pojawiają się na zmianę z prawej i lewej strony. To ilustracja klimatu,
nie zrzut mapy. Źródło i prompt: `docs/MOONVALE_ASSET_PROMPTS.md`.

Wybór żywiołu zmienia scenerię i panel z emblematem, tytułem, opisem
i etykietami; uruchamia krótką animację aktywacji z odpowiednimi cząstkami.
Pauza usuwa cząstki i anuluje rozbłysk. Reduced motion pozwala zmieniać
żywioł bez animacji. Plik worlds.css jest wersjonowany i ładowany tylko
na stronie głównej, poza panelem administracyjnym i samodzielnym sklepem.

## Przygotowana instalacja MoonVale.pl

Motyw wdrożono do `/var/www/moonvale.pl/templates/equestria/`. Docelowy
kreator ma domyślnie wybraną nazwę MoonVale.pl oraz Equestria i zapisuje
profil z `install-profile.php` do standardowych ustawień systemu.
Instrukcja tej instalacji: `/var/www/moonvale.pl/MOONVALE_SETUP.md`.
Instalacji bazy i OAuth nie wykonywano.

Od korekty 2026-10-09 treści `content.php` są źródłem jawnego importu w
panelu **Treść → Strona główna → Dodaj treści startowe motywu**. Runtime
nie używa ich jako fallbacku. Prototyp pozostaje statyczną referencją.


Przycisk kopiowania: w panelu Treść → Strona główna edytuj sekcję Hero,
ustaw etykietę pierwszego przycisku, wybierz „Skopiuj adres” w polu funkcji
i wpisz np. `moonvale.pl` w polu adresu do skopiowania. Pozostałe przyciski
zachowują adresy z listy. Można też użyć deklaracji `copy:moonvale.pl` w adresie
przycisku. Clipboard API wymaga bezpiecznego kontekstu HTTPS; komunikat
potwierdza wyłącznie udany zapis. Link Panel gracza korzysta z aktywnego
modułu player_profile lub stabilnej trasy /account.
