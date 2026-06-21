<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

function start_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function site_url(string $path = ''): string
{
    $base = rtrim(APP_URL, '/');
    $path = '/' . ltrim($path, '/');

    return $base . $path;
}

function redirect(string $path): never
{
    if (preg_match('#^https?://#', $path) === 1) {
        header('Location: ' . $path);
    } else {
        header('Location: ' . site_url($path));
    }

    exit;
}

function csrf_token(): string
{
    start_session();

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    start_session();

    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(419);
        exit('Nieprawidlowy token formularza.');
    }
}

function current_admin(): ?array
{
    start_session();

    if (empty($_SESSION[ADMIN_SESSION_KEY])) {
        return null;
    }

    try {
        $admins = db()->read('admins', [
            'id',
            'discord_user_id',
            'username',
            'global_name',
            'avatar_url',
        ], [
            'id' => (int) $_SESSION[ADMIN_SESSION_KEY],
            'LIMIT' => 1,
        ]);
    } catch (Throwable) {
        return null;
    }

    $admin = $admins[0] ?? null;

    return is_array($admin) ? $admin : null;
}

function require_admin(): array
{
    $admin = current_admin();

    if (!$admin) {
        redirect('/admin/login.php');
    }

    return $admin;
}

function panel_contexts(array $admin): array
{
    return panel_access()->contextsForUser((int) $admin['id']);
}

function panel_is_owner(array $admin): bool
{
    return panel_access()->isGlobalOwner((int) $admin['id']);
}

function panel_preview_role(array $admin): ?string
{
    if (!panel_is_owner($admin)) {
        return null;
    }

    start_session();

    $sessionKey = 'panel_preview_role_' . (int) $admin['id'];
    $requestedRole = $_GET['preview_role'] ?? null;
    $allowedRoles = ['owner', 'guild_admin', 'member'];

    if (is_string($requestedRole) && in_array($requestedRole, $allowedRoles, true)) {
        $_SESSION[$sessionKey] = $requestedRole;
    }

    $role = $_SESSION[$sessionKey] ?? 'owner';

    return is_string($role) && in_array($role, $allowedRoles, true) ? $role : 'owner';
}

function panel_preview_label(?string $previewRole): string
{
    return match ($previewRole) {
        'guild_admin' => 'Admin serwera',
        'member' => 'Uzytkownik',
        default => 'Developer',
    };
}

function panel_user_can(array $admin, string $permission, array $context): bool
{
    return panel_access()->userCan(
        (int) $admin['id'],
        $permission,
        $context,
        panel_preview_role($admin)
    );
}

function panel_guilds(array $admin): array
{
    return panel_access()->guildsForUser((int) $admin['id'], panel_preview_role($admin));
}

function panel_active_context(array $admin): array
{
    start_session();

    $adminId = (int) $admin['id'];
    $sessionKey = 'panel_context_' . $adminId;
    $requestedContext = $_GET['context'] ?? null;
    $contextKey = is_string($requestedContext) && $requestedContext !== ''
        ? $requestedContext
        : ($_SESSION[$sessionKey] ?? null);

    $context = panel_access()->contextFromKey($adminId, is_string($contextKey) ? $contextKey : null);
    $_SESSION[$sessionKey] = $context['key'];

    return $context;
}

function panel_set_active_context(array $admin, string $contextKey): array
{
    start_session();

    $context = panel_access()->contextFromKey((int) $admin['id'], $contextKey);
    $_SESSION['panel_context_' . (int) $admin['id']] = $context['key'];

    return $context;
}

function panel_require_permission(array $admin, string $permission, array $context): void
{
    if (panel_access()->userCan((int) $admin['id'], $permission, $context)) {
        return;
    }

    http_response_code(403);
    exit('Brak uprawnien do tej czesci panelu.');
}

function panel_url(string $path, ?string $contextKey = null): string
{
    if ($contextKey === null || $contextKey === '') {
        return site_url($path);
    }

    $separator = str_contains($path, '?') ? '&' : '?';

    return site_url($path . $separator . 'context=' . rawurlencode($contextKey));
}

function panel_admin_name(array $admin): string
{
    return (string) ($admin['global_name'] ?: $admin['username'] ?: 'uzytkownik');
}

