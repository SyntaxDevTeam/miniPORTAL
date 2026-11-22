<?php
/** @var array $model @var Closure $escape */
$state = $model['state'];
$manage = $model['can_manage'];
$options = static function (array $items, string $selected) use ($escape): void {
    foreach ($items as $value => $label) echo '<option value="' . $escape($value) . '"' . ((string) $value === $selected ? ' selected' : '') . '>' . $escape($label) . '</option>';
};
?>
<div class="asset-manager" data-asset-manager data-view="<?= $escape($state['view']) ?>">
<?php if ($model['mode'] !== 'browse'): ?>
    <div class="am-heading"><div><span class="am-eyebrow">Porządkowanie biblioteki</span><h2><?= $model['mode'] === 'edit' ? 'Szczegóły grafiki' : 'Edytuj zaznaczone grafiki' ?></h2><p>Zmiana nazwy, kategorii i opisu zachowuje dotychczasowy adres pliku.</p></div><a class="am-button" href="<?= $escape($model['url']) ?>">← Wróć do biblioteki</a></div>
    <form action="<?= $escape($model['mode'] === 'edit' ? $model['save_url'] : $model['bulk_url']) ?>" method="post" class="am-edit-form">
        <input type="hidden" name="_token" value="<?= $escape($model['token']) ?>">
        <?php if ($model['mode'] === 'batch-edit'): ?><input type="hidden" name="operation" value="save"><?php endif ?>
        <?php foreach ($model['assets'] as $asset): $prefix = $model['mode'] === 'edit' ? '' : 'metadata[' . $asset['id'] . ']'; $field = static fn (string $key): string => $prefix === '' ? $key : $prefix . '[' . $key . ']'; ?>
        <article class="am-edit-card">
            <input type="hidden" name="<?= $model['mode'] === 'edit' ? 'id' : 'ids[]' ?>" value="<?= $escape($asset['id']) ?>">
            <div class="am-edit-preview"><a href="<?= $escape($asset['url']) ?>" target="_blank" rel="noopener"><img src="<?= $escape($asset['url']) ?>" alt="<?= $escape($asset['alt_text']) ?>" loading="lazy"></a></div>
            <div class="am-edit-fields">
                <label>Nazwa<input name="<?= $escape($field('title')) ?>" value="<?= $escape($asset['title']) ?>" maxlength="180" required></label>
                <label>Kategoria<select name="<?= $escape($field('category')) ?>"><?php $options($model['categories'], $asset['category']) ?></select></label>
                <label class="am-wide">Tekst alternatywny<input name="<?= $escape($field('alt_text')) ?>" value="<?= $escape($asset['alt_text']) ?>" maxlength="255" aria-describedby="am-alt-help"></label>
                <p class="am-file-meta am-wide"><?= $escape($asset['original_name']) ?> · <?= $escape($asset['dimensions']) ?> · <?= $escape($asset['size']) ?> · <?= $escape($asset['mime']) ?> · <?= $escape($asset['created_at']) ?></p>
                <label class="am-wide">Adres pliku<input value="<?= $escape($asset['url']) ?>" readonly data-url-field></label>
                <button type="button" class="am-button" data-copy-url="<?= $escape($asset['url']) ?>">Kopiuj adres</button>
            </div>
        </article>
        <?php endforeach ?>
        <p id="am-alt-help">Opisz krótko zawartość obrazu dla osób korzystających z czytnika ekranu. Opis nie musi powtarzać nazwy pliku.</p>
        <?php if ($manage): ?><div class="am-save-bar"><button class="am-button am-primary" type="submit">Zapisz <?= count($model['assets']) > 1 ? 'wszystkie zmiany' : 'zmiany' ?></button><a class="am-button" href="<?= $escape($model['url']) ?>">Anuluj</a></div><?php endif ?>
    </form>
