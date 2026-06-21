<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/helpers.php';

$admin = require_admin();
$previewRole = panel_preview_role($admin);
$guilds = panel_guilds($admin);
$manageableGuilds = array_values(array_filter(
    $guilds,
    static fn (array $guild): bool => panel_user_can($admin, 'guild.settings.view', [
        'type' => 'guild',
        'guild_id' => (int) $guild['id'],
    ])
));

$selectedGuildId = isset($_GET['guild']) ? (int) $_GET['guild'] : 0;
$selectedGuild = null;
foreach ($manageableGuilds as $guild) {
    if ((int) $guild['id'] === $selectedGuildId) {
        $selectedGuild = $guild;
        break;
    }
}

if ($selectedGuild === null && $manageableGuilds !== []) {
    $selectedGuild = $manageableGuilds[0];
}

$context = $selectedGuild !== null
    ? panel_set_active_context($admin, 'guild:' . (int) $selectedGuild['id'])
    : panel_set_active_context($admin, 'account');

if ($selectedGuild !== null) {
    panel_require_permission($admin, 'guild.settings.view', $context);
}

$shopItems = [
    ['name' => 'VIP 7 dni', 'type' => 'Ranga', 'price' => '2 500'],
    ['name' => 'Kod boost', 'type' => 'Kod', 'price' => '1 200'],
    ['name' => 'Kolor nicku', 'type' => 'Ranga', 'price' => '850'],
    ['name' => 'Prywatny kanal', 'type' => 'Ranga', 'price' => '4 000'],
    ['name' => 'Mystery drop', 'type' => 'Kod', 'price' => '650'],
];
?>
<!doctype html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bot - <?= e(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(site_url('/assets/css/style.css')) ?>">
</head>
<body class="admin-body">
    <?php render_admin_header($admin, 'bot'); ?>

    <main class="admin-panel">
        <?php render_panel_modules($admin, 'bot', $context); ?>

        <div class="toolbar">
            <div>
                <p class="eyebrow">Mini modul / Bot</p>
                <h1>Zarzadzanie botem</h1>
                <p class="lead">Widok dziala w kontekscie serwera, na ktorym masz uprawnienia administratora.</p>
                <?php if (panel_is_owner($admin) && $previewRole !== 'owner'): ?>
                    <p class="preview-note">Tryb podgladu: <?= e(panel_preview_label($previewRole)) ?>.</p>
                <?php endif; ?>
            </div>
            <a class="button secondary" href="<?= e(panel_url('/admin/', (string) $context['key'])) ?>">Wroc do panelu</a>
        </div>

        <?php if ($manageableGuilds === []): ?>
            <section class="empty-state">
                <span class="module-status">Brak serwerow</span>
                <h2>Nie masz jeszcze serwera do konfiguracji bota</h2>
                <?php if ($previewRole === 'member'): ?>
                    <p class="small-note">Zwykly uzytkownik widzi tylko swoje konto i informacje, a nie konfiguracje bota dla serwera.</p>
                <?php else: ?>
                    <p class="small-note">Dostep pojawi sie po zalogowaniu kontem Discord z uprawnieniem Administrator lub Manage Server na serwerze zapisanym w `panel_guilds`.</p>
                <?php endif; ?>
            </section>
        <?php else: ?>
            <section class="server-grid" aria-label="Wybierz serwer">
                <?php foreach ($manageableGuilds as $guild): ?>
                    <a class="server-card <?= (int) $guild['id'] === (int) $selectedGuild['id'] ? 'is-active' : '' ?>" href="<?= e(panel_url('/admin/bot.php?guild=' . (int) $guild['id'], 'guild:' . (int) $guild['id'])) ?>">
                        <span><?= e($guild['plan']) ?></span>
                        <strong><?= e($guild['name']) ?></strong>
                        <em><?= number_format((int) ($guild['member_count'] ?? 0), 0, ',', ' ') ?> uzytkownikow - <?= e($guild['status']) ?></em>
                    </a>
                <?php endforeach; ?>
            </section>

            <section class="bot-dashboard" aria-label="Panel konfiguracji bota">
                <article class="bot-card bot-card-wide">
                    <div class="bot-card-header">
                        <div>
                            <span class="module-status">Wybrany serwer</span>
                            <h2><?= e($selectedGuild['name']) ?></h2>
                            <p>Glowny przelacznik uruchamia lub wylacza aktywne moduly bota dla tego serwera.</p>
                        </div>
                        <label class="switch-control">
                            <input type="checkbox" checked>
                            <span></span>
                            <strong></strong>
                        </label>
                    </div>
                    <div class="bot-mini-stats">
                        <div>
                            <span>Plan</span>
                            <strong><?= e($selectedGuild['plan']) ?></strong>
                        </div>
                        <div>
                            <span>Moduly</span>
                            <strong>4</strong>
                        </div>
                        <div>
                            <span>Status</span>
                            <strong><?= e($selectedGuild['status']) ?></strong>
                        </div>
                    </div>
                </article>

                <article class="bot-card">
                    <span class="module-status">Ekonomia</span>
                    <h2>Ustawienia ekonomii</h2>
                    <div class="settings-grid">
                        <label>
                            /daily
                            <input type="number" value="250">
                        </label>
                        <label>
                            /work minimum
                            <input type="number" value="80">
                        </label>
                        <label>
                            /work maximum
                            <input type="number" value="420">
                        </label>
                    </div>
                </article>

                <article class="bot-card">
                    <span class="module-status">Jezyk</span>
                    <h2>Jezyk bota</h2>
                    <div class="language-picker" role="group" aria-label="Jezyk bota">
                        <label>
                            <input type="radio" name="language" checked>
                            Polski
                        </label>
                        <label>
                            <input type="radio" name="language">
                            English
                        </label>
                    </div>
                    <p class="small-note">Dostepne sa przygotowane tlumaczenia PL i ENG.</p>
                </article>

                <article class="bot-card bot-card-wide">
                    <div class="bot-card-header compact">
                        <div>
                            <span class="module-status">Sklep</span>
                            <h2>Przedmioty sklepu</h2>
                            <p>Limit freemium: 5 przedmiotow na serwer.</p>
                        </div>
                        <button type="button" disabled>Limit 5/5</button>
                    </div>
                    <div class="shop-list">
                        <?php foreach ($shopItems as $item): ?>
                            <div class="shop-item">
                                <strong><?= e($item['name']) ?></strong>
                                <span><?= e($item['type']) ?></span>
                                <em><?= e($item['price']) ?> monet</em>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </article>

                <article class="bot-card">
                    <span class="module-status">Roadmap</span>
                    <h2>Kolejne kafelki</h2>
                    <ul class="feature-list">
                        <li>Logi moderacji i kanal zdarzen.</li>
                        <li>Role za aktywnosc i levele.</li>
                        <li>Panel komend slash per serwer.</li>
                        <li>Backup konfiguracji bota.</li>
                    </ul>
                </article>
            </section>
        <?php endif; ?>
    </main>
</body>
</html>
