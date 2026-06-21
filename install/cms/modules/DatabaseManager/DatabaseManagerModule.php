<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\DatabaseManager;

use SyntaxDevTeam\Cms\Core\AdminMenuRegistry;
use SyntaxDevTeam\Cms\Core\ModuleInterface;
use SyntaxDevTeam\Cms\Core\Request;
use SyntaxDevTeam\Cms\Core\Router;
use SyntaxDevTeam\Cms\Core\Security;
use SyntaxDevTeam\Cms\Core\ThemeInterface;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AdminAccessGate;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuditLogService;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuthService;

final class DatabaseManagerModule implements ModuleInterface
{
    public function __construct(
        private readonly ThemeInterface $theme,
        private readonly AdminMenuRegistry $menu,
        private readonly AuthService $auth,
        private readonly AdminAccessGate $access,
        private readonly Security $security,
        private readonly AuditLogService $audit,
        private readonly ?DatabaseExplorerRepository $databaseExplorer,
        private readonly ?DatabaseManagerHistoryRepository $history,
    ) {
    }

    public function id(): string
    {
        return 'database_manager';
    }

    public function version(): string
    {
        return '1.4.0';
    }

    public function dependencies(): array
    {
        return ['core_auth'];
    }

    public function isProtected(): bool
    {
        return false;
    }

    public function requiredPermissions(): array
    {
        return ['database.view', 'database.manage'];
    }

    public function registerAdminMenu(AdminMenuRegistry $menu): void
    {
        $menu->add('System', 'Manager SQL', '/admin/database', 'SQL', 'database.view', 58);
    }

    public function registerRoutes(Router $router): void
    {
        $router->get('/admin/database', fn (Request $request) => $this->guard(
            $request,
            'database.view',
            fn () => $this->renderDatabaseExplorer($request)
        ));
        $router->get('/admin/database/export', fn (Request $request) => $this->guard(
            $request,
            'database.view',
            fn () => $this->exportDatabaseTable($request)
        ));
        $router->get('/admin/database/query', fn (Request $request) => $this->guard(
            $request,
            'database.view',
            fn () => $this->renderDatabaseQuery()
        ));
        $router->get('/admin/database/history', fn (Request $request) => $this->guard(
            $request,
            'database.view',
            fn () => $this->renderDatabaseHistory($request)
        ));
        $router->get('/admin/database/import', fn (Request $request) => $this->guard(
            $request,
            'database.manage',
            fn () => $this->renderDatabaseImport()
        ));
        $router->get('/admin/database/row/create', fn (Request $request) => $this->guard(
            $request,
            'database.manage',
            fn () => $this->renderRowCreate($request)
        ));
        $router->post('/admin/database/row/create', fn (Request $request) => $this->guard(
            $request,
            'database.manage',
            fn () => $this->createRow($request)
        ));
        $router->get('/admin/database/row/edit', fn (Request $request) => $this->guard(
            $request,
            'database.manage',
            fn () => $this->renderRowEdit($request)
        ));
        $router->post('/admin/database/row/edit', fn (Request $request) => $this->guard(
            $request,
            'database.manage',
            fn () => $this->updateRow($request)
        ));
        $router->post('/admin/database/row/delete', fn (Request $request) => $this->guard(
            $request,
            'database.manage',
            fn () => $this->deleteRow($request)
        ));
        $router->post('/admin/database/table-operation', fn (Request $request) => $this->guard(
            $request,
            'database.manage',
            fn () => $this->runTableOperation($request)
        ));
        $router->post('/admin/database/import', fn (Request $request) => $this->guard(
            $request,
            'database.manage',
            fn () => $this->runDatabaseImport($request)
        ));
        $router->post('/admin/database/query', fn (Request $request) => $this->guard(
            $request,
            'database.view',
            fn () => $this->runDatabaseQuery($request)
        ));
        $router->post('/admin/database/query/manage', fn (Request $request) => $this->guard(
            $request,
            'database.manage',
            fn () => $this->runManagedDatabaseQuery($request)
        ));
    }

