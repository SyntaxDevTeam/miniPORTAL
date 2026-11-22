<?php
/** @var array $model @var callable $escape */
$index = $model['mode'] === 'index';
$reader = $model['mode'] === 'page';
$labels = $model['labels'] ?? ['search' => 'Find a project', 'no_results' => 'No matching projects. Try another search.'];
?>
<div class="wiki-v2 container" data-wiki lang="<?= $escape($model['language'] ?? 'en') ?>">
<?php if ($index): ?>
    <div class="wiki-directory">
        <header class="wiki-directory-hero"><span class="wiki-eyebrow">THE KNOWLEDGE HUB</span><h1>Good projects.<br><span>Great documentation.</span></h1><p>Explore the guides, find your answers and build something exceptional.</p><div class="wiki-search"><label for="wiki-search">Find a project</label><input id="wiki-search" type="search" placeholder="Search projects…" data-wiki-search autocomplete="off"><kbd>Ctrl K</kbd></div></header>
        <div class="wiki-directory-heading"><h2>Project library</h2><span><?= count($model['projects']) ?> projects</span></div>
        <div class="wiki-project-grid">
        <?php foreach ($model['projects'] as $i => $project): ?>
            <a class="wiki-project-card" href="<?= $escape($project['href']) ?>" data-wiki-result data-search="<?= $escape($project['title'] . ' ' . $project['summary']) ?>"><span class="wiki-project-number"><?= sprintf('%02d', $i + 1) ?> / PROJECT</span><h3><?= $escape($project['title']) ?></h3><p><?= $escape($project['summary']) ?></p><span class="wiki-card-cta">Explore documentation <span aria-hidden="true">↗</span></span></a>
        <?php endforeach; ?>
        </div>
        <?php if ($model['projects'] === []): ?><p>No project documentation has been published yet.</p><?php endif; ?>
        <p data-wiki-empty hidden role="status"><?= $escape($labels['no_results']) ?></p>
    </div>
