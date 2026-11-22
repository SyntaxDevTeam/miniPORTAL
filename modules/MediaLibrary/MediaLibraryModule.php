<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\MediaLibrary;

use SyntaxDevTeam\Cms\Core\{AdminMenuRegistry, AssetManagerThemeInterface, ModuleInterface, Request, Router, Security, ThemeInterface};
use SyntaxDevTeam\Cms\Modules\CoreAuth\{AuditLogService, AuthService, User};

final class MediaLibraryModule implements ModuleInterface
{
    public const MAX_FILES = 20;
    public const MAX_BATCH_BYTES = 47185920;

    public function __construct(
        private readonly ThemeInterface $theme,
        private readonly AdminMenuRegistry $menu,
        private readonly MediaAssetRepository $assets,
        private readonly MediaAssetStorage $storage,
        private readonly AuthService $auth,
        private readonly Security $security,
        private readonly AuditLogService $audit,
    ) {}

    public function id(): string { return 'media_library'; }
    public function version(): string { return '2.1.1'; }
    public function dependencies(): array { return ['core_auth']; }
    public function isProtected(): bool { return false; }
    public function requiredPermissions(): array { return ['media.view', 'media.manage']; }

    public function registerAdminMenu(AdminMenuRegistry $menu): void
    {
        $menu->add('Treść', 'Biblioteka grafik', '/admin/media', 'MG', 'media.view', 39);
    }

    public function registerRoutes(Router $router): void
    {
        $router->get('/admin/media', fn (Request $r) => $this->guard($r, 'media.view', fn () => $this->renderList($r)));
        $router->post('/admin/media/upload', fn (Request $r) => $this->guard($r, 'media.manage', fn () => $this->upload($r)));
        $router->post('/admin/media/upload-item', fn (Request $r) => $this->guard($r, 'media.manage', fn () => $this->upload($r, true), true));
        $router->post('/admin/media/richtext-upload', fn (Request $r) => $this->uploadForRichText($r));
        $router->get('/admin/media/edit', fn (Request $r) => $this->guard($r, 'media.manage', fn () => $this->renderEdit($r)));
        $router->post('/admin/media/edit', fn (Request $r) => $this->guard($r, 'media.manage', fn () => $this->update($r)));
        $router->post('/admin/media/delete', fn (Request $r) => $this->guard($r, 'media.manage', fn () => $this->delete($r)));
        $router->post('/admin/media/bulk', fn (Request $r) => $this->guard($r, 'media.manage', fn () => $this->bulk($r)));
    }

    private function query(Request $request): MediaLibraryQuery
    {
        $preferred = $_SESSION['_media_view'] ?? 'thumbnails';
        if (!isset($_SESSION['_media_view']) && preg_match('/(?:^|;\s*)miniportal_media_view=(list|thumbnails)(?:;|$)/', $request->header('Cookie'), $match)) {
            $preferred = $match[1];
        }
        $query = MediaLibraryQuery::fromRequest($request, is_string($preferred) ? $preferred : 'thumbnails');
        if ($request->method() === 'GET' && in_array($request->queryString('view'), ['list', 'thumbnails'], true)) {
            $_SESSION['_media_view'] = $query->values['view'];
            setcookie('miniportal_media_view', $query->values['view'], ['expires' => time() + 31536000, 'path' => '/', 'secure' => $request->isSecure(), 'httponly' => true, 'samesite' => 'Lax']);
        }
        return $query;
    }

    private function renderList(Request $request, string $message = '', string $variant = 'info'): void
    {
        $query = $this->query($request);
        $result = $this->assets->search($query);
        $query = new MediaLibraryQuery(array_replace($query->values, ['page' => $result['page']]));
        if ($message === '' && isset($_SESSION['_media_flash'])) {
            [$message, $variant] = $_SESSION['_media_flash'];
            unset($_SESSION['_media_flash']);
        }
        $model = $this->model($query) + $result + ['summary' => $this->assets->summary(), 'mode' => 'browse'];
        $model['assets'] = array_map(fn (MediaAsset $asset): array => $this->assetModel($asset, $query), $result['assets']);
        $this->render($model, $message, $variant);
    }

