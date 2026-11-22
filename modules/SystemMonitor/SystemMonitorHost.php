<?php

declare(strict_types=1);
namespace SyntaxDevTeam\Cms\Modules\SystemMonitor;

/** Narrow boundary to the optional host helper; never accepts commands or signals. */
final class SystemMonitorHost
{
    public function __construct(private readonly string $runtime = '/run/miniportal-vps-monitor') {}

    public function available(): bool { return file_exists($this->runtime . '/control.sock'); }

    public function storage(): ?array
    {
        $shared = SystemMonitorAgent::snapshot()['storage'] ?? null;
        if (is_array($shared) && is_numeric($shared['sampled_at'] ?? null)) {
            $shared['age_seconds'] = max(0, time() - (int)$shared['sampled_at']);
            $shared['stale'] = $shared['age_seconds'] > 7200;
            return $shared;
        }
        $file = $this->runtime . '/storage.json';
        if (!is_readable($file) || filesize($file) > 2097152) return null;
        $data = json_decode((string)file_get_contents($file), true, 32);
        if (!is_array($data) || !is_numeric($data['sampled_at'] ?? null)) return null;
        $data['age_seconds'] = max(0, time() - (int)$data['sampled_at']);
        $data['stale'] = $data['age_seconds'] > 1800;
        return $data;
    }

    public function terminate(int $pid, string $start, string $boot): array
    {
        if ($pid <= 1 || !preg_match('/\A[0-9]{1,24}\z/D', $start) || !preg_match('/\A[0-9a-f-]{36}\z/D', $boot)) throw new \InvalidArgumentException('Nieprawidłowa tożsamość procesu.');
        $socket = @stream_socket_client('unix://' . $this->runtime . '/control.sock', $code, $message, 2);
        if (!is_resource($socket)) throw new \RuntimeException('Usługa bezpiecznego zamykania procesów jest niedostępna.');
        try {
            stream_set_timeout($socket, 4);
            $request = json_encode(['pid' => $pid, 'start_ticks' => $start, 'boot_id' => $boot], JSON_THROW_ON_ERROR) . "\n";
            if (fwrite($socket, $request) !== strlen($request)) throw new \RuntimeException('Nie można przekazać żądania.');
            $response = fgets($socket, 4096);
            $result = is_string($response) ? json_decode($response, true) : null;
            if (!is_array($result) || !is_bool($result['ok'] ?? null)) throw new \RuntimeException('Nieprawidłowa odpowiedź usługi hosta.');
            return $result;
        } finally { fclose($socket); }
    }
}
