<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\MinecraftAccounts;

use JsonException;
use RuntimeException;
use SyntaxDevTeam\Cms\Modules\CoreAuth\HttpResponse;

final class NativeMinecraftApiTransport implements MinecraftApiTransportInterface
{
    public function __construct(private readonly int $timeoutSeconds = 10)
    {
    }

    public function request(
        string $method,
        string $url,
        array $headers = [],
        array $payload = [],
        bool $json = false,
    ): HttpResponse {
        if (!in_array($url, [
            'https://login.live.com/oauth20_token.srf', 'https://user.auth.xboxlive.com/user/authenticate',
            'https://xsts.auth.xboxlive.com/xsts/authorize', 'https://api.minecraftservices.com/authentication/login_with_xbox',
            'https://api.minecraftservices.com/minecraft/profile',
        ], true)) throw new RuntimeException('Niedozwolony endpoint połączenia Minecraft.');
        $method = strtoupper($method);
        if (!in_array($method, ['GET', 'POST'], true)) {
            throw new RuntimeException('Klient Minecraft obsługuje wyłącznie metody GET i POST.');
        }

        $body = '';
        if ($payload !== []) {
            if ($json) {
                try {
                    $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
                } catch (JsonException) {
                    throw new RuntimeException('Nie można przygotować żądania do Minecraft Services.');
                }
                $headers['Content-Type'] = 'application/json';
            } else {
                $body = http_build_query($payload, '', '&', PHP_QUERY_RFC3986);
                $headers['Content-Type'] = 'application/x-www-form-urlencoded';
            }
        }

        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }
        $options = [
            'method' => $method,
            'header' => implode("\r\n", $headerLines),
            'ignore_errors' => true,
            'follow_location' => 0,
            'max_redirects' => 0,
            'timeout' => max(1, min(20, $this->timeoutSeconds)),
        ];
        if ($body !== '') {
            $options['content'] = $body;
        }

        $context = stream_context_create([
            'http' => $options,
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $responseBody = @file_get_contents($url, false, $context);
        if ($responseBody === false) {
            throw new RuntimeException('Nie udało się połączyć z usługą Microsoft/Xbox/Minecraft.');
        }

        $status = 0;
        foreach ($http_response_header ?? [] as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $matches) === 1) {
                $status = (int) $matches[1];
            }
        }
        return new HttpResponse($status, $responseBody);
    }
}
