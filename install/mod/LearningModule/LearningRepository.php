<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\LearningModule;

use PDO;
use RuntimeException;
use SyntaxDevTeam\Cms\Database\CrudApp;

/**
 * Warstwa dostępu do danych modułu edukacyjnego.
 *
 * Repozytorium korzysta wyłącznie z fasady {@see CrudApp}. Zapytanie własne
 * jest uzasadnione sortowaniem i nadal przechodzi przez przygotowane parametry
 * Medoo/PDO udostępniane przez fasadę.
 */
final class LearningRepository
{
    /**
     * @param CrudApp $database Skonfigurowana fasada bazy miniPORTAL.
     */
    public function __construct(
        private readonly CrudApp $database,
    ) {
    }

    /**
     * Zwraca wszystkie wpisy od najnowszego.
     *
     * @return list<LearningEntry>
     */
    public function all(): array
    {
        $statement = $this->database->query(
            'SELECT id, title, note, status, author_id, created_at, updated_at '
            . 'FROM learning_entries ORDER BY created_at DESC, id DESC'
        );
        if ($statement === null) {
            throw new RuntimeException('Nie można pobrać wpisów edukacyjnych.');
        }

        return array_map(
            $this->hydrate(...),
            $statement->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    /**
     * Tworzy wpis przypisany do zalogowanego użytkownika.
     *
     * @return int Identyfikator nowego rekordu.
     */
    public function create(string $title, string $note, string $status, int $authorId): int
    {
        return (int) $this->database->create('learning_entries', [
            'title' => $title,
            'note' => $note,
            'status' => $status,
            'author_id' => $authorId,
        ]);
    }

    /**
     * Usuwa wskazany rekord.
     */
    public function delete(int $id): bool
    {
        $statement = $this->database->delete('learning_entries', ['id' => $id]);

        return $statement !== null && $statement->rowCount() === 1;
    }

    /**
     * Zamienia wiersz PDO na model domenowy.
     *
     * @param array<string, mixed> $row Wiersz z bazy danych.
     */
    private function hydrate(array $row): LearningEntry
    {
        return new LearningEntry(
            (int) $row['id'],
            (string) $row['title'],
            (string) $row['note'],
            (string) $row['status'],
            (int) $row['author_id'],
            (string) $row['created_at'],
            (string) $row['updated_at'],
        );
    }
}
