<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\SystemMonitor;

/** Sanitized local telemetry only; never reads runner credentials or job output. */
final class SystemMonitorActions
{
    public const SNAPSHOT = '/run/miniportal-actions/status.json';

    public static function snapshot(string $path = self::SNAPSHOT): array
    {
        if ($path === self::SNAPSHOT) {
            $shared = SystemMonitorAgent::snapshot()['actions'] ?? null;
            if (is_array($shared)) {
                $shared['stale'] = time() - (int)($shared['sampled_at'] ?? 0) > 30;
                return $shared;
            }
        }
        $raw = @file_get_contents($path, false, null, 0, 131073);
        $data = is_string($raw) && strlen($raw) <= 131072 ? json_decode($raw, true) : null;
        if (!is_array($data) || !is_int($data['sampled_at'] ?? null) || !is_array($data['runners'] ?? null)) {
            return ['available' => false, 'stale' => true, 'runners' => []];
        }
        return ['available' => true, 'stale' => time() - $data['sampled_at'] > 30,
            'sampled_at' => $data['sampled_at'], 'runners' => array_slice($data['runners'], 0, 16)];
    }

    public static function parse(string $name, bool $active, string $journal): array
    {
        $row = ['name' => $name, 'state' => $active ? 'connecting' : 'offline',
            'job' => '', 'started_at' => null, 'last_result' => '', 'finished_at' => null];
        foreach (explode("\n", $journal) as $line) {
            $event = json_decode($line, true);
            if (!is_array($event) || !is_string($event['MESSAGE'] ?? null)) {
                continue;
            }
            $message = $event['MESSAGE'];
            if (preg_match('/(\d{4}-\d\d-\d\d \d\d:\d\d:\d\dZ): Running job: (.+)$/u', $message, $match)) {
                $row['state'] = 'busy';
                $row['job'] = mb_substr($match[2], 0, 200);
                $row['started_at'] = strtotime($match[1]);
            } elseif (preg_match('/(\d{4}-\d\d-\d\d \d\d:\d\d:\d\dZ): Job .+ completed with result: (Succeeded|Failed|Canceled|Skipped)/', $message, $match)) {
                $row['state'] = 'idle';
                $row['job'] = '';
                $row['started_at'] = null;
                $row['last_result'] = $match[2];
                $row['finished_at'] = strtotime($match[1]);
            } elseif (str_contains($message, 'Listening for Jobs')) {
                $row['state'] = 'idle';
                $row['job'] = '';
                $row['started_at'] = null;
            } elseif (str_contains($message, 'Runner connect error') || str_contains($message, 'Runner listener exited')) {
                $row['state'] = 'connecting';
                $row['job'] = '';
                $row['started_at'] = null;
            }
        }
        if (!$active) {
            $row['state'] = 'offline';
            $row['job'] = '';
            $row['started_at'] = null;
        }
        return $row;
    }
}
