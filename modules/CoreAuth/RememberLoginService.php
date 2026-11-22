<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\CoreAuth;

use PDO;
use SyntaxDevTeam\Cms\Core\Security;
use SyntaxDevTeam\Cms\Database\CrudApp;

/** Device credentials are independent from OAuth access/refresh tokens. */
final class RememberLoginService
{
    public const TTL_SECONDS = 30 * 86400;

    public function __construct(
        private readonly CrudApp $database,
        private readonly Security $security,
    ) {
    }

    public function issue(User $user): ?string
    {
        if (!$this->security->canRememberLogin()) {
            return null;
        }
        $selector = bin2hex(random_bytes(16));
        $validator = bin2hex(random_bytes(32));
        $expires = time() + self::TTL_SECONDS;
        $this->database->query('DELETE FROM auth_remember_tokens WHERE expires_at < :now', [':now' => time()]);
        $this->database->create('auth_remember_tokens', [
            'selector' => $selector,
            'validator_hash' => hash('sha256', $validator),
            'user_id' => $user->id,
            'session_version' => $user->sessionVersion,
            'expires_at' => $expires,
        ]);
        $this->security->setRememberCookie($selector . '.' . $validator, $expires);
        return $selector;
    }

    /** @return array{user_id:int,session_version:int,selector:string}|null */
    public function consume(): ?array
    {
        $credential = $this->credential();
        if ($credential === null || !$this->security->canRememberLogin()) {
            return null;
        }
        [$selector, $validator] = $credential;
        $pdo = $this->database->connection()->pdo;
        $pdo->beginTransaction();
        try {
            // Lock and check the current account epoch as well as the device token.
            $row = $this->database->query(
                'SELECT t.*, u.status, u.session_version AS current_version '
                . 'FROM auth_remember_tokens t JOIN users u ON u.id = t.user_id '
                . 'WHERE t.selector = :selector'
                . ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : ''),
                [':selector' => $selector]
            )?->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row) || (int) $row['expires_at'] <= time()
                || $row['status'] === 'blocked'
                || (int) $row['session_version'] !== (int) $row['current_version']
                || !hash_equals((string) $row['validator_hash'], hash('sha256', $validator))) {
                $pdo->commit();
                // A concurrent request may already have rotated the cookie. Do not overwrite it.
                return null;
            }
            $nextValidator = bin2hex(random_bytes(32));
            $this->database->query(
                'UPDATE auth_remember_tokens SET validator_hash = :hash WHERE selector = :selector',
                [':hash' => hash('sha256', $nextValidator), ':selector' => $selector]
            );
            $pdo->commit();
            // Rotation retains the original absolute 30-day expiry.
            $this->security->setRememberCookie($selector . '.' . $nextValidator, (int) $row['expires_at']);
            return ['user_id' => (int) $row['user_id'],
                'session_version' => (int) $row['session_version'], 'selector' => $selector];
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function forget(?string $sessionSelector = null): void
    {
        try {
            if ($sessionSelector !== null) {
                $this->database->delete('auth_remember_tokens', ['selector' => $sessionSelector]);
            }
            $credential = $this->credential();
            if ($credential !== null) {
                $this->database->delete('auth_remember_tokens', [
                    'selector' => $credential[0], 'validator_hash' => hash('sha256', $credential[1]),
                ]);
            }
        } finally {
            $this->security->setRememberCookie('', time() - 3600);
        }
    }

    /** @return array{string,string}|null */
    private function credential(): ?array
    {
        $cookie = $this->security->rememberCookie();
        if (preg_match('/^([a-f0-9]{32})\.([a-f0-9]{64})$/D', $cookie, $matches) !== 1) {
            return null;
        }
        return [$matches[1], $matches[2]];
    }
}
