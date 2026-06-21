<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/render.php';

$slug = $_GET['page'] ?? 'start';
$slug = is_string($slug) ? slugify($slug) : 'start';

try {
    $page = get_page_by_slug($slug);
    $pages = get_published_pages();
} catch (Throwable $e) {
    render_setup_problem($e);
}

if (!$page) {
    http_response_code(404);
    $page = [
        'title' => 'Nie znaleziono strony',
        'excerpt' => 'Ta podstrona nie istnieje albo nie zostala jeszcze opublikowana.',
        'content' => '<p>Wroc na strone glowna albo wybierz jedna z dostepnych publikacji.</p>',
    ];
}
?>
<!doctype html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($page['title']) ?> - <?= e(APP_NAME) ?></title>
    <meta name="description" content="<?= e($page['excerpt'] ?? '') ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(site_url('/assets/css/future-template.css')) ?>">
</head>
<body class="future-template">
    <header class="site-header navbar navbar-expand-lg" aria-label="Glowne menu">
        <div class="container-xl">
            <a class="brand navbar-brand" href="<?= e(site_url('/')) ?>"><?= e(APP_NAME) ?></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#siteNav" aria-controls="siteNav" aria-expanded="false" aria-label="Pokaz menu">
                <span class="navbar-toggler-icon"></span>
            </button>
            <nav class="collapse navbar-collapse" id="siteNav">
                <div class="site-nav navbar-nav ms-lg-auto">
                    <?php foreach ($pages as $navPage): ?>
                        <a class="nav-link" href="<?= e(site_url('/' . $navPage['slug'])) ?>"><?= e($navPage['title']) ?></a>
                    <?php endforeach; ?>
                    <a class="nav-link nav-admin" href="<?= e(site_url('/admin/')) ?>">Panel</a>
                </div>
            </nav>
        </div>
    </header>

    <main>
        <section class="hero">
            <div class="container-xl">
                <div class="row g-4 align-items-end">
                    <div class="col-lg-8">
                        <p class="eyebrow">SyntaxDevBots / <?= e($page['title']) ?></p>
                        <h1><?= e($page['title']) ?></h1>
                        <?php if (!empty($page['excerpt'])): ?>
                            <p class="lead"><?= e($page['excerpt']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="col-lg-4">
                        <div class="status-panel">
                            <div class="status-line">
                                <span class="signal" aria-hidden="true"></span>
                                <span>Lifecycle</span>
                            </div>
                            <dl>
                                <div>
                                    <dt>Użytkowników</dt>
                                    <dd>45879<? /*=  count($pages)*/ ?></dd>
                                </div>
                                <div>
                                    <dt>Status bota</dt>
                                    <dd>Offline</dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="content-zone">
            <div class="container-xl">
                <div class="row g-4">
                    <article class="col-lg-8">
                        <div class="content-panel cms-content">
                            <?= $page['content'] ?>
                        </div>
                    </article>

                    <aside class="col-lg-4" aria-label="Lista stron">
                        <div class="page-list">
                            <h2>Dostepne strony</h2>
                            <div class="page-grid">
                                <?php foreach ($pages as $listedPage): ?>
                                    <a class="page-card" href="<?= e(site_url('/' . $listedPage['slug'])) ?>">
                                        <strong><?= e($listedPage['title']) ?></strong>
                                        <span><?= e($listedPage['excerpt']) ?></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </aside>
                </div>
            </div>
        </section>
    </main>

    <footer class="site-footer">
        <div class="container-xl">
            <span>&copy; <?= date('Y') ?> <?= e(APP_NAME) ?> powered by <a href="mailto:wieszczy85@gmail.com">miniCMS</a>. Developed by <a href="https://syntaxdevteam.pl">SyntaxDevTeam</a>.</span>
        </div>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
