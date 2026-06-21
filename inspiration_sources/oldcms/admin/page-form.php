<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/helpers.php';

$admin = require_admin();
if (panel_is_owner($admin) && panel_preview_role($admin) !== 'owner') {
    redirect('/admin/?preview_role=' . rawurlencode((string) panel_preview_role($admin)));
}

$context = panel_set_active_context($admin, 'global');
panel_require_permission($admin, 'global.pages.manage', $context);

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$errors = [];
$page = [
    'title' => '',
    'slug' => '',
    'excerpt' => '',
    'content' => '',
    'is_published' => 1,
    'sort_order' => 100,
];

if ($id > 0) {
    $pages = db()->read('pages', '*', [
        'id' => $id,
        'LIMIT' => 1,
    ]);
    $found = $pages[0] ?? null;

    if (!$found) {
        http_response_code(404);
        exit('Nie znaleziono strony.');
    }

    $page = $found;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $page['title'] = trim((string) ($_POST['title'] ?? ''));
    $page['slug'] = trim((string) ($_POST['slug'] ?? ''));
    $page['excerpt'] = trim((string) ($_POST['excerpt'] ?? ''));
    $page['content'] = trim((string) ($_POST['content'] ?? ''));
    $page['is_published'] = isset($_POST['is_published']) ? 1 : 0;
    $page['sort_order'] = (int) ($_POST['sort_order'] ?? 100);

    if ($page['title'] === '') {
        $errors[] = 'Tytul jest wymagany.';
    }

    if ($page['slug'] === '') {
        $page['slug'] = slugify($page['title']);
    } else {
        $page['slug'] = slugify($page['slug']);
    }

    if ($page['content'] === '') {
        $errors[] = 'Tresc jest wymagana.';
    }

    $duplicatePages = db()->read('pages', ['id'], [
        'slug' => $page['slug'],
        'id[!]' => $id,
        'LIMIT' => 1,
    ]);
    if (!empty($duplicatePages)) {
        $errors[] = 'Slug jest juz uzywany przez inna strone.';
    }

    if (!$errors) {
        $pageData = [
            'title' => $page['title'],
            'slug' => $page['slug'],
            'excerpt' => $page['excerpt'],
            'content' => $page['content'],
            'is_published' => $page['is_published'],
            'sort_order' => $page['sort_order'],
        ];

        if ($id > 0) {
            db()->update('pages', $pageData, ['id' => $id]);
        } else {
            db()->create('pages', $pageData);
        }

        redirect('/admin/pages.php?context=global');
    }
}
?>
<!doctype html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $id ? 'Edycja' : 'Nowa strona' ?> - <?= e(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(site_url('/assets/css/style.css')) ?>">
</head>
<body class="admin-body">
    <?php render_admin_header($admin, 'pages'); ?>

    <main class="admin-panel narrow">
        <p class="eyebrow">Edycja tresci</p>
        <h1><?= $id ? 'Edytuj strone' : 'Dodaj strone' ?></h1>

        <?php foreach ($errors as $error): ?>
            <p class="alert"><?= e($error) ?></p>
        <?php endforeach; ?>

        <form method="post" class="page-form">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

            <label>
                Tytul
                <input name="title" value="<?= e($page['title']) ?>" required>
            </label>

            <label>
                Slug
                <input name="slug" value="<?= e($page['slug']) ?>" placeholder="np. bot-moderacyjny">
            </label>

            <label>
                Zajawka
                <textarea name="excerpt" rows="3"><?= e($page['excerpt']) ?></textarea>
            </label>

            <div class="form-field">
                <label for="content">Tresc</label>
                <textarea id="content" name="content" rows="16" required data-wysiwyg="quill"><?= e($page['content']) ?></textarea>
                <div id="cms-content-editor" class="wysiwyg-editor"></div>
            </div>

            <div class="form-row">
                <label>
                    Kolejnosc
                    <input type="number" name="sort_order" value="<?= (int) $page['sort_order'] ?>">
                </label>
                <label class="checkbox">
                    <input type="checkbox" name="is_published" <?= $page['is_published'] ? 'checked' : '' ?>>
                    Opublikowana
                </label>
            </div>

            <div class="form-actions">
                <button type="submit">Zapisz</button>
                <a class="button secondary" href="<?= e(panel_url('/admin/pages.php', 'global')) ?>">Anuluj</a>
            </div>
        </form>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
    <script src="<?= e(site_url('/assets/js/admin-editor.js')) ?>"></script>
</body>
</html>
