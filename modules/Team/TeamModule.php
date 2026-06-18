<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\Team;

use SyntaxDevTeam\Cms\Core\AdminMenuRegistry;
use SyntaxDevTeam\Cms\Core\ModuleInterface;
use SyntaxDevTeam\Cms\Core\PublicNavigationProviderInterface;
use SyntaxDevTeam\Cms\Core\PublicNavigationRegistry;
use SyntaxDevTeam\Cms\Core\Request;
use SyntaxDevTeam\Cms\Core\Router;
use SyntaxDevTeam\Cms\Core\Security;
use SyntaxDevTeam\Cms\Core\ThemeInterface;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AdminAccessGate;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuditLogService;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuthService;
use SyntaxDevTeam\Cms\Modules\CoreAuth\User;

final class TeamModule implements ModuleInterface, PublicNavigationProviderInterface
{
    public function __construct(
        private readonly ThemeInterface $theme,
        private readonly AdminMenuRegistry $menu,
        private readonly TeamRepository $team,
        private readonly AuthService $auth,
        private readonly AdminAccessGate $access,
        private readonly Security $security,
        private readonly AuditLogService $audit,
    ) {
    }

    public function id(): string
    {
        return 'team';
    }

    public function version(): string
    {
        return '1.0.0';
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
        return ['team.manage'];
    }

    public function registerAdminMenu(AdminMenuRegistry $menu): void
    {
        $menu->add('Treść', 'Zespół', '/admin/team', 'TM', 'team.manage', 40);
    }

    public function registerPublicNavigation(PublicNavigationRegistry $navigation): void
    {
        $navigation->add('team.index', 'Zespół', '/team', 'none', 65);
    }

    public function registerRoutes(Router $router): void
    {
        $router->get('/team', fn () => $this->renderPublicList());
        $router->get('/team/member', fn (Request $request) => $this->renderPublicProfile($request->queryString('slug')));
        foreach ($this->team->visible() as $member) {
            $router->get('/team/member/' . $member->slug, fn () => $this->renderPublicProfile($member->slug));
        }

        $router->get('/admin/team', fn (Request $request) => $this->guard(
            $request,
            'team.manage',
            fn () => $this->renderAdminList()
        ));
        $router->get('/admin/team/create', fn (Request $request) => $this->guard(
            $request,
            'team.manage',
            fn () => $this->renderForm()
        ));
        $router->post('/admin/team/create', fn (Request $request) => $this->guard(
            $request,
            'team.manage',
            fn () => $this->create($request)
        ));
        $router->get('/admin/team/edit', fn (Request $request) => $this->guard(
            $request,
            'team.manage',
            fn () => $this->renderEdit($request)
        ));
        $router->post('/admin/team/edit', fn (Request $request) => $this->guard(
            $request,
            'team.manage',
            fn () => $this->update($request)
        ));
        $router->post('/admin/team/delete', fn (Request $request) => $this->guard(
            $request,
            'team.manage',
            fn () => $this->delete($request)
        ));
    }

    private function renderPublicList(): void
    {
        $members = $this->team->visible();
        $this->theme->start_page('Zespół - SyntaxDevTeam', 'Poznaj członków zespołu SyntaxDevTeam.');
        $this->theme->start_header('Zespół', 'Ludzie stojący za projektami, modułami i wsparciem społeczności.', 'SyntaxDevTeam / Team');
        $this->theme->end_header();
        $this->theme->start_section();
        if ($members === []) {
            $this->theme->render_alert('Publiczna lista zespołu jest jeszcze pusta.', 'info');
        } else {
            $this->theme->start_grid();
            foreach ($members as $member) {
                $this->theme->start_column('lg-4');
                $this->theme->start_card($member->publicName, $member->roleLabel);
                $this->renderPublicAvatar($member);
                $this->theme->render_text($this->shortBio($member->bio));
                $this->theme->render_button('Pokaż profil', '/team/member/' . rawurlencode($member->slug), 'primary');
                $this->theme->end_card();
                $this->theme->end_column();
            }
            $this->theme->end_grid();
        }
        $this->theme->end_section();
        $this->theme->end_page();
    }

