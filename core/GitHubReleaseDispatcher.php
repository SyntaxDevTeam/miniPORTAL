<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

use JsonException;
use RuntimeException;

final class GitHubReleaseDispatcher
{
    public function __construct(
        private readonly string $token,
        private readonly string $repository = 'SyntaxDevTeam/miniPORTAL-source',
        private readonly string $workflow = 'platform-release.yml',
        private readonly string $ref = 'main',
        private readonly string $apiBaseUrl = 'https://api.github.com',
    ) {
    }

    public function available(): bool
    {
        return preg_match('/^[A-Za-z0-9_]+$/', trim($this->token)) === 1
            && preg_match('~^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$~', $this->repository) === 1
            && preg_match('/^[A-Za-z0-9_.-]+\.ya?ml$/', $this->workflow) === 1
            && preg_match('/^[A-Za-z0-9._\/-]+$/', $this->ref) === 1;
    }

    /**
     * @param list<string> $changelog
     */
    public function dispatch(string $version, string $minimumVersion, array $changelog): void
    {
        if (!$this->available()) {
            throw new RuntimeException(
                'Automatyczna publikacja GitHub nie jest skonfigurowana. '
                . 'Ustaw PLATFORM_RELEASE_GITHUB_TOKEN oraz dane workflow.'
            );
        }
        foreach ([$version, $minimumVersion] as $candidate) {
            if (preg_match('/^\d+\.\d+\.\d+(?:-[a-z0-9.-]+)?$/i', $candidate) !== 1) {
                throw new RuntimeException('Wersja wydania ma nieprawidłowy format SemVer.');
            }
        }
        if (version_compare($minimumVersion, $version, '>')) {
            throw new RuntimeException('Minimalna wersja nie może być wyższa od publikowanego wydania.');
        }
        $items = [];
        foreach ($changelog as $item) {
            $item = trim($item);
            if ($item !== '') {
                $items[] = $item;
            }
        }
        if ($items === [] || count($items) > 50) {
            throw new RuntimeException('Lista zmian musi zawierać od 1 do 50 pozycji.');
        }

        try {
            $payload = json_encode([
                'ref' => $this->ref,
                'inputs' => [
                    'version' => $version,
                    'minimum_version' => $minimumVersion,
                    'changelog' => implode("\n", $items),
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Nie można przygotować zlecenia publikacji GitHub.', 0, $exception);
        }

        $url = rtrim($this->apiBaseUrl, '/')
            . '/repos/' . $this->repository
            . '/actions/workflows/' . rawurlencode($this->workflow)
            . '/dispatches';
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'timeout' => 20,
                'follow_location' => 0,
                'ignore_errors' => true,
                'header' => implode("\r\n", [
                    'Accept: application/vnd.github+json',
                    'Authorization: Bearer ' . $this->token,
                    'Content-Type: application/json',
                    'User-Agent: SyntaxDevTeam-miniPORTAL',
                    'X-GitHub-Api-Version: 2022-11-28',
                ]),
                'content' => $payload,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);
        $response = @file_get_contents($url, false, $context);
        $status = self::responseStatus($http_response_header ?? []);
        if ($response === false || $status !== 204) {
            throw new RuntimeException(
                'GitHub nie przyjął zlecenia publikacji'
                . ($status > 0 ? ' (HTTP ' . $status . ')' : '')
                . '. Sprawdź token, uprawnienie Actions: write, nazwę workflow i gałąź.'
            );
        }
    }

    /**
     * @param list<string> $headers
     */
    private static function responseStatus(array $headers): int
    {
        foreach ($headers as $header) {
            if (preg_match('~^HTTP/\S+\s+(\d{3})~i', $header, $match) === 1) {
                return (int) $match[1];
            }
        }

        return 0;
    }
}
