<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\Uptime;

use SyntaxDevTeam\Cms\Core\TemplateCacheInterface;

final class UptimeMonitorChecker
{
    public function __construct(
        private readonly UptimeRepository $uptime,
        private readonly ?TemplateCacheInterface $cache = null,
    ) {
    }

    /** @return array{changed:int,notified:int} */
    public function check(): array
    {
        $changed = 0;
        $notified = 0;
        foreach ($this->uptime->staleCandidates() as $monitor) {
            $detail = 'No heartbeat within ' . $monitor->checkIntervalMinutes . ' minutes';
            $sent = $this->notifyStale($monitor, $detail);
            if ($this->uptime->markStale($monitor, 'Offline', $sent)) {
                $changed++;
                if ($sent) {
                    $notified++;
                }
            }
        }
        if ($changed > 0) {
            $this->cache?->invalidateTags(['homepage', 'widgets', 'uptime', 'theme']);
        }

        return ['changed' => $changed, 'notified' => $notified];
    }

    private function notifyStale(UptimeMonitor $monitor, string $message): bool
    {
        if ($monitor->notificationType !== 'discord_webhook' || $monitor->notificationSentAt !== null) {
            return false;
        }
        if (!$this->safeDiscordWebhook($monitor->notificationWebhookUrl)) {
            return false;
        }

        $payload = json_encode([
            'content' => ':red_circle: **' . $monitor->name . '** is offline. ' . $message . '.',
        ], JSON_UNESCAPED_SLASHES);
        if (!is_string($payload)) {
            return false;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $payload,
                'timeout' => 5,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);
        $body = @file_get_contents($monitor->notificationWebhookUrl, false, $context);
        if ($body === false) {
            return false;
        }
        foreach ($http_response_header ?? [] as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $matches) === 1) {
                $status = (int) $matches[1];
                return $status >= 200 && $status < 300;
            }
        }

        return true;
    }

    private function safeDiscordWebhook(string $value): bool
    {
        if (!str_starts_with($value, 'https://') || filter_var($value, FILTER_VALIDATE_URL) === false) {
            return false;
        }
        $host = strtolower((string) parse_url($value, PHP_URL_HOST));
        $path = (string) parse_url($value, PHP_URL_PATH);

        return in_array($host, ['discord.com', 'discordapp.com'], true)
            && str_starts_with($path, '/api/webhooks/');
    }
}
