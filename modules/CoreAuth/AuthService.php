<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\CoreAuth;

use SyntaxDevTeam\Cms\Core\Request;
use SyntaxDevTeam\Cms\Core\Security;

final class AuthService
{
    private const SESSION_USER_ID = '_miniportal_user_id';
    private const SESSION_ROLE_PREVIEW = '_miniportal_role_preview';
    private const SESSION_ACTIVITY_TOUCHED_AT = '_miniportal_activity_touched_at';
    private const ROLE_PREVIEW_TTL_SECONDS = 14400;
    private const ACTIVITY_TOUCH_INTERVAL_SECONDS = 60;

    private bool $rolePreviewEnabled = true;

    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly Security $security,
        private readonly ?FirstAdminBootstrapper $firstOwnerBootstrapper = null,
    ) {
    }

    public function user(): ?User
    {
        $user = $this->actualUser();
        if ($user === null || !$this->rolePreviewEnabled) {
            return $user;
        }

        $preview = $this->validatedRolePreview($user);
        if ($preview === null) {
            return $user;
        }

        return new User(
            $user->id,
            $user->displayName,
            $user->email,
            $user->avatarUrl,
            $user->status,
            [$preview['role']],
            $preview['permissions'],
            $user->identities,
        );
    }

    public function actualUser(): ?User
    {
        $userId = $_SESSION[self::SESSION_USER_ID] ?? null;

        if (!is_int($userId) && !ctype_digit((string) $userId)) {
            return null;
        }

        $user = $this->users->findById((int) $userId);

        if ($user === null || $user->status === 'blocked') {
            $this->logout();
            return null;
        }

        $this->touchCurrentSession($user->id);

        return $user;
    }

    public function useRolePreview(bool $enabled): void
    {
        $this->rolePreviewEnabled = $enabled;
    }

    /**
     * @param list<string> $permissions
     */
    public function startRolePreview(string $role, string $label, array $permissions): bool
    {
        $user = $this->actualUser();
        $role = trim($role);
        $label = trim($label);
        $permissions = array_values(array_unique(array_filter(
            $permissions,
            static fn (mixed $permission): bool => is_string($permission) && $permission !== ''
        )));
        sort($permissions);

        if (
            $user === null
            || !$this->canManageRolePreview($user)
            || preg_match('/^[a-z][a-z0-9_]{1,63}$/', $role) !== 1
            || $label === ''
            || strlen($label) > 120
        ) {
            return false;
        }
        foreach ($permissions as $permission) {
            if (preg_match('/^(?:\\*|[a-z][a-z0-9_.-]{1,95})$/', $permission) !== 1) {
                return false;
            }
            if (!in_array('*', $user->permissions, true) && !in_array($permission, $user->permissions, true)) {
                return false;
            }
        }

        $_SESSION[self::SESSION_ROLE_PREVIEW] = [
            'actor_id' => $user->id,
            'role' => $role,
            'label' => $label,
            'permissions' => $permissions,
            'started_at' => time(),
        ];
        $this->rotateAuthenticatedSession($user->id);

        return true;
    }

    public function stopRolePreview(): void
    {
        $userId = $this->actualUser()?->id;
        unset($_SESSION[self::SESSION_ROLE_PREVIEW]);
        if ($userId !== null) {
            $this->rotateAuthenticatedSession($userId);
        } else {
            $this->security->regenerateSession();
        }
    }

    /**
     * @return array{role:string,label:string,permissions:list<string>,started_at:int}|null
     */
    public function rolePreview(): ?array
    {
        $user = $this->actualUser();

        return $user !== null ? $this->validatedRolePreview($user) : null;
    }

    public function rolePreviewBlocks(Request $request): bool
    {
        if ($this->rolePreview() === null || !str_starts_with($request->path(), '/admin')) {
            return false;
        }
        if ($request->path() === '/admin/roles/preview/stop') {
            return false;
        }

        return !in_array($request->method(), ['GET', 'HEAD'], true)
            || str_starts_with($request->path(), '/admin/auth/')
            || str_starts_with($request->path(), '/admin/identity/');
    }

    public function loginIdentity(ExternalIdentity $identity): ?User
    {
        $user = $this->users->findByIdentity($identity->provider, $identity->subject);

        if ($user === null) {
            if ($this->firstOwnerBootstrapper?->isAvailable()) {
                try {
                    $user = $this->firstOwnerBootstrapper->bootstrap(
                        $identity,
                        $identity->login !== '' ? $identity->login : $identity->provider
                    );
                } catch (\RuntimeException) {
                    $user = $this->users->findByIdentity($identity->provider, $identity->subject);
                }
            }
            $user ??= $this->users->createPendingFromIdentity($identity);
        }
        if ($user->status === 'blocked') {
            return null;
        }

        unset($_SESSION[self::SESSION_ROLE_PREVIEW]);
        $this->forgetCurrentSession();
        $this->security->regenerateSession();
        $_SESSION[self::SESSION_USER_ID] = $user->id;
        unset($_SESSION[self::SESSION_ACTIVITY_TOUCHED_AT]);
        $this->touchCurrentSession($user->id, true);
        $this->users->touchIdentity($user->id, $identity->provider, $identity->subject);

        return $user;
    }

    public function linkIdentity(ExternalIdentity $identity): bool
    {
        $user = $this->user();

        if ($user === null || $this->users->findByIdentity($identity->provider, $identity->subject) !== null) {
            return false;
        }

        $this->users->linkIdentity($user->id, $identity);
        return true;
    }

    public function unlinkIdentity(string $provider, string $subject): bool
    {
        $user = $this->user();

        return $user !== null && $this->users->unlinkIdentity($user->id, $provider, $subject);
    }

    public function updateProfile(string $displayName, ?string $email): bool
    {
        $user = $this->user();

        return $user !== null && $this->users->updateProfile($user->id, $displayName, $email);
    }

    public function updateAvatar(?string $avatarUrl): bool
    {
        $user = $this->user();

        return $user !== null && $this->users->updateAvatar($user->id, $avatarUrl);
    }

    public function logout(): void
    {
        $this->forgetCurrentSession();
        unset(
            $_SESSION[self::SESSION_USER_ID],
            $_SESSION[self::SESSION_ROLE_PREVIEW],
            $_SESSION[self::SESSION_ACTIVITY_TOUCHED_AT]
        );
        $this->security->regenerateSession();
    }

    private function rotateAuthenticatedSession(int $userId): void
    {
        $this->forgetCurrentSession();
        $this->security->regenerateSession();
        unset($_SESSION[self::SESSION_ACTIVITY_TOUCHED_AT]);
        $this->touchCurrentSession($userId, true);
    }

    private function touchCurrentSession(int $userId, bool $force = false): void
    {
        $now = time();
        $lastTouch = $_SESSION[self::SESSION_ACTIVITY_TOUCHED_AT] ?? 0;
        if (!$force && is_int($lastTouch) && $lastTouch > $now - self::ACTIVITY_TOUCH_INTERVAL_SECONDS) {
            return;
        }
        $sessionHash = $this->currentSessionHash();
        if ($sessionHash === '') {
            return;
        }
        try {
            $this->users->touchSession($userId, $sessionHash);
            $_SESSION[self::SESSION_ACTIVITY_TOUCHED_AT] = $now;
        } catch (\Throwable) {
            // Brak tabeli podczas kontrolowanej aktualizacji nie może zerwać logowania.
        }
    }

    private function forgetCurrentSession(): void
    {
        $sessionHash = $this->currentSessionHash();
        if ($sessionHash === '') {
            return;
        }
        try {
            $this->users->forgetSession($sessionHash);
        } catch (\Throwable) {
            // Czyszczenie obecności jest pomocnicze i nie może blokować wylogowania.
        }
    }

    private function currentSessionHash(): string
    {
        $sessionId = session_id();
        return $sessionId !== '' ? hash('sha256', $sessionId) : '';
    }

    private function canManageRolePreview(User $user): bool
    {
        return $user->isActive()
            && (in_array('*', $user->permissions, true) || in_array('roles.manage', $user->permissions, true));
    }

    /**
     * @return array{role:string,label:string,permissions:list<string>,started_at:int}|null
     */
    private function validatedRolePreview(User $user): ?array
    {
        $preview = $_SESSION[self::SESSION_ROLE_PREVIEW] ?? null;
        if (!is_array($preview)) {
            return null;
        }
        $actorId = $preview['actor_id'] ?? null;
        $role = $preview['role'] ?? null;
        $label = $preview['label'] ?? null;
        $permissions = $preview['permissions'] ?? null;
        $startedAt = $preview['started_at'] ?? null;
        if (
            (!is_int($actorId) && !ctype_digit((string) $actorId))
            || (int) $actorId !== $user->id
            || !is_string($role)
            || preg_match('/^[a-z][a-z0-9_]{1,63}$/', $role) !== 1
            || !is_string($label)
            || $label === ''
            || strlen($label) > 120
            || !is_array($permissions)
            || (!is_int($startedAt) && !ctype_digit((string) $startedAt))
            || (int) $startedAt > time() + 60
            || (int) $startedAt < time() - self::ROLE_PREVIEW_TTL_SECONDS
            || !$this->canManageRolePreview($user)
        ) {
            unset($_SESSION[self::SESSION_ROLE_PREVIEW]);
            return null;
        }

        $normalizedPermissions = [];
        foreach ($permissions as $permission) {
            if (
                !is_string($permission)
                || preg_match('/^(?:\\*|[a-z][a-z0-9_.-]{1,95})$/', $permission) !== 1
                || (!in_array('*', $user->permissions, true) && !in_array($permission, $user->permissions, true))
            ) {
                unset($_SESSION[self::SESSION_ROLE_PREVIEW]);
                return null;
            }
            $normalizedPermissions[] = $permission;
        }

        return [
            'role' => $role,
            'label' => $label,
            'permissions' => array_values(array_unique($normalizedPermissions)),
            'started_at' => (int) $startedAt,
        ];
    }
}