    private function model(MediaLibraryQuery $query): array
    {
        $user = $this->auth->user();
        return [
            'state' => $query->values,
            'categories' => MediaAssetRepository::CATEGORIES,
            'types' => MediaLibraryQuery::TYPES,
            'sorts' => MediaLibraryQuery::SORTS,
            'token' => $this->security->csrfToken(),
            'can_manage' => $user !== null && $this->auth->rolePreview() === null && (in_array('*', $user->permissions, true) || in_array('media.manage', $user->permissions, true)),
            'url' => $query->url(),
            'upload_url' => $query->url('/admin/media/upload'),
            'upload_item_url' => $query->url('/admin/media/upload-item'),
            'bulk_url' => $query->url('/admin/media/bulk'),
            'save_url' => $query->url('/admin/media/edit'),
            'reset_url' => (new MediaLibraryQuery(['view' => $query->values['view']]))->url(),
            'view_urls' => ['list' => $query->url(changes: ['view' => 'list']), 'thumbnails' => $query->url(changes: ['view' => 'thumbnails'])],
            'previous_url' => $query->url(changes: ['page' => max(1, $query->values['page'] - 1)]),
            'next_url' => $query->url(changes: ['page' => $query->values['page'] + 1]),
        ];
    }

    private function assetModel(MediaAsset $asset, MediaLibraryQuery $query): array
    {
        return [
            'id' => $asset->id, 'title' => $asset->title, 'category' => $asset->category,
            'category_label' => MediaAssetRepository::CATEGORIES[$asset->category] ?? $asset->category,
            'alt_text' => $asset->altText, 'original_name' => $asset->originalName,
            'url' => $asset->publicPath, 'mime' => $asset->mimeType,
            'format' => MediaLibraryQuery::TYPES[$asset->mimeType] ?? $asset->mimeType,
            'size' => $this->formatBytes($asset->fileSize),
            'dimensions' => $asset->width !== null && $asset->height !== null ? $asset->width . ' × ' . $asset->height . ' px' : ($asset->mimeType === 'image/svg+xml' ? 'SVG · skalowalny' : 'Brak wymiarów'),
            'created_at' => $asset->createdAt,
            'edit_url' => $query->url('/admin/media/edit', ['id' => $asset->id]),
            'delete_url' => $query->url('/admin/media/delete'),
        ];
    }

    private function render(array $model, string $message = '', string $variant = 'info'): void
    {
        $this->startAdminPage($model['mode'] === 'browse' ? 'Biblioteka grafik' : 'Edytuj grafiki', 'Manager 2.0 · Wgraj pliki, uporządkuj bibliotekę i wykorzystaj obrazy na stronie.');
        if ($message !== '') $this->theme->render_alert($message, $variant);
        if ($this->theme instanceof AssetManagerThemeInterface) {
            $this->theme->render_asset_manager($model);
        } else {
            // Older third-party themes retain basic access through ThemeInterface.
            $this->theme->render_alert('Pełny manager grafik wymaga motywu obsługującego AssetManagerThemeInterface.', 'info');
            if ($model['mode'] === 'browse') {
                if ($model['can_manage']) {
                    $this->theme->render_form($model['upload_url'], [
                        ['name' => 'asset', 'label' => 'Grafika (PNG, JPG, WEBP, GIF, SVG do 5 MiB)', 'type' => 'file', 'accept' => 'image/png,image/jpeg,image/webp,image/gif,image/svg+xml,.svg', 'required' => true],
                        ['name' => 'category', 'label' => 'Kategoria', 'type' => 'select', 'value' => 'other', 'options' => $model['categories']],
                    ], 'Dodaj grafikę', $model['token']);
                }
                $this->theme->render_admin_action_table(['Tytuł', 'Adres'], array_map(static function (array $asset) use ($model): array {
                    $actions = [['label' => 'Otwórz', 'href' => $asset['url']]];
                    if ($model['can_manage']) {
                        $actions[] = ['label' => 'Edytuj', 'href' => $asset['edit_url']];
                        $actions[] = ['label' => 'Usuń', 'action' => $asset['delete_url'], 'fields' => ['id' => $asset['id']], 'variant' => 'danger', 'confirm' => 'Usunąć grafikę z biblioteki i dysku?'];
                    }
                    return ['cells' => [$asset['title'], $asset['url']], 'actions' => $actions];
                }, $model['assets']), $model['token']);
            } elseif ($model['can_manage']) {
                foreach ($model['assets'] as $asset) {
                    $this->theme->render_form($model['save_url'], [
                        ['name' => 'id', 'label' => 'ID', 'type' => 'hidden', 'value' => $asset['id']],
                        ['name' => 'title', 'label' => 'Nazwa', 'value' => $asset['title'], 'required' => true, 'maxlength' => 180],
                        ['name' => 'category', 'label' => 'Kategoria', 'type' => 'select', 'value' => $asset['category'], 'options' => $model['categories']],
                        ['name' => 'alt_text', 'label' => 'Tekst alternatywny', 'value' => $asset['alt_text'], 'maxlength' => 255],
                    ], 'Zapisz grafikę', $model['token']);
                }
            }
        }
        $this->theme->end_admin_content();
        $this->theme->end_admin_page();
    }

