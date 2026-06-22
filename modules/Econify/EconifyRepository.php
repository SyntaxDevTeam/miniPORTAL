<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\Econify;

use PDO;
use RuntimeException;
use SyntaxDevTeam\Cms\Database\CrudApp;

final class EconifyRepository
{
    public function __construct(private readonly CrudApp $database)
    {
    }

    /** @return list<array<string, mixed>> */
    public function features(): array
    {
        return $this->rows('SELECT feature_key, label, description, is_enabled, sort_order FROM econify_features ORDER BY sort_order, feature_key');
    }

    public function featureEnabled(string $key): bool
    {
        return (int) ($this->database->query(
            'SELECT is_enabled FROM econify_features WHERE feature_key = :feature_key',
            [':feature_key' => $key]
        )?->fetchColumn() ?? 0) === 1;
    }

    public function setFeature(string $key, bool $enabled, int $userId): bool
    {
        $statement = $this->database->update('econify_features', [
            'is_enabled' => $enabled ? 1 : 0,
            'updated_by' => $userId,
        ], ['feature_key' => $key]);

        return $statement !== null && $statement->rowCount() === 1;
    }

    /** @return array<string, mixed> */
    public function platformSettings(): array
    {
        return $this->row('SELECT default_locale, default_daily_amount, default_work_min, default_work_max, freemium_shop_limit FROM econify_platform_settings WHERE id = 1')
            ?? ['default_locale' => 'pl', 'default_daily_amount' => 250, 'default_work_min' => 50, 'default_work_max' => 150, 'freemium_shop_limit' => 5];
    }

    /** @param array<string, mixed> $settings */
    public function updatePlatformSettings(array $settings, int $userId): void
    {
        $this->database->update('econify_platform_settings', $settings + ['updated_by' => $userId], ['id' => 1]);
    }

    /** @return list<array<string, mixed>> */
    public function guilds(): array
    {
        return $this->rows(
            'SELECT g.*, u.display_name AS owner_name, '
            . '(SELECT COUNT(*) FROM econify_memberships m WHERE m.guild_id = g.id AND m.is_active = 1) AS member_count '
            . 'FROM econify_guilds g JOIN users u ON u.id = g.owner_user_id ORDER BY g.created_at DESC'
        );
    }

    /** @return list<array{id:int,label:string}> */
    public function users(): array
    {
        return array_map(
            static fn (array $row): array => ['id' => (int) $row['id'], 'label' => (string) $row['display_name']],
            $this->rows("SELECT id, display_name FROM users WHERE status = 'active' ORDER BY display_name, id")
        );
    }

    public function createGuild(string $discordId, string $name, int $ownerId, string $plan): int
    {
        $defaults = $this->platformSettings();
        $guildId = (int) $this->database->create('econify_guilds', [
            'discord_guild_id' => $discordId,
            'name' => $name,
            'owner_user_id' => $ownerId,
            'plan' => $plan,
            'locale' => (string) $defaults['default_locale'],
            'daily_amount' => (int) $defaults['default_daily_amount'],
            'work_min' => (int) $defaults['default_work_min'],
            'work_max' => (int) $defaults['default_work_max'],
        ]);
        $this->database->create('econify_memberships', [
            'guild_id' => $guildId,
            'user_id' => $ownerId,
            'discord_user_id' => 'pending:' . $ownerId,
            'access_role' => 'guild_owner',
        ]);
        $this->ensureWallet($guildId, $ownerId);

        return $guildId;
    }

    /** @return array<string, mixed>|null */
    public function guild(int $guildId): ?array
    {
        return $this->row('SELECT * FROM econify_guilds WHERE id = :id', [':id' => $guildId]);
    }

    /** @return array<string, mixed>|null */
    public function guildByDiscordId(string $discordGuildId): ?array
    {
        return $this->row('SELECT * FROM econify_guilds WHERE discord_guild_id = :discord_guild_id', [':discord_guild_id' => $discordGuildId]);
    }

    /** @return list<array<string, mixed>> */
    public function memberships(int $userId): array
    {
        return $this->rows(
            'SELECT m.guild_id, m.access_role, m.discord_user_id, g.name, g.plan, g.currency_name, '
            . 'g.shop_enabled, g.market_enabled, g.is_active FROM econify_memberships m '
            . 'JOIN econify_guilds g ON g.id = m.guild_id WHERE m.user_id = :user_id AND m.is_active = 1 '
            . 'AND g.is_active = 1 ORDER BY g.name',
            [':user_id' => $userId]
        );
    }

