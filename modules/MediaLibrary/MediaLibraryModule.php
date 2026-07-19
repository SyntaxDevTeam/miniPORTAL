<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\MediaLibrary;

use SyntaxDevTeam\Cms\Core\AdminMenuRegistry;
use SyntaxDevTeam\Cms\Core\ModuleInterface;
use SyntaxDevTeam\Cms\Core\Request;
use SyntaxDevTeam\Cms\Core\Router;
use SyntaxDevTeam\Cms\Core\Security;
use SyntaxDevTeam\Cms\Core\ThemeInterface;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuditLogService;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuthService;
use SyntaxDevTeam\Cms\Modules\CoreAuth\User;

final class MediaLibraryModule implements ModuleInterface
{
    public function __construct(
        private readonly ThemeInterface $theme,
        private readonly AdminMenuRegistry $menu,
        private readonly MediaAssetRepository $assets,
        private readonly MediaAssetStorage $storage,
        private readonly AuthService $auth,
        private readonly Security $security,
        private readonly AuditLogService $audit,
    ) {
    }

    public function id(): string { return 'media_library'; }
    public function version(): string { return '1.1.0'; }
    public function dependencies(): array { return ['core_auth']; }
    public function isProtected(): bool { return false; }
    public function requiredPermissions(): array { return ['media.view', 'media.manage']; }

    public function registerAdminMenu(AdminMenuRegistry $menu): void
    {
        $menu->add('Treść', 'Biblioteka grafik', '/admin/media', 'MG', 'media.view', 39);
    }

    public function registerRoutes(Router $router): void
    {
        $router->get('/admin/media', fn (Request $request) => $this->guard($request, 'media.view', fn () => $this->renderList($request)));
        $router->post('/admin/media/upload', fn (Request $request) => $this->guard($request, 'media.manage', fn () => $this->upload($request)));
        $router->post('/admin/media/richtext-upload', fn (Request $request) => $this->uploadForRichText($request));
        $router->get('/admin/media/edit', fn (Request $request) => $this->guard($request, 'media.manage', fn () => $this->renderEdit($request)));
        $router->post('/admin/media/edit', fn (Request $request) => $this->guard($request, 'media.manage', fn () => $this->update($request)));
        $router->post('/admin/media/delete', fn (Request $request) => $this->guard($request, 'media.manage', fn () => $this->delete($request)));
    }

    private function renderList(Request $request, string $message = '', string $variant = 'info'): void
    {
        $category = $request->queryString('category');
        $assets = $this->assets->all($category);
        $this->startAdminPage('Biblioteka grafik', 'Stałe obrazy niezależne od aktywnego motywu: ikony, logo, zrzuty i grafiki prezentacyjne.');
        if ($message !== '') {
            $this->theme->render_alert($message, $variant);
        }

        $this->theme->start_admin_panel('Dodaj grafikę', 'PNG, JPG, WEBP albo GIF do 5 MB');
        $this->theme->render_form('index.php?route=/admin/media/upload', [
            ['name' => 'asset', 'label' => 'Plik grafiki', 'type' => 'file', 'accept' => 'image/png,image/jpeg,image/webp,image/gif', 'required' => true],
            ['name' => 'title', 'label' => 'Tytuł', 'value' => '', 'maxlength' => 180],
            ['name' => 'category', 'label' => 'Kategoria', 'type' => 'select', 'value' => 'other', 'options' => MediaAssetRepository::CATEGORIES],
            ['name' => 'alt_text', 'label' => 'Tekst alternatywny', 'value' => '', 'maxlength' => 255],
        ], 'Dodaj grafikę', $this->security->csrfToken());
        $this->theme->end_admin_panel();

        $this->theme->start_admin_panel('Pliki graficzne', count($assets) . ' pozycji');
        if ($assets === []) {
            $this->theme->render_alert('Brak grafik w bibliotece dla wybranego filtra.', 'info');
        } else {
            $this->theme->render_admin_action_table(['Tytuł', 'Kategoria', 'Adres', 'Rozmiar', 'Wymiary'], array_map(
                fn (MediaAsset $asset): array => [
                    'cells' => [
                        $asset->title,
                        MediaAssetRepository::CATEGORIES[$asset->category] ?? $asset->category,
                        $asset->publicPath,
                        $this->formatBytes($asset->fileSize),
                        $asset->width !== null && $asset->height !== null ? $asset->width . ' x ' . $asset->height : 'Brak danych',
                    ],
                    'actions' => [
                        ['label' => 'Otwórz', 'href' => $asset->publicPath, 'variant' => 'outline-light'],
                        ['label' => 'Edytuj', 'href' => 'index.php?route=/admin/media/edit&id=' . $asset->id, 'variant' => 'primary'],
                        ['label' => 'Usuń', 'action' => 'index.php?route=/admin/media/delete', 'fields' => ['id' => $asset->id], 'variant' => 'danger', 'confirm' => 'Usunąć grafikę z biblioteki i dysku?'],
                    ],
                ],
                $assets
            ), $this->security->csrfToken());
        }
        $this->theme->end_admin_panel();
        $this->endAdminPage();
    }

