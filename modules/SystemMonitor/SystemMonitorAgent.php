<?php

declare(strict_types=1);
namespace SyntaxDevTeam\Cms\Modules\SystemMonitor;

/** Optional read-only integration, independent of the dedicated NodeManager module.
 * HTTP/1.0 forces a close-delimited response; fixed Unix path and endpoint only.
 */
final class SystemMonitorAgent
{
    private static ?array $value = null;
    private static float $attempt = 0;
    public static function snapshot(): ?array
    {
        if (microtime(true) - self::$attempt < 1.5) return self::$value;
        self::$attempt = microtime(true); self::$value = null;
        $socket = @stream_socket_client('unix:///run/miniportal-node-agent/agent.sock', $code, $message, 0.3);
        if (!is_resource($socket)) return null;
        try {
            stream_set_timeout($socket, 1);
            $request = "GET /v1/system/monitor HTTP/1.0\r\nHost: localhost\r\nConnection: close\r\n\r\n";
            if (fwrite($socket, $request) !== strlen($request)) return null;
            $raw = stream_get_contents($socket, 2097153);
            if (!is_string($raw) || strlen($raw) > 2097152 || (stream_get_meta_data($socket)['timed_out'] ?? false)) return null;
            [$headers, $body] = array_pad(explode("\r\n\r\n", $raw, 2), 2, '');
            if (!preg_match('~\AHTTP/1\.[01] 200(?: |\r|$)~', $headers)) return null;
            $data = json_decode($body, true, 32);
            if (!is_array($data) || !is_numeric($data['metrics']['sampled_at'] ?? null) || time() - (float)$data['metrics']['sampled_at'] > 15) return null;
            self::$value = $data; return $data;
        } finally { fclose($socket); }
    }
}
