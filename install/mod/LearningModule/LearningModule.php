<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\LearningModule;

use SyntaxDevTeam\Cms\Core\AdminMenuRegistry;
use SyntaxDevTeam\Cms\Core\ModuleInterface;
use SyntaxDevTeam\Cms\Core\Request;
use SyntaxDevTeam\Cms\Core\Router;
use SyntaxDevTeam\Cms\Core\Security;
use SyntaxDevTeam\Cms\Core\ThemeInterface;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AdminAccessGate;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuditLogService;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuthService;
use SyntaxDevTeam\Cms\Modules\CoreAuth\User;

/**
 * Kompletny przykład rozszerzenia miniPORTAL.
 *
 * Klasa pokazuje granicę odpowiedzialności modułu:
 * - deklaruje metadane zgodne z `info.json`,
 * - rejestruje menu i trasy,
 * - pobiera wejście wyłącznie przez {@see Request},
 * - deleguje bazę do {@see LearningRepository},
 * - deleguje HTML do {@see ThemeInterface},
 * - chroni operacje przez ACL i CSRF,
 * - zapisuje działania administracyjne w audit logu.
 *
 * Moduł celowo nie zawiera HTML, klas Bootstrap ani bezpośredniego dostępu do
 * `$_GET`, `$_POST`, `$_SESSION` lub PDO.
 */
final class LearningModule implements ModuleInterface
{
    /**
     * @param ThemeInterface $theme Aktywny, wymienialny motyw aplikacji.
     * @param AdminMenuRegistry $menu Wspólny rejestr menu panelu.
     * @param LearningRepository $entries Repozytorium danych modułu.
     * @param AuthService $auth Bieżąca sesja i użytkownik.
     * @param AdminAccessGate $access Centralna kontrola ACL tras panelu.
     * @param Security $security Tokeny CSRF i zabezpieczenia sesji.
     * @param AuditLogService $audit Rejestr operacji administracyjnych.
     */
    public function __construct(
        private readonly ThemeInterface $theme,
        private readonly AdminMenuRegistry $menu,
        private readonly LearningRepository $entries,
        private readonly AuthService $auth,
        private readonly AdminAccessGate $access,
        private readonly Security $security,
        private readonly AuditLogService $audit,
    ) {
    }

    /**
     * Zwraca stabilny identyfikator zgodny z manifestem i nazwą tabel.
     */
    public function id(): string
    {
        return 'learning_module';
    }

    /**
     * Zwraca wersję kodu; musi być identyczna z `info.json`.
     */
    public function version(): string
    {
        return '1.1.0';
    }

    /**
     * @return list<string> Identyfikatory modułów uruchamianych wcześniej.
     */
    public function dependencies(): array
    {
        return ['core_auth'];
    }

    /**
     * Rozszerzenie może być wyłączane i odinstalowywane.
     */
    public function isProtected(): bool
    {
        return false;
    }

    /**
     * @return list<string> Uprawnienia dostarczane przez `install.sql`.
     */
    public function requiredPermissions(): array
    {
        return ['learning.view', 'learning.manage'];
    }

    /**
     * Rejestruje pozycję menu bez generowania HTML.
     */
    public function registerAdminMenu(AdminMenuRegistry $menu): void
    {
        $menu->add(
            'Treść',
            'Nauka modułów',
            '/admin/learning',
            'LM',
            'learning.view',
            80
        );
    }

    /**
     * Rejestruje trasy GET/POST. Każda trasa panelu przechodzi przez ACL.
     */
    public function registerRoutes(Router $router): void
    {
        $router->get('/admin/learning', fn (Request $request) => $this->guard(
            $request,
            'learning.view',
            fn () => $this->renderList()
        ));
        $router->post('/admin/learning/create', fn (Request $request) => $this->guard(
            $request,
            'learning.manage',
            fn () => $this->create($request)
        ));
        $router->post('/admin/learning/delete', fn (Request $request) => $this->guard(
            $request,
            'learning.manage',
            fn () => $this->delete($request)
        ));
    }

