<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Templates\DefaultTheme;

use SyntaxDevTeam\Cms\Core\ThemeInterface;

final class Theme implements ThemeInterface
{
    public function start_page(string $title, string $description = ''): void
    {
        $title = $this->escape($title);
        $description = $this->escape($description);

        echo '<!doctype html><html lang="pl" data-bs-theme="dark"><head>';
        echo '<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<meta name="description" content="' . $description . '">';
        echo '<title>' . $title . '</title>';
        echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" ';
        echo 'integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">';
        echo '<link rel="stylesheet" href="templates/default/assets/css/stylebook.css">';
        echo '</head><body>';
        echo '<nav class="navbar border-bottom"><div class="container">';
        echo '<a class="navbar-brand fw-bold" href="index.php">&lt;/&gt; miniPORTAL</a>';
        echo '<div class="d-flex gap-2">';
        echo '<a class="btn btn-sm btn-outline-light" href="templates/default/admin-stylebook.html">Panel admin</a>';
        echo '<a class="btn btn-sm btn-outline-light" href="templates/default/homepage.html">Strona główna</a>';
        echo '<a class="btn btn-sm btn-outline-light" href="templates/default/stylebook.html">Stylebook</a>';
        echo '</div>';
        echo '</div></nav><main>';
    }

    public function end_page(): void
    {
        echo '</main><footer class="border-top py-4"><div class="container text-secondary small">';
        echo 'miniPORTAL · Core → Modules → Templates';
        echo '</div></footer></body></html>';
    }

    public function start_header(string $title, string $lead = ''): void
    {
        echo '<header class="stylebook-hero border-bottom"><div class="container py-5">';
        echo '<p class="eyebrow mb-2">miniPORTAL / punkt integracji</p>';
        echo '<h1 class="display-4 fw-bold">' . $this->escape($title) . '</h1>';

        if ($lead !== '') {
            echo '<p class="lead text-secondary col-lg-8 mb-0">' . $this->escape($lead) . '</p>';
        }
    }

    public function end_header(): void
    {
        echo '</div></header>';
    }

    public function start_section(): void
    {
        echo '<section class="container py-5">';
    }

    public function end_section(): void
    {
        echo '</section>';
    }

    public function start_grid(): void
    {
        echo '<div class="row g-4 mb-5">';
    }

    public function end_grid(): void
    {
        echo '</div>';
    }

    public function start_column(string $size = '12'): void
    {
        $allowedSizes = ['12', 'lg-5', 'lg-6', 'lg-7', 'md-6', 'md-8'];
        $size = in_array($size, $allowedSizes, true) ? $size : '12';
        echo '<div class="col-' . $size . '">';
    }

    public function end_column(): void
    {
        echo '</div>';
    }

    public function start_card(string $title = '', string $label = ''): void
    {
        echo '<article class="showcase-card h-100">';

        if ($label !== '') {
            echo '<p class="showcase-label">' . $this->escape($label) . '</p>';
        }

        if ($title !== '') {
            echo '<h2 class="h4">' . $this->escape($title) . '</h2>';
        }
    }

    public function end_card(): void
    {
        echo '</article>';
    }

    public function render_text(string $text): void
    {
        echo '<p class="text-secondary">' . $this->escape($text) . '</p>';
    }

    public function render_button(string $label, string $href, string $variant = 'primary'): void
    {
        $allowedVariants = ['primary', 'secondary', 'success', 'danger', 'warning', 'info', 'light', 'dark'];
        $variant = in_array($variant, $allowedVariants, true) ? $variant : 'primary';

        echo '<a class="btn btn-' . $variant . '" href="' . $this->escape($href) . '">';
        echo $this->escape($label) . '</a>';
    }

    public function render_alert(string $message, string $variant = 'info'): void
    {
        $allowedVariants = ['success', 'danger', 'warning', 'info'];
        $variant = in_array($variant, $allowedVariants, true) ? $variant : 'info';

        echo '<div class="alert alert-' . $variant . '" role="status">' . $this->escape($message) . '</div>';
    }