    private function renderDatabaseExplorer(Request $request, string $message = '', string $variant = 'info'): void
    {
        $user = $this->auth->user();
        if ($this->databaseExplorer === null) {
            $this->startPage(
                'Manager SQL',
                '/admin/database',
                'Bezpieczny podgląd tabel, rozmiarów i struktury kolumn bez edycji danych.'
            );
            $this->theme->render_alert('Manager SQL wymaga aktywnego połączenia z bazą danych.', 'danger');
            $this->endPage();
            return;
        }

        $tables = $this->databaseExplorer->tables();
        $tableNames = array_column($tables, 'name');
        $selectedTable = $request->queryString('table', $request->postString('table'));
        if (!in_array($selectedTable, $tableNames, true)) {
            $selectedTable = $tableNames[0] ?? '';
        }
        $page = max(1, $request->queryInt('page', 1) ?? 1);
        $perPage = max(10, min(50, $request->queryInt('per_page', 25) ?? 25));
        $tableData = $selectedTable !== ''
            ? $this->databaseExplorer->data($selectedTable, $page, $perPage)
            : ['headers' => [], 'rows' => [], 'records' => [], 'total' => 0, 'page' => 1, 'per_page' => $perPage, 'pages' => 1];
        $canManage = $user !== null && $this->hasPermission($user->permissions, 'database.manage');
        $primaryKey = $selectedTable !== '' ? $this->databaseExplorer->primaryKey($selectedTable) : null;
        $actions = [[
            'label' => 'Konsola SQL',
            'href' => 'index.php?route=/admin/database/query',
            'variant' => 'primary',
        ], [
            'label' => 'Historia',
            'href' => 'index.php?route=/admin/database/history',
            'variant' => 'outline-light',
        ]];
        if ($selectedTable !== '') {
            $actions[] = [
                'label' => 'Eksportuj SQL',
                'href' => 'index.php?route=/admin/database/export&format=sql&table=' . rawurlencode($selectedTable),
                'variant' => 'primary',
            ];
            $actions[] = [
                'label' => 'Eksportuj CSV',
                'href' => 'index.php?route=/admin/database/export&format=csv&table=' . rawurlencode($selectedTable),
                'variant' => 'outline-light',
            ];
        }
        if ($canManage) {
            if ($selectedTable !== '') {
                $actions[] = [
                    'label' => 'Dodaj rekord',
                    'href' => 'index.php?route=/admin/database/row/create&table=' . rawurlencode($selectedTable),
                    'variant' => 'primary',
                ];
            }
            $actions[] = [
                'label' => 'Import SQL',
                'href' => 'index.php?route=/admin/database/import',
                'variant' => 'danger',
            ];
        }

        $this->startPage(
            'Manager SQL',
            '/admin/database',
            'Bezpieczny podgląd tabel, rozmiarów i struktury kolumn bez edycji danych.',
            $actions
        );

        if ($message !== '') {
            $this->theme->render_alert($message, $variant);
        }

        $this->theme->start_admin_metrics();
        $this->theme->render_admin_metric('Baza', $this->databaseExplorer->databaseName(), 'SQL', 'Aktywne połączenie');
        $this->theme->render_admin_metric('Tabele', (string) count($tables), 'TB', 'information_schema');
        $this->theme->render_admin_metric('Wybrana tabela', $selectedTable !== '' ? $selectedTable : 'Brak', 'SEL', 'Struktura i dane');
        $this->theme->render_admin_metric('Rekordy', number_format((int) $tableData['total'], 0, ',', ' '), 'ROW', 'Podgląd read-only');
        $this->theme->end_admin_metrics();

        $this->theme->start_admin_panel_grid('balanced');
        $this->theme->start_admin_panel('Tabele', count($tables) . ' obiektów');
        $this->theme->render_admin_action_table(
            ['Tabela', 'Silnik', 'Wiersze', 'Rozmiar', 'Kodowanie'],
            array_map(
                static fn (array $table): array => [
                    'cells' => [
                        $table['name'],
                        $table['engine'],
                        $table['rows'],
                        $table['size'],
                        $table['collation'],
                    ],
                    'actions' => [[
                        'label' => 'Struktura',
                        'href' => 'index.php?route=/admin/database&table=' . rawurlencode($table['name']),
                        'variant' => $table['name'] === $selectedTable ? 'primary' : 'outline-light',
                    ]],
                ],
                $tables
            ),
            $this->security->csrfToken()
        );
        $this->theme->end_admin_panel();

        $this->theme->start_admin_panel(
            $selectedTable !== '' ? 'Struktura: ' . $selectedTable : 'Struktura',
            $selectedTable !== '' ? 'Kolumny tabeli' : 'Brak tabel'
        );
        if ($selectedTable === '') {
            $this->theme->render_alert('Baza nie zawiera tabel do pokazania.', 'info');
        } else {
            $this->theme->render_admin_table(
                ['Kolumna', 'Typ', 'NULL', 'Klucz', 'Domyślnie', 'Extra'],
                array_map(
                    static fn (array $column): array => [
                        $column['name'],
                        $column['type'],
                        $column['nullable'],
                        $column['key'],
                        $column['default'],
                        $column['extra'],
                    ],
                    $this->databaseExplorer->columns($selectedTable)
                )
            );
        }
        $this->theme->end_admin_panel();
        $this->theme->end_admin_panel_grid();

        if ($selectedTable !== '' && $canManage) {
            $this->renderTableOperationsPanel($selectedTable);
        }

        $this->theme->start_admin_panel(
            $selectedTable !== '' ? 'Dane: ' . $selectedTable : 'Dane tabeli',
            $selectedTable !== ''
                ? 'Strona ' . $tableData['page'] . ' z ' . $tableData['pages'] . ', limit ' . $tableData['per_page']
                : 'Brak tabel'
        );
        if ($selectedTable === '') {
            $this->theme->render_alert('Wybierz tabelę, aby zobaczyć dane.', 'info');
        } elseif ($tableData['headers'] === []) {
            $this->theme->render_alert('Tabela nie ma kolumn do pokazania.', 'info');
        } else {
            if ($canManage && $primaryKey !== null) {
                $headers = $tableData['headers'];
                $this->theme->render_admin_action_table(
                    $headers,
                    array_map(
                        fn (array $row, array $record): array => [
                            'cells' => $row,
                            'actions' => [[
                                'label' => 'Edytuj',
                                'href' => 'index.php?route=/admin/database/row/edit&table=' . rawurlencode($selectedTable)
                                    . '&id=' . rawurlencode((string) ($record[$primaryKey] ?? '')),
                                'variant' => 'outline-primary',
                            ], [
                                'label' => 'Usuń',
                                'action' => 'index.php?route=/admin/database/row/delete',
                                'fields' => [
                                    'table' => $selectedTable,
                                    'id' => (string) ($record[$primaryKey] ?? ''),
                                ],
                                'variant' => 'outline-danger',
                                'confirm' => 'Usunąć rekord z tabeli ' . $selectedTable . '?',
                            ]],
                        ],
                        $tableData['rows'],
                        $tableData['records']
                    ),
                    $this->security->csrfToken()
                );
            } else {
                $this->theme->render_admin_table($tableData['headers'], $tableData['rows']);
                if ($canManage && $primaryKey === null) {
                    $this->theme->render_alert('Edycja i usuwanie wierszy wymagają pojedynczego klucza głównego tabeli.', 'warning');
                }
            }
            $actions = [];
            if ($tableData['page'] > 1) {
                $actions[] = [
                    'label' => 'Poprzednia strona',
                    'href' => 'index.php?route=/admin/database&table=' . rawurlencode($selectedTable)
                        . '&page=' . ($tableData['page'] - 1) . '&per_page=' . $tableData['per_page'],
                ];
            }
            if ($tableData['page'] < $tableData['pages']) {
                $actions[] = [
                    'label' => 'Następna strona',
                    'href' => 'index.php?route=/admin/database&table=' . rawurlencode($selectedTable)
                        . '&page=' . ($tableData['page'] + 1) . '&per_page=' . $tableData['per_page'],
                    'variant' => 'primary',
                ];
            }
            if ($actions !== []) {
                $this->theme->render_admin_panel_actions($actions);
            }
        }
        $this->theme->end_admin_panel();

        $this->renderHistoryPanel();
        $this->endPage();
    }

