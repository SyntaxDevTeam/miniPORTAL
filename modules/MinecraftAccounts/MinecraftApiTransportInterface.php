<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\MinecraftAccounts;

use SyntaxDevTeam\Cms\Modules\CoreAuth\HttpResponse;

interface MinecraftApiTransportInterface
{
    /**
     * @param array<string, string> $headers
     * @param array<string, scalar|array<array-key, mixed>> $payload
     */
    public function request(
        string $method,
        string $url,
        array $headers = [],
        array $payload = [],
        bool $json = false,
    ): HttpResponse;
}
