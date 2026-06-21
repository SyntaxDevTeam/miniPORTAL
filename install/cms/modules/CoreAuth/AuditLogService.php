<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\CoreAuth;

use SyntaxDevTeam\Cms\Core\Request;
use SyntaxDevTeam\Cms\Database\CrudApp;

final class AuditLogService
{
    public function __construct(
        private readonly ?CrudApp $database,
        private readonly string $hashKey,
    ) {
    }

    public function record(
        Request $request,
        string $eventType,
        string $result,
        ?string $provider = null,
        ?int $userId = null,
    ): void {
        if ($this->database === null) {
            return;
        }

        $ip = $request->clientIp();
        $this->database->insert('auth_events', [
            'user_id' => $userId,
            'provider' => $provider !== null ? substr($provider, 0, 32) : null,
            'event_type' => substr($eventType, 0, 64),
            'result' => substr($result, 0, 32),
            'ip_hash' => $ip !== '' && $this->hashKey !== ''
                ? hash_hmac('sha256', $ip, $this->hashKey)
                : null,
            'user_agent' => $request->userAgent() !== '' ? $request->userAgent() : null,
        ]);
    }
}
