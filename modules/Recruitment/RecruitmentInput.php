<?php
declare(strict_types=1);
namespace SyntaxDevTeam\Cms\Modules\Recruitment;
use InvalidArgumentException;
use SyntaxDevTeam\Cms\Core\Request;
final class RecruitmentInput
{
    public static function text(Request $r, string $key, int $max, bool $required = true): string
    {
        $v = trim($r->postString($key));
        if (($required && $v === '') || mb_strlen($v) > $max) throw new InvalidArgumentException('Uzupełnij poprawnie pole: ' . $key . ' (maks. ' . $max . ' znaków).');
        return $v;
    }
    public static function answers(Request $r, array $questions): array
    {
        $answers = [];
        foreach ($questions as $q) {
            $v = self::text($r, 'q_' . $q['id'], 4000, (bool)$q['required']);
            if ($q['type'] === 'scale' && $v !== '' && !in_array($v, array_map('strval', range(1,10)), true)) throw new InvalidArgumentException('Ocena musi wynosić od 1 do 10.');
            $answers[] = ['label' => $q['label'], 'answer' => $v];
        }
        return $answers;
    }
    public static function statuses(): array { return ['new'=>'Nowe','review'=>'W trakcie oceny','interview'=>'Rozmowa','accepted'=>'Przyjęte','rejected'=>'Odrzucone','withdrawn'=>'Wycofane']; }
}