    private function exportDatabaseTable(Request $request): void
    {
        $actor = $this->auth->user();
        $table = $request->queryString('table');
        $format = $request->queryString('format', 'sql') === 'csv' ? 'csv' : 'sql';
        if ($this->databaseExplorer === null) {
            $this->audit->record($request, 'database_export', 'unavailable', $table, $actor?->id);
            http_response_code(503);
            $this->theme->render_admin_access_state(
                503,
                'Baza niedostępna',
                'Eksport wymaga aktywnego połączenia z bazą danych.',
                'index.php?route=/admin/database',
                'Wróć do Managera SQL'
            );
            return;
        }

        $tableNames = array_column($this->databaseExplorer->tables(), 'name');
        if (!in_array($table, $tableNames, true)) {
            $this->audit->record($request, 'database_export', 'invalid_table', $table, $actor?->id);
            http_response_code(404);
            $this->theme->render_admin_access_state(
                404,
                'Nie znaleziono tabeli',
                'Wybrana tabela nie istnieje w aktywnej bazie.',
                'index.php?route=/admin/database',
                'Wróć do Managera SQL'
            );
            return;
        }

        try {
            $stream = fopen('php://output', 'wb');
            $this->audit->record($request, 'database_export', 'success', $format . ':' . $table, $actor?->id);
            if ($format === 'csv') {
                $export = $this->databaseExplorer->exportData($table);
                $this->recordHistory($actor?->id, 'export_csv', 'success', $table, null, (int) $export['exported']);
                if (!headers_sent()) {
                    header('Content-Type: text/csv; charset=UTF-8');
                    header('Content-Disposition: attachment; filename="table-' . $this->safeFilename($table) . '.csv"');
                    header('X-Content-Type-Options: nosniff');
                }
                (new DatabaseTableCsvExporter())->write($stream, $export['headers'], $export['rows']);
            } else {
                $export = $this->databaseExplorer->exportSqlData($table);
                $this->recordHistory($actor?->id, 'export_sql', 'success', $table, null, (int) $export['exported']);
                if (!headers_sent()) {
                    header('Content-Type: application/sql; charset=UTF-8');
                    header('Content-Disposition: attachment; filename="table-' . $this->safeFilename($table) . '.sql"');
                    header('X-Content-Type-Options: nosniff');
                }
                (new DatabaseTableSqlExporter($this->databaseExplorer))->write($stream, $table, $export);
            }
            if (is_resource($stream)) {
                fclose($stream);
            }
        } catch (\Throwable $exception) {
            $this->audit->record($request, 'database_export', 'failed', $table, $actor?->id);
            $this->recordHistory($actor?->id, 'export_' . $format, 'failed', $table, null, null, $exception->getMessage());
            http_response_code(500);
            $this->theme->render_admin_access_state(
                500,
                'Eksport nieudany',
                $exception->getMessage(),
                'index.php?route=/admin/database&table=' . rawurlencode($table),
                'Wróć do tabeli'
            );
        }
    }

    private function renderDatabaseQuery(?array $result = null, string $message = '', string $variant = 'info', string $sql = ''): void
    {
        $this->startPage(
            'Konsola SQL',
            '/admin/database',
            'Tryb tylko do odczytu dla pojedynczych zapytań SELECT, SHOW, DESCRIBE i EXPLAIN.',
            array_values(array_filter([[
                'label' => 'Wróć do Managera SQL',
                'href' => 'index.php?route=/admin/database',
                'variant' => 'outline-light',
            ], [
                'label' => 'Historia',
                'href' => 'index.php?route=/admin/database/history',
                'variant' => 'outline-light',
            ], $this->hasPermission($this->auth->user()?->permissions ?? [], 'database.manage') ? [
                'label' => 'Tryb zapisu',
                'href' => '#sql-write',
                'variant' => 'danger',
            ] : null]))
        );
        if ($message !== '') {
            $this->theme->render_alert($message, $variant);
        }
        if ($this->databaseExplorer === null) {
            $this->theme->render_alert('Konsola SQL wymaga aktywnego połączenia z bazą danych.', 'danger');
            $this->endPage();
            return;
        }

        $this->theme->start_admin_panel('Zapytanie read-only', 'Bez INSERT, UPDATE, DELETE, DROP i wielu instrukcji');
        $this->theme->render_form(
            'index.php?route=/admin/database/query',
            [[
                'name' => 'sql',
                'label' => 'SQL',
                'type' => 'textarea',
                'value' => $sql !== '' ? $sql : 'SHOW TABLES',
                'rows' => 8,
                'help' => 'Wykonane zostanie jedno zapytanie. Wynik jest ograniczony do 100 wierszy.',
            ]],
            'Wykonaj zapytanie',
            $this->security->csrfToken()
        );
        $this->theme->end_admin_panel();

        if ($this->hasPermission($this->auth->user()?->permissions ?? [], 'database.manage')) {
            $this->theme->start_admin_panel('Zapytanie zmieniające', 'Wymaga uprawnienia database.manage i potwierdzenia WRITE');
            $this->theme->render_form(
                'index.php?route=/admin/database/query/manage',
                [[
                    'name' => 'sql',
                    'label' => 'SQL',
                    'type' => 'textarea',
                    'value' => '',
                    'rows' => 8,
                    'help' => 'Jedna instrukcja INSERT, UPDATE, DELETE, REPLACE, CREATE, ALTER, DROP, TRUNCATE, OPTIMIZE, ANALYZE, CHECK albo REPAIR.',
                ], [
                    'name' => 'confirmation',
                    'label' => 'Potwierdzenie',
                    'type' => 'text',
                    'value' => '',
                    'help' => 'Wpisz WRITE, aby wykonać zapytanie zmieniające dane lub strukturę.',
                ]],
                'Wykonaj zapytanie zapisowe',
                $this->security->csrfToken()
            );
            $this->theme->end_admin_panel();
        }

        if ($result !== null) {
            $this->theme->start_admin_panel(
                'Wynik zapytania',
                $result['truncated'] ? 'Pokazano pierwsze 100 wierszy' : count($result['rows']) . ' wierszy'
            );
            if ($result['headers'] === []) {
                $this->theme->render_alert('Zapytanie nie zwróciło tabelarycznego wyniku.', 'info');
            } else {
                $this->theme->render_admin_table($result['headers'], $result['rows']);
            }
            $this->theme->end_admin_panel();
        }

        $this->endPage();
    }

