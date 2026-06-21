<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/helpers.php';

$admin = require_admin();
$context = panel_active_context($admin);
$previewRole = panel_preview_role($admin);
$canManagePages = panel_user_can($admin, 'global.pages.manage', ['type' => 'global']);
$pages = $canManagePages
    ? (db()->read('pages', ['id', 'title', 'is_published', 'updated_at'], [
        'ORDER' => [
            'updated_at' => 'DESC',
        ],
    ]) ?? [])
    : [];
$publishedCount = count(array_filter($pages, static fn (array $page): bool => (bool) $page['is_published']));
$draftCount = count($pages) - $publishedCount;
$latestUpdate = $pages[0]['updated_at'] ?? 'Brak danych';
$guilds = panel_guilds($admin);
$adminGuildCount = count(array_filter($guilds, static fn (array $guild): bool => ($guild['access_role'] ?? '') !== 'member'));
$visibleModules = panel_access()->modulesForContext((int) $admin['id'], $context, $previewRole);
$hasBotModule = count(array_filter(
    $visibleModules,
    static fn (array $module): bool => ($module['key'] ?? '') === 'bot'
)) > 0;

$botStats = [
    'servers' => count($guilds),
    'admin_servers' => $adminGuildCount,
    'modules' => 4,
    'shop_limit' => '5/5',
];
?>
<!doctype html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Panel - <?= e(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(site_url('/assets/css/style.css')) ?>">
</head>
<body class="admin-body">
    <?php render_admin_header($admin, 'dashboard'); ?>

    <main class="admin-panel">
        <div class="toolbar">
            <div>
                <p class="eyebrow">miniCMS / <?= e($context['description']) ?></p>
                <h1><?= e($context['label']) ?></h1>
                <p class="lead">Jeden panel, a widoczne moduly wynikaja z aktywnego kontekstu i uprawnien.</p>
                <?php if (panel_is_owner($admin) && $previewRole !== 'owner'): ?>
                    <p class="preview-note">Tryb podgladu: <?= e(panel_preview_label($previewRole)) ?>. Realne uprawnienia konta nie zostaly zmienione.</p>
                <?php endif; ?>
            </div>
        </div>

        <?php render_panel_modules($admin, 'dashboard', $context); ?>

        <section class="dashboard-stats" aria-label="Statystyki panelu">
            <?php if ($canManagePages): ?>
                <article class="stat-board">
                    <div class="stat-board-header">
                        <span>Strona</span>
                        <a href="<?= e(panel_url('/admin/pages.php', 'global')) ?>">Zarzadzaj</a>
                    </div>
                    <div class="admin-stats compact">
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
                    </div>
                    <p class="small-note">Ostatnia aktualizacja: <?= e($latestUpdate) ?></p>
                </article>
            <?php endif; ?>

            <?php if ($hasBotModule): ?>
                <article class="stat-board">
                    <div class="stat-board-header">
                        <span>Bot</span>
                        <a href="<?= e(panel_url('/admin/bot.php', (string) $context['key'])) ?>">Podglad</a>
                    </div>
                    <div class="admin-stats compact">
                        <div>
                            <span>Serwery</span>
                            <strong><?= $botStats['servers'] ?></strong>
                        </div>
                        <div>
                            <span>Zarzadzane</span>
                            <strong><?= $botStats['admin_servers'] ?></strong>
                        </div>
                        <div>
                            <span>Moduly</span>
                            <strong><?= $botStats['modules'] ?></strong>
                        </div>
                    </div>
                    <p class="small-note">Sklep freemium: <?= e($botStats['shop_limit']) ?> przedmiotow. Zakres zalezy od kontekstu Discord.</p>
                </article>
            <?php endif; ?>

            <article class="stat-board">
                <div class="stat-board-header">
                    <span>Konto</span>
                    <a href="<?= e(panel_url('/admin/account.php', 'account')) ?>">Profil</a>
                </div>
                <div class="admin-stats compact">
                    <div>
                        <span>Konteksty</span>
                        <strong><?= count(panel_contexts($admin)) ?></strong>
                    </div>
                    <div>
                        <span>Rola</span>
                        <strong><?= e($context['role'] === 'owner' ? 'Root' : ($context['role'] === 'guild_admin' ? 'Admin' : 'User')) ?></strong>
                    </div>
                    <div>
                        <span>Discord</span>
                        <strong>#<?= (int) $admin['id'] ?></strong>
                    </div>
                </div>
                <p class="small-note">Zalogowano jako <?= e(panel_admin_name($admin)) ?>.</p>
            </article>
        </section>
    </main>
</body>
</html>
