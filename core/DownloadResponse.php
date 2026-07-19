<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

use RuntimeException;

final class DownloadResponse
{
    public static function send(
        string $path,
        string $filename,
        string $contentType = 'application/octet-stream',
        bool $private = true,
        array $headers = [],
    ): void {
        if (!is_file($path) || is_link($path) || !is_readable($path)) {
            throw new RuntimeException('Plik nie jest dostępny do pobrania.');
        }
        if (!headers_sent()) {
            header('Content-Type: ' . self::headerValue($contentType, 'application/octet-stream'));
            header('Content-Length: ' . (string) filesize($path));
            header('Content-Disposition: attachment; filename="' . self::asciiFilename($filename) . '"');
            header('X-Content-Type-Options: nosniff');
            header('Cache-Control: ' . ($private ? 'private, no-store' : 'public, max-age=300'));
            self::extraHeaders($headers);
        }
        self::stream($path);
    }

    public static function sendString(
        string $content,
        string $filename,
        string $contentType = 'application/octet-stream',
        bool $private = true,
        array $headers = [],
    ): void {
        if (!headers_sent()) {
            header('Content-Type: ' . self::headerValue($contentType, 'application/octet-stream'));
            header('Content-Length: ' . (string) strlen($content));
            header('Content-Disposition: attachment; filename="' . self::asciiFilename($filename) . '"');
            header('X-Content-Type-Options: nosniff');
            header('Cache-Control: ' . ($private ? 'private, no-store' : 'public, max-age=300'));
            self::extraHeaders($headers);
        }
        echo $content;
    }

    private static function stream(string $path): void
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Nie można otworzyć pliku do pobrania.');
        }
        try {
            while (!feof($handle)) {
                $chunk = fread($handle, 1048576);
                if ($chunk === false) {
                    break;
                }
                echo $chunk;
                if (function_exists('flush')) {
                    flush();
                }
            }
        } finally {
            fclose($handle);
        }
    }

    private static function asciiFilename(string $filename): string
    {
        $filename = basename(str_replace('\\', '/', $filename));
        $filename = preg_replace('/[^A-Za-z0-9._-]+/', '_', $filename) ?? 'download.bin';
        $filename = trim($filename, '._-');

        return addcslashes($filename !== '' ? substr($filename, 0, 180) : 'download.bin', "\"\\");
    }

    private static function headerValue(string $value, string $fallback): string
    {
        $value = strtolower(trim($value));

        return preg_match('#^[a-z0-9.+-]+/[a-z0-9.+-]+(?:;\s*[a-z0-9_-]+=[A-Za-z0-9._-]+)*$#', $value) === 1
            ? $value
            : $fallback;
    }

    /** @param array<string, string> $headers */
    private static function extraHeaders(array $headers): void
    {
        foreach ($headers as $name => $value) {
            $name = trim((string) $name);
            $value = trim((string) $value);
            if (
                preg_match('/^[A-Za-z0-9-]{1,80}$/', $name) !== 1
                || preg_match('/[\r\n]/', $value) === 1
            ) {
                continue;
            }
            header($name . ': ' . $value);
        }
    }
}