    private function upload(Request $request, bool $json = false): void
    {
        if (!$this->validCsrf($request, 'media_upload', $json)) return;
        $files = $request->files('assets');
        if ($files === []) $files = $request->files('asset');
        if ($files === [] || count($files) > ($json ? 1 : self::MAX_FILES) || array_sum(array_column($files, 'size')) > self::MAX_BATCH_BYTES) {
            $message = 'Wybierz do 20 grafik, maksymalnie 5 MiB na plik i 45 MiB na zestaw.';
            if ($json) $this->json(['ok' => false, 'message' => $message], 422);
            else $this->renderList($request, $message, 'warning');
            return;
        }
        $key = $request->postString('upload_key');
        $receipt = $this->auth->user()?->id . ':' . $key;
        $remember = $json && preg_match('/^[a-f0-9]{32}$/D', $key) === 1;
        if ($remember && isset($_SESSION['_media_uploads'][$receipt])) {
            $id = (int) $_SESSION['_media_uploads'][$receipt];
            $asset = $this->assets->find($id);
            if ($asset !== null) {
                $this->json(['ok' => true, 'id' => $id, 'url' => $asset->publicPath, 'message' => 'Plik został już dodany.']);
                return;
            }
        }
        $uploaded = []; $errors = [];
        foreach ($files as $file) {
            try {
                $id = $this->storeAsset($file, $request, $this->auth->user()?->id);
                $uploaded[] = $id;
                if ($remember) {
                    $_SESSION['_media_uploads'][$receipt] = $id;
                    $_SESSION['_media_uploads'] = array_slice($_SESSION['_media_uploads'], -100, null, true);
                }
                $this->audit->record($request, 'media_upload', 'success', 'media:' . $id, $this->auth->user()?->id);
            } catch (\Throwable $exception) {
                $errors[] = mb_substr($file['name'], 0, 100) . ': ' . $this->uploadError($exception);
                $this->audit->record($request, 'media_upload', 'failed', 'media', $this->auth->user()?->id);
            }
        }
        if ($json) {
            $asset = $errors === [] ? $this->assets->find($uploaded[0]) : null;
            $this->json(['ok' => $errors === [], 'id' => $uploaded[0] ?? null, 'url' => $asset?->publicPath, 'message' => $errors[0] ?? 'Dodano do biblioteki.'], $errors === [] ? 201 : 422);
            return;
        }
        $message = 'Dodano grafik: ' . count($uploaded) . '.' . ($errors !== [] ? ' Nie dodano: ' . implode(' · ', $errors) : ' Nazwy i opisy możesz teraz uzupełnić zbiorczo.');
        $this->redirect($request, $message, $errors === [] ? 'success' : 'warning');
    }

    private function storeAsset(array $file, Request $request, ?int $actorId, ?string $category = null): int
    {
        $stored = $this->storage->store($file);
        try {
            $id = $this->assets->create($stored + [
                'title' => $request->postString('title'), 'category' => $category ?? $request->postString('category', 'other'),
                'alt_text' => $request->postString('alt_text'), 'created_by' => $actorId,
            ]);
            if ($id < 1) throw new \RuntimeException('Nie zapisano rekordu grafiki.');
            return $id;
        } catch (\Throwable $exception) {
            $this->storage->delete($stored['stored_name']);
            throw $exception;
        }
    }

    private function uploadError(\Throwable $exception): string
    {
        // Only known validation errors may be exposed; database/provider details stay private.
        $message = $exception->getMessage();
        return in_array($message, [
            'Nie wybrano poprawnego pliku grafiki.', 'Plik grafiki jest pusty albo przekracza limit 5 MB.',
            'Dozwolone są tylko PNG, JPG, WEBP, GIF i SVG.', 'Plik nie zawiera poprawnego obrazu.',
            'Nieprawidłowy plik SVG. Wymagany jest dokument XML w UTF-8.',
            'SVG nie może zawierać definicji encji ani wewnętrznego DTD.',
            'Plik nie zawiera poprawnego dokumentu SVG.',
            'SVG nie zawiera obsługiwanej grafiki statycznej.',
            'SVG jest zbyt złożony. Limit to 10000 elementów i 64 poziomy.',
        ], true) ? $message : 'Nie udało się zapisać grafiki. Spróbuj ponownie lub sprawdź konfigurację magazynu i kompresji.';
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
        if ($this->auth->rolePreviewBlocks($request)) {
            $this->json(['ok' => false, 'message' => 'Podgląd roli jest tylko do odczytu.'], 403);
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
            $id = $this->storeAsset($file, $request, $actor->id, 'content');
            $asset = $this->assets->find($id);
            $this->audit->record($request, 'media_richtext_upload', 'success', 'media:' . $id, $actor->id);
            $this->json([
                'ok' => true,
                'url' => $asset?->publicPath ?? '',
                'alt' => $asset?->altText ?? $request->postString('alt_text'),
            ]);
        } catch (\Throwable $exception) {
            $this->audit->record($request, 'media_richtext_upload', 'failed', 'media', $actor->id);
            $this->json(['ok' => false, 'message' => $this->uploadError($exception)], 422);
        }
    }


