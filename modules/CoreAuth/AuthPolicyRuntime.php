<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\CoreAuth;

final class AuthPolicyRuntime
{
    private const AFTER_LOGIN_SESSION_KEY = '_miniportal_after_login';

    private static ?AuthPolicyProviderInterface $provider = null;

    public static function use(AuthPolicyProviderInterface $provider): void
    {
        self::$provider = $provider;
    }

    public static function reset(): void
    {
        self::$provider = null;
    }

    public static function provider(): AuthPolicyProviderInterface
    {
        return self::$provider ??= new DefaultAuthPolicyProvider();
    }

    public static function applyLoginRedirectPolicy(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $policy = self::provider();
        switch ($policy->loginRedirectMode()) {
            case 'core_default':
                return;

            case 'admin':
                unset($_SESSION[self::AFTER_LOGIN_SESSION_KEY]);
                return;

            case 'home':
                $_SESSION[self::AFTER_LOGIN_SESSION_KEY] = '/';
                return;

            case 'custom':
                $target = self::normalizeLocalRedirect((string) $policy->loginRedirectTarget());
                if ($target === null) {
                    unset($_SESSION[self::AFTER_LOGIN_SESSION_KEY]);
                    return;
                }
                $_SESSION[self::AFTER_LOGIN_SESSION_KEY] = $target;
                return;

            default:
                unset($_SESSION[self::AFTER_LOGIN_SESSION_KEY]);
        }
    }

    public static function normalizeLocalRedirect(string $redirect): ?string
    {
        $redirect = trim($redirect);
        if (
            $redirect === ''
            || str_contains($redirect, "\r")
            || str_contains($redirect, "\n")
            || str_starts_with($redirect, '//')
            || str_contains($redirect, '\\')
        ) {
            return null;
        }

        if (str_starts_with($redirect, 'index.php?route=/')) {
            $route = substr($redirect, strlen('index.php?route='));
            return self::isBlockedAuthRoute($route) ? null : $redirect;
        }

        if (str_starts_with($redirect, '/')) {
            return self::isBlockedAuthRoute($redirect) ? null : $redirect;
        }

        return null;
    }

    private static function isBlockedAuthRoute(string $route): bool
    {
        return $route === '/admin/login'
            || str_starts_with($route, '/admin/login?')
            || str_starts_with($route, '/admin/login&')
            || str_starts_with($route, '/admin/auth/')
            || str_starts_with($route, '/admin/identity/');
    }
}