    private function runDatabaseQuery(Request $request): void
    {
        $actor = $this->auth->user();
        $sql = $request->postString('sql');
        if (!$this->security->validateCsrfToken($request->postString('_token'))) {
            $this->audit->record($request, 'database_query', 'invalid_csrf', 'read_only', $actor?->id);
            $this->renderDatabaseQuery(null, 'Token CSRF jest nieprawidłowy lub wygasł.', 'danger', $sql);
            return;
        }
        if ($this->databaseExplorer === null) {
            $this->audit->record($request, 'database_query', 'unavailable', 'read_only', $actor?->id);
            $this->renderDatabaseQuery(null, 'Konsola SQL wymaga aktywnego połączenia z bazą danych.', 'danger', $sql);
            return;
        }

        try {
            $result = $this->databaseExplorer->readOnlyQuery($sql, 100);
            $this->audit->record($request, 'database_query', 'success', 'read_only', $actor?->id);
            $this->recordHistory(
                $actor?->id,
                'query_read_only',
                'success',
                null,
                $result['sql'],
                count($result['rows']),
                $result['truncated'] ? 'Wynik obcięty do 100 wierszy.' : null
            );
            $this->renderDatabaseQuery($result, 'Zapytanie wykonane w trybie read-only.', 'success', $result['sql']);
        } catch (\Throwable $exception) {
            $this->audit->record($request, 'database_query', 'failed', 'read_only', $actor?->id);
            $this->recordHistory($actor?->id, 'query_read_only', 'failed', null, $sql, null, $exception->getMessage());
            $this->renderDatabaseQuery(null, $exception->getMessage(), 'danger', $sql);
        }
    }

    private function runManagedDatabaseQuery(Request $request): void
    {
        $actor = $this->auth->user();
        $sql = $request->postString('sql');
        if (!$this->security->validateCsrfToken($request->postString('_token'))) {
            $this->audit->record($request, 'database_query', 'invalid_csrf', 'managed', $actor?->id);
            $this->renderDatabaseQuery(null, 'Token CSRF jest nieprawidłowy lub wygasł.', 'danger', $sql);
            return;
        }
        if (strtoupper($request->postString('confirmation')) !== 'WRITE') {
            $this->audit->record($request, 'database_query', 'missing_confirmation', 'managed', $actor?->id);
            $this->recordHistory($actor?->id, 'query_managed', 'missing_confirmation', null, $sql, null, 'Brak potwierdzenia WRITE.');
            $this->renderDatabaseQuery(null, 'Aby wykonać zapytanie zapisowe, wpisz potwierdzenie WRITE.', 'danger', $sql);
            return;
        }
        if ($this->databaseExplorer === null) {
            $this->audit->record($request, 'database_query', 'unavailable', 'managed', $actor?->id);
            $this->renderDatabaseQuery(null, 'Konsola SQL wymaga aktywnego połączenia z bazą danych.', 'danger', $sql);
            return;
        }

        try {
            $result = $this->databaseExplorer->mutableQuery($sql);
            $this->audit->record($request, 'database_query', 'success', 'managed:' . $result['operation'], $actor?->id);
            $this->recordHistory(
                $actor?->id,
                'query_' . $result['operation'],
                'success',
                null,
                $result['sql'],
                (int) $result['affected'],
                'Zapytanie zapisowe wykonane.'
            );
            $this->renderDatabaseQuery(
                null,
                'Zapytanie zapisowe wykonane. Zmienione rekordy: ' . $result['affected'] . '.',
                'success',
                $result['sql']
            );
        } catch (\Throwable $exception) {
            $this->audit->record($request, 'database_query', 'failed', 'managed', $actor?->id);
            $this->recordHistory($actor?->id, 'query_managed', 'failed', null, $sql, null, $exception->getMessage());
            $this->renderDatabaseQuery(null, $exception->getMessage(), 'danger', $sql);
        }
    }

    private function runTableOperation(Request $request): void
    {
        $actor = $this->auth->user();
        $table = $request->postString('table');
        $operation = strtolower($request->postString('operation'));
        if (!$this->security->validateCsrfToken($request->postString('_token'))) {
            $this->audit->record($request, 'database_table_operation', 'invalid_csrf', $operation . ':' . $table, $actor?->id);
            $this->renderDatabaseExplorer($request, 'Token CSRF jest nieprawidłowy lub wygasł.', 'danger');
            return;
        }
        if ($this->databaseExplorer === null) {
            $this->audit->record($request, 'database_table_operation', 'unavailable', $operation . ':' . $table, $actor?->id);
            $this->renderDatabaseExplorer($request, 'Manager SQL wymaga aktywnego połączenia z bazą danych.', 'danger');
            return;
        }
        $tableNames = array_column($this->databaseExplorer->tables(), 'name');
        if (!in_array($table, $tableNames, true)) {
            $this->audit->record($request, 'database_table_operation', 'invalid_table', $operation . ':' . $table, $actor?->id);
            $this->renderDatabaseExplorer($request, 'Wybrana tabela nie istnieje w aktywnej bazie.', 'danger');
            return;
        }
        if (in_array($operation, ['truncate', 'drop'], true) && $request->postString('confirmation') !== $table) {
            $this->audit->record($request, 'database_table_operation', 'missing_confirmation', $operation . ':' . $table, $actor?->id);
            $this->recordHistory($actor?->id, 'table_' . $operation, 'missing_confirmation', $table, null, null, 'Brak potwierdzenia nazwą tabeli.');
            $this->renderDatabaseExplorer($request, 'Operacja wymaga wpisania dokładnej nazwy tabeli.', 'danger');
            return;
        }

        try {
            $result = $this->databaseExplorer->tableOperation($table, $operation);
            $this->audit->record($request, 'database_table_operation', 'success', $operation . ':' . $table, $actor?->id);
            $this->recordHistory(
                $actor?->id,
                'table_' . $operation,
                'success',
                $table,
                null,
                $result['affected'],
                $result['rows'] !== [] ? 'Operacja zwróciła raport tabelaryczny.' : 'Operacja wykonana.'
            );
            $this->renderDatabaseExplorer(
                $request,
                'Operacja ' . strtoupper($operation) . ' dla tabeli ' . $table . ' została wykonana.',
                'success'
            );
        } catch (\Throwable $exception) {
            $this->audit->record($request, 'database_table_operation', 'failed', $operation . ':' . $table, $actor?->id);
            $this->recordHistory($actor?->id, 'table_' . $operation, 'failed', $table, null, null, $exception->getMessage());
            $this->renderDatabaseExplorer($request, $exception->getMessage(), 'danger');
        }
    }