    private function renderEdit(Request $request, string $message = '', string $variant = 'info'): void
    {
        $id = $request->postInt('id') ?? $request->queryInt('id', 0) ?? 0;
        $asset = $this->assets->find($id);
        if ($asset === null) {
            $this->renderList($request, 'Nie znaleziono grafiki.', 'warning');
            return;
        }
        $query = $this->query($request);
        $item = $this->assetModel($asset, $query);
        if ($request->method() === 'POST' && $message !== '') {
            $item = array_replace($item, ['title' => $request->postString('title'), 'alt_text' => $request->postString('alt_text'), 'category' => $request->postString('category')]);
        }
        $this->render($this->model($query) + ['mode' => 'edit', 'assets' => [$item]], $message, $variant);
    }

    private function update(Request $request): void
    {
        if (!$this->validCsrf($request, 'media_update')) return;
        $id = $request->postInt('id', 0) ?? 0;
        try {
            $data = $this->metadata($request->postStrings());
            $this->assets->updateMany([$id => $data]);
        } catch (\Throwable $exception) {
            $this->renderEdit($request, $exception instanceof \InvalidArgumentException ? $exception->getMessage() : 'Nie udało się zapisać grafiki. Odśwież bibliotekę i spróbuj ponownie.', 'danger');
            return;
        }
        $this->audit->record($request, 'media_update', 'success', 'media:' . $id, $this->auth->user()?->id);
        $this->redirect($request, 'Grafika została zapisana.');
    }

    private function metadata(array $data): array
    {
        foreach (['title', 'category', 'alt_text'] as $key) {
            if (!isset($data[$key]) || !is_string($data[$key])) throw new \InvalidArgumentException('Uzupełnij poprawne metadane grafik.');
        }
        $title = trim($data['title']);
        if ($title === '' || mb_strlen($title) > 180 || mb_strlen($data['alt_text']) > 255 || !isset(MediaAssetRepository::CATEGORIES[$data['category']])) {
            throw new \InvalidArgumentException('Podaj nazwę do 180 znaków, kategorię i opis alternatywny do 255 znaków.');
        }
        return ['title' => $title, 'category' => $data['category'], 'alt_text' => trim($data['alt_text'])];
    }

    private function delete(Request $request): void
    {
        if (!$this->validCsrf($request, 'media_delete')) return;
        $id = $request->postInt('id', 0) ?? 0;
        $this->removeAsset($id, $request);
        $this->redirect($request, 'Zakończono usuwanie grafiki.');
    }

    private function removeAsset(int $id, Request $request): bool
    {
        $asset = $this->assets->delete($id);
        if ($asset !== null) $this->storage->delete($asset->storedName);
        $this->audit->record($request, 'media_delete', $asset !== null ? 'success' : 'failed', 'media:' . $id, $this->auth->user()?->id);
        return $asset !== null;
    }

