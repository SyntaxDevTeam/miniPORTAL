<?php
/** Dedicated administrative workspace. Public gallery markup is not loaded here. */
$view = $model['admin_view'] ?? 'photos';
$status = $model['status'] ?? 'pending';
$labels = ['pending'=>'Do akceptacji', 'published'=>'Opublikowane', 'rejected'=>'Odrzucone', 'all'=>'Wszystkie'];
$counts = $model['counts'];
$admin = true; $scope = 'moderate';
$options = static function(int $selected = 0) use ($model, $e): void {
    foreach ($model['categories'] as $category) echo '<option value="'.$e($category['id']).'"'.((int)$category['id']===$selected?' selected':'').'>'.$e($category['name']).'</option>';
};
$token = static function() use ($model, $e): void { echo '<input type="hidden" name="_token" value="'.$e($model['token']).'">'; };
?>
<div class="ga" data-gallery-root>
<nav class="ga-sections" aria-label="Zarządzanie galerią">
    <a href="/index.php?route=/admin/gallery" <?= in_array($view,['photos','edit','batch'],true)?'aria-current="page"':'' ?>>Zdjęcia</a>
    <a href="/index.php?route=/admin/gallery&amp;view=upload" <?= $view==='upload'?'aria-current="page"':'' ?>>Dodaj zdjęcia</a>
    <a href="/index.php?route=/admin/gallery&amp;view=categories" <?= $view==='categories'?'aria-current="page"':'' ?>>Kategorie <span><?= count($model['categories']) ?></span></a>
    <div class="ga-section-actions">
        <a href="/gallery" target="_blank" rel="noopener">Otwórz galerię ↗</a>
        <?php if($model['navigation_manage']??false): ?><a href="/index.php?route=/admin/settings#admin-public-nav-gallery-row">Ustaw link w menu</a><?php endif ?>
    </div>
</nav>
<?php if($model['error']): ?><div class="ga-notice ga-error" role="alert"><?= $e($model['error']) ?></div><?php endif ?>
<?php if($model['saved']): ?><div class="ga-notice" role="status">Zmiany zostały zapisane.</div><?php endif ?>
<?php if($model['moderation_notice']??''): ?><div class="ga-notice" role="status"><?= $e($model['moderation_notice']) ?></div><?php endif ?>
<?php if($view==='upload'): ?>
    <div class="ga-heading"><h2>Dodaj zdjęcia do galerii</h2><p>Wgraj partię zdjęć i uzupełnij opisy po przesłaniu.</p></div>
    <?php require __DIR__.'/photo-collection-bulk.php'; ?>
<?php elseif($view==='categories'): ?>
    <div class="ga-heading"><h2>Kategorie zdjęć</h2><p>Porządkuj galerię. Usunąć można wyłącznie pustą kategorię.</p></div>
    <div class="ga-category-layout">
        <section class="ga-panel"><h3>Istniejące kategorie</h3>
        <?php foreach($model['categories'] as $category): ?>
            <div class="ga-category-row">
                <form method="post" action="/index.php?route=/gallery/category"><?php $token() ?><input type="hidden" name="id" value="<?= $e($category['id']) ?>"><label>Nazwa<input name="name" maxlength="80" value="<?= $e($category['name']) ?>" required></label><button>Zapisz</button></form>
                <span class="ga-muted"><?= $e($model['category_counts'][$category['id']]??0) ?> zdjęć</span>
                <form method="post" action="/index.php?route=/gallery/delete-category"><?php $token() ?><input type="hidden" name="id" value="<?= $e($category['id']) ?>"><button class="ga-secondary" <?= ($model['category_counts'][$category['id']]??0)>0?'disabled title="Najpierw przenieś zdjęcia do innej kategorii"':'' ?>>Usuń</button></form>
            </div>
        <?php endforeach ?>
        </section>
        <section class="ga-panel"><h3>Nowa kategoria</h3><form method="post" action="/index.php?route=/gallery/category"><?php $token() ?><label>Nazwa kategorii<input name="name" maxlength="80" required placeholder="Np. Zawody"></label><button>Dodaj kategorię</button></form></section>
    </div>
