<?php
/** Administrative upload queue and metadata editor. Presentation only. */
?>
<?php if (($scope === 'moderate' && ($model['admin_view'] ?? 'upload') === 'upload') || ($scope === 'mine' && $admin)): ?>
<details class="mv-gallery-upload" id="gallery-bulk-upload" open>
    <summary>Dodaj wiele zdjęć</summary>
    <p class="mv-gallery-hint">Zdjęcia administracji publikujemy od razu. Tytuły otrzymają nazwy plików — opisy i kategorie poszczególnych zdjęć możesz uzupełnić później.</p>
    <form method="post" enctype="multipart/form-data" action="/index.php?route=/gallery/batch-upload" data-gallery-bulk hx-boost="false">
        <?php $token() ?>
        <label>Kategoria dla tej partii
            <select name="category_id" required><option value="">Wybierz kategorię</option><?php $options($model['category']) ?></select>
        </label>
        <label class="mv-gallery-drop" data-gallery-bulk-drop>
            <span class="mv-gallery-upload-icon" aria-hidden="true">↑</span>
            <strong>Przeciągnij zdjęcia lub wybierz wiele plików</strong>
            <span>JPG, PNG, WebP · do 8 MiB na zdjęcie · do 200 zdjęć w kolejce</span>
            <input type="file" name="photos[]" multiple accept="image/jpeg,image/png,image/webp" required>
        </label>
        <noscript><p>Bez JavaScript: do 20 zdjęć i 45 MiB łącznie na partię.</p></noscript>
        <div class="mv-gallery-bulk-controls">
            <button type="submit" data-gallery-bulk-start>Wgraj i opublikuj zdjęcia</button>
            <button type="button" class="mv-gallery-secondary" data-gallery-bulk-clear hidden>Wyczyść kolejkę</button>
            <a class="mv-gallery-batch-link" data-gallery-bulk-describe hidden>Opisz wgrane zdjęcia →</a>
        </div>
        <p role="status" aria-live="polite" data-gallery-bulk-status></p>
        <progress data-gallery-bulk-progress value="0" max="1" aria-label="Postęp przesyłania zdjęć" hidden></progress>
        <ol class="mv-gallery-queue" data-gallery-bulk-list aria-label="Kolejka zdjęć"></ol>
    </form>
</details>
<?php endif ?>
<?php if ($scope === 'moderate' && ($model['batch'] ?? []) !== []): ?>
<section class="mv-gallery-upload" id="gallery-describe" aria-labelledby="gallery-describe-title">
    <h2 id="gallery-describe-title">Opisz wgrane zdjęcia</h2>
    <p class="mv-gallery-hint">Uzupełnij opisy, tytuły i kategorie, a następnie zapisz całą partię. Edycja opisów zachowuje aktualny stan publikacji zdjęć.</p>
    <form method="post" action="/index.php?route=/gallery/describe" hx-boost="false">
        <?php $token() ?>
        <input type="hidden" name="batch" value="<?= $e(implode(',', array_column($model['batch'], 'id'))) ?>">
        <div class="mv-gallery-batch-editor">
        <?php foreach ($model['batch'] as $item): $prefix = 'photos[' . (int) $item['id'] . ']'; ?>
            <article class="mv-gallery-batch-row">
                <a href="/index.php?route=/gallery/image&amp;id=<?= $e($item['id']) ?>" target="_blank" rel="noopener">
                    <img loading="lazy" src="/index.php?route=/gallery/image&amp;size=thumb&amp;id=<?= $e($item['id']) ?>" alt="<?= $e($item['title']) ?>">
                </a>
                <div class="mv-gallery-fields">
                    <input type="hidden" name="<?= $e($prefix) ?>[revision]" value="<?= $e($item['revision']) ?>">
                    <label>Tytuł<input name="<?= $e($prefix) ?>[title]" value="<?= $e($item['title']) ?>" maxlength="120" required></label>
                    <label>Kategoria<select name="<?= $e($prefix) ?>[category_id]" required><?php $options((int) $item['category_id']) ?></select></label>
                    <label class="mv-gallery-wide">Krótki opis <span>(opcjonalnie)</span><textarea name="<?= $e($prefix) ?>[description]" maxlength="500" rows="2"><?= $e($item['description']) ?></textarea></label>
                </div>
            </article>
        <?php endforeach ?>
        </div>
        <button type="submit">Zapisz opisy całej partii</button>
    </form>
</section>
<?php endif ?>
