<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\CoreAuth;

use SyntaxDevTeam\Cms\Core\Request;

/** Validated directory filters; SQL identifiers and ordering are closed choices. */
final readonly class UserDirectoryQuery
{
    public string $search;
    public string $status;
    public string $role;
    public string $provider;
    public string $presence;
    public string $sort;
    public int $page;
    public int $perPage;

    public function __construct(Request $request)
    {
        $this->search = mb_substr(trim($request->queryString('q')), 0, 120);
        $this->status = self::choice($request, 'status', ['active', 'pending', 'blocked']);
        $role = $request->queryString('role');
        $this->role = preg_match('/^[a-z][a-z0-9_]{1,63}$/D', $role) === 1 ? $role : '';
        $this->provider = self::choice($request, 'provider', ['github', 'discord', 'google', 'microsoft', 'none']);
        $this->presence = self::choice($request, 'presence', ['online', 'offline']);
        $this->sort = self::choice($request, 'sort', ['newest', 'oldest', 'name', 'login']) ?: 'newest';
        $this->page = max(1, min(1000000, $request->queryInt('page', 1)));
        $size = $request->queryInt('per_page', 25);
        $this->perPage = in_array($size, [25, 50, 100], true) ? $size : 25;
    }

    private static function choice(Request $request, string $key, array $choices): string
    {
        $value = $request->queryString($key);
        return in_array($value, $choices, true) ? $value : '';
    }

    public function where(): array
    {
        $conditions = [];
        $parameters = [];
        if ($this->search !== '') {
            $like = '%' . strtr($this->search, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
            $conditions[] = "(users.display_name LIKE :search_name ESCAPE '!' OR users.email LIKE :search_email ESCAPE '!' OR CAST(users.id AS CHAR) = :search_id)";
            $parameters += [':search_name' => $like, ':search_email' => $like, ':search_id' => $this->search];
        }
        if ($this->status !== '') {
            $conditions[] = 'users.status = :status';
            $parameters[':status'] = $this->status;
        }
        if ($this->role !== '') {
            $conditions[] = 'EXISTS (SELECT 1 FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = users.id AND r.name = :role)';
            $parameters[':role'] = $this->role;
        }
        if ($this->provider !== '') {
            $conditions[] = ($this->provider === 'none' ? 'NOT ' : '') . 'EXISTS (SELECT 1 FROM user_identities ui WHERE ui.user_id = users.id' . ($this->provider === 'none' ? ')' : ' AND ui.provider = :provider)');
            if ($this->provider !== 'none') $parameters[':provider'] = $this->provider;
        }
        if ($this->presence !== '') {
            $online = "(users.status <> 'blocked' AND EXISTS (SELECT 1 FROM auth_active_sessions s WHERE s.user_id = users.id AND s.last_seen_at >= :online_cutoff))";
            $conditions[] = ($this->presence === 'offline' ? 'NOT ' : '') . $online;
            $parameters[':online_cutoff'] = gmdate('Y-m-d H:i:s', time() - 300);
        }
        return [$conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions), $parameters];
    }

    public function order(): string
    {
        return match ($this->sort) {
            'oldest' => 'users.created_at ASC, users.id ASC',
            'name' => 'users.display_name ASC, users.id ASC',
            'login' => 'users.last_login_at DESC, users.id DESC',
            default => 'users.created_at DESC, users.id DESC',
        };
    }

    public function url(int $page = 1): string
    {
        return 'index.php?' . http_build_query(['route' => '/admin/users', 'q' => $this->search, 'status' => $this->status, 'role' => $this->role, 'provider' => $this->provider, 'presence' => $this->presence, 'sort' => $this->sort, 'per_page' => $this->perPage, 'page' => $page]);
    }
}