    public function render_table(array $headers, array $rows): void
    {
        echo '<div class="table-responsive showcase-card p-0"><table class="table table-hover align-middle mb-0"><thead><tr>';

        foreach ($headers as $header) {
            echo '<th scope="col">' . $this->escape($header) . '</th>';
        }

        echo '</tr></thead><tbody>';

        foreach ($rows as $row) {
            echo '<tr>';
            foreach ($row as $cell) {
                echo '<td>' . $this->escape((string) $cell) . '</td>';
            }
            echo '</tr>';
        }

        echo '</tbody></table></div>';
    }

    public function render_form(string $action, array $fields, string $submitLabel, string $csrfToken = ''): void
    {
        echo '<form class="showcase-card" action="' . $this->escape($action) . '" method="post">';

        if ($csrfToken !== '') {
            $this->csrf_field($csrfToken);
        }

        foreach ($fields as $field) {
            $name = $this->escape($field['name']);
            $label = $this->escape($field['label']);
            $type = $field['type'] ?? 'text';
            $value = $this->escape($field['value'] ?? '');

            if ($type === 'hidden') {
                echo '<input type="hidden" name="' . $name . '" value="' . $value . '">';
                continue;
            }

            if ($type === 'checkbox') {
                $checked = ($field['checked'] ?? false) ? ' checked' : '';
                echo '<div class="form-check mb-3">';
                echo '<input class="form-check-input" id="' . $name . '" name="' . $name . '" type="checkbox" value="1"' . $checked . '>';
                echo '<label class="form-check-label" for="' . $name . '">' . $label . '</label></div>';
                continue;
            }

            echo '<div class="mb-3"><label class="form-label" for="' . $name . '">' . $label . '</label>';

            if ($type === 'textarea') {
                $rows = max(2, min(20, (int) ($field['rows'] ?? 5)));
                echo '<textarea class="form-control" id="' . $name . '" name="' . $name . '" rows="' . $rows . '">';
                echo $value . '</textarea>';
            } elseif ($type === 'select') {
                echo '<select class="form-select" id="' . $name . '" name="' . $name . '">';
                foreach ($field['options'] ?? [] as $optionValue => $optionLabel) {
                    $selected = (string) $optionValue === ($field['value'] ?? '') ? ' selected' : '';
                    echo '<option value="' . $this->escape((string) $optionValue) . '"' . $selected . '>';
                    echo $this->escape($optionLabel) . '</option>';
                }
                echo '</select>';
            } else {
                $allowedTypes = ['text', 'email', 'password', 'number', 'url', 'date'];
                $type = in_array($type, $allowedTypes, true) ? $type : 'text';
                echo '<input class="form-control" id="' . $name . '" name="' . $name . '" type="' . $type . '" value="' . $value . '">';
            }

            echo '</div>';
        }

        echo '<button class="btn btn-primary" type="submit">' . $this->escape($submitLabel) . '</button></form>';
    }

    public function csrf_field(string $token): void
    {
        echo '<input type="hidden" name="_token" value="' . $this->escape($token) . '">';
    }