    private function renderRowCreate(Request $request, string $message = '', string $variant = 'info'): void
    {
        $table = $request->queryString('table', $request->postString('table'));
        if (!$this->canUseTable($table)) {
            $this->renderDatabaseExplorer($request, 'Wybrana tabela nie istnieje w aktywnej bazie.', 'danger');
            return;
        }

        $this->startPage(
            'Dodaj rekord',
            '/admin/database',
            'Dodawanie wiersza do tabeli ' . $table . '.',
            [[
                'label' => 'Wróć do tabeli',
                'href' => 'index.php?route=/admin/database&table=' . rawurlencode($table),
                'variant' => 'outline-light',
            ]]
        );
        if ($message !== '') {
            $this->theme->render_alert($message, $variant);
        }

        $columns = $this->databaseExplorer?->columns($table) ?? [];
        $this->theme->start_admin_panel('Nowy rekord', 'Kolumny AUTO_INCREMENT są pomijane');
        $this->theme->render_form(
            'index.php?route=/admin/database/row/create',
            $this->rowFields($table, $columns, [], null, true),
            'Dodaj rekord',
            $this->security->csrfToken()
        );
        $this->theme->end_admin_panel();
        $this->endPage();
    }

    private function createRow(Request $request): void
    {
        $actor = $this->auth->user();
        $table = $request->postString('table');
        if (!$this->security->validateCsrfToken($request->postString('_token'))) {
            $this->audit->record($request, 'database_row_create', 'invalid_csrf', $table, $actor?->id);
            $this->renderRowCreate($request, 'Token CSRF jest nieprawidłowy lub wygasł.', 'danger');
            return;
        }
        if (!$this->canUseTable($table)) {
            $this->audit->record($request, 'database_row_create', 'invalid_table', $table, $actor?->id);
            $this->renderDatabaseExplorer($request, 'Wybrana tabela nie istnieje w aktywnej bazie.', 'danger');
            return;
        }

        try {
            $columns = $this->databaseExplorer?->columns($table) ?? [];
            $affected = $this->databaseExplorer?->insertRow($table, $this->rowPayload($request, $columns, null, true)) ?? 0;
            $this->audit->record($request, 'database_row_create', 'success', $table, $actor?->id);
            $this->recordHistory($actor?->id, 'row_create', 'success', $table, null, $affected, 'Dodano rekord.');
            $this->renderDatabaseExplorer($request, 'Rekord został dodany do tabeli ' . $table . '.', 'success');
        } catch (\Throwable $exception) {
            $this->audit->record($request, 'database_row_create', 'failed', $table, $actor?->id);
            $this->recordHistory($actor?->id, 'row_create', 'failed', $table, null, null, $exception->getMessage());
            $this->renderRowCreate($request, $exception->getMessage(), 'danger');
        }
    }

    private function renderRowEdit(Request $request, string $message = '', string $variant = 'info'): void
    {
        $table = $request->queryString('table', $request->postString('table'));
        $id = $request->queryString('id', $request->postString('id'));
        if (!$this->canUseTable($table)) {
            $this->renderDatabaseExplorer($request, 'Wybrana tabela nie istnieje w aktywnej bazie.', 'danger');
            return;
        }
        $primaryKey = $this->databaseExplorer?->primaryKey($table);
        if ($primaryKey === null) {
            $this->renderDatabaseExplorer($request, 'Edycja rekordu wymaga pojedynczego klucza głównego tabeli.', 'danger');
            return;
        }
        $row = $this->databaseExplorer?->findRow($table, $primaryKey, $id);
        if ($row === null) {
            $this->renderDatabaseExplorer($request, 'Nie znaleziono rekordu do edycji.', 'danger');
            return;
        }

        $this->startPage(
            'Edytuj rekord',
            '/admin/database',
            'Edycja wiersza z tabeli ' . $table . '.',
            [[
                'label' => 'Wróć do tabeli',
                'href' => 'index.php?route=/admin/database&table=' . rawurlencode($table),
                'variant' => 'outline-light',
            ]]
        );
        if ($message !== '') {
            $this->theme->render_alert($message, $variant);
        }

        $columns = $this->databaseExplorer?->columns($table) ?? [];
        $this->theme->start_admin_panel('Rekord #' . $id, 'Klucz główny: ' . $primaryKey);
        $this->theme->render_form(
            'index.php?route=/admin/database/row/edit',
            $this->rowFields($table, $columns, $row, $primaryKey, false, $id),
            'Zapisz rekord',
            $this->security->csrfToken()
        );
        $this->theme->end_admin_panel();
        $this->endPage();
    }

    private function updateRow(Request $request): void
    {
        $actor = $this->auth->user();
        $table = $request->postString('table');
        $id = $request->postString('id');
        if (!$this->security->validateCsrfToken($request->postString('_token'))) {
            $this->audit->record($request, 'database_row_update', 'invalid_csrf', $table, $actor?->id);
            $this->renderRowEdit($request, 'Token CSRF jest nieprawidłowy lub wygasł.', 'danger');
            return;
        }
        if (!$this->canUseTable($table)) {
            $this->audit->record($request, 'database_row_update', 'invalid_table', $table, $actor?->id);
            $this->renderDatabaseExplorer($request, 'Wybrana tabela nie istnieje w aktywnej bazie.', 'danger');
            return;
        }
        $primaryKey = $this->databaseExplorer?->primaryKey($table);
        if ($primaryKey === null) {
            $this->renderDatabaseExplorer($request, 'Aktualizacja rekordu wymaga pojedynczego klucza głównego tabeli.', 'danger');
            return;
        }

        try {
            $columns = $this->databaseExplorer?->columns($table) ?? [];
            $affected = $this->databaseExplorer?->updateRow(
                $table,
                $primaryKey,
                $id,
                $this->rowPayload($request, $columns, $primaryKey, false)
            ) ?? 0;
            $this->audit->record($request, 'database_row_update', 'success', $table . ':' . $id, $actor?->id);
            $this->recordHistory($actor?->id, 'row_update', 'success', $table, null, $affected, 'Zaktualizowano rekord.');
            $this->renderDatabaseExplorer($request, 'Rekord został zapisany.', 'success');
        } catch (\Throwable $exception) {
            $this->audit->record($request, 'database_row_update', 'failed', $table . ':' . $id, $actor?->id);
            $this->recordHistory($actor?->id, 'row_update', 'failed', $table, null, null, $exception->getMessage());
            $this->renderRowEdit($request, $exception->getMessage(), 'danger');
        }
    }

