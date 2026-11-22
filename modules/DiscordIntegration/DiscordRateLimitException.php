<?php

declare(strict_types=1);
namespace SyntaxDevTeam\Cms\Modules\DiscordIntegration;
final class DiscordRateLimitException extends \RuntimeException
{
    public function __construct(public readonly int $retryAfter) {parent::__construct('Discord ograniczył tempo wysyłki (HTTP 429).');}
}
