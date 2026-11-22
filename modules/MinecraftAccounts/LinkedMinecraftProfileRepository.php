<?php

declare(strict_types=1);
namespace SyntaxDevTeam\Cms\Modules\MinecraftAccounts;

use PDO;
use SyntaxDevTeam\Cms\Database\CrudApp;

final class LinkedMinecraftProfileRepository
{
    public function __construct(private readonly CrudApp $database) {}

    public function findByUserId(int $id): ?LinkedMinecraftProfile { return $this->one('user_id', $id); }
    public function findByUuid(string $uuid): ?LinkedMinecraftProfile { return $this->one('minecraft_uuid', $uuid); }

    public function save(int $userId, MinecraftProfile $profile, ?callable $audit = null): LinkedMinecraftProfile
    {
        if (preg_match('/^[A-Za-z0-9_]{3,16}$/D', $profile->username) !== 1
            || preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/D', $profile->uuid) !== 1) {
            throw new MinecraftAccountException('Otrzymano nieprawidłowy profil Minecraft.');
        }
        $pdo = $this->database->connection()->pdo;
        $pdo->beginTransaction();
        try {
            $this->lockActiveUser($userId);
            $now = gmdate('Y-m-d H:i:s');
            $data = ['minecraft_uuid' => $profile->uuid, 'minecraft_username' => $profile->username, 'verified_at' => $now, 'updated_at' => $now, 'verification_method' => 'microsoft_xbox'];
            // Never upsert on UUID/name: another account's unique key must fail, not update its row.
            if ($this->findByUserId($userId) !== null) $this->database->update('minecraft_account_profiles', $data, ['user_id' => $userId]);
            else $this->database->insert('minecraft_account_profiles', ['user_id' => $userId, 'created_at' => $now] + $data);
            $this->database->delete('minecraft_account_revocations', ['user_id' => $userId]);
            if ($audit !== null) $audit();
            $result = $this->findByUserId($userId) ?? throw new MinecraftAccountException('Nie można zapisać profilu Minecraft.');
            $pdo->commit();
            return $result;
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($exception instanceof \PDOException) throw new MinecraftAccountException('Ten profil jest już połączony albo zapis się nie powiódł.');
            throw $exception;
        }
    }

    /** Revoke the proof and record audit atomically; consumer notifications run after commit. */
    public function unlink(int $userId, string $expectedUuid, callable $audit): LinkedMinecraftProfile
    {
        $pdo = $this->database->connection()->pdo;
        $pdo->beginTransaction();
        try {
            $this->lockActiveUser($userId);
            $profile = $this->findByUserId($userId);
            if ($profile === null || !hash_equals($profile->uuid, $expectedUuid)) throw new MinecraftAccountException('Połączenie zostało zmienione albo nie istnieje.');
            $this->database->delete('minecraft_account_profiles', ['user_id' => $userId]);
            $revoked = ['revoked_at' => gmdate('Y-m-d H:i:s')];
            if ($this->database->count('minecraft_account_revocations', ['user_id' => $userId])) $this->database->update('minecraft_account_revocations', $revoked, ['user_id' => $userId]);
            else $this->database->insert('minecraft_account_revocations', ['user_id' => $userId] + $revoked);
            $audit($profile);
            $pdo->commit();
            return $profile;
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }
    }

    /** Controlled deployment import: preserve confirmation times; never overwrite an existing different claim. */
    public function importVerifiedProfiles(array $rows, callable $audit): int
    {
        $pdo = $this->database->connection()->pdo;
        $pdo->beginTransaction();
        $count = 0;
        try {
            usort($rows, static fn (array $a, array $b): int => (int) $a['user_id'] <=> (int) $b['user_id']);
            foreach ($rows as $row) {
                $id = (int) ($row['user_id'] ?? 0);
                $uuid = strtolower((string) ($row['minecraft_uuid'] ?? ''));
                $nick = (string) ($row['minecraft_username'] ?? '');
                if ($id < 1 || preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/D', $uuid) !== 1 || preg_match('/^[A-Za-z0-9_]{3,16}$/D', $nick) !== 1
                    || preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', (string) ($row['verified_at'] ?? '')) !== 1) {
                    throw new MinecraftAccountException('Nieprawidłowy historyczny profil Minecraft.');
                }
                $lock = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
                if ($this->database->query('SELECT id FROM users WHERE id=:id' . $lock, [':id'=>$id])?->fetchColumn() === false) throw new MinecraftAccountException('Brak właściciela historycznego profilu.');
                if ($this->database->count('minecraft_account_revocations', ['user_id' => $id])) continue;
                $existing = $this->findByUserId($id);
                if ($existing !== null) {
                    if ($existing->uuid !== $uuid || strcasecmp($existing->username, $nick) !== 0) throw new MinecraftAccountException('Konflikt z nowszym powiązaniem Minecraft. Import wycofano.');
                    continue;
                }
                $method = ($row['verification_method'] ?? '') === 'gateway_online_mode' ? 'gateway_online_mode' : 'microsoft_xbox';
                $this->database->insert('minecraft_account_profiles', ['user_id'=>$id, 'minecraft_uuid'=>$uuid, 'minecraft_username'=>$nick,
                    'verified_at'=>$row['verified_at'], 'verification_method'=>$method, 'created_at'=>$row['created_at'] ?? $row['verified_at'],
                    'updated_at'=>$row['updated_at'] ?? $row['verified_at']]);
                $count++;
            }
            $audit($count);
            $pdo->commit();
            return $count;
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }
    }

    private function lockActiveUser(int $id): void
    {
        $lock = $this->database->connection()->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
        $status = $this->database->query('SELECT status FROM users WHERE id = :id' . $lock, [':id' => $id])?->fetchColumn();
        if ($status !== 'active') throw new MinecraftAccountException('Połączenie wymaga aktywnego konta.');
    }

    private function one(string $column, int|string $value): ?LinkedMinecraftProfile
    {
        $row = $this->database->query('SELECT user_id, minecraft_uuid, minecraft_username, verified_at, created_at, updated_at, verification_method '
            . 'FROM minecraft_account_profiles WHERE ' . $column . ' = :value LIMIT 1', [':value' => $value])?->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? new LinkedMinecraftProfile((int) $row['user_id'], (string) $row['minecraft_uuid'],
            (string) $row['minecraft_username'], (string) $row['verified_at'], (string) $row['created_at'], (string) $row['updated_at'], (string) $row['verification_method']) : null;
    }
}
