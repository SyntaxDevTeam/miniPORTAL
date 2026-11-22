<?php

declare(strict_types=1);
namespace SyntaxDevTeam\Cms\Modules\TaskBoard;

use InvalidArgumentException;
use SyntaxDevTeam\Cms\Core\{HookProviderInterface, HookRegistry, AdminMenuRegistry, AdminSearchProviderInterface, AdminSearchRegistry, ModuleInterface, PublicNavigationProviderInterface, PublicNavigationRegistry, Request, Router, Security, TaskListThemeInterface, ThemeInterface};
use SyntaxDevTeam\Cms\Modules\CoreAuth\{AuditLogService, AuthService};

final class TaskBoardModule implements ModuleInterface, HookProviderInterface, AdminSearchProviderInterface, PublicNavigationProviderInterface
{
    public function __construct(
        private readonly ThemeInterface $theme,
        private readonly AdminMenuRegistry $menu,
        private readonly TaskRepository $tasks,
        private readonly TaskIntegrations $integrations,
        private readonly AuthService $auth,
        private readonly Security $security,
        private readonly AuditLogService $audit,
        private readonly ?TaskDiscordConfig $discord = null,
    ) {}

    public function id(): string { return 'task_board'; }
    public function version(): string { return '1.3.0'; }
    public function dependencies(): array { return ['core_auth', 'projects', 'wikipedia', 'media_library', 'build_explorer']; }
    public function isProtected(): bool { return false; }
    public function requiredPermissions(): array { return ['tasks.use', 'tasks.manage', 'tasks.publish']; }

    public function registerAdminMenu(AdminMenuRegistry $menu): void
    {
        $menu->add('Przestrzeń robocza', 'Zadania i pomysły', '/account/tasks', 'ZD', 'tasks.use', 30);
    }

    public function registerAdminSearch(AdminSearchRegistry $search): void
    {
        // Static entry only: private task text never enters the global index.
        $search->add('tasks.workspace', 'Zadania i pomysły', 'Prywatne pomysły, wspólne zadania i plan rozwoju.', 'index.php?route=/account/tasks', ['zadania', 'pomysły', 'roadmap', 'checklista'], 'tasks.use', 'Przestrzeń robocza', 30);
    }

    public function registerPublicNavigation(PublicNavigationRegistry $navigation): void
    {
        $navigation->add('task_board.roadmap', 'Plan rozwoju', '/roadmap', 'none', 58);
    }

    public function registerHooks(HookRegistry $hooks): void
    {
        $hooks->addAction('build_explorer.v2.published', function (int $projectId): void {
            (new TaskAutomation($this->tasks, $this->integrations))->run($projectId);
        });
    }

    public function registerRoutes(Router $router): void
    {
        $router->get('/roadmap', fn (Request $r) => $this->listing($r, true));
        $router->get('/account/tasks', fn (Request $r) => $this->guard($r, fn () => $this->listing($r)));
        $router->get('/account/tasks/new', fn (Request $r) => $this->guard($r, fn () => $this->detail($r, null)));
        $router->get('/account/tasks/view', fn (Request $r) => $this->guard($r, function () use ($r): void {
            $task = $this->accessible($r->queryInt('id', 0) ?? 0);
            if ($task !== null) $this->detail($r, $task);
        }));
        $router->get('/account/tasks/edit', fn (Request $r) => $this->guard($r, function () use ($r): void {
            $task = $this->accessible($r->queryInt('id', 0) ?? 0);
            if ($task === null) return;
            if (!TaskPolicy::edit($this->auth->user(), $task)) { $this->error(403, 'Brak uprawnień do edycji.'); return; }
            $this->detail($r, $task);
        }));
        $router->get('/account/tasks/discord', fn (Request $r) => $this->guard($r, fn () => $this->discordSettings($r)));
        $router->post('/account/tasks/discord', fn (Request $r) => $this->guard($r, fn () => $this->discordSettings($r)));
        $router->get('/account/tasks/image', fn (Request $r) => $this->guard($r, fn () => $this->image($r)));
        foreach (['save', 'progress', 'comment', 'attach', 'media', 'notify'] as $action) $router->post('/account/tasks/' . $action, fn (Request $r) => $this->guard($r, fn () => $this->mutate($r, $action)));
    }

    private function guard(Request $request, callable $handler): void
    {
        header('Cache-Control: private, no-store');
        header('X-Robots-Tag: noindex, nofollow');
        if (!TaskPolicy::allows($this->auth->user(), 'tasks.use')) {
            $this->error($this->auth->user() === null ? 401 : 403, 'Zaloguj się na aktywne konto z dostępem do zadań.'); return;
        }
        if ($this->auth->rolePreviewBlocks($request)) { $this->error(403, 'Podgląd roli jest tylko do odczytu.'); return; }
        $handler();
    }