    private function bulk(Request $request): void
    {
        if (!$this->validCsrf($request, 'media_bulk')) return;
        $ids = $request->postStringList('ids');
        $action = $request->postString('operation');
        $items = [];
        try {
            if ($ids === [] || count($ids) > 48) throw new \InvalidArgumentException('Zaznacz od 1 do 48 grafik na bieżącej stronie.');
            foreach ($ids as $id) {
                if (!ctype_digit($id) || (int) $id < 1 || ($asset = $this->assets->find((int) $id)) === null) {
                    throw new \InvalidArgumentException('Jedna z grafik już nie istnieje. Odśwież bibliotekę.');
                }
                $items[$asset->id] = $asset;
            }
            $query = $this->query($request);
            if ($action === 'edit') {
                $this->render($this->model($query) + ['mode' => 'batch-edit', 'assets' => array_map(fn (MediaAsset $asset): array => $this->assetModel($asset, $query), array_values($items))]);
                return;
            }
            if ($action === 'delete') {
                if (!$request->postBool('confirm_delete')) throw new \InvalidArgumentException('Potwierdź usunięcie zaznaczonych grafik. Pliki mogą być używane na stronie.');
                $deleted = 0;
                foreach ($items as $asset) if ($this->removeAsset($asset->id, $request)) $deleted++;
                $this->redirect($request, 'Usunięto grafik: ' . $deleted . '.');
                return;
            }
            $changes = [];
            $rows = $request->postArray('metadata');
            foreach ($items as $id => $asset) {
                $changes[$id] = match ($action) {
                    'save' => $this->metadata(is_array($rows[$id] ?? null) ? $rows[$id] : []),
                    'category' => $this->metadata(['title' => $asset->title, 'alt_text' => $asset->altText, 'category' => $request->postString('category')]),
                    default => throw new \InvalidArgumentException('Wybierz operację zbiorczą.'),
                };
            }
            $this->assets->updateMany($changes);
            foreach (array_keys($changes) as $id) $this->audit->record($request, 'media_update', 'success', 'media:' . $id, $this->auth->user()?->id);
            $this->redirect($request, 'Zapisano grafik: ' . count($changes) . '.');
        } catch (\Throwable $exception) {
            $message = $exception instanceof \InvalidArgumentException ? $exception->getMessage() : 'Nie udało się zakończyć operacji. Odśwież bibliotekę i sprawdź stan grafik.';
            if ($action === 'save' && $items !== []) {
                $query = $this->query($request); $rows = $request->postArray('metadata'); $assets = [];
                foreach ($items as $id => $asset) {
                    $item = $this->assetModel($asset, $query);
                    foreach (['title', 'category', 'alt_text'] as $key) if (is_string($rows[$id][$key] ?? null)) $item[$key] = $rows[$id][$key];
                    $assets[] = $item;
                }
                $this->render($this->model($query) + ['mode' => 'batch-edit', 'assets' => $assets], $message, 'danger');
            } else $this->renderList($request, $message, 'warning');
        }
    }

    private function validCsrf(Request $request, string $event, bool $json = false): bool
    {
        if ($this->security->validateCsrfToken($request->postString('_token'))) return true;
        $this->audit->record($request, $event, 'invalid_csrf', 'media', $this->auth->user()?->id);
        http_response_code(419);
        if ($json) $this->json(['ok' => false, 'message' => 'Token CSRF wygasł. Odśwież stronę.'], 419);
        else $this->renderList($request, 'Token CSRF jest nieprawidłowy lub wygasł.', 'danger');
        return false;
    }

    private function redirect(Request $request, string $message, string $variant = 'success'): void
    {
        $_SESSION['_media_flash'] = [$message, $variant];
        header('Location: ' . $this->query($request)->url(), true, 303);
    }

    private function guard(Request $request, string $permission, callable $handler, bool $json = false): void
    {
        $user = $this->auth->user();
        $status = !$user instanceof User ? 401 : ((!in_array('*', $user->permissions, true) && !in_array($permission, $user->permissions, true)) || $this->auth->rolePreviewBlocks($request) ? 403 : 200);
        if ($status !== 200) {
            $this->audit->record($request, 'admin_access', 'denied', $permission, $user?->id);
            if ($json) $this->json(['ok' => false, 'message' => $status === 401 ? 'Wymagane logowanie.' : 'Brak uprawnień lub aktywny podgląd roli.'], $status);
            else {
                http_response_code($status);
                $this->theme->render_admin_access_state($status, 'Brak dostępu', 'Biblioteka grafik wymaga aktywnej sesji i odpowiednich uprawnień.', 'index.php?route=/admin', 'Wróć do panelu');
            }
            return;
        }
        $handler();
    }

    private function startAdminPage(string $title, string $lead): void
    {
        $user = $this->auth->user();
        $this->theme->start_admin_page($title, $this->menu->visibleFor($user?->permissions ?? []), '/admin/media', [
            'name' => $user?->displayName ?? 'Gość', 'role' => $user?->primaryRole() ?? 'Gość',
            'initials' => $user?->initials() ?? 'G', 'avatar_url' => $user?->avatarUrl ?? '',
            'logout_action' => 'index.php?route=/admin/logout', 'logout_token' => $this->security->csrfToken(),
        ]);
        $this->theme->start_admin_content($title, $lead, [['label' => 'Panel', 'href' => 'index.php?route=/admin'], ['label' => 'Biblioteka grafik', 'href' => 'index.php?route=/admin/media']]);
    }

    private function formatBytes(int $bytes): string
    {
        return $bytes >= 1048576 ? round($bytes / 1048576, 2) . ' MiB' : ($bytes >= 1024 ? round($bytes / 1024, 1) . ' KiB' : $bytes . ' B');
    }

    private function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
