<?php

declare(strict_types=1);
namespace SyntaxDevTeam\Cms\Modules\TaskBoard;

final class DiscordWebhookTransport
{
    /** Returns only status/backoff; secret URLs and Discord bodies never reach logs. */
    public function send(string $url, array $payload): array
    {
        if (str_starts_with($url,'discord-route:')) {
            return class_exists(\SyntaxDevTeam\Cms\Modules\DiscordIntegration\DiscordHub::class) ? (\SyntaxDevTeam\Cms\Modules\DiscordIntegration\DiscordHub::local(dirname(__DIR__,2))?->send($url,$payload)??['status'=>410]) : ['status'=>410];
        }

        if (!TaskDiscordConfig::safeWebhook($url)) throw new \InvalidArgumentException('Nieprawidłowy webhook.');
        $context = stream_context_create([
            'http' => ['method' => 'POST', 'header' => "Content-Type: application/json\r\nAccept: application/json\r\n", 'content' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 'timeout' => 5, 'ignore_errors' => true, 'follow_location' => 0],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $body = @file_get_contents($url . '?wait=true', false, $context, 0, 65536);
        $status = 0;
        foreach ($http_response_header ?? [] as $header) if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $matches)) $status = (int)$matches[1];
        $data = is_string($body) ? json_decode($body, true) : null;
        $retry = is_array($data) && is_numeric($data['retry_after'] ?? null) ? (int)ceil((float)$data['retry_after']) : 0;
        return ['status' => $body === false ? 0 : $status, 'retry_after' => max(0, min(3600, $retry))];
    }
}