    /**
     * Renderuje panel przy użyciu wyłącznie ogólnych komponentów Theme.
     */
    private function renderList(string $message = '', string $variant = 'info'): void
    {
        $user = $this->auth->user();
        if ($user === null) {
            return;
        }

        $this->startPage($user);
        if ($message !== '') {
            $this->theme->render_alert($message, $variant);
        }

        if ($this->allows($user, 'learning.manage')) {
            $this->theme->start_admin_panel('Nowy wpis', 'Przykład formularza + CSRF');
            $this->theme->render_form(
                'index.php?route=/admin/learning/create',
                [
                    ['name' => 'title', 'label' => 'Tytuł'],
                    ['name' => 'note', 'label' => 'Notatka', 'type' => 'textarea', 'rows' => 4],
                    [
                        'name' => 'status',
                        'label' => 'Status',
                        'type' => 'select',
                        'value' => 'draft',
                        'options' => ['draft' => 'Szkic', 'ready' => 'Gotowy'],
                    ],
                ],
                'Dodaj wpis',
                $this->security->csrfToken()
            );
            $this->theme->end_admin_panel();
        }

        $rows = array_map(
            fn (LearningEntry $entry): array => [
                'cells' => [
                    $entry->title,
                    $entry->note,
                    $entry->status,
                    $entry->createdAt,
                ],
                'actions' => $this->allows($user, 'learning.manage') ? [[
                    'label' => 'Usuń',
                    'action' => 'index.php?route=/admin/learning/delete',
                    'fields' => ['id' => $entry->id],
                    'variant' => 'outline-danger',
                    'confirm' => 'Usunąć przykładowy wpis?',
                ]] : [],
            ],
            $this->entries->all()
        );

        $this->theme->start_admin_panel('Wpisy', count($rows) . ' rekordów');
        $this->theme->render_admin_action_table(
            ['Tytuł', 'Notatka', 'Status', 'Utworzono'],
            $rows,
            $this->security->csrfToken()
        );
        $this->theme->end_admin_panel();
        $this->theme->end_admin_content();
        $this->theme->end_admin_page();
    }

    /**
     * Waliduje dane wejściowe i tworzy rekord.
     */
    private function create(Request $request): void
    {
        if (!$this->validCsrf($request, 'learning_create')) {
            return;
        }
        $title = $request->postString('title');
        $note = $request->postString('note');
        $status = $request->postString('status');
        if ($title === '' || strlen($title) > 180 || strlen($note) > 4000) {
            $this->renderList('Tytuł jest wymagany, a dane przekraczają dozwolony limit.', 'danger');
            return;
        }
        if (!in_array($status, ['draft', 'ready'], true)) {
            $this->renderList('Wybrano nieprawidłowy status.', 'danger');
            return;
        }

        $userId = $this->auth->user()?->id ?? 0;
        $this->entries->create($title, $note, $status, $userId);
        $this->audit->record($request, 'learning_create', 'success', null, $userId);
        $this->renderList('Wpis został utworzony.', 'success');
    }

    /**
     * Usuwa rekord po walidacji CSRF i identyfikatora.
     */
    private function delete(Request $request): void
    {
        if (!$this->validCsrf($request, 'learning_delete')) {
            return;
        }
        $deleted = $this->entries->delete($request->postInt('id') ?? 0);
        $this->audit->record(
            $request,
            'learning_delete',
            $deleted ? 'success' : 'not_found',
            null,
            $this->auth->user()?->id
        );
        $this->renderList(
            $deleted ? 'Wpis został usunięty.' : 'Nie znaleziono wpisu.',
            $deleted ? 'success' : 'warning'
        );
    }

    /**
     * Buduje wspólny shell panelu bez znajomości jego HTML.
     */
    private function startPage(User $user): void
    {
        $this->theme->start_admin_page(
            'Nauka modułów',
            $this->menu->visibleFor($user->permissions),
            '/admin/learning',
            [
                'name' => $user->displayName,
                'role' => ucfirst($user->primaryRole()),
                'initials' => $user->initials(),
                'logout_action' => 'index.php?route=/admin/logout',
                'logout_token' => $this->security->csrfToken(),
            ]
        );
        $this->theme->start_admin_content(
            'Moduł edukacyjny',
            'Przykład kompletnego przepływu Core → Module → Theme.',
            [
                ['label' => 'Panel', 'href' => 'index.php?route=/admin'],
                ['label' => 'Nauka modułów', 'href' => ''],
            ]
        );
    }

    /**
     * Centralizuje kontrolę dostępu dla tras modułu.
     */
    private function guard(Request $request, string $permission, callable $handler): void
    {
        $decision = $this->access->check($permission);
        if ($decision !== AdminAccessGate::ALLOWED) {
            $this->audit->record(
                $request,
                'learning_acl',
                $decision,
                null,
                $this->auth->user()?->id
            );
            http_response_code($decision === AdminAccessGate::UNAUTHENTICATED ? 401 : 403);
            $this->theme->render_admin_access_state(
                http_response_code(),
                'Brak dostępu',
                'Ta operacja wymaga odpowiedniego uprawnienia modułu.',
                'index.php?route=/admin',
                'Wróć do panelu'
            );
            return;
        }

        $handler();
    }

    /**
     * Sprawdza token i zapisuje nieudaną próbę w audit logu.
     */
    private function validCsrf(Request $request, string $event): bool
    {
        if ($this->security->validateCsrfToken($request->postString('_token'))) {
            return true;
        }

        $this->audit->record($request, $event, 'invalid_csrf', null, $this->auth->user()?->id);
        http_response_code(403);
        $this->renderList('Nieprawidłowy lub wygasły token CSRF.', 'danger');

        return false;
    }

    /**
     * Sprawdza uprawnienie z uwzględnieniem administratora `*`.
     */
    private function allows(User $user, string $permission): bool
    {
        return in_array('*', $user->permissions, true)
            || in_array($permission, $user->permissions, true);
    }
}