<?php else: ?>
    <div class="wiki-topline"><nav aria-label="Breadcrumb"><a href="/wiki"><?= $escape($labels['docs']) ?></a><span aria-hidden="true">/</span><a href="<?= $escape($model['project_href']) ?>"><?= $escape($model['project']) ?></a><?php if ($reader): ?><span aria-hidden="true">/</span><span aria-current="page"><?= $escape($model['title']) ?></span><?php endif; ?></nav><nav class="wiki-languages" aria-label="Language / Język"><?php foreach ($model['languages'] as $language): ?><a href="<?= $escape($language['href']) ?>" lang="<?= $escape($language['code']) ?>" hreflang="<?= $escape($language['code']) ?>" <?= $language['active'] ? 'aria-current="true"' : '' ?> title="<?= $escape($language['overview'] ? $labels['translation'] : $language['label']) ?>"><?= $escape($language['label']) ?><?php if ($language['overview']): ?><span aria-hidden="true"> ↗</span><span class="wiki-sr-only"> — <?= $escape($labels['translation']) ?></span><?php endif; ?></a><?php endforeach; ?></nav></div>
    <div class="wiki-layout <?= $reader ? 'wiki-layout-reader' : '' ?>">
        <aside class="wiki-sidebar">
            <a class="wiki-brand" href="<?= $escape($model['project_href']) ?>"><span class="wiki-brand-icon" aria-hidden="true">≡</span><span><?= $escape($model['project']) ?><small><?= $escape($labels['docs']) ?></small></span></a>
            <div class="wiki-search"><label for="wiki-search"><?= $escape($labels['search']) ?></label><input id="wiki-search" type="search" placeholder="<?= $escape($labels['search']) ?>…" data-wiki-search autocomplete="off"><kbd>⌕</kbd></div>
            <details class="wiki-document-menu" open><summary><?= $escape($labels['contents']) ?><span><?= count($model['pages']) ?></span></summary><nav aria-label="<?= $escape($labels['contents']) ?>"><a class="wiki-overview" href="<?= $escape($model['project_href']) ?>" <?= !$reader ? 'aria-current="page"' : '' ?>><?= $escape($labels['overview']) ?></a><?php foreach ($model['pages'] as $i => $item): ?><a href="<?= $escape($item['href']) ?>" <?= $item['active'] ? 'aria-current="page"' : '' ?> data-wiki-result data-search="<?= $escape($item['title'] . ' ' . $item['summary']) ?>"><span class="wiki-nav-number" aria-hidden="true"><?= sprintf('%02d', $i + 1) ?></span><?= $escape($item['title']) ?></a><?php endforeach; ?></nav></details>
            <p data-wiki-empty hidden role="status"><?= $escape($labels['no_results']) ?></p>
        </aside>
        <div class="wiki-main">
        <?php if (!$reader): ?>
            <header class="wiki-project-hero"><span class="wiki-eyebrow">PROJECT / <?= $escape(strtoupper($model['language'])) ?></span><h1><?= $escape($model['project']) ?></h1><p><?= $escape($model['description'] ?: $labels['intro']) ?></p><div class="wiki-hero-bottom"><span><?= count($model['pages']) ?> <?= $escape($labels['contents']) ?></span><?php if ($model['pages'] !== []): ?><a class="wiki-primary" href="<?= $escape($model['pages'][0]['href']) ?>"><?= $escape($labels['start']) ?> <span aria-hidden="true">→</span></a><?php endif; ?></div></header>
            <div class="wiki-chapter-heading"><h2><?= $escape($labels['contents']) ?></h2><span><?= $escape(strtoupper($model['language'])) ?></span></div>
            <div class="wiki-chapters"><?php foreach ($model['pages'] as $i => $item): ?><a href="<?= $escape($item['href']) ?>" class="wiki-chapter" data-wiki-result data-search="<?= $escape($item['title'] . ' ' . $item['summary']) ?>"><span class="wiki-chapter-number"><?= sprintf('%02d', $i + 1) ?></span><span><h3><?= $escape($item['title']) ?></h3><p><?= $escape($item['summary']) ?></p></span><span class="wiki-chapter-arrow" aria-hidden="true">↗</span></a><?php endforeach; ?></div>
            <?php if ($model['pages'] === []): ?><p><?= $escape($labels['empty']) ?></p><?php endif; ?>
        <?php else: ?>
            <article class="wiki-article" data-wiki-article data-copy="<?= $escape($labels['copy']) ?>" data-copied="<?= $escape($labels['copied']) ?>" data-copy-error="<?= $escape($labels['copy_error']) ?>">
                <header class="wiki-article-header"><span class="wiki-eyebrow"><?= $escape($model['project']) ?> / <?= $escape(strtoupper($model['language'])) ?></span><h1><?= $escape($model['title']) ?></h1><p><?= $escape($model['description']) ?></p><div class="wiki-article-meta"><span><?= $model['minutes'] ?> <?= $escape($labels['read']) ?></span><?php if ($model['updated'] !== ''): ?><span><?= $escape($labels['updated']) ?> <time datetime="<?= $escape($model['updated']) ?>"><?= $escape($model['updated']) ?></time></span><?php endif; ?></div></header>
                <details class="wiki-inline-toc" data-wiki-toc-mobile hidden><summary><?= $escape($labels['on_page']) ?></summary><nav aria-label="<?= $escape($labels['on_page']) ?>"></nav></details>
                <div class="wiki-prose" data-wiki-prose><?php $this->render_rich_content($model['content'], $model['format']); ?></div>
            </article>
            <nav class="wiki-pagination" aria-label="<?= $escape($labels['contents']) ?>"><?php foreach (['previous', 'next'] as $direction): ?><?php if ($model[$direction] !== null): ?><a href="<?= $escape($model[$direction]['href']) ?>"><small><?= $escape($labels[$direction]) ?> <?= $direction === 'next' ? '→' : '←' ?></small><strong><?= $escape($model[$direction]['title']) ?></strong></a><?php else: ?><span></span><?php endif; ?><?php endforeach; ?></nav>
        <?php endif; ?>
        </div>
        <?php if ($reader): ?><aside class="wiki-toc" data-wiki-toc hidden><strong><?= $escape($labels['on_page']) ?></strong><nav aria-label="<?= $escape($labels['on_page']) ?>"></nav><a class="wiki-back-top" href="#wiki-search"><?= $escape($labels['search']) ?> ↑</a></aside><?php endif; ?>
    </div>
<?php endif; ?>
</div>
