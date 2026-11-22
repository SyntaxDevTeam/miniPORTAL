<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\Gallery;

/** The only path from a validated submission/moderation decision to publication. */
final class GalleryPublicationService
{
    public function __construct(
        private readonly GalleryRepository $photos,
        private readonly GalleryStorage $storage,
    ) {}

    public function submit(int $owner, array $data, string $stored, bool $publish): int
    {
        if ($publish) $this->storage->optimizeForPublication($stored);
        return $this->photos->submit($owner, $data, $stored, $publish);
    }

    public function review(int $id, int $revision, int $actor, array $data): void
    {
        $photo = $this->photos->find($id);
        if ($photo === null || (int) $photo['revision'] !== $revision) {
            throw new \InvalidArgumentException('Zdjęcie zostało już zmienione. Odśwież stronę i sprawdź aktualny stan.');
        }
        if (($data['status'] ?? '') === 'published' && $photo['status'] !== 'published') {
            $this->storage->optimizeForPublication($photo['stored_name']);
        }
        $this->photos->moderate($id, $revision, $actor, $data);
    }
}
