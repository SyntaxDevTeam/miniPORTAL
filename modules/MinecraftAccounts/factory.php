<?php

declare(strict_types=1);

use SyntaxDevTeam\Cms\Modules\MinecraftAccounts\{MinecraftAccountsModule,LinkedMinecraftProfileRepository,MinecraftMicrosoftProfileClient,NativeMinecraftApiTransport};
use SyntaxDevTeam\Cms\Modules\CoreAuth\{OAuthStateStore,OAuthAttemptLimiter};

return static function (array $services): MinecraftAccountsModule {
    $microsoft = $services['auth_config']['providers']['microsoft'] ?? [];
    return new MinecraftAccountsModule($services['theme'], $services['auth'], $services['security'], $services['audit'],
        new LinkedMinecraftProfileRepository($services['database']),
        new MinecraftMicrosoftProfileClient(new NativeMinecraftApiTransport(), (string) ($microsoft['client_id'] ?? ''),
            (string) ($microsoft['client_secret'] ?? ''), rtrim((string) ($services['config']['app']['public_url'] ?? ''), '/') . '/account/minecraft/connect/callback'),
        new OAuthStateStore(), new OAuthAttemptLimiter());
};
