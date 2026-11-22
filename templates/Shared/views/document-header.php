<?php declare(strict_types=1); ?>
<header class="stylebook-hero border-bottom document-header" data-document-header<?= !empty($model['track_statistics']) ? ' data-article-statistics' : '' ?> data-share-path="<?= $escape($path) ?>" data-share-title="<?= $escape($model['title']) ?>">
    <div class="container py-5 document-header-layout">
        <div class="document-header-copy">
            <p class="eyebrow mb-2"><?= $escape($model['eyebrow']) ?></p>
            <h1 class="display-4 fw-bold"><?= $escape($model['title']) ?></h1>
            <p class="lead text-secondary mb-0"><?= $escape($model['lead']) ?></p>
        </div>
        <div class="document-header-actions" aria-label="Opcje artykułu">
            <details class="document-share" data-document-share>
                <summary class="document-action" data-document-share-trigger>
                    <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m8 12 8-5M8 12l8 5"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="5" r="3"/><circle cx="18" cy="19" r="3"/></svg>
                    Udostępnij
                </summary>
                <div class="document-share-menu">
                    <a data-document-facebook href="https://www.facebook.com/sharer/sharer.php?u=<?= rawurlencode($url) ?>" target="_blank" rel="noopener noreferrer" hx-boost="false">Facebook</a>
                    <a data-document-mail href="mailto:?subject=<?= rawurlencode($model['title']) ?>&amp;body=<?= rawurlencode($url) ?>" hx-boost="false">E-mail</a>
                    <button type="button" data-document-copy hidden>Kopiuj link</button>
                    <label>Link do artykułu<input data-document-url type="url" readonly value="<?= $escape($url) ?>" aria-label="Link do artykułu"></label>
                </div>
            </details>
            <?php if($download!==''): ?>
            <a class="document-action" href="<?= $escape($download) ?>" download hx-boost="false" data-no-loading>
                <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3v12m-5-5 5 5 5-5M4 16v5h16v-5"/></svg>
                Pobierz jako PDF
            </a>
            <?php endif; ?>
            <span class="document-action-status" role="status" aria-live="polite" data-document-status></span>
        </div>
    </div>
</header>