    /** @return array<string, mixed>|null */
    public function membership(int $guildId, int $userId): ?array
    {
        return $this->row(
            'SELECT m.*, g.name, g.plan, g.currency_name, g.locale, g.daily_amount, g.work_min, g.work_max, '
            . 'g.transfer_tax_bps, g.vip_role_id, g.vip_daily_amount, g.shop_enabled, g.market_enabled '
            . 'FROM econify_memberships m JOIN econify_guilds g ON g.id = m.guild_id '
            . 'WHERE m.guild_id = :guild_id AND m.user_id = :user_id AND m.is_active = 1 AND g.is_active = 1',
            [':guild_id' => $guildId, ':user_id' => $userId]
        );
    }

    /** @return array{guild_id:int,user_id:int}|null */
    public function identity(string $discordGuildId, string $discordUserId): ?array
    {
        $row = $this->row(
            'SELECT m.guild_id, m.user_id FROM econify_memberships m JOIN econify_guilds g ON g.id = m.guild_id '
            . 'WHERE g.discord_guild_id = :guild AND m.discord_user_id = :user AND g.is_active = 1 AND m.is_active = 1',
            [':guild' => $discordGuildId, ':user' => $discordUserId]
        );
        return $row === null ? null : ['guild_id' => (int) $row['guild_id'], 'user_id' => (int) $row['user_id']];
    }

    /** @param array<string, mixed> $settings */
    public function updateGuild(int $guildId, array $settings): bool
    {
        $statement = $this->database->update('econify_guilds', $settings, ['id' => $guildId]);
        return $statement !== null;
    }

    public function addMembership(int $guildId, int $userId, string $discordUserId, string $role): void
    {
        $this->database->query(
            'INSERT INTO econify_memberships (guild_id, user_id, discord_user_id, access_role, is_active) '
            . 'VALUES (:guild_id, :user_id, :discord_user_id, :access_role, 1) '
            . 'ON DUPLICATE KEY UPDATE discord_user_id = VALUES(discord_user_id), access_role = VALUES(access_role), is_active = 1',
            [':guild_id' => $guildId, ':user_id' => $userId, ':discord_user_id' => $discordUserId, ':access_role' => $role]
        );
        $this->ensureWallet($guildId, $userId);
    }

    /** @return array<string, mixed> */
    public function wallet(int $guildId, int $userId): array
    {
        $this->ensureWallet($guildId, $userId);
        return $this->row(
            'SELECT balance, experience, level, updated_at FROM econify_wallets WHERE guild_id = :guild_id AND user_id = :user_id',
            [':guild_id' => $guildId, ':user_id' => $userId]
        ) ?? ['balance' => 0, 'experience' => 0, 'level' => 1, 'updated_at' => ''];
    }

    /** @return list<array<string, mixed>> */
    public function transactions(int $guildId, int $userId, int $limit = 30): array
    {
        $limit = max(1, min(100, $limit));
        return $this->rows(
            'SELECT transaction_type, amount, balance_after, description, created_at FROM econify_transactions '
            . 'WHERE guild_id = :guild_id AND user_id = :user_id ORDER BY id DESC LIMIT ' . $limit,
            [':guild_id' => $guildId, ':user_id' => $userId]
        );
    }

    /** @return list<array<string, mixed>> */
    public function shopItems(int $guildId, bool $activeOnly = true): array
    {
        return $this->rows(
            'SELECT id, name, description, price, stock, delivery_type, delivery_reference, is_active FROM econify_shop_items '
            . 'WHERE guild_id = :guild_id' . ($activeOnly ? ' AND is_active = 1' : '') . ' ORDER BY price, name',
            [':guild_id' => $guildId]
        );
    }

    public function addShopItem(int $guildId, string $name, string $description, int $price, ?int $stock, string $deliveryType, ?string $reference): int
    {
        return (int) $this->database->create('econify_shop_items', [
            'guild_id' => $guildId,
            'name' => $name,
            'description' => $description,
            'price' => $price,
            'stock' => $stock,
            'delivery_type' => $deliveryType,
            'delivery_reference' => $reference,
        ]);
    }

    public function activeShopItemCount(int $guildId): int
    {
        return (int) $this->database->count('econify_shop_items', ['guild_id' => $guildId, 'is_active' => 1]);
    }