<?php else: $summary = $model['summary']; ?>
    <div class="am-summary" aria-label="Podsumowanie biblioteki">
        <div><span>Wszystkie grafiki</span><strong><?= $escape($summary['total']) ?></strong></div>
        <div><span>Zajęte miejsce</span><strong><?= $escape(round($summary['bytes'] / 1048576, 1)) ?> <small>MiB</small></strong></div>
        <a href="<?= $escape($model['reset_url'] . '&alt=missing') ?>"><span>Bez opisu alternatywnego</span><strong><?= $escape($summary['missing_alt']) ?></strong></a>
    </div>
    <?php if ($manage): ?>
    <details class="am-upload-panel" <?= $summary['total'] === 0 ? 'open' : '' ?> data-upload-panel>
        <summary>+ Dodaj grafiki <span>Wiele plików · przeciągnij i upuść</span></summary>
        <form action="<?= $escape($model['upload_url']) ?>" method="post" enctype="multipart/form-data" data-upload-form data-item-url="<?= $escape($model['upload_item_url']) ?>">
            <input type="hidden" name="_token" value="<?= $escape($model['token']) ?>">
            <div class="am-dropzone" data-dropzone>
                <label for="am-files"><strong>Wybierz grafiki lub upuść je tutaj</strong><span>PNG, JPG, WEBP, GIF, SVG · do 20 plików · 5 MiB na plik · 45 MiB łącznie</span></label>
                <input id="am-files" type="file" name="assets[]" accept="image/png,image/jpeg,image/webp,image/gif,image/svg+xml,.svg" multiple required>
            </div>
            <div class="am-upload-options"><label>Kategoria na start<select name="category"><?php $options($model['categories'], $state['category'] ?: 'other') ?></select></label><p>Nazwy pobierzemy z plików. Kategorie, nazwy i opisy możesz uzupełnić po wgraniu.</p></div>
            <ul class="am-queue" data-upload-queue aria-label="Kolejka plików"></ul>
            <p class="am-status" data-upload-status role="status" aria-live="polite"></p>
            <div class="am-upload-actions"><button class="am-button am-primary" type="submit" data-upload-start>Wgraj grafiki</button><button class="am-button" type="button" data-clear-queue hidden>Wyczyść kolejkę</button><a class="am-button am-primary" href="<?= $escape($model['url']) ?>" data-upload-done hidden>Przejdź do grafik</a></div>
        </form>
        <form action="<?= $escape($model['bulk_url']) ?>" method="post" data-upload-edit hidden>
            <input type="hidden" name="_token" value="<?= $escape($model['token']) ?>"><input type="hidden" name="operation" value="edit">
            <span data-uploaded-ids></span><button class="am-button am-primary" type="submit" data-upload-edit-button>Opisz wgrane grafiki</button>
        </form>
    </details>
    <?php endif ?>
    <section class="am-collection" aria-labelledby="am-collection-title">
        <div class="am-heading"><div><span class="am-eyebrow">Twoje zasoby</span><h2 id="am-collection-title">Grafiki <small><?= $escape($model['total']) ?></small></h2></div><nav class="am-view-switch" aria-label="Widok biblioteki"><?php foreach (['thumbnails' => 'Miniatury', 'list' => 'Lista'] as $key => $label): ?><a class="am-button <?= $state['view'] === $key ? 'am-primary' : '' ?>" href="<?= $escape($model['view_urls'][$key]) ?>" <?= $state['view'] === $key ? 'aria-current="page"' : '' ?>><?= $label ?></a><?php endforeach ?></nav></div>
        <form class="am-filters" action="index.php" method="get" role="search">
            <input type="hidden" name="route" value="/admin/media"><input type="hidden" name="view" value="<?= $escape($state['view']) ?>">
            <label class="am-search">Szukaj grafik<input type="search" name="q" value="<?= $escape($state['q']) ?>" placeholder="Nazwa, plik lub opis…" maxlength="180"></label>
            <label>Kategoria<select name="category"><?php $options(['' => 'Wszystkie kategorie'] + $model['categories'], $state['category']) ?></select></label>
            <label>Format<select name="type"><?php $options(['' => 'Wszystkie formaty'] + $model['types'], $state['type']) ?></select></label>
            <label>Opis alternatywny<select name="alt"><?php $options(['' => 'Wszystkie', 'missing' => 'Brak opisu'], $state['alt']) ?></select></label>
            <label>Sortowanie<select name="sort"><?php $options($model['sorts'], $state['sort']) ?></select></label>
            <button class="am-button am-primary" type="submit">Filtruj</button><a class="am-reset" href="<?= $escape($model['reset_url']) ?>">Wyczyść</a>
        </form>
        <?php if ($model['assets'] === []): ?>
        <div class="am-empty"><strong><?= $summary['total'] === 0 ? 'Twoja biblioteka czeka na pierwsze grafiki' : 'Brak grafik pasujących do filtrów' ?></strong><p><?= $summary['total'] === 0 ? 'Zacznij od dodania plików. Potem zaznacz je i uporządkuj za jednym razem.' : 'Zmień wyszukiwaną frazę lub wyczyść filtry.' ?></p></div>
        <?php else: ?>
        <form action="<?= $escape($model['bulk_url']) ?>" method="post" data-bulk-form>
            <input type="hidden" name="_token" value="<?= $escape($model['token']) ?>">
            <?php if ($manage): ?>
            <div class="am-bulk-toolbar">
                <label class="am-check"><input type="checkbox" data-select-all> Zaznacz stronę</label><output data-selection-count aria-live="polite">0 zaznaczonych</output>
                <label>Operacja<select name="operation" data-operation><option value="edit">Edytuj nazwy i opisy</option><option value="category">Zmień kategorię</option><option value="delete">Usuń grafiki</option></select></label>
                <label data-bulk-category>Kategoria<select name="category"><?php $options($model['categories'], 'other') ?></select></label>
                <label class="am-check am-danger-text" data-bulk-confirm><input type="checkbox" name="confirm_delete" value="1"> Potwierdzam usunięcie plików, także używanych na stronie</label>
                <button class="am-button" type="submit" data-bulk-submit>Wykonaj</button>
            </div>
            <?php endif ?>
            <div class="am-assets am-assets--<?= $escape($state['view']) ?>">
                <?php foreach ($model['assets'] as $index => $asset): ?>
                <article class="am-asset" data-category="<?= $escape($asset['category']) ?>" data-asset data-index="<?= $escape($index) ?>" data-title="<?= $escape($asset['title']) ?>" data-url="<?= $escape($asset['url']) ?>" data-alt="<?= $escape($asset['alt_text']) ?>" data-details="<?= $escape($asset['dimensions'] . ' · ' . $asset['size'] . ' · ' . $asset['mime']) ?>">
                    <?php if ($manage): ?><label class="am-select"><input type="checkbox" name="ids[]" value="<?= $escape($asset['id']) ?>" aria-label="<?= $escape('Zaznacz: ' . $asset['title']) ?>" data-select-asset></label><?php endif ?>
                    <a class="am-preview" href="<?= $escape($asset['url']) ?>" target="_blank" rel="noopener" data-preview="<?= $escape($index) ?>" aria-label="<?= $escape('Podgląd: ' . $asset['title']) ?>"><img src="<?= $escape($asset['url']) ?>" alt="<?= $escape($asset['alt_text']) ?>" loading="lazy" decoding="async"></a>
                    <div class="am-asset-body"><span class="am-category"><?= $escape($asset['category_label']) ?></span><h3 title="<?= $escape($asset['title']) ?>"><?php if ($manage): ?><a href="<?= $escape($asset['edit_url']) ?>"><?= $escape($asset['title']) ?></a><?php else: ?><?= $escape($asset['title']) ?><?php endif ?></h3><p class="am-original" title="<?= $escape($asset['original_name']) ?>"><?= $escape($asset['original_name']) ?></p><p class="am-file-meta"><?= $escape($asset['dimensions']) ?> · <?= $escape($asset['size']) ?> · <?= $escape($asset['format']) ?></p><?php if ($asset['alt_text'] === ''): ?><span class="am-missing-alt">Brak opisu ALT</span><?php endif ?></div>
                    <div class="am-asset-actions"><?php if ($manage): ?><a class="am-button" href="<?= $escape($asset['edit_url']) ?>">Edytuj</a><?php endif ?><button class="am-button" type="button" data-copy-url="<?= $escape($asset['url']) ?>">Kopiuj adres</button><a class="am-button" href="<?= $escape($asset['url']) ?>" target="_blank" rel="noopener">Otwórz ↗</a></div>
                </article>
                <?php endforeach ?>
            </div>
        </form>
        <nav class="am-pagination" aria-label="Strony biblioteki"><?php if ($model['page'] > 1): ?><a class="am-button" href="<?= $escape($model['previous_url']) ?>">← Poprzednia</a><?php endif ?><span>Strona <?= $escape($model['page']) ?> z <?= $escape($model['pages']) ?> · do 48 grafik na stronę</span><?php if ($model['page'] < $model['pages']): ?><a class="am-button" href="<?= $escape($model['next_url']) ?>">Następna →</a><?php endif ?></nav>
        <?php endif ?>
    </section>
    <dialog class="am-dialog" data-preview-dialog aria-labelledby="am-preview-title">
        <div class="am-dialog-heading"><h2 id="am-preview-title" data-preview-title></h2><button type="button" class="am-button" data-close-preview aria-label="Zamknij podgląd">Zamknij ×</button></div>
        <div class="am-dialog-image"><img alt="" data-preview-image></div><p data-preview-details></p>
        <div class="am-dialog-actions"><button type="button" class="am-button" data-preview-previous aria-label="Poprzednia grafika">← Poprzednia</button><button type="button" class="am-button" data-preview-next aria-label="Następna grafika">Następna →</button><a class="am-button" data-preview-open target="_blank" rel="noopener">Otwórz oryginał ↗</a></div>
    </dialog>
<?php endif ?>
    <p class="am-status" role="status" aria-live="polite" data-copy-status></p>
</div>
