<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

use JsonException;
use RuntimeException;

final class GoogleCloudTranslationService implements MachineTranslationInterface
{
    public function __construct(
        private readonly string $apiKey,
        private readonly int $timeout = 12,
    ) {
    }

    public function available(): bool
    {
        return $this->apiKey !== '';
    }

    public function translate(
        string $text,
        string $sourceLocale,
        string $targetLocale,
        string $format = 'text',
    ): string {
        if (!$this->available()) {
            throw new RuntimeException('Google Cloud Translation nie jest skonfigurowany.');
        }
        if ($text === '') {
            return '';
        }
        if (strlen($text) > 30000) {
            throw new RuntimeException('Pojedyncze pole przekracza limit tłumaczenia maszynowego.');
        }
        if (
            preg_match('/^[a-z]{2}$/', $sourceLocale) !== 1
            || preg_match('/^[a-z]{2}$/', $targetLocale) !== 1
            || !in_array($format, ['text', 'html'], true)
        ) {
            throw new RuntimeException('Parametry tłumaczenia są nieprawidłowe.');
        }

        $body = http_build_query([
            'q' => $text,
            'source' => $sourceLocale,
            'target' => $targetLocale,
            'format' => $format,
        ], '', '&', PHP_QUERY_RFC3986);
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\nAccept: application/json",
                'content' => $body,
                'ignore_errors' => true,
                'timeout' => $this->timeout,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);
        $url = 'https://translation.googleapis.com/language/translate/v2?key=' . rawurlencode($this->apiKey);
        $response = @file_get_contents($url, false, $context);
        if (!is_string($response)) {
            throw new RuntimeException('Nie udało się połączyć z Google Cloud Translation.');
        }

        $status = 0;
        foreach ($http_response_header ?? [] as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $matches) === 1) {
                $status = (int) $matches[1];
            }
        }
        try {
            $payload = json_decode($response, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new RuntimeException('Google Cloud Translation zwrócił nieprawidłową odpowiedź.');
        }
        $translated = $payload['data']['translations'][0]['translatedText'] ?? null;
        if ($status < 200 || $status >= 300 || !is_string($translated)) {
            throw new RuntimeException('Google Cloud Translation odrzucił żądanie.');
        }

        return html_entity_decode($translated, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
