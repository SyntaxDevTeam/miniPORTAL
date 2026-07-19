<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

use ErrorException;
use Throwable;

final class ErrorHandler
{
    private static bool $handled = false;

    /** @param callable(): array{theme?: mixed, request?: mixed, config?: mixed} $context */
    public static function register(callable $context): void
    {
        register_shutdown_function(static function () use ($context): void {
            $error = error_get_last();
            if ($error === null || self::$handled) {
                return;
            }
            $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
            if (!in_array((int) ($error['type'] ?? 0), $fatalTypes, true)) {
                return;
            }

            try {
                $values = $context();
            } catch (Throwable) {
                $values = [];
            }

            self::handle(
                new ErrorException(
                    (string) ($error['message'] ?? 'Fatal error'),
                    0,
                    (int) ($error['type'] ?? E_ERROR),
                    (string) ($error['file'] ?? ''),
                    (int) ($error['line'] ?? 0),
                ),
                ($values['theme'] ?? null) instanceof ThemeInterface ? $values['theme'] : null,
                ($values['request'] ?? null) instanceof Request ? $values['request'] : null,
                is_array($values['config'] ?? null) ? $values['config'] : [],
            );
        });
    }

    public static function handle(
        Throwable $throwable,
        ?ThemeInterface $theme = null,
        ?Request $request = null,
        array $config = [],
    ): void {
        if (self::$handled) {
            return;
        }
        self::$handled = true;

        if ($throwable instanceof PayloadTooLargeException) {
            self::$handled = true;
            if (!headers_sent()) {
                http_response_code(413);
                header('Content-Type: text/html; charset=UTF-8');
                header('Cache-Control: no-store, private');
            }
            $message = 'Przesłane dane są za duże. Maksymalny rozmiar tego typu żądania to '
                . self::formatBytes($throwable->maxBytes) . '.';
            if ($theme !== null && !headers_sent()) {
                $theme->render_public_error(
                    413,
                    'Żądanie jest za duże',
                    $message,
                    'Wróć do strony głównej',
                    '/',
                );
                return;
            }
            echo self::fallbackPayloadTooLargeHtml($message);
            return;
        }

        $incidentId = self::incidentId();
        self::log($throwable, $incidentId, $request);

        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=UTF-8');
            header('Cache-Control: no-store, private');
        }

        $message = 'Poczekaj chwilę i odśwież stronę albo wróć na stronę główną. '
            . 'Kod zdarzenia: ' . $incidentId . '.';

        if ($theme !== null && !headers_sent()) {
            $theme->render_public_error(
                500,
                'Poszło coś nie tak',
                $message,
                'Wróć na stronę główną',
                '/',
            );
            return;
        }

        echo self::fallbackHtml($incidentId);
    }

    public static function fallbackHtml(string $incidentId): string
    {
        $incidentId = htmlspecialchars($incidentId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return '<!doctype html><html lang="pl-PL"><head>'
            . '<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<meta name="robots" content="noindex, nofollow">'
            . '<title>Poszło coś nie tak - miniPORTAL</title>'
            . '<style>'
            . ':root{color-scheme:dark;--bg:#07101d;--panel:#101b2b;--line:#29405c;--text:#edf6ff;--muted:#a9b8ca;--accent:#64c7ff}'
            . '*{box-sizing:border-box}body{display:grid;min-height:100vh;margin:0;place-items:center;padding:1rem;color:var(--text);background:var(--bg);font:16px/1.55 system-ui,sans-serif}'
            . 'main{width:min(720px,100%);padding:1.5rem;background:var(--panel);border:1px solid var(--line);border-radius:1rem}'
            . 'p{color:var(--muted)}a{display:inline-flex;margin-top:.5rem;color:var(--accent);font-weight:700}'
            . 'code{color:var(--accent)}'
            . '</style></head><body><main>'
            . '<p>500 / błąd aplikacji</p><h1>Poszło coś nie tak</h1>'
            . '<p>Poczekaj chwilę i odśwież stronę albo wróć na stronę główną.</p>'
            . '<p>Kod zdarzenia: <code>' . $incidentId . '</code></p>'
            . '<a href="/">Wróć na stronę główną</a>'
            . '</main></body></html>';
    }

    private static function fallbackPayloadTooLargeHtml(string $message): string
    {
        $message = htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return '<!doctype html><html lang="pl-PL"><head>'
            . '<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<meta name="robots" content="noindex, nofollow">'
            . '<title>Żądanie jest za duże - miniPORTAL</title>'
            . '</head><body><main>'
            . '<p>413 / payload too large</p><h1>Żądanie jest za duże</h1>'
            . '<p>' . $message . '</p>'
            . '<a href="/">Wróć na stronę główną</a>'
            . '</main></body></html>';
    }

    private static function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return rtrim(rtrim(number_format($bytes / 1048576, 2, ',', ' '), '0'), ',') . ' MB';
        }
        if ($bytes >= 1024) {
            return rtrim(rtrim(number_format($bytes / 1024, 2, ',', ' '), '0'), ',') . ' KB';
        }

        return $bytes . ' B';
    }

    private static function incidentId(): string
    {
        try {
            return strtoupper(bin2hex(random_bytes(6)));
        } catch (Throwable) {
            return strtoupper(substr(hash('sha256', uniqid('miniportal-error-', true)), 0, 12));
        }
    }

    private static function log(Throwable $throwable, string $incidentId, ?Request $request): void
    {
        $path = $request?->path() ?? 'unknown';
        $method = $request?->method() ?? 'unknown';
        error_log(sprintf(
            '[miniPORTAL] incident=%s method=%s path=%s throwable=%s message=%s at %s:%d trace=%s',
            $incidentId,
            $method,
            $path,
            $throwable::class,
            $throwable->getMessage(),
            $throwable->getFile(),
            $throwable->getLine(),
            str_replace(["\r", "\n"], ' | ', $throwable->getTraceAsString()),
        ));
    }
}
