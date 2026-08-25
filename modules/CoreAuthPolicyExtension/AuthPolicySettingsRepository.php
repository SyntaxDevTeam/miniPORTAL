<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\CoreAuthPolicyExtension;

use PDO;
use RuntimeException;
use SyntaxDevTeam\Cms\Database\CrudApp;

final class AuthPolicySettingsRepository
{
    private const FIXED_KEYS = [
        'redirect.mode',
        'redirect.target',
        'registration.mode',
        'registration.default_role',
        'providers.order',
        'ui.login_footer',
        'ui.login_locations',
    ];

    public function __construct(
        private readonly CrudApp $database,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        try {
            $statement = $this->database->query(
                'SELECT setting_key, setting_value FROM core_auth_policy_settings ORDER BY setting_key'
            );
            if ($statement === null) {
                return [];
            }
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }

        $settings = [];
        foreach ($rows as $row) {
            $key = (string) ($row['setting_key'] ?? '');
            if ($this->isAllowedKey($key)) {
                $settings[$key] = (string) ($row['setting_value'] ?? '');
            }
        }

        return $settings;
    }

    /**
     * @param array<string, string> $settings
     */
    public function save(array $settings): void
    {
        foreach ($settings as $key => $value) {
            if (!$this->isAllowedKey($key)) {
                throw new RuntimeException("Nieobsługiwane ustawienie polityki logowania: {$key}.");
            }
            if (strlen($value) > 2048) {
                throw new RuntimeException("Wartość ustawienia {$key} jest zbyt długa.");
            }
        }

        $pdo = $this->database->connection()->pdo;
        $pdo->beginTransaction();
        try {
            $statement = $pdo->prepare(
                'INSERT INTO core_auth_policy_settings (setting_key, setting_value) '
                . 'VALUES (:setting_key, :setting_value) '
                . 'ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP'
            );
            foreach ($settings as $key => $value) {
                $statement->execute([
                    ':setting_key' => $key,
                    ':setting_value' => $value,
                ]);
            }
            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public static function providerEnabledKey(string $provider): string
    {
        return 'provider.' . $provider . '.enabled';
    }

    public static function providerVisibleKey(string $provider): string
    {
        return 'provider.' . $provider . '.visible';
    }

    private function isAllowedKey(string $key): bool
    {
        if (in_array($key, self::FIXED_KEYS, true)) {
            return true;
        }

        return preg_match('/^provider\.[a-z][a-z0-9_-]{1,31}\.(?:enabled|visible)$/', $key) === 1;
    }
}
