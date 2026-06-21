<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/helpers.php';

$admin = require_admin();
panel_require_permission($admin, 'global.pages.manage', ['type' => 'global']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/pages.php?context=global');
}

verify_csrf();

$id = (int) ($_POST['id'] ?? 0);

if ($id > 0) {
    db()->delete('pages', ['id' => $id]);
}

redirect('/admin/pages.php?context=global');
