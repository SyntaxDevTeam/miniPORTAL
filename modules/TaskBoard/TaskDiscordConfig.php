<?php

declare(strict_types=1);
namespace SyntaxDevTeam\Cms\Modules\TaskBoard;

use InvalidArgumentException;
use RuntimeException;

/** Secret stays in the protected per-installation config directory. */
final class TaskDiscordConfig
{
    private bool $hubResolved=false;
    private ?\SyntaxDevTeam\Cms\Modules\DiscordIntegration\DiscordHub $discordHub=null;
    private function hub(): ?\SyntaxDevTeam\Cms\Modules\DiscordIntegration\DiscordHub
    {
        if (!$this->hubResolved) {
            $this->hubResolved=true;
            if (class_exists(\SyntaxDevTeam\Cms\Modules\DiscordIntegration\DiscordHub::class)) $this->discordHub=\SyntaxDevTeam\Cms\Modules\DiscordIntegration\DiscordHub::local(dirname($this->file,3));
        }
        return $this->discordHub;
    }
    public function centrallyManaged(): bool { return $this->hub()?->managed('task_board.assignments')??false; }

    public function __construct(private readonly string $file, private readonly string $baseUrl = '') {}

    public static function safeWebhook(string $url): bool
    {
        return preg_match('~\Ahttps://discord\.com/api/webhooks/[0-9]{17,20}/[A-Za-z0-9_-]{40,200}\z~D', $url) === 1;
    }

    public function settings(): array
    {
        $data = $this->read();
        return ['enabled' => !empty($data['enabled']), 'configured' => self::safeWebhook($data['webhook'] ?? ''), 'base_url' => $this->baseUrl];
    }

    public function ready(): bool
    {
        if ($this->centrallyManaged()) return $this->hub()->ready('task_board.assignments') && self::safeBase($this->baseUrl);
        $settings = $this->settings();
        return $settings['enabled'] && $settings['configured'] && self::safeBase($this->baseUrl);
    }

    public function webhook(): string { if ($this->centrallyManaged()) return $this->hub()->destination('task_board.assignments'); return (string)($this->read()['webhook'] ?? ''); }
    public function taskUrl(int $id): string { return rtrim($this->baseUrl, '/') . '/index.php?route=%2Faccount%2Ftasks%2Fview&id=' . $id; }

    public function save(string $webhook, bool $enabled, bool $clear = false): void
    {
        $webhook = trim($webhook);
        if ($webhook === '' && !$clear) $webhook = (string)($this->read()['webhook'] ?? '');
        if ($clear) $webhook = '';
        if ($webhook !== '' && !self::safeWebhook($webhook)) throw new InvalidArgumentException('Podaj poprawny webhook HTTPS z discord.com.');
        if ($enabled && (!self::safeWebhook($webhook) || !self::safeBase($this->baseUrl))) throw new InvalidArgumentException('Włączenie powiadomień wymaga webhooka i poprawnego publicznego adresu HTTPS portalu.');
        $directory = dirname($this->file);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) throw new RuntimeException('Brak katalogu konfiguracji.');
        $temporary = tempnam($directory, '.task-discord-');
        if ($temporary === false) throw new RuntimeException('Nie można zapisać konfiguracji.');
        try {
            if (!chmod($temporary, 0600) || file_put_contents($temporary, json_encode(['enabled' => $enabled, 'webhook' => $webhook], JSON_THROW_ON_ERROR), LOCK_EX) === false || !rename($temporary, $this->file)) throw new RuntimeException('Nie można zapisać konfiguracji.');
        } finally { if (is_file($temporary)) unlink($temporary); }
    }

    private function read(): array
    {
        if (!is_file($this->file)) return [];
        $contents = file_get_contents($this->file);
        if (!is_string($contents)) throw new RuntimeException('Nie można odczytać konfiguracji Discord.');
        $data = json_decode($contents, true, 8, JSON_THROW_ON_ERROR);
        return is_array($data) ? $data : [];
    }

    private static function safeBase(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false && parse_url($url, PHP_URL_SCHEME) === 'https'
            && parse_url($url, PHP_URL_USER) === null && parse_url($url, PHP_URL_PASS) === null
            && parse_url($url, PHP_URL_QUERY) === null && parse_url($url, PHP_URL_FRAGMENT) === null;
    }
}
