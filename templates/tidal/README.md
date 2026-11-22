# Tidal — SyntaxDevTeam

Granatowy szablon z przezroczystym szkłem Aero, grafitowymi zagłębieniami, białym neonem i polerowanymi refleksami. To strona SyntaxDevTeam: bez symboli macOS, fikcyjnej ścieżki, napisu Workspace, dekoracyjnego doku i udawanych przycisków okien.

## Podgląd i wybór

- [Pełna strona SyntaxDevTeam](homepage.html) — publiczny zrzut rzeczywistych sekcji, kart, partnerów, widgetów, kontaktów i nawigacji tej instalacji.
- [Komponenty](stylebook.html), [panel](admin-stylebook.html), [logowanie](login.html) — prezentacyjne dane testowe.

Podglądy mają `noindex`, pomijają reklamę i nie służą do zapisywania ustawień. Zrzut strony nie zmienia rekordów CMS. W działającym motywie treści nadal pochodzą z modułów i hooków, więc ich aktualizacja nie wymaga edycji szablonu.

W **Ustawienia → Szablon → Aktywny motyw** wybierz **Tidal**. Samo dodanie plików nie przełącza aktywnego motywu.

## Szkło

Własna wektorowa sceneria `assets/img/tidal-prism.svg` prześwituje przez panele. Ich bazowe krycie to około 0,2, a rozmycie 7–12 px. Refleksy, białe górne krawędzie i podświetlone przyciski tworzą efekt polerowanego szkła. Światło delikatnie podąża za precyzyjnym kursorem; jeden `requestAnimationFrame`, bez pętli animacji i przechylania treści. Efekt wyłącza się przy ograniczeniu ruchu lub przezroczystości.

Tekst pozostaje na ciemnej warstwie, a pola mają grafitowe zagłębienia. Bez `backdrop-filter` i przy `prefers-reduced-transparency` używane są nieprzezroczyste powierzchnie. Obsługiwane są urządzenia mobilne, klawiatura, widoczny fokus i link pomijający nawigację.

## Kompletność

Zachowano wszystkie publiczne metody i dodatkowe capabilities GlassNight, w tym `TextWorkspaceThemeInterface`, `CollapsibleSectionThemeInterface`, współdzielone katalogi, ustawienia i edytory. Warstwa wizualna nie ładuje kodu ani CSS GlassNight.

Obejmuje: wszystkie układy strony głównej i akrostych, terminal, uptime, partnerów, technologie, kanały i osoby kontaktowe, strony i artykuły, dokumentację, projekty, profile, sklepy, formularze, tabele, wykresy, modale, stany błędów, logowanie i tożsamości, panel z ACL, wyszukiwarką i podglądem roli, SEO, zgody prywatności i warunkowe zasoby.

`components.css` utrzymuje kontrakty strukturalne CMS; nowy system wizualny definiują `stylebook.css`, `homepage.css`, `admin.css`. Logika bezpieczeństwa pozostaje w dotychczasowych warstwach.

## Odtwarzanie podglądów

`php tests/support/render_tidal_previews.php` odświeża bibliotekę komponentów, panel i logowanie. Istniejący pełny podgląd strony głównej pozostaje zachowany. Opcjonalny argument to ścieżka do lokalnego zrzutu publicznego modelu `{sections, navigation, config}`; jego podanie odświeża również stronę główną. Nie umieszczaj w zrzucie sekretów, sesji ani danych administracyjnych.

## Kontrole

`php tests/integration/tidal_theme.php` sprawdza pełny kontrakt i capabilities, identyczność widocznej treści z rendererem GlassNight (w tym kontakty, partnerzy, technologie i uptime), logowanie, samodzielny sklep, SEO, prywatność, escaping, CSRF i lokalne zasoby.

Przy korekcie porównano także cały rzeczywisty publiczny tekst oraz liczbę linków, nagłówków i kontrolek ze stroną GlassNight. Chromium: sześć kart, trzy współprace, siedem kontaktów, terminal i menu, trzy/dwie/jedna kolumna zależnie od szerokości. Osobno sprawdzono pełną stronę po przewinięciu, panel, stylebook, logowanie i ustawienia dostępności.

Realizacja: Fazy 1–2, Kroki 2–3 i 5B. Kopia dystrybucyjna: `install/cms/templates/tidal`. Bez migracji, zmiany aktywnego motywu i edycji opublikowanych treści.
