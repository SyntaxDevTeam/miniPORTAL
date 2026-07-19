<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

use Closure;
use RuntimeException;

final class DashboardRegistry
{
    /** @var array<string, array{id:string,type:string,label:string,description:string,symbol:string,permission:string,order:int,default_enabled:bool,provider:Closure}> */
    private array $items = [];

    /** @param callable(): array{value:string|int,detail?:string} $provider */
    public function addMetric(string $id, string $label, string $description, string $symbol, callable $provider, string $permission, int $order = 100, bool $defaultEnabled = true): void
    {
        $this->add($id, 'metric', $label, $description, $symbol, $provider, $permission, $order, $defaultEnabled);
    }

    /** @param callable(): array{meta?:string,headers:list<string>,rows:list<list<scalar|null>>,empty?:string} $provider */
    public function addPanel(string $id, string $label, string $description, callable $provider, string $permission, int $order = 100, bool $defaultEnabled = true): void
    {
        $this->add($id, 'panel', $label, $description, '', $provider, $permission, $order, $defaultEnabled);
    }

    private function add(string $id, string $type, string $label, string $description, string $symbol, callable $provider, string $permission, int $order, bool $defaultEnabled): void
    {
        if (preg_match('/^[a-z][a-z0-9_.-]{1,95}$/', $id) !== 1 || trim($label) === '' || $permission === '' || isset($this->items[$id])) {
            throw new RuntimeException('Nieprawidłowy lub zduplikowany element dashboardu.');
        }
        $this->items[$id] = [
            'id' => $id,
            'type' => $type,
            'label' => substr(trim($label), 0, 120),
            'description' => substr(trim($description), 0, 240),
            'symbol' => strtoupper(substr($symbol, 0, 3)),
            'permission' => $permission,
            'order' => max(0, min(65535, $order)),
            'default_enabled' => $defaultEnabled,
            'provider' => Closure::fromCallable($provider),
        ];
    }

    /** @param list<string> $permissions @return list<array{id:string,type:string,label:string,description:string,order:int,default_enabled:bool}> */
    public function definitions(array $permissions): array
    {
        return array_map(static function (array $item): array {
            unset($item['permission'], $item['provider'], $item['symbol']);
            return $item;
        }, $this->allowed($permissions));
    }

    /** @param list<string> $permissions @param array<string,bool> $settings @return list<array{label:string,value:string,symbol:string,detail:string}> */
    public function metrics(array $permissions, array $settings): array
    {
        $result = [];
        foreach ($this->allowed($permissions) as $item) {
            if ($item['type'] !== 'metric' || !($settings[$item['id']] ?? $item['default_enabled'])) continue;
            try {
                $data = ($item['provider'])();
                $result[] = ['label' => $item['label'], 'value' => (string) ($data['value'] ?? '0'), 'symbol' => $item['symbol'], 'detail' => (string) ($data['detail'] ?? '')];
            } catch (\Throwable) {
                $result[] = ['label' => $item['label'], 'value' => '—', 'symbol' => $item['symbol'], 'detail' => 'Dane chwilowo niedostępne'];
            }
        }
        return $result;
    }

    /** @param list<string> $permissions @param array<string,bool> $settings @return list<array{label:string,meta:string,headers:list<string>,rows:list<list<scalar|null>>,empty:string}> */
    public function panels(array $permissions, array $settings): array
    {
        $result = [];
        foreach ($this->allowed($permissions) as $item) {
            if ($item['type'] !== 'panel' || !($settings[$item['id']] ?? $item['default_enabled'])) continue;
            try {
                $data = ($item['provider'])();
                $result[] = ['label' => $item['label'], 'meta' => (string) ($data['meta'] ?? ''), 'headers' => $data['headers'] ?? [], 'rows' => $data['rows'] ?? [], 'empty' => (string) ($data['empty'] ?? 'Brak danych.')];
            } catch (\Throwable) {
                $result[] = ['label' => $item['label'], 'meta' => 'Błąd danych', 'headers' => [], 'rows' => [], 'empty' => 'Dane modułu są chwilowo niedostępne.'];
            }
        }
        return $result;
    }

    /** @param list<string> $permissions @return list<array<string,mixed>> */
    private function allowed(array $permissions): array
    {
        $items = array_values(array_filter($this->items, static fn (array $item): bool => in_array('*', $permissions, true) || in_array($item['permission'], $permissions, true)));
        usort($items, static fn (array $a, array $b): int => [$a['order'], $a['label']] <=> [$b['order'], $b['label']]);
        return $items;
    }
}
