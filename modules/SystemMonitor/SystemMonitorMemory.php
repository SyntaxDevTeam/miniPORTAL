<?php

declare(strict_types=1);
namespace SyntaxDevTeam\Cms\Modules\SystemMonitor;

final class SystemMonitorMemory
{
    public static function snapshot(?string $contents = null): array
    {
        $contents ??= (string)@file_get_contents('/proc/meminfo');
        preg_match_all('/^([A-Za-z0-9_()]+):\s+(\d+)\s+kB$/m', $contents, $matches, PREG_SET_ORDER);
        $values = []; foreach ($matches as $match) $values[$match[1]] = (int)$match[2] * 1024;
        $total = $values['MemTotal'] ?? 0;
        $available = $values['MemAvailable'] ?? (($values['MemFree'] ?? 0) + ($values['Buffers'] ?? 0) + ($values['Cached'] ?? 0));
        $components = [
            ['label' => 'Pamięć anonimowa aplikacji', 'bytes' => $values['AnonPages'] ?? 0],
            ['label' => 'Cache plików (w tym współdzielona)', 'bytes' => $values['Cached'] ?? 0],
            ['label' => 'Bufory dyskowe', 'bytes' => $values['Buffers'] ?? 0],
            ['label' => 'Jądro: slab, stosy i tablice stron', 'bytes' => ($values['Slab'] ?? 0) + ($values['KernelStack'] ?? 0) + ($values['PageTables'] ?? 0)],
            ['label' => 'Całkowicie wolne (MemFree)', 'bytes' => $values['MemFree'] ?? 0],
        ];
        $components[] = ['label' => 'Pozostała pamięć / różnica pomiaru', 'bytes' => max(0, $total - array_sum(array_column($components, 'bytes')))];
        return ['total' => $total, 'available' => $available, 'used' => max(0, $total - $available), 'used_percent' => $total > 0 ? max(0, ($total - $available) / $total * 100) : null, 'components' => $components];
    }
}