    private function deleteRow(Request $request): void
    {
        $actor = $this->auth->user();
        $table = $request->postString('table');
        $id = $request->postString('id');
        if (!$this->security->validateCsrfToken($request->postString('_token'))) {
            $this->audit->record($request, 'database_row_delete', 'invalid_csrf', $table . ':' . $id, $actor?->id);
            $this->renderDatabaseExplorer($request, 'Token CSRF jest nieprawidłowy lub wygasł.', 'danger');
            return;
        }
        if (!$this->canUseTable($table)) {
            $this->audit->record($request, 'database_row_delete', 'invalid_table', $table, $actor?->id);
            $this->renderDatabaseExplorer($request, 'Wybrana tabela nie istnieje w aktywnej bazie.', 'danger');
            return;
        }
        $primaryKey = $this->databaseExplorer?->primaryKey($table);
        if ($primaryKey === null) {
            $this->renderDatabaseExplorer($request, 'Usuwanie rekordu wymaga pojedynczego klucza głównego tabeli.', 'danger');
            return;
        }

        try {
            $affected = $this->databaseExplorer?->deleteRow($table, $primaryKey, $id) ?? 0;
            $this->audit->record($request, 'database_row_delete', 'success', $table . ':' . $id, $actor?->id);
            $this->recordHistory($actor?->id, 'row_delete', 'success', $table, null, $affected, 'Usunięto rekord.');
            $this->renderDatabaseExplorer($request, 'Rekord został usunięty.', 'success');
        } catch (\Throwable $exception) {
            $this->audit->record($request, 'database_row_delete', 'failed', $table . ':' . $id, $actor?->id);
            $this->recordHistory($actor?->id, 'row_delete', 'failed', $table, null, null, $exception->getMessage());
            $this->renderDatabaseExplorer($request, $exception->getMessage(), 'danger');
        }
    }

    private function renderDatabaseHistory(Request $request): void
    {
        $this->startPage(
            'Historia Managera SQL',
            '/admin/database',
            'Ostatnie operacje wykonane przez moduł Manager SQL.',
            [[
                'label' => 'Wróć do Managera SQL',
                'href' => 'index.php?route=/admin/database',
                'variant' => 'outline-light',
            ], [
                'label' => 'Konsola SQL',
                'href' => 'index.php?route=/admin/database/query',
                'variant' => 'primary',
            ]]
        );
        if ($this->history === null) {
            $this->theme->render_alert('Historia wymaga aktywnej bazy danych i zainstalowanego modułu.', 'danger');
            $this->endPage();
            return;
        }

        $page = max(1, $request->queryInt('page', 1) ?? 1);
        $perPage = 25;
        try {
            $total = $this->history->count();
            $pages = max(1, (int) ceil($total / $perPage));
            $page = min($page, $pages);
            $events = $this->history->page($page, $perPage);
        } catch (\Throwable $exception) {
            $this->theme->render_alert($exception->getMessage(), 'danger');
            $this->endPage();
            return;
        }

        $this->theme->start_admin_panel(
            'Operacje',
            'Strona ' . $page . ' z ' . $pages . ', wpisów: ' . number_format($total, 0, ',', ' ')
        );
        if ($events === []) {
            $this->theme->render_alert('Brak zapisanych operacji Managera SQL.', 'info');
        } else {
            $this->theme->render_admin_table(
                ['Czas', 'Użytkownik', 'Operacja', 'Cel', 'Wynik', 'Wiersze', 'Zapytanie / komunikat'],
                array_map(
                    static fn (array $event): array => [
                        (string) ($event['created_at'] ?? ''),
                        (string) ($event['display_name'] ?? 'System'),
                        (string) ($event['operation'] ?? ''),
                        (string) ($event['target_table'] ?? ''),
                        (string) ($event['result'] ?? ''),
                        $event['rows_count'] !== null ? (string) $event['rows_count'] : '',
                        (string) (($event['sql_preview'] ?? '') !== '' ? $event['sql_preview'] : ($event['message'] ?? '')),
                    ],
                    $events
                )
            );
            $actions = [];
            if ($page > 1) {
                $actions[] = [
                    'label' => 'Poprzednia strona',
                    'href' => 'index.php?route=/admin/database/history&page=' . ($page - 1),
                    'variant' => 'outline-light',
                ];
            }
            if ($page < $pages) {
                $actions[] = [
                    'label' => 'Następna strona',
                    'href' => 'index.php?route=/admin/database/history&page=' . ($page + 1),
                    'variant' => 'primary',
                ];
            }
            if ($actions !== []) {
                $this->theme->render_admin_panel_actions($actions);
            }
        }
        $this->theme->end_admin_panel();
        $this->endPage();
    }

