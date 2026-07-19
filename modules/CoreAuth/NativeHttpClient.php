<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\CoreAuth;

use RuntimeException;

final class NativeHttpClient implements HttpClientInterface
{
    public function __construct(
        private readonly int $timeout = 10,
    ) {
    }

    public function request(string $method, string $url, array $headers = [], array $form = []): HttpResponse
    {
        $method = strtoupper($method);

        if (!in_array($method, ['GET', 'POST'], true)) {
            throw new RuntimeException('Klient HTTP obsługuje wyłącznie metody GET i POST.');
        }

        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        $options = [
            'method' => $method,
            'header' => implode("\r\n", $headerLines),
            'ignore_errors' => true,
            'timeout' => $this->timeout,
        ];

        if ($form !== []) {
            $options['content'] = http_build_query($form, '', '&', PHP_QUERY_RFC3986);
            $options['header'] .= ($options['header'] !== '' ? "\r\n" : '')
                . 'Content-Type: application/x-www-form-urlencoded';
        }

        $context = stream_context_create([
            'http' => $options,
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);
        $body = @file_get_contents($url, false, $context);

        if ($body === false) {
            throw new RuntimeException('Nie udało się połączyć z dostawcą tożsamości.');
        }

        $status = 0;
        foreach ($http_response_header ?? [] as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $matches) === 1) {
                $status = (int) $matches[1];
            }
        }

        return new HttpResponse($status, $body);
    }
}
