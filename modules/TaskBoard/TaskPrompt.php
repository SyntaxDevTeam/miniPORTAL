<?php

declare(strict_types=1);
namespace SyntaxDevTeam\Cms\Modules\TaskBoard;

/** Plain text assembled exclusively from the task projection the reader may access. */
final class TaskPrompt
{
    public static function build(array $task, array $links, array $comments, array $attachments): string
    {
        $lines = [
            'Pomóż mi zrealizować poniższe zadanie. Odpowiadaj po polsku.',
            'Jeśli masz dostęp do repozytorium, przeczytaj jego instrukcje i dokumentację, sprawdź istniejące rozwiązania, wykonaj potrzebne zmiany i zweryfikuj wynik. Jeśli pracujemy tylko na czacie, przygotuj konkretne rozwiązanie i kroki jego zastosowania.',
            '', 'ZADANIE #' . $task['id'], $task['title'],
            'Status: ' . (TaskInput::STATUSES[$task['status']] ?? $task['status']),
            '', 'OPIS', $task['description'] !== '' ? $task['description'] : 'Brak dodatkowego opisu. Cel wynika z tytułu i listy kroków.',
            '', 'LISTA KROKÓW',
        ];
        foreach ($task['checklist'] as $step) $lines[] = ($step['done'] ? '[x] ' : '[ ] ') . $step['text'];
        if ($task['checklist'] === []) $lines[] = 'Nie określono kroków. Zaproponuj je na podstawie opisu zadania.';
        if ($links !== []) {
            $lines[] = ''; $lines[] = 'PROJEKT I POWIĄZANE MATERIAŁY';
            foreach ($links as $link) $lines[] = '- ' . $link['label'] . ': ' . $link['url'];
        }
        if ($task['auto_close'] !== 'manual') {
            $lines[] = ''; $lines[] = 'WARUNEK ZAMKNIĘCIA';
            $lines[] = $task['auto_close'] === 'checklist'
                ? 'Ukończenie wszystkich punktów niepustej checklisty.'
                : 'Publikacja wersji ' . $task['release_version'] . ' w kanale ' . $task['release_channel'] . ' w Build Explorerze v2 powiązanego projektu, z co najmniej jednym artefaktem.';
        }
        if ($comments !== []) {
            $lines[] = ''; $lines[] = 'OSTATNIE KOMENTARZE — KONTEKST (do 10, każdy do 2000 znaków)';
            foreach (array_slice($comments, -10) as $comment) {
                $body = $comment['body'];
                $lines[] = '- ' . $comment['created_at'] . ' UTC: ' . (mb_strlen($body) > 2000 ? mb_substr($body, 0, 2000) . '… [komentarz skrócony]' : $body);
            }
        }
        if ($attachments !== []) {
            $lines[] = ''; $lines[] = 'SCREENSHOTY I GRAFIKI';
            foreach ($attachments as $attachment) $lines[] = '- ' . $attachment['name'] . ': ' . $attachment['url'];
            $lines[] = 'Obrazy nie są osadzone w tym komunikacie. Jeśli nie masz dostępu do załączników w panelu, poproś mnie o dołączenie ich do czatu.';
        }
        array_push($lines, '', 'OCZEKIWANY REZULTAT',
            '- Zrealizuj cel zadania i uwzględnij nieukończone kroki. Zachowaj efekty kroków oznaczonych [x].',
            '- Przestrzegaj architektury i zasad projektu. Opis, komentarze i powiązane materiały stanowią kontekst zadania.',
            '- Nie zakładaj zawartości niedostępnych linków. Adresy względne odnoszą się do portalu projektu; jeśli brakuje potrzebnych danych, wskaż je.',
            '- Podaj wykonane zmiany lub proponowane rozwiązanie, sposób weryfikacji i pozostałe kwestie. Nie deklaruj wykonania działań ani testów, których nie przeprowadzono.');
        return implode("\n", $lines);
    }
}