    public function purchaseItem(int $guildId, int $userId, int $itemId): int
    {
        $orderId = 0;
        $this->database->action(function () use ($guildId, $userId, $itemId, &$orderId): void {
            $item = $this->row(
                'SELECT id, name, price, stock FROM econify_shop_items WHERE id = :id AND guild_id = :guild_id AND is_active = 1 FOR UPDATE',
                [':id' => $itemId, ':guild_id' => $guildId]
            );
            $wallet = $this->row(
                'SELECT balance FROM econify_wallets WHERE guild_id = :guild_id AND user_id = :user_id FOR UPDATE',
                [':guild_id' => $guildId, ':user_id' => $userId]
            );
            if ($item === null || $wallet === null) {
                throw new RuntimeException('Przedmiot lub portfel nie istnieje.');
            }
            $price = (int) $item['price'];
            if ((int) $wallet['balance'] < $price) {
                throw new RuntimeException('Brak wystarczających środków.');
            }
            if ($item['stock'] !== null && (int) $item['stock'] < 1) {
                throw new RuntimeException('Przedmiot jest wyprzedany.');
            }
            $balance = (int) $wallet['balance'] - $price;
            $this->database->update('econify_wallets', ['balance' => $balance], ['guild_id' => $guildId, 'user_id' => $userId]);
            if ($item['stock'] !== null) {
                $this->database->update('econify_shop_items', ['stock' => (int) $item['stock'] - 1], ['id' => $itemId]);
            }
            $orderId = (int) $this->database->create('econify_shop_orders', [
                'guild_id' => $guildId, 'item_id' => $itemId, 'user_id' => $userId, 'price' => $price,
            ]);
            $this->recordTransaction($guildId, $userId, 'shop_purchase', -$price, $balance, 'Zakup: ' . (string) $item['name'], 'shop:' . $orderId);
        });

        return $orderId;
    }

    /** @return list<array<string, mixed>> */
    public function market(int $guildId, int $userId): array
    {
        return $this->rows(
            'SELECT a.id, a.symbol, a.name, a.current_price, COALESCE(h.quantity, 0) AS quantity, '
            . 'COALESCE(h.average_price, 0) AS average_price FROM econify_market_assets a '
            . 'LEFT JOIN econify_market_holdings h ON h.asset_id = a.id AND h.user_id = :user_id '
            . 'WHERE a.guild_id = :guild_id AND a.is_active = 1 ORDER BY a.symbol',
            [':user_id' => $userId, ':guild_id' => $guildId]
        );
    }

    /** @return list<array<string, mixed>> */
    public function quotes(int $assetId): array
    {
        return $this->rows(
            'SELECT price, quoted_at FROM econify_market_quotes WHERE asset_id = :asset_id ORDER BY quoted_at DESC, id DESC LIMIT 30',
            [':asset_id' => $assetId]
        );
    }

    public function trade(int $guildId, int $userId, int $assetId, int $quantity, bool $buy): void
    {
        if ($quantity < 1 || $quantity > 100000) {
            throw new RuntimeException('Nieprawidłowa liczba jednostek.');
        }
        $this->database->action(function () use ($guildId, $userId, $assetId, $quantity, $buy): void {
            $asset = $this->row('SELECT id, symbol, current_price FROM econify_market_assets WHERE id = :id AND guild_id = :guild_id AND is_active = 1 FOR UPDATE', [':id' => $assetId, ':guild_id' => $guildId]);
            $wallet = $this->row('SELECT balance FROM econify_wallets WHERE guild_id = :guild_id AND user_id = :user_id FOR UPDATE', [':guild_id' => $guildId, ':user_id' => $userId]);
            if ($asset === null || $wallet === null) {
                throw new RuntimeException('Aktywo lub portfel nie istnieje.');
            }
            $holding = $this->row('SELECT quantity, average_price FROM econify_market_holdings WHERE asset_id = :asset_id AND user_id = :user_id FOR UPDATE', [':asset_id' => $assetId, ':user_id' => $userId]);
            $owned = (int) ($holding['quantity'] ?? 0);
            $price = (int) $asset['current_price'];
            $value = $price * $quantity;
            $balance = (int) $wallet['balance'];
            if ($buy && $balance < $value) {
                throw new RuntimeException('Brak wystarczających środków.');
            }
            if (!$buy && $owned < $quantity) {
                throw new RuntimeException('Brak wystarczającej liczby jednostek.');
            }
            $newQuantity = $buy ? $owned + $quantity : $owned - $quantity;
            $newBalance = $buy ? $balance - $value : $balance + $value;
            $average = $buy ? (int) round(((int) ($holding['average_price'] ?? 0) * $owned + $value) / $newQuantity) : (int) ($holding['average_price'] ?? 0);
            $this->database->query(
                'INSERT INTO econify_market_holdings (asset_id, user_id, quantity, average_price) VALUES (:asset_id, :user_id, :quantity, :average_price) '
                . 'ON DUPLICATE KEY UPDATE quantity = VALUES(quantity), average_price = VALUES(average_price)',
                [':asset_id' => $assetId, ':user_id' => $userId, ':quantity' => $newQuantity, ':average_price' => $average]
            );
            $this->database->update('econify_wallets', ['balance' => $newBalance], ['guild_id' => $guildId, 'user_id' => $userId]);
            $this->recordTransaction($guildId, $userId, $buy ? 'market_buy' : 'market_sell', $buy ? -$value : $value, $newBalance, ($buy ? 'Kupno ' : 'Sprzedaż ') . $quantity . ' ' . (string) $asset['symbol']);
        });
    }