    private function renderPublicProfile(string $slug): void
    {
        $member = $this->team->findVisibleBySlug($slug);
        if (!$member instanceof TeamMember) {
            $this->theme->render_public_error(404, 'Nie znaleziono profilu', 'Ten profil zespołu nie jest dostępny publicznie.', 'Wróć do zespołu', '/team');
            return;
        }

        $this->theme->start_page($member->publicName . ' - Zespół SyntaxDevTeam', $this->shortBio($member->bio));
        $this->theme->start_header($member->publicName, $member->roleLabel, 'SyntaxDevTeam / Profil');
        $this->theme->end_header();
        $this->theme->start_section();
        $this->theme->start_grid();
        $this->theme->start_column('md-4');
        $this->theme->start_card($member->publicName, 'Profil');
        $this->renderPublicAvatar($member);
        $this->theme->render_table([
            'Pole',
            'Wartość',
        ], [
            ['Rola', $member->roleLabel],
            ['Profil systemowy', $member->displayName],
            ['Kontakt', $member->profileUrl !== '' ? $member->profileUrl : 'Przez kanały zespołu'],
        ]);
        $this->theme->end_card();
        $this->theme->end_column();
        $this->theme->start_column('md-8');
        $this->theme->start_card('O członku zespołu', 'Opis publiczny');
        $this->theme->render_text($member->bio);
        if ($member->profileUrl !== '') {
            $this->theme->render_button('Otwórz link profilu', $member->profileUrl, 'primary');
        }
        $this->theme->end_card();
        $this->theme->end_column();
        $this->theme->end_grid();
        $this->theme->end_section();
        $this->theme->end_page();
    }

    private function renderAdminList(string $message = '', string $variant = 'info'): void
    {
        $this->startAdminPage(
            'Zespół',
            'Zarządzaj publiczną listą członków drużyny i profilami widocznymi na stronie.',
            [[
                'label' => 'Dodaj członka',
                'href' => 'index.php?route=/admin/team/create',
                'variant' => 'primary',
            ], [
                'label' => 'Publiczna lista',
                'href' => '/team',
                'variant' => 'outline-light',
            ]]
        );
        if ($message !== '') {
            $this->theme->render_alert($message, $variant);
        }

        $members = $this->team->all();
        $this->theme->start_admin_panel('Członkowie zespołu', count($members) . ' profili');
        if ($members === []) {
            $this->theme->render_alert('Nie dodano jeszcze żadnego publicznego profilu zespołu.', 'info');
        } else {
            $this->theme->render_admin_action_table(
                ['Kolejność', 'Nazwa', 'Użytkownik', 'Rola', 'Widoczność'],
                array_map(
                    static fn (TeamMember $member): array => [
                        'cells' => [
                            $member->sortOrder,
                            $member->publicName,
                            $member->displayName,
                            $member->roleLabel,
                            $member->visible ? 'Widoczny' : 'Ukryty',
                        ],
                        'actions' => [[
                            'label' => 'Profil',
                            'href' => '/team/member/' . rawurlencode($member->slug),
                            'variant' => 'outline-light',
                        ], [
                            'label' => 'Edytuj',
                            'href' => 'index.php?route=/admin/team/edit&id=' . $member->id,
                            'variant' => 'primary',
                        ], [
                            'label' => 'Usuń',
                            'action' => 'index.php?route=/admin/team/delete',
                            'variant' => 'danger',
                            'fields' => ['id' => $member->id],
                            'confirm' => 'Usunąć profil z publicznej listy zespołu?',
                        ]],
                    ],
                    $members
                ),
                $this->security->csrfToken()
            );
        }
        $this->theme->end_admin_panel();
        $this->endAdminPage();
    }

    private function renderForm(?TeamMember $member = null, string $message = '', string $variant = 'info'): void
    {
        $options = $this->team->activeUserOptions($member?->userId);
        $this->startAdminPage(
            $member === null ? 'Dodaj członka zespołu' : 'Edytuj członka zespołu',
            'Profil publiczny korzysta z konta użytkownika, ale opis i rola należą do modułu Team.',
            [[
                'label' => 'Wróć do listy',
                'href' => 'index.php?route=/admin/team',
                'variant' => 'outline-light',
            ]]
        );
        if ($message !== '') {
            $this->theme->render_alert($message, $variant);
        }
        if ($options === []) {
            $this->theme->render_alert('Brak aktywnych użytkowników dostępnych do dodania.', 'warning');
            $this->endAdminPage();
            return;
        }

        $this->theme->start_admin_panel('Profil publiczny', 'Dane wyświetlane na /team');
        $this->theme->render_form(
            'index.php?route=' . ($member === null ? '/admin/team/create' : '/admin/team/edit'),
            array_merge(
                $member !== null ? [[
                    'name' => 'id',
                    'label' => 'ID',
                    'type' => 'hidden',
                    'value' => (string) $member->id,
                ]] : [],
                [[
                    'name' => 'user_id',
                    'label' => 'Użytkownik',
                    'type' => 'select',
                    'value' => (string) ($member?->userId ?? array_key_first($options)),
                    'options' => $this->stringOptions($options),
                ], [
                    'name' => 'public_name',
                    'label' => 'Nazwa publiczna',
                    'value' => $member?->publicName ?? '',
                ], [
                    'name' => 'slug',
                    'label' => 'Slug profilu',
                    'value' => $member?->slug ?? '',
                    'help' => 'Adres publiczny: /team/member/slug. Puste pole wygeneruje slug z nazwy.',
                ], [
                    'name' => 'role_label',
                    'label' => 'Rola w zespole',
                    'value' => $member?->roleLabel ?? '',
                ], [
                    'name' => 'bio',
                    'label' => 'Opis publiczny',
                    'type' => 'textarea',
                    'value' => $member?->bio ?? '',
                    'rows' => 8,
                ], [
                    'name' => 'profile_url',
                    'label' => 'Link profilu lub kontaktu',
                    'type' => 'url',
                    'value' => $member?->profileUrl ?? '',
                ], [
                    'name' => 'sort_order',
                    'label' => 'Kolejność',
                    'type' => 'number',
                    'value' => (string) ($member?->sortOrder ?? 100),
                ], [
                    'name' => 'is_visible',
                    'label' => 'Widoczny publicznie',
                    'type' => 'checkbox',
                    'checked' => $member?->visible ?? true,
                ]]
            ),
            $member === null ? 'Dodaj do zespołu' : 'Zapisz profil',
            $this->security->csrfToken()
        );
        $this->theme->end_admin_panel();
        $this->endAdminPage();
    }

