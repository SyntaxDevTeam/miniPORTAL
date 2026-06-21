<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/helpers.php';

$admin = require_admin();
if (panel_is_owner($admin) && panel_preview_role($admin) !== 'owner') {
    redirect('/admin/?preview_role=' . rawurlencode((string) panel_preview_role($admin)));
}

$context = panel_set_active_context($admin, 'global');
panel_require_permission($admin, 'global.pages.manage', $context);
$pages = db()->read('pages', ['id', 'title', 'slug', 'is_published', 'sort_order', 'updated_at'], [
    'ORDER' => [
        'sort_order' => 'ASC',
        'title' => 'ASC',
    ],
]) ?? [];
$publishedCount = count(array_filter($pages, static fn (array $page): bool => (bool) $page['is_published']));
$draftCount = count($pages) - $publishedCount;
?>
<!doctype html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Strony - <?= e(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(site_url('/assets/css/style.css')) ?>">
</head>
<body class="admin-body">
    <?php render_admin_header($admin, 'pages'); ?>

    <main class="admin-panel">
        <?php render_panel_modules($admin, 'pages', $context); ?>

        <div class="toolbar">
            <div>
                <p class="eyebrow">Mini modul / Strony</p>
                <h1>Zarzadzanie stronami</h1>
            </div>
            <a class="button" href="<?= e(panel_url('/admin/page-form.php', 'global')) ?>">Dodaj strone</a>
        </div>

        <section class="admin-stats" aria-label="Statystyki stron">
            <div>
                <span>Wszystkie</span>
                <strong><?= count($pages) ?></strong>
            </div>
            <div>
                <span>Opublikowane</span>
                <strong><?= $publishedCount ?></strong>
            </div>
            <div>
                <span>Szkice</span>
                <strong><?= $draftCount ?></strong>
            </div>
        </section>

        <table>
            <thead>
                <tr>
                    <th>Tytul</th>
                    <th>Slug</th>
                    <th>Status</th>
                    <th>Kolejnosc</th>
                    <th>Aktualizacja</th>
                    <th>Akcje</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pages as $page): ?>
                    <tr>
                        <td><?= e($page['title']) ?></td>
                        <td><code><?= e($page['slug']) ?></code></td>
                        <td><?= $page['is_published'] ? 'Opublikowana' : 'Szkic' ?></td>
                        <td><?= (int) $page['sort_order'] ?></td>
                        <td><?= e($page['updated_at']) ?></td>
                        <td class="actions">
                            <a href="<?= e(panel_url('/admin/page-form.php?id=' . $page['id'], 'global')) ?>">Edytuj</a>
                            <form method="post" action="<?= e(panel_url('/admin/delete-page.php', 'global')) ?>" onsubmit="return confirm('Usunac te strone?');">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="id" value="<?= (int) $page['id'] ?>">
                                <button type="submit" class="link-button">Usun</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </main>
</body>
</html>