    private function renderDatabaseImport(string $message = '', string $variant = 'info', string $sql = ''): void
    {
        $this->startPage(
            'Import SQL',
            '/admin/database',
            'Kontrolowany import pliku lub treści SQL do aktywnej bazy danych.',
            [[
                'label' => 'Wróć do Managera SQL',
                'href' => 'index.php?route=/admin/database',
                'variant' => 'outline-light',
            ], [
                'label' => 'Historia',
                'href' => 'index.php?route=/admin/database/history',
                'variant' => 'outline-light',
            ]]
        );
        if ($message !== '') {
            $this->theme->render_alert($message, $variant);
        }
        if ($this->databaseExplorer === null) {
            $this->theme->render_alert('Import SQL wymaga aktywnego połączenia z bazą danych.', 'danger');
            $this->endPage();
            return;
        }

        $this->theme->start_admin_panel('Import SQL', 'Limit 2 MB, wymagane potwierdzenie IMPORT');
        $this->theme->render_form(
            'index.php?route=/admin/database/import',
            [[
                'name' => 'sql_file',
                'label' => 'Plik SQL',
                'type' => 'file',
                'accept' => '.sql,text/sql,application/sql,text/plain',
                'help' => 'Opcjonalnie wybierz plik .sql. Jeśli pole zostanie puste, użyta będzie treść z pola SQL.',
            ], [
                'name' => 'sql',
                'label' => 'Treść SQL',
                'type' => 'textarea',
                'value' => $sql,
                'rows' => 12,
                'help' => 'Import może zawierać wiele instrukcji SQL, np. dump wygenerowany przez eksport Managera SQL.',
            ], [
                'name' => 'confirmation',
                'label' => 'Potwierdzenie',
                'type' => 'text',
                'value' => '',
                'help' => 'Wpisz IMPORT, aby uruchomić import.',
            ]],
            'Importuj SQL',
            $this->security->csrfToken()
        );
        $this->theme->end_admin_panel();
        $this->endPage();
    }

    private function runDatabaseImport(Request $request): void
    {
        $actor = $this->auth->user();
        $sql = $request->postString('sql');
        if (!$this->security->validateCsrfToken($request->postString('_token'))) {
            $this->audit->record($request, 'database_import', 'invalid_csrf', 'sql', $actor?->id);
            $this->renderDatabaseImport('Token CSRF jest nieprawidłowy lub wygasł.', 'danger', $sql);
            return;
        }
        if (strtoupper($request->postString('confirmation')) !== 'IMPORT') {
            $this->audit->record($request, 'database_import', 'missing_confirmation', 'sql', $actor?->id);
            $this->recordHistory($actor?->id, 'import_sql', 'missing_confirmation', null, null, null, 'Brak potwierdzenia IMPORT.');
            $this->renderDatabaseImport('Aby wykonać import, wpisz potwierdzenie IMPORT.', 'danger', $sql);
            return;
        }
        if ($this->databaseExplorer === null) {
            $this->audit->record($request, 'database_import', 'unavailable', 'sql', $actor?->id);
            $this->renderDatabaseImport('Import SQL wymaga aktywnego połączenia z bazą danych.', 'danger', $sql);
            return;
        }

        try {
            $source = 'textarea';
            $file = $request->file('sql_file');
            if ($file !== null && $file['error'] !== UPLOAD_ERR_NO_FILE) {
                if ($file['error'] !== UPLOAD_ERR_OK) {
                    throw new \RuntimeException('Nie udało się odebrać pliku SQL.');
                }
                if (!str_ends_with(strtolower($file['name']), '.sql')) {
                    throw new \RuntimeException('Import przyjmuje wyłącznie pliki .sql.');
                }
                $content = file_get_contents($file['tmp_name']);
                if ($content === false) {
                    throw new \RuntimeException('Nie można odczytać pliku SQL.');
                }
                $sql = $content;
                $source = 'file:' . $file['name'];
            }

            $result = $this->databaseExplorer->importSql($sql);
            $this->audit->record($request, 'database_import', 'success', $source, $actor?->id);
            $this->recordHistory(
                $actor?->id,
                'import_sql',
                'success',
                null,
                null,
                $result['affected'],
                'Źródło: ' . $source . ', bajty: ' . $result['bytes']
            );
            $this->renderDatabaseImport(
                'Import SQL wykonany. Zmienione rekordy: ' . $result['affected'] . '.',
                'success'
            );
        } catch (\Throwable $exception) {
            $this->audit->record($request, 'database_import', 'failed', 'sql', $actor?->id);
            $this->recordHistory($actor?->id, 'import_sql', 'failed', null, null, null, $exception->getMessage());
            $this->renderDatabaseImport($exception->getMessage(), 'danger', $sql);
        }
    }

    private function recordHistory(
        ?int $userId,
        string $operation,
        string $result,
        ?string $targetTable = null,
        ?string $sqlPreview = null,
        ?int $rowsCount = null,
        ?string $message = null,
    ): void {
        try {
            $this->history?->record($userId, $operation, $result, $targetTable, $sqlPreview, $rowsCount, $message);
        } catch (\Throwable) {
            // Historia Managera SQL jest pomocnicza; audit log pozostaje źródłem zdarzeń bezpieczeństwa.
        }
    }

    private function renderHistoryPanel(): void
    {
        if ($this->history === null) {
            return;
        }

        try {
            $events = $this->history->recent(10);
        } catch (\Throwable) {
            return;
        }

        $this->theme->start_admin_panel('Historia operacji', count($events) . ' ostatnich wpisów');
        if ($events === []) {
            $this->theme->render_alert('Brak zapisanych operacji Managera SQL.', 'info');
        } else {
            $this->theme->render_admin_table(
                ['Czas', 'Użytkownik', 'Operacja', 'Cel', 'Wynik', 'Wiersze', 'Komunikat'],
                array_map(
                    static fn (array $event): array => [
                        (string) ($event['created_at'] ?? ''),
                        (string) ($event['display_name'] ?? 'System'),
                        (string) ($event['operation'] ?? ''),
                        (string) ($event['target_table'] ?? ''),
                        (string) ($event['result'] ?? ''),
                        $event['rows_count'] !== null ? (string) $event['rows_count'] : '',
                        (string) ($event['message'] ?? ''),
                    ],
                    $events
                )
            );
        }
        $this->theme->end_admin_panel();
    }

    private function renderTableOperationsPanel(string $selectedTable): void
    {
        $this->theme->start_admin_panel('Operacje tabeli', 'Konserwacja i akcje destrukcyjne wymagają database.manage');
        $this->theme->render_form(
            'index.php?route=/admin/database/table-operation',
            [[
                'name' => 'table',
                'label' => 'Tabela',
                'type' => 'hidden',
                'value' => $selectedTable,
            ], [
                'name' => 'operation',
                'label' => 'Operacja',
                'type' => 'select',
                'value' => 'optimize',
                'options' => [
                    'optimize' => 'OPTIMIZE TABLE',
                    'check' => 'CHECK TABLE',
                    'analyze' => 'ANALYZE TABLE',
                    'repair' => 'REPAIR TABLE',
                    'truncate' => 'TRUNCATE TABLE',
                    'drop' => 'DROP TABLE',
                ],
            ], [
                'name' => 'confirmation',
                'label' => 'Potwierdzenie dla TRUNCATE/DROP',
                'type' => 'text',
                'value' => '',
                'help' => 'Przy opróżnianiu albo usuwaniu wpisz dokładną nazwę tabeli: ' . $selectedTable,
            ]],
            'Wykonaj operację',
            $this->security->csrfToken()
        );
        $this->theme->end_admin_panel();
    }