    public function start_admin_page(string $title, array $menuItems, string $activePath, array $user): void
    {
        echo '<!doctype html><html lang="pl" data-bs-theme="dark"><head>';
        echo '<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<meta name="description" content="Panel administracyjny miniPORTAL">';
        echo '<title>' . $this->escape($title) . ' - miniPORTAL Admin</title>';
        echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" ';
        echo 'integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">';
        echo '<link rel="stylesheet" href="templates/default/assets/css/stylebook.css">';
        echo '<link rel="stylesheet" href="templates/default/assets/css/admin.css">';
        echo '</head><body class="admin-stylebook"><div class="site-grid" aria-hidden="true"></div>';
        echo '<a class="visually-hidden-focusable skip-link" href="#admin-main">Przejdź do treści panelu</a>';
        echo '<div class="offcanvas offcanvas-start text-bg-dark" tabindex="-1" id="adminMobileSidebar" ';
        echo 'aria-labelledby="adminMobileSidebarLabel"><div class="offcanvas-header border-bottom">';
        echo '<h2 class="offcanvas-title h5" id="adminMobileSidebarLabel">miniPORTAL Admin</h2>';
        echo '<button class="btn-close btn-close-white" type="button" data-bs-dismiss="offcanvas" aria-label="Zamknij"></button>';
        echo '</div><div class="offcanvas-body p-0">';
        $this->renderAdminMenu($menuItems, $activePath, true);
        echo '</div></div>';
        echo '<div class="admin-preview rounded-0 border-0"><div class="admin-shell">';
        echo '<aside class="admin-sidebar" aria-label="Nawigacja panelu"><div class="admin-sidebar-header">';
        echo '<a class="admin-brand text-decoration-none" href="index.php?route=/admin">';
        echo '<span class="admin-brand-mark" aria-hidden="true">&lt;/&gt;</span><span>miniPORTAL</span></a></div>';
        $this->renderAdminMenu($menuItems, $activePath);
        echo '<div class="admin-sidebar-footer"><div class="admin-user">';
        echo '<span class="admin-avatar" aria-hidden="true">' . $this->escape($user['initials']) . '</span>';
        echo '<span class="admin-user-copy"><strong>' . $this->escape($user['name']) . '</strong>';
        echo '<span>' . $this->escape($user['role']) . '</span></span></div></div></aside>';
        echo '<div class="admin-workspace"><header class="admin-topbar">';
        echo '<button class="admin-icon-button d-lg-none" type="button" data-admin-sidebar-toggle aria-label="Otwórz nawigację panelu">MN</button>';
        echo '<div class="admin-search"><span class="admin-search-mark" aria-hidden="true">SZ</span>';
        echo '<label class="visually-hidden" for="admin-global-search">Szukaj w panelu</label>';
        echo '<input class="form-control" id="admin-global-search" type="search" placeholder="Szukaj w panelu..."></div>';
        echo '<div class="ms-auto d-flex align-items-center gap-2">';
        echo '<a class="admin-icon-button text-decoration-none" href="index.php" aria-label="Wróć do strony głównej">HM</a>';
        if (($user['logout_action'] ?? '') !== '' && ($user['logout_token'] ?? '') !== '') {
            echo '<form action="' . $this->escape($user['logout_action']) . '" method="post" class="m-0">';
            $this->csrf_field($user['logout_token']);
            echo '<button class="admin-icon-button" type="submit" aria-label="Wyloguj">EX</button></form>';
        }
        echo '<span class="admin-avatar d-none d-sm-grid" aria-hidden="true">' . $this->escape($user['initials']) . '</span>';
        echo '</div></header><main id="admin-main" class="admin-content">';
    }

    public function end_admin_page(): void
    {
        echo '</main></div></div></div>';
        echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" ';
        echo 'integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>';
        echo '<script src="templates/default/assets/js/admin.js"></script></body></html>';
    }

    public function start_admin_content(
        string $title,
        string $lead = '',
        array $breadcrumbs = [],
        ?array $action = null,
    ): void {
        if ($breadcrumbs !== []) {
            echo '<nav aria-label="Breadcrumb"><ol class="breadcrumb admin-breadcrumb">';
            foreach ($breadcrumbs as $index => $breadcrumb) {
                $isLast = $index === array_key_last($breadcrumbs);
                if ($isLast || $breadcrumb['href'] === '') {
                    echo '<li class="breadcrumb-item active" aria-current="page">' . $this->escape($breadcrumb['label']) . '</li>';
                } else {
                    echo '<li class="breadcrumb-item"><a href="' . $this->escape($breadcrumb['href']) . '">';
                    echo $this->escape($breadcrumb['label']) . '</a></li>';
                }
            }
            echo '</ol></nav>';
        }

        echo '<div class="admin-page-heading"><div><h1>' . $this->escape($title) . '</h1>';
        if ($lead !== '') {
            echo '<p class="text-secondary mb-0">' . $this->escape($lead) . '</p>';
        }
        echo '</div>';
        if ($action !== null) {
            echo '<a class="btn btn-primary" href="' . $this->escape($action['href']) . '">';
            echo $this->escape($action['label']) . '</a>';
        }
        echo '</div>';
    }

    public function end_admin_content(): void
    {
    }

    public function start_admin_metrics(): void
    {
        echo '<div class="metric-grid mb-4">';
    }

