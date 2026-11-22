<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\BuildExplorer;

use SyntaxDevTeam\Cms\Core\{Request, Router, ThemeInterface};
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuthService;

final class PublicBuildApiController
{
    public function __construct(
        private readonly PublicBuildRepository $repository,
        private readonly AuthService $auth,
        private readonly ThemeInterface $theme,
    ) {}

    public function registerRoutes(Router $router): void
    {
        $router->get('/api/v2/builds/openapi', fn() => $this->respond(200, json_decode((string) file_get_contents(__DIR__ . '/openapi.json'), true, flags: JSON_THROW_ON_ERROR)));
        $router->get('/builds/api', fn() => $this->documentation());
        $router->get('/builds/api/guide', fn() => $this->guide());
        $router->get('/api/v2/builds', fn() => $this->respond(200, ['api' => 'v2', 'links' => [
            'projects' => '/api/v2/builds/projects', 'catalog' => '/api/v2/builds/catalog', 'openapi' => '/api/v2/builds/openapi', 'documentation' => '/builds/api']]));
        $friendly = '/api/v2/builds/{project}/{platform}/{game_version}/{channel}/{version}';
        $router->get($friendly, fn(Request $r) => $this->friendly($r, false));
        $router->get($friendly . '/info', fn(Request $r) => $this->friendly($r, true));
        $router->get('/api/v2/builds/projects', fn(Request $r) => $this->handle($r, 'projects'));
        $router->get('/api/v2/builds/catalog', fn(Request $r) => $this->handle($r, 'catalog'));
        $base = '/api/v2/builds/projects/{project}';
        $router->get($base, fn(Request $r) => $this->handle($r, 'project'));
        foreach (['versions', 'releases', 'artifacts', 'latest', 'latest/download', 'options'] as $resource) {
            $router->get($base . '/' . $resource, fn(Request $r) => $this->handle($r, $resource));
        }
        foreach (['versions' => 'version', 'releases' => 'release', 'artifacts' => 'artifact'] as $resource => $key) {
            $router->get($base . '/' . $resource . '/{' . $key . '}', fn(Request $r) => $this->handle($r, $resource . '/item'));
        }
    }

