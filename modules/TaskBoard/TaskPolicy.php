<?php

declare(strict_types=1);
namespace SyntaxDevTeam\Cms\Modules\TaskBoard;

use SyntaxDevTeam\Cms\Modules\CoreAuth\User;

final class TaskPolicy
{
    public static function allows(?User $user, string $permission): bool
    {
        return $user?->isActive() === true && (in_array('*', $user->permissions, true) || in_array($permission, $user->permissions, true));
    }

    public static function owner(?User $user): bool
    {
        return $user?->isActive() === true && in_array('owner', $user->roles, true) && self::allows($user, 'tasks.use');
    }

    public static function read(?User $user, array $task): bool
    {
        return self::allows($user, 'tasks.use') && ($task['visibility'] !== 'private' || (int)$task['author_id'] === $user->id || self::owner($user));
    }

    public static function edit(?User $user, array $task): bool
    {
        if (!self::read($user, $task) || ($task['visibility'] === 'public' && !self::allows($user, 'tasks.publish'))) return false;
        return (int)$task['author_id'] === $user->id || ($task['visibility'] !== 'private' && self::allows($user, 'tasks.manage'));
    }

    public static function progress(?User $user, array $task): bool
    {
        return self::read($user, $task) && ((int)$task['author_id'] === $user->id || ($task['visibility'] !== 'private' && (in_array($user->id, $task['assignee_ids'] ?? [], true) || self::allows($user, 'tasks.manage'))));
    }

    public static function notify(?User $user, array $task): bool
    {
        return self::read($user, $task) && $task['visibility'] !== 'private'
            && ((int)$task['author_id'] === $user->id || self::allows($user, 'tasks.manage'));
    }

    public static function comment(?User $user, array $task): bool
    {
        // Owner's silent oversight is strictly read-only.
        return self::read($user, $task) && ($task['visibility'] !== 'private' || (int)$task['author_id'] === $user->id);
    }
}