    private function renderEdit(Request $request): void
    {
        $member = $this->team->find($request->queryInt('id', 0) ?? 0);
        if (!$member instanceof TeamMember) {
            $this->renderAdminList('Nie znaleziono profilu zespołu.', 'danger');
            return;
        }

        $this->renderForm($member);
    }

    private function create(Request $request): void
    {
        $this->save($request);
    }

    private function update(Request $request): void
    {
        $member = $this->team->find($request->postInt('id', 0) ?? 0);
        if (!$member instanceof TeamMember) {
            $this->renderAdminList('Nie znaleziono profilu zespołu.', 'danger');
            return;
        }

        $this->save($request, $member);
    }

    private function save(Request $request, ?TeamMember $member = null): void
    {
        $actor = $this->auth->user();
        if (!$this->security->validateCsrfToken($request->postString('_token'))) {
            $this->audit->record($request, 'team_save', 'invalid_csrf', 'team', $actor?->id);
            $this->renderForm($member, 'Token CSRF jest nieprawidłowy lub wygasł.', 'danger');
            return;
        }

        $userId = $request->postInt('user_id', 0) ?? 0;
        $publicName = $this->bounded($request->postString('public_name'), 160);
        $roleLabel = $this->bounded($request->postString('role_label'), 160);
        $bio = $this->bounded($request->postString('bio'), 4000);
        $profileUrl = $this->bounded($request->postString('profile_url'), 500);
        $sortOrder = max(0, $request->postInt('sort_order', 100) ?? 100);
        $slug = $this->slugify($request->postString('slug') !== '' ? $request->postString('slug') : $publicName);
        if ($userId <= 0 || $publicName === '' || $roleLabel === '' || $bio === '' || $slug === '') {
            $this->renderForm($member, 'Uzupełnij użytkownika, nazwę, slug, rolę i opis.', 'warning');
            return;
        }
        if ($profileUrl !== '' && filter_var($profileUrl, FILTER_VALIDATE_URL) === false) {
            $this->renderForm($member, 'Link profilu musi być poprawnym adresem URL albo pozostać pusty.', 'warning');
            return;
        }
        if ($this->team->slugExists($slug, $member?->id)) {
            $this->renderForm($member, 'Ten slug profilu jest już używany.', 'warning');
            return;
        }

        try {
            if ($member === null) {
                $id = $this->team->create($userId, $slug, $publicName, $roleLabel, $bio, $profileUrl, $sortOrder, $request->postBool('is_visible'));
                $this->audit->record($request, 'team_create', 'success', 'member:' . $id, $actor?->id);
                $this->renderAdminList('Profil członka zespołu został dodany.', 'success');
                return;
            }

            $this->team->update($member->id, $userId, $slug, $publicName, $roleLabel, $bio, $profileUrl, $sortOrder, $request->postBool('is_visible'));
            $this->audit->record($request, 'team_update', 'success', 'member:' . $member->id, $actor?->id);
            $this->renderAdminList('Profil członka zespołu został zapisany.', 'success');
        } catch (\Throwable $exception) {
            $this->audit->record($request, 'team_save', 'failed', 'team', $actor?->id);
            $this->renderForm($member, 'Nie udało się zapisać profilu: ' . $exception->getMessage(), 'danger');
        }
    }