    private function accessible(int $id): ?array
    {
        $task = $this->tasks->find($id);
        if ($task === null || !TaskPolicy::read($this->auth->user(), $task)) { $this->error(404, 'Nie znaleziono zadania.'); return null; }
        return $task;
    }

    private function listing(Request $request, bool $public = false): void
    {
        // A separate projection and no-store prevent anonymous cache from mixing scopes.
        header('Cache-Control: private, no-store');
        $scope = $request->queryString('scope', 'mine');
        if (!in_array($scope, ['mine', 'members', 'public', 'oversight'], true) || ($scope === 'oversight' && !TaskPolicy::owner($this->auth->user()))) $scope = 'mine';
        $status = $request->queryString('status');
        $filters = ['scope' => $scope, 'q' => mb_substr($request->queryString('q'), 0, 120), 'project' => max(0, $request->queryInt('project', 0) ?? 0), 'status' => isset(TaskInput::STATUSES[$status]) ? $status : '', 'page' => max(1, $request->queryInt('page', 1) ?? 1)];
        $result = $this->tasks->page($this->auth->user(), $filters, $public);
        $this->render($result + $filters + ['public' => $public, 'mode' => 'list', 'projects' => array_column($this->integrations->projects(), 'name', 'id')]);
    }

    private function detail(Request $request, ?array $task, string $message = '', ?array $draft = null): void
    {
        $user = $this->auth->user();
        $preview = $this->auth->rolePreview() !== null;
        $model = ['mode' => 'detail', 'editing' => $task === null || $request->path() === '/account/tasks/edit' || $message !== '', 'task' => $draft ?? $task, 'message' => $message !== '' ? $message : ($request->queryInt('queued', 0) === 1 ? 'Powiadomienie zlecone. Ta sama rewizja zadania nie jest wysyłana ponownie.' : ''),
            'can_edit' => !$preview && ($task === null || TaskPolicy::edit($user, $task)),
            'can_progress' => !$preview && $task !== null && TaskPolicy::progress($user, $task),
            'can_comment' => !$preview && $task !== null && TaskPolicy::comment($user, $task),
            'can_publish' => TaskPolicy::allows($user, 'tasks.publish'),
            'projects' => array_column($this->integrations->projects(), 'name', 'id'),
            'wiki' => [], 'users' => $this->integrations->users(), 'media' => $this->integrations->mediaOptions($user),
            'links' => $task ? $this->integrations->links($task) : [], 'comments' => $task ? $this->tasks->comments($task['id']) : [],
            'discord_ready' => $this->discord?->ready() ?? false,
            'can_notify' => !$preview && $task !== null && TaskPolicy::notify($user, $task) && $task['assignee_ids'] !== [],
            'notification' => $task ? $this->tasks->notificationStatus($task['id']) : null,
            'attachments' => [], 'history' => $task ? $this->tasks->history($task['id']) : [],
        ];
        foreach ($this->integrations->wiki() as $page) $model['wiki'][$page['id']] = $page['project_slug'] . ' / ' . $page['title'];
        foreach ($task ? $this->tasks->attachments($task['id']) : [] as $attachment) {
            if ($attachment['media_id']) {
                $asset = $this->integrations->media((int)$attachment['media_id']);
                if ($asset !== null) $model['attachments'][] = $asset;
            } else $model['attachments'][] = ['name' => $attachment['name'], 'url' => 'index.php?route=/account/tasks/image&id=' . (int)$attachment['id']];
        }
        if ($task !== null) $model['prompt'] = TaskPrompt::build($task, $model['links'], $model['comments'], $model['attachments']);
        if ($task !== null && $task['visibility'] === 'private' && $task['author_id'] !== $user->id) $this->audit->record($request, 'task_board_private_read', 'success', 'task_board', $user->id);
        $this->render($model);
    }

