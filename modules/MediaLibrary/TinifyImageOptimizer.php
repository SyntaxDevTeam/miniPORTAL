<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\MediaLibrary;

final class TinifyImageOptimizer
{
    private const PROVIDER = 'tinify';
    private const API_URL = 'https://api.tinify.com/shrink';

    public function __construct(
        private readonly string $apiKey,
        private readonly MediaOptimizationUsageRepository $usage,
        private readonly int $monthlyLimit = 500,
        private readonly int $timeoutSeconds = 20,
    ) {
    }

    public function optimize(string $path): bool
    {
        if ($this->apiKey === '' || !$this->usage->reserveUse(self::PROVIDER, $this->monthlyLimit)) {
            return false;
        }

        try {
            $source = file_get_contents($path);
            if ($source === false || $source === '') {
                throw new \RuntimeException('Nie udało się odczytać grafiki do kompresji.');
            }
            $response = $this->request(self::API_URL, $source, 'POST', [
                'Authorization: Basic ' . base64_encode('api:' . $this->apiKey),
                'Content-Type: application/octet-stream',
            ]);
            if ($response['status'] < 200 || $response['status'] >= 300) {
                throw new \RuntimeException('Tinify odrzuciło kompresję grafiki.');
            }

            $data = json_decode($response['body'], true, 16, JSON_THROW_ON_ERROR);
            $location = is_array($data) ? (string) ($data['output']['url'] ?? '') : '';
            if ($location === '' || filter_var($location, FILTER_VALIDATE_URL) === false) {
                throw new \RuntimeException('Tinify nie zwróciło adresu zoptymalizowanej grafiki.');
            }

            $optimized = $this->request($location, '', 'GET');
            if ($optimized['status'] < 200 || $optimized['status'] >= 300 || $optimized['body'] === '') {
                throw new \RuntimeException('Nie udało się pobrać zoptymalizowanej grafiki z Tinify.');
            }
            if (file_put_contents($path, $optimized['body'], LOCK_EX) === false) {
                throw new \RuntimeException('Nie udało się zapisać zoptymalizowanej grafiki.');
            }
        } catch (\Throwable $exception) {
            $this->usage->releaseUse(self::PROVIDER);
            throw $exception;
        }

        return true;
    }

    /** @param list<string> $headers @return array{status:int,body:string} */
    private function request(string $url, string $body = '', string $method = 'GET', array $headers = []): array
    {
        $options = [
            'method' => $method,
            'header' => implode("\r\n", $headers),
            'ignore_errors' => true,
            'timeout' => $this->timeoutSeconds,
        ];
        if ($method === 'POST') {
            $options['content'] = $body;
        }

        $context = stream_context_create([
            'http' => $options,
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);
        $responseBody = @file_get_contents($url, false, $context);
        if ($responseBody === false) {
            throw new \RuntimeException('Nie udało się połączyć z Tinify.');
        }

        $status = 0;
        foreach ($http_response_header ?? [] as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $matches) === 1) {
                $status = (int) $matches[1];
            }
        }

        return ['status' => $status, 'body' => $responseBody];
    }
}
