# Silnik kart gracza

Krok 15B, Fazy 1/2: skinview3d 3.4.2 dostarcza niezmodyfikowaną geometrię
skina, UV, nakładki i rozpoznawanie modeli Alex/Steve (skinview-utils 0.7.1).
Three.js 0.156.1 zapewnia kamerę, tekstury, oświetlenie i bufor głębokości.
Adapter dodaje statyczną pozę i oryginalną geometrię konia. Nie buduje własnych
brył postaci ze spłaszczonego obrazu frontu. Bez WebGL pokazujemy źródłowy front.

Wszystkie trzy biblioteki mają licencję MIT. Wersje i integralność paczek
zapisuje package-lock.json. Licencje publikujemy razem z lokalnym bundlem
w templates/shared/assets/vendor/player-card-engine-LICENSES.txt.

Budowanie wymaga Node 18+, webpack 5 i terser-webpack-plugin 5
(w środowisku projektu są dostępne systemowo):

```sh
cd tools/player-card-engine
npm ci --ignore-scripts
npm run build
```

Wynik: templates/shared/assets/js/player-model-renderer.js. Po przebudowie
skopiuj bundle i licencje do odpowiadających ścieżek install/cms.
Serwer WWW i przeglądarka nie potrzebują npm, CDN ani dostępu do zewnętrznych
serwisów renderujących. Jedna współdzielona scena renderuje tylko statyczne
klatki; modele nie uruchamiają ciągłej pętli animacji.

Źródła upstream: https://github.com/bs-community/skinview3d,
https://github.com/bs-community/skinview-utils, https://github.com/mrdoob/three.js.