    private function mutate(Request $request, string $action): void
    {
        $user = $this->auth->user();
        if (!$this->security->validateCsrfToken($request->postString('_token'))) {
            $this->audit->record($request, 'task_board_' . $action, 'invalid_csrf', 'task_board', $user->id);
            $this->error(403, 'Token formularza wygasł. Odśwież stronę.'); return;
        }
        $id = $request->postInt('id', 0) ?? 0;
        $task = $id > 0 ? $this->accessible($id) : null;
        if ($id > 0 && $task === null) return;
        if ($action !== 'save' && $task === null) { $this->error(404, 'Nie znaleziono zadania.'); return; }
        $allowed = match ($action) {
            'save' => $task === null || TaskPolicy::edit($user, $task),
            'notify' => TaskPolicy::notify($user, $task),
            'progress' => TaskPolicy::progress($user, $task),
            default => TaskPolicy::comment($user, $task),
        };
        if (!$allowed) { $this->error(403, 'Brak uprawnień do tej operacji.'); return; }
        $data = null;
        try {
            $notify = $request->postBool('notify_assignees');
            if (($action === 'notify' || ($action === 'save' && $notify)) && !($this->discord?->ready() ?? false)) throw new InvalidArgumentException('Powiadomienia Discord nie są skonfigurowane lub są wyłączone.');
            if ($action === 'save') {
                $data = TaskInput::parse($request);
                if (($data['visibility'] === 'public' || ($task['visibility'] ?? '') === 'public') && !TaskPolicy::allows($user, 'tasks.publish')) { $this->error(403, 'Publikacja wymaga uprawnienia tasks.publish.'); return; }
                if (!TaskPolicy::allows($user, 'tasks.publish')) {
                    $data['public_title'] = $task['public_title'] ?? '';
                    $data['public_summary'] = $task['public_summary'] ?? '';
                }
                $this->integrations->validate($data, $task);
                $data = (new TaskAutomation($this->tasks, $this->integrations))->apply($data);
                if ($task === null) $id = $this->tasks->create($data, $user->id, $notify);
                else $this->tasks->update($id, $request->postInt('revision', 0) ?? 0, $data, $user->id, 'Zaktualizowano zadanie: ' . TaskInput::STATUSES[$data['status']], $notify);
            } elseif ($action === 'notify') {
                $this->tasks->queueNotification($id, $request->postInt('revision', 0) ?? 0, $user);
            } elseif ($action === 'progress') {
                $data = $task;
                $data['status'] = TaskInput::choice($request->postString('status'), array_keys(TaskInput::STATUSES));
                $done = array_map(static fn ($value) => is_scalar($value) ? (string)$value : '', $request->postArray('done'));
                foreach ($data['checklist'] as $index => &$step) $step['done'] = in_array((string)$index, $done, true);
                unset($step);
                $data = (new TaskAutomation($this->tasks, $this->integrations))->apply($data);
                $this->tasks->update($id, $request->postInt('revision', 0) ?? 0, $data, $user->id, 'Zmieniono postęp: ' . TaskInput::STATUSES[$data['status']]);
            } elseif ($action === 'comment') {
                $this->tasks->addComment($id, $user->id, TaskInput::text($request->postString('body'), 5000, true));
            } elseif ($action === 'media') {
                if (!TaskPolicy::allows($user, 'media.manage')) { $this->error(403, 'Wybór Biblioteki wymaga uprawnienia media.manage.'); return; }
                $mediaId = $request->postInt('media_id', 0) ?? 0;
                $asset = $this->integrations->media($mediaId);
                if ($asset === null) throw new InvalidArgumentException('Nie znaleziono grafiki w Bibliotece.');
                $this->tasks->addAttachment($id, $user->id, ['media_id' => $mediaId, 'name' => mb_substr($asset['name'], 0, 180), 'mime_type' => $asset['mime_type'], 'image_data' => null]);
            } elseif ($action === 'attach') {
                $file = $request->file('screenshot');
                if (!$file || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_string($file['tmp_name'] ?? null) || !is_uploaded_file($file['tmp_name']) || filesize($file['tmp_name']) > Screenshot::MAX_BYTES) throw new InvalidArgumentException('Wybierz poprawny screenshot do 4 MiB.');
                $bytes = file_get_contents($file['tmp_name']);
                if (!is_string($bytes)) throw new InvalidArgumentException('Nie można odczytać pliku.');
                $this->tasks->addAttachment($id, $user->id, Screenshot::normalize($bytes));
            }
            $this->audit->record($request, 'task_board_' . $action, 'success', 'task_board', $user->id);
            header('Location: index.php?route=/account/tasks/view&id=' . $id . ($action === 'notify' || ($action === 'save' && $notify) ? '&queued=1' : ''), true, 303);
        } catch (InvalidArgumentException|TaskConflict $error) {
            http_response_code($error instanceof TaskConflict ? 409 : 422);
            $draft = $action === 'save' && $data !== null && !$error instanceof TaskConflict ? $data + ['id' => $id, 'revision' => $task['revision'] ?? 0] : null;
            $this->detail($request, $task, $error->getMessage(), $draft);
        } catch (\Throwable $error) {
            error_log('[miniPORTAL] task_board_write_failed ' . $error::class);
            $this->error(500, 'Nie udało się zapisać. Odśwież zadanie i sprawdź jego stan.');
        }
    }