    private function delete(Request $request): void
    {
        $actor = $this->auth->user();
        if (!$this->security->validateCsrfToken($request->postString('_token'))) {
            $this->audit->record($request, 'team_delete', 'invalid_csrf', 'team', $actor?->id);
            $this->renderAdminList('Token CSRF jest nieprawidłowy lub wygasł.', 'danger');
            return;
        }

        $id = $request->postInt('id', 0) ?? 0;
        if (!$this->team->delete($id)) {
            $this->audit->record($request, 'team_delete', 'failed', 'member:' . $id, $actor?->id);
            $this->renderAdminList('Nie udało się usunąć profilu zespołu.', 'danger');
            return;
        }

        $this->audit->record($request, 'team_delete', 'success', 'member:' . $id, $actor?->id);
        $this->renderAdminList('Profil został usunięty z listy zespołu.', 'success');
    }

    private function renderPublicAvatar(TeamMember $member): void
    {
        $this->theme->render_avatar($member->publicName, $member->avatarUrl, 'lg');
    }

    private function shortBio(string $bio): string
    {
        $bio = trim(preg_replace('/\s+/', ' ', $bio) ?? $bio);
        if (function_exists('mb_strlen') && mb_strlen($bio) > 180) {
            return mb_substr($bio, 0, 177) . '...';
        }
        if (!function_exists('mb_strlen') && strlen($bio) > 180) {
            return substr($bio, 0, 177) . '...';
        }

        return $bio;
    }

    private function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        $value = trim($value, '-');

        return substr($value, 0, 191);
    }

    private function bounded(string $value, int $max): string
    {
        $value = trim($value);
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $max);
        }

        return substr($value, 0, $max);
    }

    /**
     * @param array<int, string> $options
     * @return array<string, string>
     */
    private function stringOptions(array $options): array
    {
        $result = [];
        foreach ($options as $value => $label) {
            $result[(string) $value] = $label;
        }

        return $result;
    }

    /**
     * @param callable(): void $handler
     */
    private function guard(Request $request, string $permission, callable $handler): void
    {
        $user = $this->auth->user();
        if (!$user instanceof User) {
            $this->theme->render_admin_access_state(401, 'Wymagane logowanie', 'Zarządzanie zespołem wymaga aktywnej sesji.', 'index.php?route=/admin/login', 'Przejdź do logowania');
            return;
        }
        if (!$this->hasPermission($user->permissions, $permission)) {
            $this->audit->record($request, 'admin_access', 'denied', $permission, $user->id);
            $this->theme->render_admin_access_state(403, 'Brak uprawnień', 'Twoje konto nie ma uprawnienia: ' . $permission, 'index.php?route=/admin', 'Wróć do panelu');
            return;
        }

        $handler();
    }

    /**
     * @param list<string> $permissions
     */
    private function hasPermission(array $permissions, string $permission): bool
    {
        return in_array('*', $permissions, true) || in_array($permission, $permissions, true);
    }

    /**
     * @param array{label: string, href: string, variant?: string}|list<array{label: string, href: string, variant?: string}>|null $actions
     */
    private function startAdminPage(string $title, string $lead, ?array $actions = null): void
    {
        $user = $this->auth->user();
        $this->theme->start_admin_page($title, $this->menu->items($user?->permissions ?? []), '/admin/team', [
            'name' => $user?->displayName ?? 'Gość',
            'role' => $user?->primaryRole() ?? 'Gość',
            'initials' => $user?->initials() ?? 'G',
            'avatar_url' => $user?->avatarUrl ?? '',
            'logout_action' => 'index.php?route=/admin/logout',
            'logout_token' => $this->security->csrfToken(),
            'profile_links' => [
                ['label' => 'Pokaż profil', 'href' => 'index.php?route=/admin/profile'],
                ['label' => 'Edytuj dane', 'href' => 'index.php?route=/admin/profile/edit'],
                ['label' => 'Połączone konta', 'href' => 'index.php?route=/admin/profile/identities'],
                ['label' => 'Ustawienia avatara', 'href' => 'index.php?route=/admin/profile/avatar'],
                ['label' => 'Bezpieczeństwo', 'href' => 'index.php?route=/admin/profile/security'],
            ],
        ]);
        $this->theme->start_admin_content(
            $title,
            $lead,
            [['label' => 'Panel', 'href' => 'index.php?route=/admin'], ['label' => 'Zespół', 'href' => 'index.php?route=/admin/team']],
            $actions
        );
    }

    private function endAdminPage(): void
    {
        $this->theme->end_admin_content();
        $this->theme->end_admin_page();
    }
}