    public function render_admin_metric(string $label, string $value, string $symbol, string $detail = ''): void
    {
        echo '<article class="metric-card" data-symbol="' . $this->escape(substr($symbol, 0, 3)) . '">';
        echo '<span class="metric-label">' . $this->escape($label) . '</span>';
        echo '<strong class="metric-value">' . $this->escape($value) . '</strong>';
        if ($detail !== '') {
            echo '<span class="metric-change">' . $this->escape($detail) . '</span>';
        }
        echo '</article>';
    }

    public function end_admin_metrics(): void
    {
        echo '</div>';
    }

    public function start_admin_panel(string $title, string $meta = ''): void
    {
        echo '<section class="admin-panel"><div class="admin-panel-header"><h2 class="h5 mb-0">';
        echo $this->escape($title) . '</h2>';
        if ($meta !== '') {
            echo '<span class="status-badge status-badge-published">' . $this->escape($meta) . '</span>';
        }
        echo '</div><div class="admin-panel-body">';
    }

    public function end_admin_panel(): void
    {
        echo '</div></section>';
    }

    public function render_admin_table(array $headers, array $rows): void
    {
        echo '<div class="table-responsive"><table class="table table-hover align-middle admin-data-table">';
        echo '<thead><tr>';
        foreach ($headers as $header) {
            echo '<th scope="col">' . $this->escape($header) . '</th>';
        }
        echo '</tr></thead><tbody>';
        foreach ($rows as $row) {
            echo '<tr>';
            foreach ($row as $cell) {
                echo '<td>' . $this->escape((string) $cell) . '</td>';
            }
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    }

    public function render_admin_login(
        string $action,
        array $identities,
        string $csrfToken,
        string $message = '',
        string $variant = 'info',
    ): void {
        echo '<!doctype html><html lang="pl" data-bs-theme="dark"><head>';
        echo '<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<meta name="description" content="Logowanie do panelu miniPORTAL">';
        echo '<title>Logowanie - miniPORTAL Admin</title>';
        echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" ';
        echo 'integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">';
        echo '<link rel="stylesheet" href="templates/default/assets/css/stylebook.css">';
        echo '<link rel="stylesheet" href="templates/default/assets/css/admin.css">';
        echo '</head><body class="admin-stylebook"><div class="site-grid" aria-hidden="true"></div>';
        echo '<main class="min-vh-100 d-grid align-items-center py-4"><div class="container">';
        echo '<div class="login-stage border-0 bg-transparent shadow-none"><section class="login-panel">';
        echo '<a class="admin-brand text-decoration-none" href="index.php">';
        echo '<span class="admin-brand-mark" aria-hidden="true">&lt;/&gt;</span><span>miniPORTAL Admin</span></a>';
        echo '<p class="showcase-label mt-5 mb-2">Bezpieczny dostęp</p>';
        echo '<h1 class="h2 fw-bold">Zaloguj się do panelu</h1>';
        echo '<p class="text-secondary">Konto i uprawnienia pozostają lokalne, niezależnie od wybranego dostawcy tożsamości.</p>';

        if ($message !== '') {
            $allowedVariants = ['success', 'danger', 'warning', 'info'];
            $variant = in_array($variant, $allowedVariants, true) ? $variant : 'info';
            echo '<div class="alert alert-' . $variant . '" role="alert">' . $this->escape($message) . '</div>';
        }

        echo '<div class="provider-list">';
        foreach ($identities as $identity) {
            $icon = strtoupper(substr($identity['provider'], 0, 2));

            if (($identity['href'] ?? '') !== '') {
                echo '<a class="provider-button text-decoration-none" href="' . $this->escape($identity['href']) . '">';
                echo '<span class="provider-icon provider-icon-github" aria-hidden="true">' . $this->escape($icon) . '</span>';
                echo '<span><strong class="d-block">' . $this->escape($identity['label']) . '</strong>';
                echo '<small class="text-secondary">' . $this->escape($identity['description']) . '</small></span>';
                echo '<span class="provider-arrow" aria-hidden="true">-&gt;</span></a>';
                continue;
            }

            echo '<form action="' . $this->escape($action) . '" method="post">';
            $this->csrf_field($csrfToken);
            echo '<input type="hidden" name="provider" value="' . $this->escape($identity['provider']) . '">';
            echo '<input type="hidden" name="subject" value="' . $this->escape($identity['subject']) . '">';
            echo '<button class="provider-button" type="submit"><span class="provider-icon provider-icon-github" aria-hidden="true">';
            echo $this->escape($icon) . '</span>';
            echo '<span><strong class="d-block">' . $this->escape($identity['label']) . '</strong>';
            echo '<small class="text-secondary">' . $this->escape($identity['description']) . '</small></span>';
            echo '<span class="provider-arrow" aria-hidden="true">-&gt;</span></button></form>';
        }
        if ($identities === []) {
            echo '<div class="state-card py-4"><span class="state-icon" aria-hidden="true">OFF</span>';
            echo '<h2 class="h5">Brak aktywnych dostawców</h2>';
            echo '<p class="text-secondary mb-0">Skonfiguruj adapter OAuth albo świadomie włącz tryb demonstracyjny.</p></div>';
        }
        echo '</div><div class="security-note mt-4"><span aria-hidden="true">[SEC]</span>';
        echo '<span>Przepływy zewnętrzne używają state i PKCE, a sesja jest rotowana po zalogowaniu.</span></div>';
        echo '</section></div></div></main></body></html>';
    }

    public function render_admin_access_state(
        int $status,
        string $title,
        string $message,
        string $actionHref,
        string $actionLabel,
    ): void {
        echo '<!doctype html><html lang="pl" data-bs-theme="dark"><head>';
        echo '<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<title>' . $this->escape((string) $status) . ' - miniPORTAL Admin</title>';
        echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">';
        echo '<link rel="stylesheet" href="templates/default/assets/css/stylebook.css">';
        echo '<link rel="stylesheet" href="templates/default/assets/css/admin.css">';
        echo '</head><body class="admin-stylebook"><main class="container min-vh-100 d-grid align-items-center py-5">';
        echo '<section class="state-card access-state-card">';
        echo '<span class="state-code">' . $this->escape((string) $status) . '</span>';
        echo '<h1 class="h3">' . $this->escape($title) . '</h1>';
        echo '<p class="text-secondary">' . $this->escape($message) . '</p>';
        echo '<a class="btn btn-primary" href="' . $this->escape($actionHref) . '">' . $this->escape($actionLabel) . '</a>';
        echo '</section></main></body></html>';
    }

    public function render_admin_identities(
        array $user,
        array $providers,
        string $unlinkAction,
        string $csrfToken,
        string $message = '',
        string $variant = 'info',
    ): void {
        echo '<!doctype html><html lang="pl" data-bs-theme="dark"><head>';
        echo '<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<title>Połączone konta - miniPORTAL Admin</title>';
        echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">';
        echo '<link rel="stylesheet" href="templates/default/assets/css/stylebook.css">';
        echo '<link rel="stylesheet" href="templates/default/assets/css/admin.css">';
        echo '</head><body class="admin-stylebook"><main class="container py-5">';
        echo '<a class="admin-brand text-decoration-none mb-4" href="index.php?route=/admin">';
        echo '<span class="admin-brand-mark" aria-hidden="true">&lt;/&gt;</span><span>miniPORTAL Admin</span></a>';
        echo '<section class="admin-panel mt-4"><div class="admin-panel-header">';
        echo '<div><p class="showcase-label mb-1">Profil</p><h1 class="h3 mb-1">Połączone tożsamości</h1>';
        echo '<p class="text-secondary mb-0">' . $this->escape($user['name']) . ' · ' . $this->escape($user['role']) . '</p></div>';
        echo '<a class="btn btn-outline-light" href="index.php?route=/admin">Wróć do panelu</a></div>';

        if ($message !== '') {
            $allowedVariants = ['success', 'danger', 'warning', 'info'];
            $variant = in_array($variant, $allowedVariants, true) ? $variant : 'info';
            echo '<div class="alert alert-' . $variant . ' mx-4 mt-4" role="alert">' . $this->escape($message) . '</div>';
        }

        echo '<div class="table-responsive"><table class="table admin-table align-middle mb-0">';
        echo '<thead><tr><th>Dostawca</th><th>Status</th><th class="text-end">Akcja</th></tr></thead><tbody>';
        foreach ($providers as $provider) {
            echo '<tr><td><strong>' . $this->escape($provider['label']) . '</strong></td><td>';
            echo $provider['linked']
                ? '<span class="badge text-bg-success">Połączono</span>'
                : ($provider['configured']
                    ? '<span class="badge text-bg-secondary">Dostępny</span>'
                    : '<span class="badge text-bg-dark">Nieskonfigurowany</span>');
            echo '</td><td class="text-end">';

            if ($provider['linked']) {
                echo '<form class="d-inline" action="' . $this->escape($unlinkAction) . '" method="post">';
                $this->csrf_field($csrfToken);
                echo '<input type="hidden" name="provider" value="' . $this->escape($provider['name']) . '">';
                echo '<button class="btn btn-sm btn-outline-danger" type="submit">Odłącz</button></form>';
            } elseif ($provider['configured']) {
                echo '<a class="btn btn-sm btn-primary" href="index.php?route=/admin/identity/';
                echo $this->escape(rawurlencode($provider['name'])) . '/link">Połącz</a>';
            } else {
                echo '<span class="text-secondary">Brak konfiguracji</span>';
            }
            echo '</td></tr>';
        }
        echo '</tbody></table></div></section></main></body></html>';
    }

    public function render_admin_pages(
        array $pages,
        array $menuItems,
        array $user,
        array $permissions,
        string $csrfToken,
        string $message = '',
        string $variant = 'info',
    ): void {
        $allows = static fn (string $permission): bool => in_array('*', $permissions, true)
            || in_array($permission, $permissions, true);

        $this->start_admin_page('Strony', $menuItems, '/admin/pages', $user);
        $this->start_admin_content(
            'Strony',
            'Twórz, edytuj i publikuj treści przez moduł core_pages.',
            [
                ['label' => 'Panel', 'href' => 'index.php?route=/admin'],
                ['label' => 'Strony', 'href' => ''],
            ],
            $allows('pages.create')
                ? ['label' => 'Dodaj stronę', 'href' => 'index.php?route=/admin/pages/create']
                : null
        );

        if ($message !== '') {
            $this->render_alert($message, $variant);
        }

        $this->start_admin_panel('Lista stron', count($pages) . ' rekordów');

        if ($pages === []) {
            echo '<div class="state-card border-0"><span class="state-icon" aria-hidden="true">PG</span>';
            echo '<h2 class="h5">Brak stron</h2><p class="text-secondary mb-0">Utwórz pierwszy szkic, aby rozpocząć.</p></div>';
        } else {
            echo '<div class="table-responsive"><table class="table admin-table align-middle mb-0">';
            echo '<thead><tr><th>Tytuł</th><th>Slug</th><th>Status</th><th>Aktualizacja</th><th class="text-end">Akcje</th></tr></thead><tbody>';
            foreach ($pages as $page) {
                echo '<tr><td><strong>' . $this->escape($page['title']) . '</strong></td>';
                echo '<td><code>' . $this->escape($page['slug']) . '</code></td>';
                echo '<td><span class="badge ' . ($page['status'] === 'published' ? 'text-bg-success' : 'text-bg-secondary') . '">';
                echo $page['status'] === 'published' ? 'Opublikowana' : 'Szkic';
                echo '</span></td><td>' . $this->escape($page['updated_at']) . '</td><td class="text-end">';

                if ($allows('pages.edit')) {
                    echo '<a class="btn btn-sm btn-outline-light me-1" href="index.php?route=/admin/pages/edit&amp;id=';
                    echo $this->escape((string) $page['id']) . '">Edytuj</a>';
                }
                if ($allows('pages.publish')) {
                    echo '<form class="d-inline" action="index.php?route=/admin/pages/publish" method="post">';
                    $this->csrf_field($csrfToken);
                    echo '<input type="hidden" name="id" value="' . $this->escape((string) $page['id']) . '">';
                    echo '<input type="hidden" name="action" value="' . ($page['status'] === 'published' ? 'draft' : 'publish') . '">';
                    echo '<button class="btn btn-sm btn-outline-primary me-1" type="submit">';
                    echo $page['status'] === 'published' ? 'Cofnij' : 'Publikuj';
                    echo '</button></form>';
                }
                if ($allows('pages.delete')) {
                    echo '<form class="d-inline" action="index.php?route=/admin/pages/delete" method="post">';
                    $this->csrf_field($csrfToken);
                    echo '<input type="hidden" name="id" value="' . $this->escape((string) $page['id']) . '">';
                    echo '<button class="btn btn-sm btn-outline-danger" type="submit">Usuń</button></form>';
                }
                echo '</td></tr>';
            }
            echo '</tbody></table></div>';
        }

        $this->end_admin_panel();
        $this->end_admin_content();
        $this->end_admin_page();
    }

    public function render_admin_page_form(
        ?array $page,
        array $menuItems,
        array $user,
        string $csrfToken,
        string $message = '',
        string $variant = 'info',
    ): void {
        $editing = $page !== null;
        $title = $editing ? 'Edytuj stronę' : 'Dodaj stronę';

        $this->start_admin_page($title, $menuItems, '/admin/pages', $user);
        $this->start_admin_content(
            $title,
            'Podstawowy formularz treści bez zależności od edytora WYSIWYG.',
            [
                ['label' => 'Panel', 'href' => 'index.php?route=/admin'],
                ['label' => 'Strony', 'href' => 'index.php?route=/admin/pages'],
                ['label' => $editing ? 'Edycja' : 'Nowa', 'href' => ''],
            ]
        );

        if ($message !== '') {
            $this->render_alert($message, $variant);
        }

        $this->start_admin_panel('Dane strony', $editing ? 'ID ' . $page['id'] : 'Nowy szkic');
        $this->render_form(
            $editing
                ? 'index.php?route=/admin/pages/edit'
                : 'index.php?route=/admin/pages/create',
            [
                ...($editing ? [[
                    'name' => 'id',
                    'label' => 'ID',
                    'type' => 'hidden',
                    'value' => (string) $page['id'],
                ]] : []),
                ['name' => 'title', 'label' => 'Tytuł', 'value' => $page['title'] ?? ''],
                ['name' => 'slug', 'label' => 'Slug (opcjonalnie)', 'value' => $page['slug'] ?? ''],
                [
                    'name' => 'content',
                    'label' => 'Treść',
                    'type' => 'textarea',
                    'rows' => 12,
                    'value' => $page['content'] ?? '',
                ],
            ],
            $editing ? 'Zapisz zmiany' : 'Utwórz szkic',
            $csrfToken
        );
        $this->end_admin_panel();
        $this->end_admin_content();
        $this->end_admin_page();
    }

    public function render_public_page(string $title, string $content, string $publishedAt): void
    {
        $this->start_page($title . ' - miniPORTAL', $title);
        $this->start_header($title, 'Opublikowano: ' . $publishedAt);
        $this->end_header();
        $this->start_section();
        echo '<article class="showcase-card">';

        foreach (preg_split('/\R{2,}/', trim($content)) ?: [] as $paragraph) {
            echo '<p>' . nl2br($this->escape($paragraph)) . '</p>';
        }

        echo '<a class="btn btn-outline-light" href="index.php">Wróć do strony głównej</a></article>';
        $this->end_section();
        $this->end_page();
    }

    public function render_page_not_found(string $title, string $message): void
    {
        $this->start_page('404 - miniPORTAL', $message);
        $this->start_header($title, $message);
        $this->end_header();
        $this->start_section();
        $this->render_alert($message, 'warning');
        $this->render_button('Wróć do strony głównej', 'index.php', 'outline-light');
        $this->end_section();
        $this->end_page();
    }

    private function renderAdminMenu(array $menuItems, string $activePath, bool $mobile = false): void
    {
        echo '<nav class="admin-nav">';
        $currentSection = null;

        foreach ($menuItems as $item) {
            if ($item['section'] !== $currentSection) {
                $currentSection = $item['section'];
                echo '<span class="admin-nav-label">' . $this->escape($currentSection) . '</span>';
            }

            $active = $item['path'] === $activePath;
            echo '<a class="admin-nav-link' . ($active ? ' active' : '') . '" href="index.php?route=';
            echo rawurlencode($item['path']) . '"';
            if ($active) {
                echo ' aria-current="page"';
            }
            if ($mobile) {
                echo ' data-bs-dismiss="offcanvas"';
            }
            echo '>';
            echo '<span class="admin-nav-icon" aria-hidden="true">' . $this->escape($item['icon']) . '</span>';
            echo $this->escape($item['label']) . '</a>';
        }

        echo '</nav>';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