    private function canConfigureDiscord(): bool
    {
        $user = $this->auth->user();
        return TaskPolicy::allows($user, 'tasks.manage') && array_intersect($user->roles, ['owner', 'administrator']) !== [];
    }

    private function discordSettings(Request $request): void
    {
        if (!$this->canConfigureDiscord() || $this->discord === null) { $this->error(403, 'Konfiguracja Discord jest dostępna dla Ownera i systemowego Administratora.'); return; }
        if ($this->discord->centrallyManaged()) {header('Location: /admin/discord',true,303);return;}
        $message = '';
        if ($request->method() === 'POST') {
            if (!$this->security->validateCsrfToken($request->postString('_token'))) { $this->error(403, 'Token formularza wygasł.'); return; }
            try {
                $this->discord->save($request->postString('webhook'), $request->postBool('enabled'), $request->postBool('clear_webhook'));
                $this->audit->record($request, 'task_board_discord_config', 'success', 'task_board', $this->auth->user()->id);
                header('Location: index.php?route=/account/tasks/discord&saved=1', true, 303); return;
            } catch (InvalidArgumentException $error) { http_response_code(422); $message = $error->getMessage(); }
            catch (\Throwable) { http_response_code(500); $message = 'Nie udało się zapisać konfiguracji Discord.'; }
        }
        $this->render(['mode' => 'discord', 'discord_settings' => $this->discord->settings(), 'message' => $message ?: ($request->queryInt('saved', 0) === 1 ? 'Konfiguracja zapisana.' : '')]);
    }

    private function image(Request $request): void
    {
        $attachment = $this->tasks->attachment($request->queryInt('id', 0) ?? 0);
        if ($attachment === null) { $this->error(404, 'Nie znaleziono grafiki.'); return; }
        $task = $this->accessible((int)$attachment['task_id']);
        if ($task === null) return;
        if ($attachment['media_id'] !== null || $attachment['mime_type'] !== 'image/png') { $this->error(404, 'Nie znaleziono screenshotu.'); return; }
        if ($task['visibility'] === 'private' && $task['author_id'] !== $this->auth->user()->id) $this->audit->record($request, 'task_board_private_image', 'success', 'task_board', $this->auth->user()->id);
        header('Content-Type: image/png'); header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: inline; filename="Screenshot.png"');
        echo $attachment['image_data'];
    }

    private function render(array $model): void
    {
        if (!$this->theme instanceof TaskListThemeInterface) { $this->error(503, 'Wybrany motyw wymaga kontraktu listy zadań.'); return; }
        $public = $model['public'] ?? false;
        $title = $public ? 'Plan rozwoju projektów' : 'Zadania i pomysły';
        if ($public) { $this->theme->start_page($title, 'Pomysły, nadchodzące zmiany i ukończone prace.'); $this->theme->start_header($title); $this->theme->end_header(); }
        else {
            $user = $this->auth->user();
            $this->theme->start_admin_page($title, $this->menu->visibleFor($user->permissions), '/account/tasks', ['name' => $user->displayName, 'role' => ucfirst($user->primaryRole()), 'initials' => $user->initials(), 'avatar_url' => $user->avatarUrl ?? '', 'logout_action' => 'index.php?route=/admin/logout', 'logout_token' => $this->security->csrfToken()]);
            $this->theme->start_admin_content($title, 'Zapisz pomysł, rozpisz kroki i śledź postęp.', [['label' => 'Zadania', 'href' => 'index.php?route=/account/tasks']]);
        }
        $this->theme->render_task_list($model + ['can_discord_settings' => $this->canConfigureDiscord(), 'statuses' => TaskInput::STATUSES, 'owner' => TaskPolicy::owner($this->auth->user()), 'token' => $public ? '' : $this->security->csrfToken()]);
        if ($public) $this->theme->end_page(); else { $this->theme->end_admin_content(); $this->theme->end_admin_page(); }
    }

    private function error(int $status, string $message): void
    {
        http_response_code($status);
        $this->theme->render_admin_access_state($status, 'Zadania i plany', $message, $status === 401 ? 'index.php?route=/admin/login&next=' . rawurlencode('index.php?route=/account/tasks') : 'index.php?route=/account/tasks', $status === 401 ? 'Zaloguj się' : 'Wróć do zadań');
    }
}
