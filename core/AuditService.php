<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

use DateTimeImmutable;
use DateTimeZone;
use JsonException;

final class AuditService
{
    public function __construct(
        private readonly ?AuditStoreInterface $store,
        private readonly string $ipHashKey = '',
    ) {
    }

    public function record(AuditEntry $entry, ?Request $request = null): string
    {
        $eventId = $this->uuidV4();
        if ($this->store === null) {
            return $eventId;
        }

        $tenant = $entry->tenant ?? TenantContext::platform();
        $ipHash = null;
        $userAgent = null;
        if ($request !== null) {
            $ip = $request->clientIp();
            if ($ip !== '' && $this->ipHashKey !== '') {
                $ipHash = hash_hmac('sha256', $ip, $this->ipHashKey);
            }
            $userAgent = $request->userAgent() !== ''
                ? substr($request->userAgent(), 0, 500)
                : null;
        }

        try {
            $metadataJson = $entry->metadata !== []
                ? json_encode(
                    $entry->metadata,
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                )
                : null;
        } catch (JsonException) {
            $metadataJson = null;
        }

        $this->store->append([
            'event_id' => $eventId,
            'module_id' => $entry->moduleId,
            'action' => $entry->action,
            'result' => $entry->result,
            'actor_type' => $entry->actorType,
            'actor_id' => $entry->actorId,
            'tenant_type' => $tenant->type,
            'tenant_id' => $tenant->id,
            'target_type' => $entry->targetType,
            'target_id' => $entry->targetId,
            'correlation_id' => $entry->correlationId,
            'metadata_json' => $metadataJson,
            'ip_hash' => $ipHash,
            'user_agent' => $userAgent,
            'occurred_at' => (new DateTimeImmutable('now', new DateTimeZone('UTC')))
                ->format('Y-m-d H:i:s.u'),
        ]);

        return $eventId;
    }

    private function uuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return substr($hex, 0, 8) . '-'
            . substr($hex, 8, 4) . '-'
            . substr($hex, 12, 4) . '-'
            . substr($hex, 16, 4) . '-'
            . substr($hex, 20, 12);
    }
}