<?php elseif($view==='batch'): ?>
    <a class="ga-back" href="/index.php?route=/admin/gallery&amp;status=published">← Wróć do zdjęć</a>
    <?php require __DIR__.'/photo-collection-bulk.php'; ?>
<?php elseif($view==='edit'): ?>
    <a class="ga-back" href="/index.php?route=/admin/gallery&amp;status=<?= $e($model['edit']['status']) ?>">← Wróć do listy</a>
    <?php require __DIR__.'/gallery-admin-edit.php'; ?>
<?php else: ?>
<nav class="ga-status-tabs" aria-label="Stan zdjęć">
<?php foreach($labels as $key=>$label): ?><a href="/index.php?route=/admin/gallery&amp;status=<?= $e($key) ?>&amp;category=<?= $e($model['category']) ?>" <?= $status===$key?'aria-current="page"':'' ?>><span><?= $e($label) ?></span><strong><?= $e($counts[$key]) ?></strong></a><?php endforeach ?>
</nav>
<div class="ga-toolbar">
    <form method="get" action="/index.php"><input type="hidden" name="route" value="/admin/gallery"><input type="hidden" name="status" value="<?= $e($status) ?>"><label>Kategoria<select name="category"><option value="0">Wszystkie kategorie</option><?php $options($model['category']) ?></select></label><button class="ga-secondary">Filtruj</button></form>
    <span class="ga-muted"><?= $e($model['total']) ?> zdjęć<?= $model['total']>0?' · strona '.$e($model['page']).' z '.$e($model['pages']):'' ?></span>
    <?php if($model['photos']): ?><a class="ga-button ga-secondary" href="/index.php?route=/admin/gallery&amp;status=<?= $e($status) ?>&amp;batch=<?= $e(implode(',',array_column($model['photos'],'id'))) ?>">Opisz zdjęcia z tej strony</a><?php endif ?>
</div>
<?php if(array_filter($model['photos'],static fn(array $p):bool=>$p['status']==='pending')): ?>
<form id="gallery-approval" class="ga-approval" action="/index.php?route=/gallery/approve" method="post" data-gallery-approval hx-boost="false">
    <?php $token() ?><input type="hidden" name="category" value="<?= $e($model['category']) ?>">
    <label class="ga-check"><input type="checkbox" data-gallery-select-all> Zaznacz całą stronę</label>
    <span data-gallery-selection-count aria-live="polite">Wybierz zdjęcia do publikacji</span>
    <button type="submit" data-gallery-approve>Zatwierdź zaznaczone</button>
    <p role="status" data-gallery-approval-status></p>
    <progress value="0" max="1" data-gallery-approval-progress aria-label="Postęp zatwierdzania" hidden></progress>
    <a class="ga-button ga-secondary" href="/index.php?route=/admin/gallery&amp;category=<?= $e($model['category']) ?>" data-gallery-approval-refresh hidden>Odśwież kolejkę</a>
</form>
<?php endif ?>
<?php if(!$model['photos']): ?>
    <section class="ga-empty"><h2><?= $status==='pending'?'Brak zdjęć do akceptacji':'Brak zdjęć w tym widoku' ?></h2><p><?= $status==='pending'?'Nowe zgłoszenia graczy pojawią się tutaj.':'Wybierz inną kategorię lub dodaj nowe zdjęcia.' ?></p><a class="ga-button" href="/index.php?route=/admin/gallery&amp;<?= $status==='pending'&&$counts['published']>0?'status=published':'view=upload' ?>"><?= $status==='pending'&&$counts['published']>0?'Pokaż opublikowane':'Dodaj zdjęcia' ?></a></section>
