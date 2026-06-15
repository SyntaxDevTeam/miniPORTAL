<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\LearningModule;

/**
 * Niezmienny model pojedynczego rekordu modułu edukacyjnego.
 *
 * Model nie zna bazy danych, routingu ani HTML. Jego zadaniem jest przenoszenie
 * już znormalizowanych danych pomiędzy repozytorium i modułem wykonawczym.
 */
final readonly class LearningEntry
{
    /**
     * @param int $id Klucz główny rekordu.
     * @param string $title Tytuł wyświetlany w panelu.
     * @param string $note Bezpieczna treść tekstowa wpisu.
     * @param string $status Stan domenowy: `draft` albo `ready`.
     * @param int $authorId Lokalny identyfikator autora z tabeli `users`.
     * @param string $createdAt Data utworzenia w formacie zwracanym przez MySQL.
     * @param string $updatedAt Data ostatniej aktualizacji.
     */
    public function __construct(
        public int $id,
        public string $title,
        public string $note,
        public string $status,
        public int $authorId,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }
}
