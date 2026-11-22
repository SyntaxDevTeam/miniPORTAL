<?php

declare(strict_types=1);

/** @var array $model */
?>
<section class="wiki-v2 article-catalog container" lang="en" aria-labelledby="article-catalog-title">
    <div class="wiki-directory">
        <header class="wiki-directory-hero">
            <h1 id="article-catalog-title">Articles <span>&amp; tutorials</span></h1>
            <p>Follow a programming course, learn a new tool or catch up on project updates.</p>
            <form class="wiki-search article-catalog-search" action="/articles" method="get" role="search" aria-label="Search articles">
                <label for="articles-query">Search articles</label>
                <input id="articles-query" name="q" type="search" maxlength="120" placeholder="Search articles…" value="<?= $escape($model['query']) ?>">
                <?php if ($model['category'] !== ''): ?><input type="hidden" name="category" value="<?= $escape($model['category']) ?>"><?php endif; ?>
                <?php if ($model['language'] !== ''): ?><input type="hidden" name="lang" value="<?= $escape($model['language']) ?>"><?php endif; ?>
                <button type="submit">Search</button>
            </form>
        </header>

        <section aria-labelledby="article-catalog-results">
            <div class="wiki-directory-heading article-catalog-status">
                <h2 id="article-catalog-results"><?= $escape($model['results_title']) ?></h2>
                <?php if ($model['total'] > 0): ?>
                    <span><?= $escape($model['first']) ?>–<?= $escape($model['last']) ?> of <?= $escape($model['total']) ?> articles</span>
                <?php endif; ?>
            </div>

            <form class="article-catalog-filters" action="/articles" method="get" aria-label="Filter articles">
                <?php if ($model['query'] !== ''): ?><input type="hidden" name="q" value="<?= $escape($model['query']) ?>"><?php endif; ?>
                <label for="articles-category">Topic
                    <select id="articles-category" name="category">
                        <option value="">All topics</option>
                        <?php foreach ($model['categories'] as $root): ?>
                            <option value="<?= $escape($root['slug']) ?>"<?= $root['slug'] === $model['category'] ? ' selected' : '' ?>><?= $escape($root['name']) ?></option>
                            <?php foreach ($root['children'] as $child): ?>
                                <option value="<?= $escape($child['slug']) ?>"<?= $child['slug'] === $model['category'] ? ' selected' : '' ?>><?= $escape($root['name'] . ' / ' . $child['name']) ?></option>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label for="articles-language">Language
                    <select id="articles-language" name="lang">
                        <option value="">All languages</option>
                        <?php foreach ($model['languages'] as $code => $label): ?>
                            <option value="<?= $escape($code) ?>"<?= $code === $model['language'] ? ' selected' : '' ?>><?= $escape($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button type="submit">Apply filters</button>
            </form>

            <?php if ($model['active_filters'] !== []): ?>
                <ul class="article-catalog-active" aria-label="Active filters">
                    <?php foreach ($model['active_filters'] as $filter): ?>
                        <li><a href="<?= $escape($filter['href']) ?>" aria-label="<?= $escape('Remove filter: ' . $filter['label']) ?>"><?= $escape($filter['label']) ?> <span aria-hidden="true">×</span></a></li>
                    <?php endforeach; ?>
                    <?php if (count($model['active_filters']) > 1): ?>
                        <li><a class="article-catalog-clear" href="/articles">Clear all</a></li>
                    <?php endif; ?>
                </ul>
            <?php endif; ?>

            <?php if ($model['items'] !== []): ?>
                <ul class="wiki-project-grid article-catalog-list" role="list">
                    <?php foreach ($model['items'] as $item): ?>
                        <li class="wiki-project-card">
                            <div class="wiki-project-number article-catalog-meta">
                                <?php foreach ($item['categories'] as $category): ?>
                                    <a href="<?= $escape($category['href']) ?>"><?= $escape($category['name']) ?></a>
                                <?php endforeach; ?>
                                <?php if ($item['language'] !== 'und'): ?><span><?= $escape(strtoupper($item['language'])) ?></span><?php endif; ?>
                                <?php if ($item['date'] !== ''): ?><time datetime="<?= $escape($item['date']) ?>"><?= $escape($item['date']) ?></time><?php endif; ?>
                            </div>
                            <h3><a class="article-catalog-open" href="<?= $escape($item['href']) ?>" lang="<?= $escape($item['language']) ?>"><?= $escape($item['title']) ?></a></h3>
                            <p lang="<?= $escape($item['language']) ?>"><?= $escape($item['summary']) ?></p>
                            <span class="wiki-card-cta" aria-hidden="true">Read article <span>↗</span></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="wiki-project-card article-catalog-empty">
                    <p><?= $model['active_filters'] === [] ? 'No articles have been published yet.' : 'No articles match these filters.' ?></p>
                    <?php if ($model['active_filters'] !== []): ?><a href="/articles">Browse all articles</a><?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($model['previous'] !== null || $model['next'] !== null): ?>
                <nav class="article-catalog-pagination" aria-label="Article pages">
                    <?php if ($model['previous'] !== null): ?><a href="<?= $escape($model['previous']) ?>" rel="prev">← Previous</a><?php endif; ?>
                    <span>Page <?= $escape($model['page']) ?> of <?= $escape($model['pages']) ?></span>
                    <?php if ($model['next'] !== null): ?><a href="<?= $escape($model['next']) ?>" rel="next">Next →</a><?php endif; ?>
                </nav>
            <?php endif; ?>
        </section>
    </div>
</section>