<?php else: ?>
<div class="ga-table-wrap"><table class="ga-table" data-sortable="false"><thead><tr><th scope="col">Wybór</th><th scope="col">Zdjęcie</th><th scope="col">Tytuł i opis</th><th scope="col">Kategoria</th><th scope="col">Stan</th><th scope="col">Dodano</th><th scope="col">Akcje</th></tr></thead><tbody>
<?php foreach($model['photos'] as $photo): ?>
<tr data-gallery-item>
    <td data-label="Wybór"><?php if($photo['status']==='pending'): ?><label class="ga-check"><input type="checkbox" form="gallery-approval" name="ids[]" value="<?= $e($photo['id']) ?>" data-gallery-select data-revision="<?= $e($photo['revision']) ?>"><span class="visually-hidden">Zaznacz <?= $e($photo['title']) ?></span></label><input type="hidden" form="gallery-approval" name="revisions[<?= $e($photo['id']) ?>]" value="<?= $e($photo['revision']) ?>"><?php else: ?><span class="ga-muted">—</span><?php endif ?></td>
    <td data-label="Podgląd"><a class="ga-thumbnail" href="<?= $e($photo['url']) ?>" data-gallery-photo data-title="<?= $e($photo['title']) ?>" data-description="<?= $e($photo['description']) ?>"><img loading="lazy" src="<?= $e($photo['thumbnail']) ?>" alt="<?= $e($photo['title']) ?>"></a></td>
    <td class="ga-description" data-label="Zdjęcie" data-gallery-caption><a class="ga-photo-title" href="/index.php?route=/admin/gallery&amp;edit=<?= $e($photo['id']) ?>"><?= $e($photo['title']) ?></a><?php if($photo['description']): ?><p><?= $e($photo['description']) ?></p><?php else: ?><small class="ga-muted">Bez opisu</small><?php endif ?><?php if($photo['moderation_note']): ?><p class="ga-feedback"><?= $e($photo['moderation_note']) ?></p><?php endif ?></td>
    <td data-label="Kategoria"><?= $e($photo['category_name']) ?></td>
    <td data-label="Stan"><span class="mv-gallery-status" data-state="<?= $e($photo['status']) ?>"><?= $e($labels[$photo['status']]??'Oczekuje') ?></span></td>
    <td data-label="Dodano"><time datetime="<?= $e(str_replace(' ','T',$photo['created_at'])) ?>"><?= $e(substr($photo['created_at'],0,10)) ?></time></td>
    <td data-label="Akcje"><a class="ga-button ga-secondary" href="/index.php?route=/admin/gallery&amp;edit=<?= $e($photo['id']) ?>"><?= $photo['status']==='pending'?'Sprawdź':'Edytuj' ?></a></td>
</tr>
<?php endforeach ?>
</tbody></table></div>
<?php endif ?>
<?php if($model['pages']>1): ?><nav class="ga-pagination" aria-label="Strony zdjęć"><?php if($model['page']>1): ?><a class="ga-button ga-secondary" href="/index.php?route=/admin/gallery&amp;status=<?= $e($status) ?>&amp;category=<?= $e($model['category']) ?>&amp;page=<?= $e($model['page']-1) ?>">← Poprzednia</a><?php endif ?><span><?= $e($model['page']) ?> / <?= $e($model['pages']) ?></span><?php if($model['page']<$model['pages']): ?><a class="ga-button ga-secondary" href="/index.php?route=/admin/gallery&amp;status=<?= $e($status) ?>&amp;category=<?= $e($model['category']) ?>&amp;page=<?= $e($model['page']+1) ?>">Następna →</a><?php endif ?></nav><?php endif ?>
<?php endif ?>
<dialog class="mv-gallery-dialog" aria-label="Podgląd zdjęcia"><div class="mv-gallery-dialog-bar"><button type="button" data-gallery-prev aria-label="Poprzednie zdjęcie">←</button><span data-gallery-position></span><button type="button" data-gallery-next aria-label="Następne zdjęcie">→</button><button type="button" data-gallery-close aria-label="Zamknij podgląd">✕</button></div><img alt=""><div class="mv-gallery-dialog-caption"><h2></h2><p></p></div></dialog>
</div>
