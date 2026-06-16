miniPORTAL to CMS pisany na czystym klasycznym stylu PHP 8.4 lub nowszym + MySQL + HTML5/CSS3 + Boostram 5
W założeniu ma nie być opierany na frameworkach czy instalacjach automatycznych a kod silnika i cały portal to warstwy klas CRUD itp. 

Plan napisania go chce oprzeć o namacalne widoczne elementy więc zamiast pisać backend podejdę do tego zupełnie inaczej zaczynając od frontendu + ewentualna logika wykonawcza. 

Zależy mi aby całkowicie oddzielić PHP od HTML na poziomie takim ze w sytuacji zmiany szablnu nie ma konieczności ingerowania w kod związany z wyświetlaniem zawartości. Pomysł powstał w oparciu o już nie istniejący jPORTAL 2.1 gdzie w PHP były zdefiniowane metody w postaci warst np. start_header(), end_header() a definicje tych metod były w jednej z klas szablonu określającej poczatek i koniec nagłówków itd
jPORTAL był pisany w PHP4 i wygląd był głównie definiowany w HTML i na tamte czasy pozbawiony wielu usprawnień które można dzisiaj dzięki najnowszym technologiom znacznie usprawnić. Boostram 5 pozwala na wiele wizualnych usprawnień animacji itd. Mamy ikony Font Awesome, edytory WYSIWYG, zapis pracy w czasie rzeczywistym i wiele innym bajerów az po profesione zabezpieczenia czy PDO, Medoo itd
Właściwie to jedynym ograniczeniem dzisiaj to tylko ludzka wyobraźnia. 
Nie mniej jednak mimo wielu skomplikowanych nowoczesnych elementów całość można sprowadzić do prostych modułów które można opracowywać każdy z osobna i łączyć ze sobą jak klocki Lego. Dlatego też chciałbym właśnie opracowywać wszystko modułowo. No dobra ale odbiegam od początku na którym mi zależy aby go w mój wymyślony sposób zacząć. 
Strona główna będzie przygotowana w 2 wersjach:
- wersja oficjalna z przykładowymi treściami tematyki przyszłej zamienniczki obecnej strony SyntaxDevTeam.pl
- wersja zawierająca zdefiniowany wyglad najpopularniejszych elementów jak listy, formularze, nagłówki, tabele, obrazki itp itd. 
Ta 2 wersja byłaby źródłem tego jak ma wyglądać dany element HTML/CSS który będziemy definiować w metodach w PHP. Załóżmy że pisząc moduł artykułów nie muszę myśleć o tym czy wybrać H2 czy H3 dla naglowku i czy będzie on w takim czy innym kolorze, rozmiarze itd tylko wstawiam start_header(); echo $title; end header(); do kolejnej warstwy szablonu modułu artykułów i dziękuję! Maksymalna uniwersalność to główny i wymagany motyw tego CMSa! 
Każdy moduł będzie wrzucany do folderu modułów a w panelu administracyjnym będzie można instalować go w managerze modułów ktory korzystając ze zdefiniowanych elementów takich jak plik .sql z definicjami tabel modułu, plik informacyjny o wersji, przeznaczniu itd i samej struktura katalogów z plikami do skopiowania w ich docelowe miejsca przeznaczenia w CMS aby prawidłowo funkcjonowały, zainstaluje, uaktualni, wł/wył lub usunie moduł. Myślę że system dynamicznego ładowania klas będzie miał yu zastosowanie. Oczywiście będzie kilka modułów na stałe zainstalowanych bez możliwości modyfikacji czu usunięcia które będą podstawowa całego CMS np. moduł edycji strony głównej+ dodawanie na jej wzór kolejnych podstron. System logowania, obsługi adminów, uprawnień itd. 
No ale jak mówiłem nie wszystko na raz ponieważ po to chcę pisać to w modułach aby nie przejmować się tym że ingerencja w moduł X spowoduję konieczność zmian w module Y. 
Katalogi będą prosto się dzielony na nieruszalny core, moduły, szablonu, biblioteki, konfiguracje itd

Całość musi okraszać
- bezpieczeństwo, 
- zabezpieczenie przed potencjalnymi włamaniami, wstrzykiwaniami złośliwego kodu itp 
- optymalizacja jak stosowanie cache dla szablonów gdzie będą używane już skopletowane pliki pod warunkiem braku ostatnich zmian w treści 
- indexy i inne optymalizacje dla treści w bazie danych
- responsywność i kompatybilność niezależnie od użytego urządzenia na którym wyświetlany jest CMS
