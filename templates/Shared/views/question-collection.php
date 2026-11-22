<?php
/** @var array $model @var callable $escape */
$categories = $model['categories'];
$countLabel = static function (int $count, string $one, string $few, string $many): string {
    return $count . ' ' . ($count === 1 ? $one : (($count % 10 >= 2 && $count % 10 <= 4 && ($count % 100 < 12 || $count % 100 > 14)) ? $few : $many));
};
$total = array_sum(array_map(static fn (array $c): int => count($c['questions']), $categories));
?>
<section class="help-center" data-question-collection aria-label="<?= $escape($model['title']) ?>">
    <header class="help-hero">
        <div class="help-eyebrow"><span aria-hidden="true">✦</span> WIEDZA POD RĘKĄ</div>
        <h1><?= $escape($model['title']) ?></h1>
        <p class="help-intro"><?= $escape($model['intro']) ?></p>
        <?php if ($total > 0): ?>
        <form class="help-search" role="search" hx-boost="false" data-help-search data-help-enhancement hidden>
            <label for="help-query">Czego szukasz?</label>
            <div class="help-search-field"><span aria-hidden="true">⌕</span><input id="help-query" type="search" maxlength="160" placeholder="Np. koń, działka, /spawn" autocomplete="off" enterkeyhint="search" aria-controls="help-results" aria-describedby="help-search-feedback"><button type="button" data-help-clear aria-label="Wyczyść wyszukiwanie" title="Wyczyść wyszukiwanie" hidden><span aria-hidden="true">×</span></button></div>
            <div class="help-category-picker"><label for="help-category-select">Temat</label><select id="help-category-select" data-help-category-select aria-controls="help-results"><option value="all">Wszystkie tematy (<?= $total ?>)</option><?php foreach ($categories as $category): ?><option value="<?= (int)$category['id'] ?>"><?= $escape($category['name']) ?><?= ($category['visibility'] ?? 'public') === 'staff' ? ' · Administracja' : '' ?> (<?= count($category['questions']) ?>)</option><?php endforeach; ?></select></div>
            <div class="help-search-actions"><p id="help-search-feedback" data-help-feedback role="status" aria-live="polite" aria-atomic="true"></p><button type="submit" class="help-search-submit">Pokaż wyniki <span aria-hidden="true">↓</span></button></div>
            <button type="button" class="help-search-scope" data-help-all hidden>Szukaj we wszystkich tematach</button>
        </form>
        <?php endif; ?>
        <div class="help-stats"><span><?= $countLabel($total, 'odpowiedź', 'odpowiedzi', 'odpowiedzi') ?></span><span><?= $countLabel(count($categories), 'kategoria', 'kategorie', 'kategorii') ?></span><span>W jednym miejscu</span></div>
    </header>
    <?php if ($total === 0): ?>
        <div class="help-empty"><span aria-hidden="true">✧</span><h2>Tu znajdziesz odpowiedzi</h2><p>Przygotowujemy dla Ciebie pierwsze poradniki. Zajrzyj ponownie wkrótce.</p></div>
    <?php else: ?>
    <nav class="help-categories" aria-label="Kategorie pomocy">
        <a class="help-category is-active" href="#help-results" data-help-category="all" aria-current="true"><span class="help-category-icon" aria-hidden="true">✦</span><span><strong>Wszystkie tematy</strong><small>Cała wiedza w jednym miejscu</small></span><span class="help-count"><?= $total ?></span></a>
        <?php foreach ($categories as $category): ?>
        <a class="help-category" href="#help-category-<?= (int) $category['id'] ?>" data-help-category="<?= (int) $category['id'] ?>"><span class="help-category-icon" aria-hidden="true"><?= $escape($category['icon']) ?></span><span><strong><?= $escape($category['name']) ?></strong><small><?= $escape($category['description']) ?></small><?php if (($category['visibility'] ?? 'public') === 'staff'): ?><span class="help-access-label">Administracja serwera</span><?php endif; ?></span><span class="help-count"><?= count($category['questions']) ?></span></a>
        <?php endforeach; ?>
    </nav>
    <div class="help-workspace">
        <aside class="help-aside"><span class="help-eyebrow">OD CZEGO ZACZĄĆ?</span><h2>Najpierw to, co najważniejsze.</h2><p>Wybierz temat lub rozwiń pytanie. Każdą odpowiedź możesz udostępnić bezpośrednim linkiem.</p>
            <?php $featured = array_slice(array_merge(...array_map(static fn(array $c): array => array_values(array_filter($c['questions'], static fn(array $q): bool => (bool)$q['is_featured'])), $categories)), 0, 5); ?>
            <?php if ($featured !== []): ?><h3>Polecane na początek</h3><ul><?php foreach ($featured as $question): ?><li><a href="#answer-<?= (int)$question['id'] ?>" data-help-featured><?= $escape($question['question']) ?><span aria-hidden="true"> ↗</span></a></li><?php endforeach; ?></ul><?php endif; ?>
        </aside>
        <div id="help-results" class="help-results" tabindex="-1">
            <div class="help-toolbar" data-help-enhancement hidden><p data-help-status><?= $countLabel($total, 'odpowiedź', 'odpowiedzi', 'odpowiedzi') ?></p><div class="help-result-actions"><button type="button" data-help-edit aria-label="Wróć do wyszukiwarki">Szukaj ↑</button><button type="button" data-help-expand aria-expanded="false">Rozwiń odpowiedzi</button></div></div>
            <?php foreach ($categories as $category): ?>
            <section class="help-group" id="help-category-<?= (int)$category['id'] ?>" data-help-group="<?= (int)$category['id'] ?>" aria-labelledby="help-heading-<?= (int)$category['id'] ?>">
                <header class="help-group-heading"><span aria-hidden="true"><?= $escape($category['icon']) ?></span><div><h2 id="help-heading-<?= (int)$category['id'] ?>"><?= $escape($category['name']) ?></h2><?php if (($category['visibility'] ?? 'public') === 'staff'): ?><span class="help-access-label">Administracja serwera</span><?php endif; ?><p><?= $escape($category['description']) ?></p></div></header>
                <?php foreach ($category['questions'] as $question): ?>
                <details class="help-question" id="answer-<?= (int)$question['id'] ?>" data-help-question data-keywords="<?= $escape($question['keywords']) ?>">
                    <summary><span class="help-question-label"><span data-help-title><?= $escape($question['question']) ?></span><?php if (($question['effective_visibility'] ?? 'public') === 'staff'): ?><span class="help-access-label">Tylko dla administracji</span><?php endif; ?><small class="help-match" data-help-match hidden></small></span><span class="help-toggle" aria-hidden="true">+</span></summary>
                    <div class="help-answer rich-content"><?= $question['answer_html'] ?></div>
                    <footer class="help-answer-footer"><a href="#answer-<?= (int)$question['id'] ?>" data-help-permalink>Link do odpowiedzi <span aria-hidden="true">↗</span></a><button type="button" data-help-copy data-help-enhancement hidden>Kopiuj link</button></footer>
                </details>
                <?php endforeach; ?>
            </section>
            <?php endforeach; ?>
            <div class="help-empty" data-help-empty hidden><span aria-hidden="true">⌕</span><h2>Nie znaleźliśmy takiej odpowiedzi</h2><p>Spróbuj krótszego hasła albo sprawdź wszystkie kategorie.</p><button type="button" data-help-reset>Pokaż wszystkie pytania</button></div>
        </div>
    </div>
    <?php endif; ?>
    <?php if ($model['contact_url'] !== ''): ?><footer class="help-contact"><div><span class="help-eyebrow">POROZMAWIAJMY</span><h2>Nadal potrzebujesz pomocy?</h2><p>Nie na każde pytanie wystarczy gotowa odpowiedź. Jesteśmy tutaj dla Ciebie.</p></div><a href="<?= $escape($model['contact_url']) ?>"><?= $escape($model['contact_label']) ?> <span aria-hidden="true">↗</span></a></footer><?php endif; ?>
</section>
