<?php

declare(strict_types=1);
namespace SyntaxDevTeam\Cms\Modules\TaskBoard;

use InvalidArgumentException;
use SyntaxDevTeam\Cms\Core\Request;

final class TaskInput
{
    public const STATUSES = ['idea' => 'Pomysł', 'todo' => 'Do zrobienia', 'in_progress' => 'W toku', 'blocked' => 'Zablokowane', 'done' => 'Gotowe', 'cancelled' => 'Anulowane'];

    public static function text(string $value, int $limit, bool $required = false): string
    {
        $value = trim($value);
        if (mb_strlen($value) > $limit || ($required && $value === '') || str_contains($value, "\0")) throw new InvalidArgumentException('Uzupełnij wymagane pola i zachowaj limit długości.');
        return $value;
    }

    public static function choice(string $value, array $choices): string
    {
        if (!in_array($value, $choices, true)) throw new InvalidArgumentException('Wybierz poprawny status, zakres lub regułę.');
        return $value;
    }

    public static function parse(Request $request): array
    {
        $lines = preg_split('/\R/u', self::text($request->postString('checklist'), 54000)) ?: [];
        $checklist = [];
        foreach ($lines as $line) {
            if (trim($line) === '') continue;
            $done = preg_match('/^\s*\[[xX]\]\s*/u', $line) === 1;
            $text = preg_replace('/^\s*(?:\[[xX ]\]|[-*])\s*/u', '', $line) ?? $line;
            $checklist[] = ['text' => self::text($text, 300, true), 'done' => $done];
        }
        if (count($checklist) > 150) throw new InvalidArgumentException('Checklista może mieć najwyżej 150 kroków.');
        $data = [
            'title' => self::text($request->postString('title'), 180, true),
            'description' => self::text($request->postString('description'), 20000),
            'status' => self::choice($request->postString('status'), array_keys(self::STATUSES)),
            'visibility' => self::choice($request->postString('visibility'), ['private', 'members', 'public']),
            'project_id' => ($request->postInt('project_id', 0) ?? 0) ?: null,
            'assignee_ids' => self::assignees($request->postArray('assignee_ids'), $request->postInt('assignee_id', 0) ?? 0),
            'wiki_page_id' => ($request->postInt('wiki_page_id', 0) ?? 0) ?: null,
            'public_title' => self::text($request->postString('public_title'), 180),
            'public_summary' => self::text($request->postString('public_summary'), 4000),
            'auto_close' => self::choice($request->postString('auto_close'), ['manual', 'checklist', 'release']),
            'release_channel' => self::choice($request->postString('release_channel', 'release'), ['release', 'snapshot', 'dev', 'wip']),
            'release_version' => self::text($request->postString('release_version'), 120),
            'checklist' => $checklist,
        ];
        if ($data['visibility'] === 'public' && (!$data['project_id'] || $data['public_title'] === '' || $data['public_summary'] === '')) throw new InvalidArgumentException('Publiczny plan wymaga projektu, osobnego tytułu i opisu publicznego.');
        if ($data['auto_close'] === 'release' && (!$data['project_id'] || $data['release_version'] === '')) throw new InvalidArgumentException('Reguła wydania wymaga projektu i dokładnej wersji.');
        if ($data['visibility'] === 'private' && $data['assignee_ids'] !== []) throw new InvalidArgumentException('Prywatne zadanie nie może być przypisane do innej osoby.');
        return $data;
    }

    public static function assignees(array $values, int $legacy = 0): array
    {
        if ($legacy > 0) $values[] = $legacy;
        if (count($values) > 20) throw new InvalidArgumentException('Wybierz najwyżej 20 osób.');
        $ids = [];
        foreach ($values as $value) {
            if (!is_scalar($value) || filter_var($value, FILTER_VALIDATE_INT) === false || (int)$value < 1) throw new InvalidArgumentException('Wybierz poprawne konta.');
            $ids[] = (int)$value;
        }
        $ids = array_values(array_unique($ids)); sort($ids);
        return $ids;
    }

    public static function completed(array $task): bool
    {
        return $task['auto_close'] === 'checklist' && $task['checklist'] !== [] && count(array_filter($task['checklist'], static fn (array $step): bool => $step['done'] === true)) === count($task['checklist']);
    }
}