    private function handle(Request $request, string $resource): void
    {
        try {
            $filters = $this->filters($request);
            $page = $this->integer($request->queryString('page', '1'), 1, 1000000);
            $perPage = $this->integer($request->queryString('per_page', '25'), 1, 100);
            $offset = ($page - 1) * $perPage;
            $authenticated = $this->auth->actualUser() !== null;
            $slug = $request->routeString('project');
            $project = null;
            if ($slug !== '') {
                $project = $this->repository->projects($authenticated, 1, 0, $slug)['items'][0] ?? null;
                if ($project === null) { $this->notFound(); return; }
                $filters['project'] = $slug;
            }
            foreach (['release', 'artifact'] as $key) {
                if ($request->routeString($key) !== '') { $filters[$key] = $this->integer($request->routeString($key), 1, PHP_INT_MAX); }
            }
            if ($request->routeString('version') !== '') { $filters['version'] = $request->routeString('version'); }
            $single = false;
            switch ($resource) {
                case 'projects':
                    $result = $this->repository->projects($authenticated, $perPage, $offset, $filters['project'] ?? null);
                    break;
                case 'project':
                    $this->respond(200, ['api' => 'v2', 'data' => $project + $this->repository->capabilities($authenticated, $slug)]);
                    return;
                case 'options':
                    $this->respond(200, ['api' => 'v2', 'data' => $this->repository->capabilities($authenticated, $slug)]);
                    return;
                case 'versions':
                    $result = $this->repository->versions($authenticated, $filters, $perPage, $offset);
                    break;
                case 'artifacts/item':
                    $single = true;
                    $result = $this->repository->artifacts($authenticated, $filters, 1, 0);
                    break;
                case 'artifacts':
                    $result = $this->repository->artifacts($authenticated, $filters, $perPage, $offset);
                    break;
                case 'latest':
                case 'latest/download':
                    if (!isset($filters['platform'], $filters['channel'])) { throw new \InvalidArgumentException('platform and channel are required.'); }
                    $result = $this->repository->artifacts($authenticated, $filters, 1, 0);
                    if ($result['items'] === []) { $this->notFound(); return; }
                    if ($resource === 'latest/download') {
                        // The existing download handler rechecks publication, access and file availability.
                        header('Cache-Control: private, no-store');
                        header('Vary: Cookie');
                        header('Location: ' . $result['items'][0]['links']['download'], true, 302);
                        return;
                    }
                    $single = true;
                    break;
                default:
                    $single = $resource === 'releases/item';
                    $result = $this->repository->releases($authenticated, $filters, $single ? 1 : $perPage, $single ? 0 : $offset);
                    if ($resource === 'versions/item' && $result['total'] === 0) { $this->notFound(); return; }
            }
            if ($single) {
                if ($result['items'] === []) { $this->notFound(); return; }
                $this->respond(200, ['api' => 'v2', 'data' => $result['items'][0]]);
                return;
            }
            $query = $filters;
            unset($query['release'], $query['artifact']);
            $link = fn(int $number): string => $request->path() . '?' . http_build_query($query + ['page' => $number, 'per_page' => $perPage]);
            $this->respond(200, ['api' => 'v2', 'data' => $result['items'],
                'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $result['total'],
                    'next' => $offset + $perPage < $result['total'] ? $link($page + 1) : null,
                    'previous' => $page > 1 ? $link($page - 1) : null]]);
        } catch (\InvalidArgumentException $error) {
            $this->respond(400, ['api' => 'v2', 'error' => ['code' => 'invalid_request', 'message' => $error->getMessage()]]);
        } catch (\Throwable $error) {
            error_log('[miniPORTAL] public_build_api_failed ' . $error::class);
            $this->respond(500, ['api' => 'v2', 'error' => ['code' => 'internal_error', 'message' => 'Build catalogue is temporarily unavailable.']]);
        }
    }

    private function friendly(Request $request, bool $metadata): void
    {
        try {
            $slug = $request->routeString('project');
            $platform = strtolower($request->routeString('platform'));
            $gameVersion = $request->routeString('game_version');
            $channel = strtolower($request->routeString('channel'));
            $version = $request->routeString('version');
            if (preg_match('/^[a-zA-Z0-9_-]{1,191}$/D', $slug) !== 1
                || preg_match('/^[a-z0-9._-]{1,80}$/D', $platform) !== 1
                || ($gameVersion !== 'any' && preg_match('/^[0-9]{1,3}(?:\.[0-9]{1,3}){1,3}$/D', $gameVersion) !== 1)
                || !in_array($channel, ['release', 'snapshot', 'dev', 'wip'], true)
                || preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._+\-]{0,119}$/D', $version) !== 1) {
                throw new \InvalidArgumentException('Invalid project, platform, game version, channel or project version.');
            }
            $filters = ['project' => $slug, 'platform' => $platform, 'channel' => $channel];
            if ($gameVersion !== 'any') { $filters['game_version'] = $gameVersion; }
            if (strtolower($version) !== 'latest') { $filters['version'] = $version; }
            $artifact = $this->repository->friendlyArtifact($this->auth->actualUser() !== null, $filters);
            if ($artifact === null) { $this->notFound(); return; }
            if ($metadata) { $this->respond(200, ['api' => 'v2', 'data' => $artifact]); return; }
            header('Cache-Control: private, no-store');
            header('Vary: Cookie');
            header('Location: ' . $artifact['links']['download'], true, 302);
        } catch (\InvalidArgumentException $error) {
            $this->respond(400, ['api' => 'v2', 'error' => ['code' => 'invalid_request', 'message' => $error->getMessage()]]);
        } catch (\Throwable $error) {
            error_log('[miniPORTAL] friendly_build_api_failed ' . $error::class);
            $this->respond(500, ['api' => 'v2', 'error' => ['code' => 'internal_error', 'message' => 'Build catalogue is temporarily unavailable.']]);
        }
    }

    private function filters(Request $request): array
    {
        $result = [];
        foreach (['project' => 160, 'version' => 120, 'build_number' => 80, 'platform' => 80, 'channel' => 20, 'game_version' => 40, 'platform_family' => 20, 'java_version' => 2] as $key => $max) {
            $value = trim($request->queryString($key));
            if ($value === '') { continue; }
            if (mb_strlen($value) > $max || preg_match('/[\x00-\x1f\x7f]/', $value)) { throw new \InvalidArgumentException('Invalid ' . $key . '.'); }
            if (in_array($key, ['platform', 'channel', 'platform_family'], true)) { $value = strtolower($value); }
            if ($key === 'platform' && !preg_match('/^[a-z0-9._-]+$/D', $value)) { throw new \InvalidArgumentException('Invalid platform.'); }
            if ($key === 'channel' && !in_array($value, ['release', 'snapshot', 'dev', 'wip'], true)) { throw new \InvalidArgumentException('Invalid channel.'); }
            if ($key === 'platform_family' && !in_array($value, ['server', 'proxy', 'other'], true)) { throw new \InvalidArgumentException('Invalid platform_family.'); }
            if ($key === 'java_version') { $value = $this->integer($value, 8, 99); }
            $result[$key] = $value;
        }
        return $result;
    }

    private function integer(string $value, int $min, int $max): int
    {
        if (!preg_match('/^[0-9]+$/D', $value) || ($number = filter_var($value, FILTER_VALIDATE_INT)) === false || $number < $min || $number > $max) {
            throw new \InvalidArgumentException('Invalid integer parameter.');
        }
        return $number;
    }

    private function notFound(): void
    {
        $this->respond(404, ['api' => 'v2', 'error' => ['code' => 'not_found', 'message' => 'No accessible published resource matches the request.']]);
    }

    private function respond(int $status, array $body): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: private, no-store');
        header('Vary: Cookie');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
    }

    private function documentation(): void
    {
        if (!$this->theme instanceof \SyntaxDevTeam\Cms\Core\ApiReferenceThemeInterface) { $this->guide(); return; }
        $this->theme->start_page('Build Explorer API — interaktywna dokumentacja', 'Przeglądaj endpointy, sprawdzaj parametry i wykonuj zapytania w konsoli Swagger UI.', true);
        $this->theme->render_api_reference([
            'title' => 'Build Explorer API',
            'lead' => 'Projekty, wydania i pliki w jednym API. Wybierz endpoint, uzupełnij parametry i zobacz odpowiedź bez wychodzenia ze strony.',
            'version' => 'v2',
            'spec_url' => '/api/v2/builds/openapi',
            'guide_url' => '/builds/api/guide',
            'back_url' => '/builds',
        ]);
        $this->theme->end_page();
    }

    private function guide(): void
    {
        $this->theme->start_page('Build Explorer API — poradnik integracji', 'Przykłady zapytań, stronicowanie i zgodność plików Build Explorer.', true);
        $this->theme->start_header('Poradnik integracji', 'Przykłady zapytań, znaczenie pól i reguły doboru plików.', 'Build Explorer API');
        $this->theme->end_header();
        $this->theme->start_section();
        $this->theme->render_breadcrumb([['label' => 'Build Explorer', 'href' => '/builds'], ['label' => 'Konsola API', 'href' => '/builds/api'], ['label' => 'Poradnik']]);
        $this->theme->render_rich_content((string) file_get_contents(__DIR__ . '/PUBLIC_API.md'), 'markdown');
        $this->theme->end_section();
        $this->theme->end_page();
    }
}
