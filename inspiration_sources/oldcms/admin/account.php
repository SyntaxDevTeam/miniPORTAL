<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/helpers.php';

$admin = require_admin();
$context = panel_set_active_context($admin, 'account');
panel_require_permission($admin, 'user.profile.view', $context);

$contexts = panel_contexts($admin);
$previewRole = panel_preview_role($admin);
$guilds = panel_guilds($admin);
$adminGuilds = array_values(array_filter(
    $guilds,
    static fn (array $guild): bool => ($guild['access_role'] ?? '') !== 'member'
));
?>
<!doctype html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Moje konto - <?= e(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(site_url('/assets/css/style.css')) ?>">
</head>
<body class="admin-body">
    <?php render_admin_header($admin, 'account'); ?>

    <main class="admin-panel">
        <?php render_panel_modules($admin, 'account', $context); ?>

        <div class="toolbar">
            <div>
                <p class="eyebrow">Mini modul / Konto</p>
                <h1><?= e(panel_admin_name($admin)) ?></h1>
                <p class="lead">Profil Discord i zakres dostepu wykryty dla tego konta.</p>
                <?php if (panel_is_owner($admin) && $previewRole !== 'owner'): ?>
                    <p class="preview-note">Aktualnie ogladasz panel jako: <?= e(panel_preview_label($previewRole)) ?>.</p>
                <?php endif; ?>
            </div>
        </div>

        <section class="account-layout" aria-label="Profil i dostep">
            <article class="account-profile">
                <?php if (!empty($admin['avatar_url'])): ?>
                    <img src="<?= e($admin['avatar_url']) ?>" alt="">
                <?php else: ?>
                    <span><?= e(mb_substr(panel_admin_name($admin), 0, 1, 'UTF-8')) ?></span>
                <?php endif; ?>
                <div>
                    <span class="module-status">Discord</span>
                    <h2><?= e(panel_admin_name($admin)) ?></h2>
                    <p><code><?= e($admin['discord_user_id']) ?></code></p>
                </div>
            </article>

            <section class="admin-stats compact" aria-label="Zakres konta">
                <div>
                    <span>Konteksty</span>
                    <strong><?= count($contexts) ?></strong>
                </div>
                <div>
                    <span>Serwery</span>
                    <strong><?= count($guilds) ?></strong>
                </div>
                <div>
                    <span>Admin</span>
                    <strong><?= count($adminGuilds) ?></strong>
                </div>
            </section>
        </section>

        <section class="context-list" aria-label="Dostepne konteksty">
            <?php foreach ($contexts as $availableContext): ?>
                <a class="context-row" href="<?= e(panel_url('/admin/', (string) $availableContext['key'])) ?>">
                    <span><?= e($availableContext['label']) ?></span>
                    <strong><?= e($availableContext['description']) ?></strong>
                </a>
            <?php endforeach; ?>
        </section>
    </main>
</body>
</html>