    private function upload(Request $request): void
    {
        $actor = $this->auth->user();
        if (!$this->security->validateCsrfToken($request->postString('_token'))) {
            $this->audit->record($request, 'media_upload', 'invalid_csrf', 'media', $actor?->id);
            $this->renderList($request, 'Token CSRF jest nieprawidłowy lub wygasł.', 'danger');
            return;
        }
        $file = $request->file('asset');
        if ($file === null) {
            $this->renderList($request, 'Wybierz plik grafiki.', 'warning');
            return;
        }
        try {
            $stored = $this->storage->store($file);
            $id = $this->assets->create($stored + [
                'title' => $request->postString('title'),
                'category' => $request->postString('category'),
                'alt_text' => $request->postString('alt_text'),
                'created_by' => $actor?->id,
            ]);
            $this->audit->record($request, 'media_upload', 'success', 'media:' . $id, $actor?->id);
            $this->renderList($request, 'Grafika została dodana do biblioteki.', 'success');
        } catch (\Throwable $exception) {
            $this->audit->record($request, 'media_upload', 'failed', 'media', $actor?->id);
            $this->renderList($request, 'Nie udało się dodać grafiki: ' . $exception->getMessage(), 'danger');
        }
    }

    private function uploadForRichText(Request $request): void
    {
        $actor = $this->auth->user();
        if (!$actor instanceof User) {
            $this->json(['ok' => false, 'message' => 'Wymagane logowanie.'], 401);
            return;
        }
        if (!in_array('*', $actor->permissions, true) && !in_array('media.manage', $actor->permissions, true)) {
            $this->audit->record($request, 'media_richtext_upload', 'denied', 'media', $actor->id);
            $this->json(['ok' => false, 'message' => 'Brak uprawnień do dodawania grafik.'], 403);
            return;
        }
        if (!$this->security->validateCsrfToken($request->postString('_token'))) {
            $this->audit->record($request, 'media_richtext_upload', 'invalid_csrf', 'media', $actor->id);
            $this->json(['ok' => false, 'message' => 'Token CSRF jest nieprawidłowy lub wygasł.'], 419);
            return;
        }

        $file = $request->file('asset');
        if ($file === null) {
            $this->json(['ok' => false, 'message' => 'Wybierz plik grafiki.'], 422);
            return;
        }

        try {
            $stored = $this->storage->store($file);
            $id = $this->assets->create($stored + [
                'title' => $request->postString('title'),
                'category' => 'content',
                'alt_text' => $request->postString('alt_text'),
                'created_by' => $actor->id,
            ]);
            $asset = $this->assets->find($id);
            $this->audit->record($request, 'media_richtext_upload', 'success', 'media:' . $id, $actor->id);
            $this->json([
                'ok' => true,
                'url' => $asset?->publicPath ?? $stored['public_path'],
                'alt' => $asset?->altText ?? $request->postString('alt_text'),
            ]);
        } catch (\Throwable $exception) {
            $this->audit->record($request, 'media_richtext_upload', 'failed', 'media', $actor->id);
            $this->json(['ok' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    private function renderEdit(Request $request, string $message = '', string $variant = 'info'): void
    {
        $asset = $this->assets->find($request->queryInt('id', 0) ?? 0);
        if (!$asset instanceof MediaAsset) {
            $this->renderList($request, 'Nie znaleziono grafiki.', 'danger');
            return;
        }
        $this->startAdminPage('Edytuj grafikę', 'Aktualizacja metadanych pliku w bibliotece.');
        if ($message !== '') {
            $this->theme->render_alert($message, $variant);
        }
        $this->theme->render_detail_card($asset->title, 'Podgląd', [
            ['label' => 'Adres publiczny', 'value' => $asset->publicPath],
            ['label' => 'MIME', 'value' => $asset->mimeType],
            ['label' => 'Rozmiar', 'value' => $this->formatBytes($asset->fileSize)],
        ], [], [], [
            ['label' => 'Otwórz grafikę', 'href' => $asset->publicPath, 'variant' => 'outline-light'],
        ]);
        $this->theme->start_admin_panel('Metadane', 'Opis i kategoria');
        $this->theme->render_form('index.php?route=/admin/media/edit', [
            ['name' => 'id', 'label' => 'ID', 'type' => 'hidden', 'value' => (string) $asset->id],
            ['name' => 'title', 'label' => 'Tytuł', 'value' => $asset->title, 'required' => true, 'maxlength' => 180],
            ['name' => 'category', 'label' => 'Kategoria', 'type' => 'select', 'value' => $asset->category, 'options' => MediaAssetRepository::CATEGORIES],
            ['name' => 'alt_text', 'label' => 'Tekst alternatywny', 'value' => $asset->altText, 'maxlength' => 255],
        ], 'Zapisz grafikę', $this->security->csrfToken());
        $this->theme->end_admin_panel();
        $this->endAdminPage();
    }

    private function update(Request $request): void
    {
        $actor = $this->auth->user();
        if (!$this->security->validateCsrfToken($request->postString('_token'))) {
            $this->audit->record($request, 'media_update', 'invalid_csrf', 'media', $actor?->id);
            $this->renderEdit($request, 'Token CSRF jest nieprawidłowy lub wygasł.', 'danger');
            return;
        }
        $id = $request->postInt('id', 0) ?? 0;
        $ok = $this->assets->update($id, [
            'title' => $request->postString('title'),
            'category' => $request->postString('category'),
            'alt_text' => $request->postString('alt_text'),
        ]);
        $this->audit->record($request, 'media_update', $ok ? 'success' : 'failed', 'media:' . $id, $actor?->id);
        $this->renderList($request, $ok ? 'Grafika została zapisana.' : 'Nie udało się zapisać grafiki.', $ok ? 'success' : 'danger');
    }

    private function delete(Request $request): void
    {
        $actor = $this->auth->user();
        if (!$this->security->validateCsrfToken($request->postString('_token'))) {
            $this->audit->record($request, 'media_delete', 'invalid_csrf', 'media', $actor?->id);
            $this->renderList($request, 'Token CSRF jest nieprawidłowy lub wygasł.', 'danger');
            return;
        }
        $id = $request->postInt('id', 0) ?? 0;
        $asset = $this->assets->delete($id);
        if ($asset instanceof MediaAsset) {
            $this->storage->delete($asset->storedName);
        }
        $this->audit->record($request, 'media_delete', $asset instanceof MediaAsset ? 'success' : 'failed', 'media:' . $id, $actor?->id);
        $this->renderList($request, $asset instanceof MediaAsset ? 'Grafika została usunięta.' : 'Nie udało się usunąć grafiki.', $asset instanceof MediaAsset ? 'success' : 'danger');
    }

    private function guard(Request $request, string $permission, callable $handler): void
    {
        $user = $this->auth->user();
        if (!$user instanceof User) {
            http_response_code(401);
            $this->theme->render_admin_access_state(401, 'Wymagane logowanie', 'Biblioteka grafik wymaga aktywnej sesji.', 'index.php?route=/admin/login', 'Przejdź do logowania');
            return;
        }
        if (!in_array('*', $user->permissions, true) && !in_array($permission, $user->permissions, true)) {
            $this->audit->record($request, 'admin_access', 'denied', $permission, $user->id);
            http_response_code(403);
            $this->theme->render_admin_access_state(403, 'Brak uprawnień', 'Twoje konto nie ma uprawnienia: ' . $permission, 'index.php?route=/admin', 'Wróć do panelu');
            return;
        }
        $handler();
    }

    private function startAdminPage(string $title, string $lead): void
    {
        $user = $this->auth->user();
        $this->theme->start_admin_page($title, $this->menu->visibleFor($user?->permissions ?? []), '/admin/media', [
            'name' => $user?->displayName ?? 'Gość',
            'role' => $user?->primaryRole() ?? 'Gość',
            'initials' => $user?->initials() ?? 'G',
            'avatar_url' => $user?->avatarUrl ?? '',
            'logout_action' => 'index.php?route=/admin/logout',
            'logout_token' => $this->security->csrfToken(),
        ]);
        $this->theme->start_admin_content($title, $lead, [
            ['label' => 'Panel', 'href' => 'index.php?route=/admin'],
            ['label' => 'Biblioteka grafik', 'href' => 'index.php?route=/admin/media'],
        ]);
    }

    private function endAdminPage(): void
    {
        $this->theme->end_admin_content();
        $this->theme->end_admin_page();
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }

        return $bytes . ' B';
    }

    /** @param array<string, mixed> $payload */
    private function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