    public function addAsset(int $guildId, string $symbol, string $name, int $price): int
    {
        $id = (int) $this->database->create('econify_market_assets', ['guild_id' => $guildId, 'symbol' => $symbol, 'name' => $name, 'current_price' => $price]);
        $this->database->create('econify_market_quotes', ['asset_id' => $id, 'price' => $price]);
        return $id;
    }

    public function updateAssetPrice(int $guildId, int $assetId, int $price): bool
    {
        $statement = $this->database->update('econify_market_assets', ['current_price' => $price], ['id' => $assetId, 'guild_id' => $guildId]);
        if ($statement === null || $statement->rowCount() !== 1) {
            return false;
        }
        $this->database->create('econify_market_quotes', ['asset_id' => $assetId, 'price' => $price]);
        return true;
    }

    public function syncEconomy(int $guildId, int $userId, string $type, int $amount, int $experience, int $level, string $reference, string $description): bool
    {
        $created = false;
        $this->database->action(function () use ($guildId, $userId, $type, $amount, $experience, $level, $reference, $description, &$created): void {
            $this->ensureWallet($guildId, $userId);
            if ($this->database->count('econify_transactions', ['guild_id' => $guildId, 'external_reference' => $reference]) > 0) {
                return;
            }
            $wallet = $this->wallet($guildId, $userId);
            $balance = max(0, (int) $wallet['balance'] + $amount);
            $this->database->update('econify_wallets', ['balance' => $balance, 'experience' => $experience, 'level' => $level], ['guild_id' => $guildId, 'user_id' => $userId]);
            $this->recordTransaction($guildId, $userId, $type, $amount, $balance, $description, $reference);
            $created = true;
        });
        return $created;
    }

    /** @return array{guilds:int,players:int,orders:int,volume:int} */
    public function stats(): array
    {
        return [
            'guilds' => (int) $this->database->count('econify_guilds', ['is_active' => 1]),
            'players' => (int) $this->database->count('econify_memberships', ['is_active' => 1]),
            'orders' => (int) $this->database->count('econify_shop_orders'),
            'volume' => (int) ($this->database->query('SELECT COALESCE(SUM(price), 0) FROM econify_shop_orders')?->fetchColumn() ?? 0),
        ];
    }

    private function ensureWallet(int $guildId, int $userId): void
    {
        $this->database->query(
            'INSERT IGNORE INTO econify_wallets (guild_id, user_id) VALUES (:guild_id, :user_id)',
            [':guild_id' => $guildId, ':user_id' => $userId]
        );
    }

    private function recordTransaction(int $guildId, int $userId, string $type, int $amount, int $balance, string $description, ?string $reference = null): void
    {
        $this->database->create('econify_transactions', [
            'guild_id' => $guildId, 'user_id' => $userId, 'transaction_type' => $type,
            'amount' => $amount, 'balance_after' => $balance, 'description' => $description,
            'external_reference' => $reference,
        ]);
    }

    /** @param array<string, scalar> $parameters @return list<array<string, mixed>> */
    private function rows(string $sql, array $parameters = []): array
    {
        return $this->database->query($sql, $parameters)?->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @param array<string, scalar> $parameters @return array<string, mixed>|null */
    private function row(string $sql, array $parameters = []): ?array
    {
        $row = $this->database->query($sql, $parameters)?->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }
}
