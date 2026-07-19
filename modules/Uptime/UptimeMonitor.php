<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\Uptime;

final readonly class UptimeMonitor
{
    public function __construct(
        public int $id,
        public string $key,
        public string $uuid,
        public string $name,
        public string $targetUrl,
        public string $type,
        public string $expectedEvent,
        public int $expectedStatus,
        public int $checkIntervalMinutes,
        public string $notificationType,
        public string $notificationWebhookUrl,
        public string $lastStatus,
        public string $lastEvent,
        public string $lastMessage,
        public ?string $lastEventAt,
        public ?string $lastCheckedAt,
        public ?string $notificationSentAt,
        public int $sortOrder,
        public bool $visible,
        public bool $active,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }
}
