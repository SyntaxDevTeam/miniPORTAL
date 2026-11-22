<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\CoreAuth;

/** A short-lived, account-bound notice, including an in-game linking code when requested. */
final class LinkedAccountNoticeStore
{
    private const KEY = '_miniportal_linked_account_notice';

    public static function put(int $userId, string $message, string $variant = 'info'): void
    {
        $_SESSION[self::KEY] = ['user' => $userId, 'message' => $message,
            'variant' => in_array($variant, ['info', 'success', 'warning', 'danger'], true) ? $variant : 'info',
            'expires' => time() + 300];
    }

    /** @return array{message:string,variant:string}|null */
    public static function consume(int $userId): ?array
    {
        $notice = $_SESSION[self::KEY] ?? null;
        unset($_SESSION[self::KEY]);
        if (!is_array($notice) || ($notice['user'] ?? 0) !== $userId || ($notice['expires'] ?? 0) < time()) return null;
        return ['message' => (string) $notice['message'], 'variant' => (string) $notice['variant']];
    }
}
