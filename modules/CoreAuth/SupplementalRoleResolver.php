<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\CoreAuth;

use PDO;
use SyntaxDevTeam\Cms\Core\HookRegistry;
use SyntaxDevTeam\Cms\Database\CrudApp;

/** CoreAuth alone resolves external role IDs into current local permissions. No grants are persisted. */
final class SupplementalRoleResolver
{
    private const ELIGIBLE = "r.is_system=0 AND r.name NOT IN ('owner','administrator','maintainer','editor','auditor','support','user') "
        . "AND NOT EXISTS (SELECT 1 FROM role_permissions deny_rp JOIN permissions deny_p ON deny_p.id=deny_rp.permission_id WHERE deny_rp.role_id=r.id AND deny_p.name='*')";

    public function __construct(private readonly CrudApp $database) {}

    /** Public contract for configuration UIs. System roles and wildcard-bearing roles are excluded. */
    public function options(): array
    {
        $rows = $this->database->query('SELECT r.id,r.label FROM roles r WHERE ' . self::ELIGIBLE . ' ORDER BY r.label,r.id')?->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $options = [];
        foreach ($rows as $row) $options[(string)$row['id']] = (string)$row['label'];
        return $options;
    }

    /** @return array{roles:list<string>,permissions:list<string>} */
    public function forUser(HookRegistry $hooks, int $userId): array
    {
        $empty = ['roles'=>[], 'permissions'=>[]];
        try {
            $ids = $hooks->applyFilters('core_auth.supplemental_role_ids', [], $userId);
            if (!is_array($ids) || !array_is_list($ids) || count($ids)>128) return $empty;
            $ids = array_values(array_unique(array_filter($ids, static fn ($id) => is_int($id) && $id>0)));
            if ($ids === []) return $empty;
            $parameters = []; $placeholders = [];
            foreach ($ids as $i=>$id) { $placeholders[] = ':r'.$i; $parameters[':r'.$i] = $id; }
            $rows = $this->database->query('SELECT r.name,p.name AS permission FROM roles r '
                . 'LEFT JOIN role_permissions rp ON rp.role_id=r.id LEFT JOIN permissions p ON p.id=rp.permission_id '
                . 'WHERE r.id IN (' . implode(',', $placeholders) . ') AND ' . self::ELIGIBLE . ' ORDER BY r.id,p.name', $parameters)?->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $roles = []; $permissions = [];
            foreach ($rows as $row) {
                $roles[] = (string)$row['name'];
                if ($row['permission']!==null) $permissions[] = (string)$row['permission'];
            }
            return ['roles'=>array_values(array_unique($roles)), 'permissions'=>array_values(array_unique($permissions))];
        } catch (\Throwable) {
            // Optional authorities must fail closed while preserving manual access, including recovery by Owner.
            error_log('[miniPORTAL] supplemental_roles_unavailable');
            return $empty;
        }
    }
}
