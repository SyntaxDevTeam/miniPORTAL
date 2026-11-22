<?php declare(strict_types=1); ?>
<section class="api-reference" data-api-reference data-spec-url="<?= $escape($model['spec_url']) ?>" aria-labelledby="api-reference-title">
    <header class="api-reference__hero">
        <div>
            <a class="api-reference__back" href="<?= $escape($model['back_url']) ?>">← Build Explorer</a>
            <div class="api-reference__eyebrow"><span class="api-reference__status" aria-hidden="true"></span> DEVELOPER API <span class="api-reference__version"><?= $escape($model['version']) ?></span></div>
            <h1 id="api-reference-title"><?= $escape($model['title']) ?></h1>
            <p><?= $escape($model['lead']) ?></p>
        </div>
        <div class="api-reference__origin"><span>BAZOWY ADRES API</span><code>/api/v2/builds</code><small>JSON · UTF-8 · HTTPS</small></div>
    </header>
    <nav class="api-reference__tabs" aria-label="Dokumentacja API">
        <a href="#api-endpoints" aria-current="page">Konsola API <span>Swagger UI</span></a>
        <a href="#api-latest-download">Jak pobrać najnowszy build?</a>
        <a href="<?= $escape($model['guide_url']) ?>">Poradnik integracji ↗</a>
        <a href="<?= $escape($model['spec_url']) ?>" download="build-explorer-openapi.json">Pobierz OpenAPI ↓</a>
    </nav>
    <div class="api-reference__workspace">
        <aside class="api-reference__sidebar" aria-label="Nawigacja po API">
            <p class="api-reference__label">ZASOBY</p>
            <nav class="api-reference__resources" aria-label="Grupy endpointów">
                <a href="#/Projekty"><span>01</span> Projekty</a>
                <a href="#/Wersje%20i%20wydania"><span>02</span> Wersje i wydania</a>
                <a href="#/Pliki"><span>03</span> Pliki i zgodność</a>
                <a href="#/Najnowszy%20build"><span>04</span> Najnowszy build</a>
                <a href="#/System"><span>05</span> Informacje o API</a>
            </nav>
            <details class="api-reference__glossary" open>
                <summary class="api-reference__label">SŁOWNIK POJĘĆ</summary>
                <dl>
                    <dt>Projekt</dt><dd>Plugin lub aplikacja, np. PunisherX. W adresie używasz jego identyfikatora <code>punisherx</code>.</dd>
                    <dt>Wersja projektu</dt><dd>Numer pluginu lub aplikacji, np. <code>1.8.0</code>.</dd>
                    <dt>Wydanie / build</dt><dd>Konkretna publikacja wersji w kanale Release, Snapshot, DEV lub WIP.</dd>
                    <dt>Plik / artefakt</dt><dd>Plik do pobrania dla danej platformy, np. Paper lub Velocity.</dd>
                    <dt>Wersja Minecrafta</dt><dd>Wersja gry, z którą działa plik, np. <code>26.3</code>. To osobny parametr <code>game_version</code>.</dd>
                </dl>
            </details>
            <p class="api-reference__session">Publiczne projekty odczytasz bez klucza API. Dostęp do prywatnych projektów zależy od zalogowania na stronie.</p>
        </aside>
        <div class="api-reference__main">
            <div class="api-reference__quickstart" aria-labelledby="api-start-title">
                <div class="api-reference__section-heading"><h2 id="api-start-title">Od czego zacząć?</h2><span>3 PROSTE KROKI</span></div>
                <div class="api-reference__steps">
                    <a href="#/Projekty/Projects"><span class="api-reference__step-number">1</span><strong>Znajdź projekt</strong><span>Odczytaj jego nazwę i identyfikator z listy projektów.</span><code>GET /projects</code></a>
                    <a href="#/Najnowszy%20build/LatestVersionInfo"><span class="api-reference__step-number">2</span><strong>Dobierz zgodny plik</strong><span>Podaj projekt, platformę, kanał i opcjonalnie wersję MC.</span><code>GET …/latest/info</code></a>
                    <a href="#/Najnowszy%20build/DownloadLatestVersion"><span class="api-reference__step-number">3</span><strong>Pobierz build</strong><span>Stały adres zawsze wskaże najnowszy pasujący plik.</span><code>GET …/latest</code></a>
                </div>
            </div>
            <div class="api-reference__download" id="api-latest-download" aria-labelledby="api-download-title">
                <div class="api-reference__section-heading"><h2 id="api-download-title">Pobierz build jednym przyjaznym adresem</h2><span>STAŁY ADRES POBIERANIA</span></div>
                <p>Podaj projekt, platformę, wersję Minecrafta i kanał w ścieżce. Na końcu wpisz <strong><code>latest</code> albo konkretną wersję pluginu</strong>, np. <code>1.7.3</code>.</p>
                <p>Przykład: <strong>PunisherX → Paper → Minecraft 26.3 → Release</strong>.</p>
                <pre><code>GET /api/v2/builds/punisherx/paper/26.3/release/latest</code></pre>
                <pre><code>curl -fL 'https://syntaxdevteam.pl/api/v2/builds/punisherx/paper/26.3/release/latest' -o PunisherX.jar</code></pre>
                <div class="api-reference__download-links">
                    <a href="/api/v2/builds/punisherx/paper/26.3/release/latest">Pobierz najnowszy zgodny Release ↓</a>
                    <a href="/api/v2/builds/punisherx/paper/26.3/release/latest/info">Sprawdź wybraną wersję i SHA-256 w JSON ↗</a>
                </div>
                <p class="api-reference__download-note">Aby przypiąć wersję pluginu, zamień końcowe <code>latest</code> na jej numer: <code>/api/v2/builds/punisherx/paper/26.3/release/1.7.3</code>. <code>26.3</code> oznacza Minecrafta; <code>1.7.3</code> — plugin. Kanał <code>release</code> można zmienić na <code>snapshot</code>, <code>dev</code> lub <code>wip</code>.</p>
                <p class="api-reference__download-note">Przy <code>latest</code> najpierw wybierana jest najwyższa zgodna wersja pluginu, a następnie jej ostatnio opublikowany build. Dla konkretnego numeru wybierany jest najnowszy build wyłącznie tej wersji. Brak zgodności daje 404. Dla proxy bez deklaracji MC użyj <code>any</code> zamiast numeru Minecrafta. W curl użyj <code>-L</code>, aby obsłużyć przekierowanie do pliku. Projekty prywatne wymagają sesji zalogowanego użytkownika.</p>
            </div>
            <div class="api-reference__console" id="api-endpoints">
                <div class="api-reference__section-heading"><h2>Endpointy</h2><span>ROZWIŃ → TRY IT OUT → EXECUTE</span></div>
                <p class="api-reference__console-hint">Rozwiń wybrany endpoint. Przycisk <strong>Try it out</strong> włącza edycję parametrów, a <strong>Execute</strong> wysyła zapytanie i pokazuje odpowiedź. Gwiazdka oznacza pole wymagane.</p>
                <p class="api-reference__loading" data-api-loading role="status">Ładowanie interaktywnej dokumentacji…</p>
                <div class="api-reference__error" data-api-error role="alert" hidden>Nie udało się załadować konsoli API. Odśwież stronę lub otwórz <a href="<?= $escape($model['guide_url']) ?>">poradnik integracji</a>.</div>
                <div id="api-reference-swagger" data-api-swagger></div>
                <noscript><p>Włącz JavaScript, aby korzystać z interaktywnej konsoli. <a href="<?= $escape($model['guide_url']) ?>">Poradnik i przykłady zapytań</a> są dostępne bez JavaScript.</p></noscript>
            </div>
            <footer class="api-reference__footer"><span>OpenAPI 3.1 · Swagger UI</span><a href="<?= $escape($model['guide_url']) ?>">Stronicowanie, zgodność i przykłady integracji →</a></footer>
        </div>
    </div>
</section>
