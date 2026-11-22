# GlassNight Aurora / Nocturne

Ciemny, mocno przeszklony wariant szablonu GlassNight w `miniPORTAL-source`.

## Najważniejsze założenia
- **Bardzo ciemna kolorystyka:** grafit, atramentowy granat i przygaszona morska zieleń. Brak jaskrawych niebieskich teł.
- **Prawdziwy efekt matowego szkła:** półprzezroczyste warstwy o kryciu około 0,34, rozmycie tła `blur(24px)` lub `blur(31px)`, refleksy na górnych krawędziach i miękkie cienie.
- **Widoczna sceneria za szkłem:** lokalny wektor `assets/img/aurora-field.svg` (ciemna geometria, świetlne smugi, mgła) + animowane warstwy atmosfery. Bez połączeń z CDN.
- **Drobne interakcje:** lekki ruch paneli i odbicie światła zależne od położenia kursora, obsługiwane delegacją zdarzeń oraz jednym `requestAnimationFrame`.
- **Oszczędność zasobów:** brak bibliotek, odłączanie efektów na urządzeniach bez precyzyjnego kursora, `prefers-reduced-motion`, `prefers-reduced-transparency` oraz alternatywa dla urządzeń bez `backdrop-filter`.

## Zakres
Strona główna, podstrony, stylebook, nawigacja, formularze, tabele, ekran logowania, panel administracyjny. Silnik motywu oraz funkcje PHP nie są zmieniane. Motyw nie jest aktywowany automatycznie.

## Instalacja
Wybierz motyw **Glassnight Aurora** w ustawieniach miniPORTAL. Motyw znajduje się w `templates/glassnight-aurora/` oraz `install/cms/templates/glassnight-aurora/`.

## Zalecane sprawdzenie po wdrożeniu
1. Odśwież zasoby przeglądarki (CSS/JS z cache).
2. Porównaj jasność tła i widoczność rozmycia kart na stronie głównej oraz w panelu administracyjnym.
3. Sprawdź mobile (375px), klawiaturę, modalne okna i tabele.
4. Włącz redukcję ruchu i przejrzystości, przetestuj alternatywę dla `backdrop-filter`.
5. Zweryfikuj przeglądarki Chromium i Firefox.