    /**
     * @param list<array{name: string, type: string, nullable: string, key: string, default: string, extra: string}> $columns
     * @param array<string, mixed> $row
     * @return list<array<string, mixed>>
     */
    private function rowFields(
        string $table,
        array $columns,
        array $row,
        ?string $primaryKey,
        bool $insert,
        string $id = '',
    ): array {
        $fields = [[
            'name' => 'table',
            'label' => 'Tabela',
            'type' => 'hidden',
            'value' => $table,
        ]];
        if (!$insert) {
            $fields[] = [
                'name' => 'id',
                'label' => 'Identyfikator',
                'type' => 'hidden',
                'value' => $id,
            ];
        }

        foreach ($columns as $column) {
            $name = $column['name'];
            if ($insert && str_contains(strtolower($column['extra']), 'auto_increment')) {
                continue;
            }
            if (!$insert && $name === $primaryKey) {
                continue;
            }

            $value = $row[$name] ?? '';
            $fields[] = [
                'name' => 'values[' . $name . ']',
                'label' => $name . ' (' . $column['type'] . ')',
                'type' => $this->fieldType($column['type']),
                'value' => $value !== null && is_scalar($value) ? (string) $value : '',
                'rows' => 6,
                'help' => $column['nullable'] === 'YES' ? 'Zaznacz NULL poniżej, aby zapisać wartość NULL.' : '',
            ];
            if ($column['nullable'] === 'YES') {
                $fields[] = [
                    'name' => 'nulls[' . $name . ']',
                    'label' => 'Ustaw ' . $name . ' jako NULL',
                    'type' => 'checkbox',
                    'checked' => array_key_exists($name, $row) && $row[$name] === null,
                ];
            }
        }

        return $fields;
    }

    /**
     * @param list<array{name: string, type: string, nullable: string, key: string, default: string, extra: string}> $columns
     * @return array<string, scalar|null>
     */
    private function rowPayload(Request $request, array $columns, ?string $primaryKey, bool $insert): array
    {
        $values = $request->postArray('values');
        $nulls = $request->postArray('nulls');
        $payload = [];
        foreach ($columns as $column) {
            $name = $column['name'];
            if ($insert && str_contains(strtolower($column['extra']), 'auto_increment')) {
                continue;
            }
            if (!$insert && $name === $primaryKey) {
                continue;
            }
            if (!array_key_exists($name, $values) && !array_key_exists($name, $nulls)) {
                continue;
            }
            if ($column['nullable'] === 'YES' && array_key_exists($name, $nulls)) {
                $payload[$name] = null;
                continue;
            }
            $value = $values[$name] ?? '';
            $payload[$name] = is_scalar($value) ? (string) $value : '';
        }

        return $payload;
    }

    private function fieldType(string $columnType): string
    {
        $type = strtolower($columnType);
        if (str_contains($type, 'text') || str_contains($type, 'json') || str_contains($type, 'blob')) {
            return 'textarea';
        }
        if (preg_match('/\b(int|decimal|float|double|real|bit)\b/', $type) === 1) {
            return 'number';
        }
        if (str_contains($type, 'date') && !str_contains($type, 'time')) {
            return 'date';
        }

        return 'text';
    }

    private function canUseTable(string $table): bool
    {
        if ($this->databaseExplorer === null || $table === '') {
            return false;
        }

        return in_array($table, array_column($this->databaseExplorer->tables(), 'name'), true);
    }

    private function safeFilename(string $table): string
    {
        $filename = preg_replace('/[^a-z0-9_.-]+/i', '-', $table) ?? 'table';
        $filename = trim($filename, '-.');

        return $filename !== '' ? $filename : 'table';
    }

    /**
     * @param list<string> $permissions
     */
    private function hasPermission(array $permissions, string $permission): bool
    {
        return in_array('*', $permissions, true) || in_array($permission, $permissions, true);
    }

    private function startPage(string $title, string $activePath, string $lead, ?array $actions = null): void
    {
        $user = $this->auth->user();

        if ($user === null) {
            return;
        }

        $this->theme->start_admin_page(
            $title,
            $this->menu->visibleFor($user->permissions),
            $activePath,
            [
                'name' => $user->displayName,
                'role' => ucfirst($user->primaryRole()),
                'initials' => $user->initials(),
                'avatar_url' => $user->avatarUrl ?? '',
                'logout_action' => 'index.php?route=/admin/logout',
                'logout_token' => $this->security->csrfToken(),
            ]
        );
        $this->theme->start_admin_content(
            $title,
            $lead,
            [
                ['label' => 'Panel', 'href' => 'index.php?route=/admin'],
                ['label' => $title, 'href' => ''],
            ],
            $actions
        );
    }

    private function endPage(): void
    {
        $this->theme->end_admin_content();
        $this->theme->end_admin_page();
    }

    private function guard(Request $request, string $permission, callable $handler): void
    {
        $decision = $this->access->check($permission);
        $userId = $this->auth->user()?->id;

        if ($decision === AdminAccessGate::UNAUTHENTICATED) {
            $this->audit->record($request, 'admin_access', 'unauthenticated', null);
            http_response_code(401);
            $this->theme->render_admin_access_state(
                401,
                'Wymagane logowanie',
                'Ta trasa panelu jest dostępna wyłącznie dla zalogowanych użytkowników.',
                'index.php?route=/admin/login',
                'Przejdź do logowania'
            );
            return;
        }

        if ($decision === AdminAccessGate::FORBIDDEN) {
            $this->audit->record($request, 'admin_access', 'forbidden', null, $userId);
            http_response_code(403);
            $this->theme->render_admin_access_state(
                403,
                'Brak uprawnienia',
                "Twoje konto nie posiada uprawnienia {$permission}.",
                'index.php?route=/admin',
                'Wróć do dashboardu'
            );
            return;
        }

        $handler();
    }
}