function render_preview_switch(array $admin): void
{
    if (!panel_is_owner($admin)) {
        return;
    }

    $previewRole = panel_preview_role($admin) ?? 'owner';
    ?>
    <form class="preview-switch" method="get">
        <?php foreach ($_GET as $key => $value): ?>
            <?php if ($key === 'preview_role' || is_array($value)): ?>
                <?php continue; ?>
            <?php endif; ?>
            <input type="hidden" name="<?= e((string) $key) ?>" value="<?= e((string) $value) ?>">
        <?php endforeach; ?>
        <label>
            Podglad jako
            <select name="preview_role" onchange="this.form.submit()">
                <option value="owner" <?= $previewRole === 'owner' ? 'selected' : '' ?>>Developer</option>
                <option value="guild_admin" <?= $previewRole === 'guild_admin' ? 'selected' : '' ?>>Admin serwera</option>
                <option value="member" <?= $previewRole === 'member' ? 'selected' : '' ?>>Uzytkownik</option>
            </select>
        </label>
    </form>
    <?php
}

function render_admin_header(array $admin, string $activeModule = ''): void
{
    $context = panel_active_context($admin);
    $modules = panel_access()->modulesForContext((int) $admin['id'], $context, panel_preview_role($admin));
    ?>
    <header class="admin-header">
        <a class="admin-brand" href="<?= e(panel_url('/admin/', (string) $context['key'])) ?>">
            <strong><?= e(APP_NAME) ?></strong>
        </a>
        <nav>
            <a class="<?= $activeModule === 'dashboard' ? 'is-active' : '' ?>" href="<?= e(panel_url('/admin/', (string) $context['key'])) ?>">Panel</a>
            <?php foreach ($modules as $module): ?>
                <a class="<?= $activeModule === $module['key'] ? 'is-active' : '' ?>" href="<?= e(panel_url((string) $module['url'], (string) $module['context_key'])) ?>"><?= e($module['label']) ?></a>
            <?php endforeach; ?>
            <a href="<?= e(site_url('/')) ?>">Strona</a>
            <a href="<?= e(site_url('/admin/logout.php')) ?>">Wyloguj <?= e(panel_admin_name($admin)) ?></a>
        </nav>
        <?php render_preview_switch($admin); ?>
    </header>
    <?php
}

function render_panel_modules(array $admin, string $activeModule, array $context): void
{
    $modules = panel_access()->modulesForContext((int) $admin['id'], $context, panel_preview_role($admin));
    ?>
    <section class="module-grid dashboard-modules" aria-label="Wybierz modul">
        <?php foreach ($modules as $index => $module): ?>
            <a class="module-card <?= $activeModule === $module['key'] ? 'is-active' : '' ?>" href="<?= e(panel_url((string) $module['url'], (string) $module['context_key'])) ?>">
                <span><?= e($module['badge']) ?></span>
                <strong><?= e($module['label']) ?></strong>
                <em><?= e($module['description']) ?></em>
                <b>Otworz modul</b>
            </a>
        <?php endforeach; ?>
    </section>
    <?php
}

function slugify(string $text): string
{
    $text = trim(mb_strtolower($text, 'UTF-8'));
    $map = [
        'ą' => 'a',
        'ć' => 'c',
        'ę' => 'e',
        'ł' => 'l',
        'ń' => 'n',
        'ó' => 'o',
        'ś' => 's',
        'ź' => 'z',
        'ż' => 'z',
    ];
    $text = strtr($text, $map);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    $text = trim($text, '-');

    return $text !== '' ? $text : 'strona';
}

function get_page_by_slug(string $slug): ?array
{
    $pages = db()->read('pages', '*', [
        'slug' => $slug,
        'is_published' => 1,
        'LIMIT' => 1,
    ]);

    $page = $pages[0] ?? null;

    return is_array($page) ? $page : null;
}

function get_home_page(): ?array
{
    return get_page_by_slug('start');
}

function get_published_pages(): array
{
    return db()->read('pages', ['title', 'slug', 'excerpt'], [
        'is_published' => 1,
        'ORDER' => [
            'sort_order' => 'ASC',
            'title' => 'ASC',
        ],
    ]) ?? [];
}
