<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\Team;

use SyntaxDevTeam\Cms\Core\AdminMenuRegistry;
use SyntaxDevTeam\Cms\Core\AdminSearchProviderInterface;
use SyntaxDevTeam\Cms\Core\AdminSearchRegistry;
use SyntaxDevTeam\Cms\Core\DashboardProviderInterface;
use SyntaxDevTeam\Cms\Core\DashboardRegistry;
use SyntaxDevTeam\Cms\Core\ModuleInterface;
use SyntaxDevTeam\Cms\Core\PublicNavigationProviderInterface;
use SyntaxDevTeam\Cms\Core\PublicNavigationRegistry;
use SyntaxDevTeam\Cms\Core\Request;
use SyntaxDevTeam\Cms\Core\Router;
use SyntaxDevTeam\Cms\Core\Security;
use SyntaxDevTeam\Cms\Core\SeoIndex;
use SyntaxDevTeam\Cms\Core\SeoProviderInterface;
use SyntaxDevTeam\Cms\Core\ThemeInterface;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AdminAccessGate;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuditLogService;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuthService;
use SyntaxDevTeam\Cms\Modules\CoreAuth\User;

final class TeamModule implements ModuleInterface, PublicNavigationProviderInterface, AdminSearchProviderInterface, DashboardProviderInterface, SeoProviderInterface
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
        return '1.2.0';
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
        $navigation->add('team.index', 'Team', '/team', 'none', 65);
    }

    public function registerAdminSearch(AdminSearchRegistry $search): void
    {
        $search->add(
            'team.create',
            'Dodaj członka zespołu',
            'Utwórz publiczny profil użytkownika w module Zespół.',
            'index.php?route=/admin/team/create',
            ['team', 'zespół', 'profil', 'użytkownik', 'członek', 'dodaj'],
            'team.manage',
            'Treść',
            41,
        );
    }

    public function registerDashboard(DashboardRegistry $dashboard): void
    {
        $dashboard->addMetric(
            'team.members',
            'Członkowie zespołu',
            'Liczba profili zgłoszona przez moduł Team.',
            'TM',
            function (): array {
                $stats = $this->team->dashboardStats();
                return ['value' => $stats['visible'], 'detail' => $stats['all'] . ' wszystkich profili'];
            },
            'team.manage',
            120,
        );
    }

    public function registerRoutes(Router $router): void
    {
        $router->get('/team', fn () => $this->renderPublicList());
        $router->get('/team/member', fn (Request $request) => $this->renderPublicProfile($request->queryString('slug')));
        $router->get('/team/member/{slug}', fn (Request $request) => $this->renderPublicProfile($request->routeString('slug')));

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

    public function registerSeo(SeoIndex $seo): void
    {
        unset($seo);
    }

    private function renderPublicList(): void
    {
        $members = $this->team->visible();
        $this->theme->start_page('Team - SyntaxDevTeam', 'Meet the SyntaxDevTeam members.', false);
        $this->theme->start_header('Team', 'The people behind the projects, modules and community support.', 'SyntaxDevTeam / Team');
        $this->theme->end_header();
        $this->theme->start_section();
        if ($members === []) {
            $this->theme->render_alert('The public team list is empty for now.', 'info');
        } else {
            $this->theme->start_grid();
            foreach ($members as $member) {
                $this->theme->start_column('lg-4');
                $this->theme->start_card($member->publicName, $member->roleLabel);
                $this->renderPublicAvatar($member);
                $this->theme->render_text($this->shortBio($member->bio));
                $this->theme->render_button('View profile', '/team/member/' . rawurlencode($member->slug), 'primary');
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
            $this->theme->render_public_error(404, 'Profile not found', 'This team profile is not publicly available.', 'Back to team', '/team');
            return;
        }

        $this->theme->start_page($member->publicName . ' - SyntaxDevTeam Team', $this->shortBio($member->bio), false);
        $this->theme->start_section();
        $this->theme->render_team_member_profile($this->publicProfileData($member));
        $this->theme->render_structured_data([
            '@type' => 'ProfilePage',
            'dateModified' => $member->updatedAt,
            'mainEntity' => [
                '@type' => 'Person',
                'name' => $member->publicName,
                'jobTitle' => $member->roleLabel,
                'description' => $this->shortBio($member->bio),
            ],
        ]);
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
        try {
            $options = $this->team->activeUserOptions($member?->userId);
        } catch (\Throwable $exception) {
            $options = [];
            $message = 'Nie można pobrać aktywnych użytkowników. Spróbuj ponownie lub sprawdź stan bazy danych.';
            $variant = 'danger';
        }
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
            if ($message === '') {
                $this->theme->render_alert('Brak aktywnych użytkowników dostępnych do dodania.', 'warning');
            }
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
                    'name' => 'headline',
                    'label' => 'Nagłówek profilu',
                    'value' => $member?->headline ?? '',
                    'help' => 'Krótki opis widoczny w hero profilu. Puste pole użyje roli.',
                ], [
                    'name' => 'bio',
                    'label' => 'Opis publiczny',
                    'type' => 'textarea',
                    'value' => $member?->bio ?? '',
                    'rows' => 8,
                ], [
                    'name' => 'focus_tags',
                    'label' => 'Fokus / tagi',
                    'type' => 'textarea',
                    'value' => $member?->focusTags ?? '',
                    'rows' => 3,
                    'help' => 'Jeden tag w linii albo lista rozdzielona przecinkami.',
                ], [
                    'name' => 'highlights',
                    'label' => 'How I work',
                    'type' => 'textarea',
                    'value' => $member?->highlights ?? '',
                    'rows' => 4,
                    'help' => 'Jeden punkt sposobu pracy w linii, np. Projektuję boty event-driven albo Integruję API i panele administracyjne.',
                ], [
                    'name' => 'skills',
                    'label' => 'Umiejętności',
                    'type' => 'textarea',
                    'value' => $member?->skills ?? '',
                    'rows' => 5,
                    'help' => 'Format linii: Nazwa | Poziom | Notatka.',
                ], [
                    'name' => 'featured_projects',
                    'label' => 'Projekty / portfolio',
                    'type' => 'textarea',
                    'value' => $member?->featuredProjects ?? '',
                    'rows' => 5,
                    'help' => 'Format linii: Projekt | Rola | Notatka.',
                ], [
                    'name' => 'profile_url',
                    'label' => 'Link profilu lub kontaktu',
                    'type' => 'url',
                    'value' => $member?->profileUrl ?? '',
                ], [
                    'name' => 'contact_email',
                    'label' => 'Email kontaktowy',
                    'type' => 'email',
                    'value' => $member?->contactEmail ?? '',
                ], [
                    'name' => 'contact_discord',
                    'label' => 'Kontakt Discord',
                    'value' => $member?->contactDiscord ?? '',
                ], [
                    'name' => 'primary_cta_label',
                    'label' => 'Główny przycisk - etykieta',
                    'value' => $member?->primaryCtaLabel ?? '',
                ], [
                    'name' => 'primary_cta_url',
                    'label' => 'Główny przycisk - URL',
                    'type' => 'url',
                    'value' => $member?->primaryCtaUrl ?? '',
                ], [
                    'name' => 'secondary_cta_label',
                    'label' => 'Drugi przycisk - etykieta',
                    'value' => $member?->secondaryCtaLabel ?? '',
                ], [
                    'name' => 'secondary_cta_url',
                    'label' => 'Drugi przycisk - URL',
                    'type' => 'url',
                    'value' => $member?->secondaryCtaUrl ?? '',
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
        $headline = $this->bounded($request->postString('headline'), 220);
        $bio = $this->bounded($request->postString('bio'), 4000);
        $focusTags = $this->boundedMultiline($request->postString('focus_tags'), 1000);
        $highlights = $this->boundedMultiline($request->postString('highlights'), 2000);
        $skills = $this->boundedMultiline($request->postString('skills'), 3000);
        $featuredProjects = $this->boundedMultiline($request->postString('featured_projects'), 3000);
        $profileUrl = $this->bounded($request->postString('profile_url'), 500);
        $contactEmail = $this->bounded($request->postString('contact_email'), 190);
        $contactDiscord = $this->bounded($request->postString('contact_discord'), 120);
        $primaryCtaLabel = $this->bounded($request->postString('primary_cta_label'), 80);
        $primaryCtaUrl = $this->bounded($request->postString('primary_cta_url'), 500);
        $secondaryCtaLabel = $this->bounded($request->postString('secondary_cta_label'), 80);
        $secondaryCtaUrl = $this->bounded($request->postString('secondary_cta_url'), 500);
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
        if ($contactEmail !== '' && filter_var($contactEmail, FILTER_VALIDATE_EMAIL) === false) {
            $this->renderForm($member, 'Email kontaktowy musi być poprawny albo pozostać pusty.', 'warning');
            return;
        }
        foreach ([[$primaryCtaLabel, $primaryCtaUrl], [$secondaryCtaLabel, $secondaryCtaUrl]] as [$label, $url]) {
            if (($label === '') !== ($url === '')) {
                $this->renderForm($member, 'Przyciski CTA wymagają jednocześnie etykiety i adresu URL.', 'warning');
                return;
            }
            if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL) === false) {
                $this->renderForm($member, 'Adres URL przycisku CTA musi być poprawny.', 'warning');
                return;
            }
        }
        if ($this->team->slugExists($slug, $member?->id)) {
            $this->renderForm($member, 'Ten slug profilu jest już używany.', 'warning');
            return;
        }

        try {
            if ($member === null) {
                $id = $this->team->create(
                    $userId,
                    $slug,
                    $publicName,
                    $roleLabel,
                    $headline,
                    $bio,
                    $focusTags,
                    $highlights,
                    $skills,
                    $featuredProjects,
                    $profileUrl,
                    $contactEmail,
                    $contactDiscord,
                    $primaryCtaLabel,
                    $primaryCtaUrl,
                    $secondaryCtaLabel,
                    $secondaryCtaUrl,
                    $sortOrder,
                    $request->postBool('is_visible')
                );
                $this->audit->record($request, 'team_create', 'success', 'member:' . $id, $actor?->id);
                $this->renderAdminList('Profil członka zespołu został dodany.', 'success');
                return;
            }

            $this->team->update(
                $member->id,
                $userId,
                $slug,
                $publicName,
                $roleLabel,
                $headline,
                $bio,
                $focusTags,
                $highlights,
                $skills,
                $featuredProjects,
                $profileUrl,
                $contactEmail,
                $contactDiscord,
                $primaryCtaLabel,
                $primaryCtaUrl,
                $secondaryCtaLabel,
                $secondaryCtaUrl,
                $sortOrder,
                $request->postBool('is_visible')
            );
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

    /**
    /**
     * @return list<array{label:string,href:string,variant:string}>
     */
    private function ctaActions(TeamMember $member): array
    {
        $actions = [];
        if ($member->primaryCtaLabel !== '' && $member->primaryCtaUrl !== '' && !$this->isPlaceholder($member->primaryCtaLabel)) {
            $actions[] = ['label' => $member->primaryCtaLabel, 'href' => $member->primaryCtaUrl, 'variant' => 'primary'];
        }
        if ($member->secondaryCtaLabel !== '' && $member->secondaryCtaUrl !== '' && !$this->isPlaceholder($member->secondaryCtaLabel)) {
            $actions[] = ['label' => $member->secondaryCtaLabel, 'href' => $member->secondaryCtaUrl, 'variant' => 'secondary'];
        }
        if ($actions === [] && $member->profileUrl !== '' && !str_contains($member->profileUrl, '/team/member/')) {
            $actions[] = ['label' => 'Open profile link', 'href' => $member->profileUrl, 'variant' => 'primary'];
        }

        return $actions;
    }

    /**
     * @return array<string, mixed>
     */
    private function publicProfileData(TeamMember $member): array
    {
        $tags = $this->listItems($member->focusTags);
        $work = $this->lineItems($member->highlights);

        return [
            'name' => $member->publicName,
            'role' => $member->roleLabel,
            'headline' => $member->bio,
            'bio' => $member->bio,
            'avatar_url' => $member->avatarUrl ?? '',
            'tags' => $tags,
            'work' => $work,
            'skills' => $this->skillItems($member->skills),
            'projects' => $this->projectItems($member->featuredProjects),
            'actions' => $this->ctaActions($member),
            'contact_email' => $member->contactEmail,
            'contact_discord' => $member->contactDiscord,
        ];
    }

    /**
     * @return list<string>
     */
    private function listItems(string $value): array
    {
        $items = preg_split('/[\r\n,]+/', $value) ?: [];
        $result = [];
        foreach ($items as $item) {
            $item = trim($item);
            if ($item !== '' && !$this->isPlaceholder($item)) {
                $result[] = $item;
            }
        }

        return $result;
    }

    /**
     * @return list<list<string>>
     */
    private function listRows(string $value, string $fallback): array
    {
        $rows = [];
        foreach ($this->listItems($value) as $item) {
            $rows[] = [$item !== '' ? $item : $fallback];
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    private function lineItems(string $value): array
    {
        $items = preg_split('/\r\n|\r|\n/', $value) ?: [];
        $result = [];
        foreach ($items as $item) {
            $item = trim($item);
            if ($item !== '' && !$this->isPlaceholder($item)) {
                $result[] = $item;
            }
        }

        return $result;
    }

    /**
     * @param list<string> $headers
     * @return list<list<string>>
     */
    private function pipeRows(string $value, array $headers, string $fallback): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $value) ?: [];
        $rows = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $cells = array_map('trim', explode('|', $line));
            if ($cells === []) {
                $cells = [$fallback];
            }
            while (count($cells) < count($headers)) {
                $cells[] = '';
            }
            $rows[] = array_slice($cells, 0, count($headers));
        }

        return $rows;
    }

    /**
     * @return list<array{name:string,level:string,percent:int}>
     */
    private function skillItems(string $value): array
    {
        $items = [];
        foreach ($this->pipeRows($value, ['Skill', 'Level', 'Percent'], 'Skill') as $row) {
            $name = trim($row[0] ?? '');
            if ($name === '' || $this->isPlaceholder($name)) {
                continue;
            }
            $level = trim($row[1] ?? '');
            $percentValue = trim($row[2] ?? '');
            if ($percentValue === '' && preg_match('/^(\d{1,3})\s*%?$/u', $level, $match) === 1) {
                $percentValue = $match[1];
                $level = $match[1] . '%';
            }
            if ($percentValue !== '' && preg_match('/^(\d{1,3})\s*%?$/u', $percentValue, $match) === 1) {
                $percent = (int) $match[1];
            } else {
                $percent = match (strtolower($level)) {
                'expert' => 94,
                'advanced' => 82,
                'senior' => 88,
                'solid' => 76,
                'junior' => 45,
                default => 70,
                };
            }
            $items[] = ['name' => $name, 'level' => $level, 'percent' => max(5, min(100, $percent))];
        }

        return $items;
    }

    /**
     * @return list<array{title:string,description:string}>
     */
    private function projectItems(string $value): array
    {
        $items = [];
        foreach ($this->pipeRows($value, ['Project', 'Role', 'Notes'], 'Project') as $row) {
            $title = trim($row[0] ?? '');
            if ($title === '' || $this->isPlaceholder($title)) {
                continue;
            }
            $parts = array_filter([trim($row[1] ?? ''), trim($row[2] ?? '')], static fn (string $part): bool => $part !== '');
            $items[] = ['title' => $title, 'description' => implode(' - ', $parts)];
        }

        return $items;
    }

    private function isPlaceholder(string $value): bool
    {
        return in_array(strtolower(trim($value)), [
            'wyróżniki',
            'highlight',
            'highlights',
            'skill',
            'project',
            'główny przycisk',
            'glowny przycisk',
            'drugi przycisk',
            'primary button',
            'secondary button',
        ], true);
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

    private function boundedMultiline(string $value, int $max): string
    {
        $value = trim(str_replace(["\r\n", "\r"], "\n", $value));
        $value = preg_replace("/\n{3,}/", "\n\n", $value) ?? $value;
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
        $this->theme->start_admin_page($title, $this->menu->visibleFor($user?->permissions ?? []), '/admin/team', [
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
