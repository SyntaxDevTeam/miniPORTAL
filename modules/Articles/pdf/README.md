# Lokalny silnik PDF artykułów

Dompdf 3.1.6 i jego biblioteki pomocnicze są dołączone jako źródła PHP.
Nie jest używany Composer, katalog vendor, pliki lock ani generowany autoloader.
`autoload.php` zawiera wyłącznie jawną mapę pięciu przestrzeni nazw oraz klasy Cpdf.
Instalacja miniPORTAL nie pobiera ani nie rozwiązuje zależności przez sieć.

Pochodzenie, przypięte commity i SHA-256 archiwów z oficjalnych repozytoriów
zapisano w SOURCES.json. Oryginalne licencje bibliotek i fontów pozostają
w lib/. Dompdf: LGPL-2.1, font-lib: LGPL-2.1, SVG lib: LGPL-3.0,
HTML5: MIT, CSS parser: MIT; obowiązują teksty licencji dołączonych źródeł.

Aktualizacja: pobierz archiwa oficjalnych repozytoriów dla jawnie wybranych
commitów, zweryfikuj SHA-256 i zależności według upstream, podmień lib/ oraz
SOURCES.json, zachowaj licencje i uruchom testy article_document_actions.php.
Paczka zawiera tylko potrzebne źródła, fonty i dokumentację — bez testów upstream,
przykładów i metadanych menedżera pakietów. Nie modyfikuj implementacji bibliotek.
