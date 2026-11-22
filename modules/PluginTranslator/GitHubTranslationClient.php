<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\PluginTranslator;

final class GitHubTranslationClient implements GitHubTranslationClientInterface
{
    public function revision(string $repository,string $ref): string
    {
        $data=$this->json($this->repositoryPath($repository).'/commits/'.rawurlencode($ref));
        $sha=(string)($data['sha']??'');
        if(preg_match('/\A[a-f0-9]{40}\z/D',$sha)!==1)throw new \RuntimeException('Invalid GitHub commit revision.');
        return $sha;
    }

    public function directory(string $repository, string $ref, string $directory): array
    {
        return $this->json($this->repositoryPath($repository) . '/contents/'
            . implode('/', array_map('rawurlencode', explode('/', $directory))) . '?ref=' . rawurlencode($ref));
    }

    public function blob(string $repository, string $sha): array
    {
        if (preg_match('/\A[a-f0-9]{40}\z/D', $sha) !== 1) {
            throw new \InvalidArgumentException('Invalid GitHub source revision.');
        }
        return $this->json($this->repositoryPath($repository) . '/git/blobs/' . $sha);
    }

    private function repositoryPath(string $repository): string
    {
        if (preg_match('~\A[A-Za-z0-9][A-Za-z0-9_.-]{0,99}/[A-Za-z0-9][A-Za-z0-9_.-]{0,99}\z~D', $repository) !== 1) {
            throw new \InvalidArgumentException('Invalid GitHub source repository.');
        }
        return '/repos/' . $repository;
    }

    private function json(string $path): array
    {
        // A fixed HTTPS host, no credentials, redirects or provider-supplied URLs.
        $context = stream_context_create([
            'http' => [
                'method' => 'GET', 'timeout' => 5, 'follow_location' => 0, 'ignore_errors' => true,
                'header' => "User-Agent: miniPORTAL-PluginTranslator/2.1\r\nAccept: application/vnd.github+json\r\nX-GitHub-Api-Version: 2022-11-28\r\n",
            ],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $input = @fopen('https://api.github.com' . $path, 'rb', false, $context);
        if (!is_resource($input)) {
            throw new \RuntimeException('GitHub sources are temporarily unavailable. Please try again or upload your own YAML file.');
        }
        try {
            $metadata = stream_get_meta_data($input);
            $status = 0;
            foreach ($metadata['wrapper_data'] ?? [] as $header) {
                if (preg_match('~^HTTP/\S+\s+(\d{3})~', (string) $header, $matches)) {
                    $status = (int) $matches[1];
                }
            }
            if ($status !== 200) {
                throw new \RuntimeException('GitHub could not provide this source (HTTP ' . $status . '). Try again later or upload your own YAML file.');
            }
            $body = stream_get_contents($input, 1048577);
            if (!is_string($body) || strlen($body) > 1048576 || (stream_get_meta_data($input)['timed_out'] ?? false)) {
                throw new \RuntimeException('GitHub returned an incomplete or oversized source response.');
            }
        } finally {
            fclose($input);
        }
        try {
            $data = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new \RuntimeException('GitHub returned an invalid source response.');
        }
        if (!is_array($data)) {
            throw new \RuntimeException('GitHub returned an invalid source response.');
        }
        return $data;
    }
}
