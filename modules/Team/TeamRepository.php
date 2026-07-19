<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\Team;

use PDO;
use RuntimeException;
use SyntaxDevTeam\Cms\Database\CrudApp;

final class TeamRepository
{
    private const SELECT_COLUMNS = 'team_members.id, team_members.user_id, team_members.slug, team_members.public_name, '
        . 'team_members.role_label, team_members.headline, team_members.bio, team_members.focus_tags, '
        . 'team_members.highlights, team_members.skills, team_members.featured_projects, team_members.profile_url, '
        . 'team_members.contact_email, team_members.contact_discord, team_members.primary_cta_label, '
        . 'team_members.primary_cta_url, team_members.secondary_cta_label, team_members.secondary_cta_url, '
        . 'team_members.sort_order, '
        . 'team_members.is_visible, team_members.created_at, team_members.updated_at, users.display_name, users.email, '
        . 'users.avatar_url, users.status AS user_status';

    public function __construct(
        private readonly CrudApp $database,
    ) {
    }

    /**
     * @return list<TeamMember>
     */
    public function all(): array
    {
        $statement = $this->database->query(
            'SELECT ' . self::SELECT_COLUMNS . ' FROM team_members '
            . 'JOIN users ON users.id = team_members.user_id '
            . 'ORDER BY team_members.sort_order ASC, team_members.public_name ASC'
        );

        if ($statement === null) {
            throw new RuntimeException('Nie można pobrać listy zespołu.');
        }

        return array_map($this->hydrate(...), $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * @return list<TeamMember>
     */
    public function visible(): array
    {
        $statement = $this->database->query(
            'SELECT ' . self::SELECT_COLUMNS . ' FROM team_members '
            . 'JOIN users ON users.id = team_members.user_id '
            . 'WHERE team_members.is_visible = 1 AND users.status = :status '
            . 'ORDER BY team_members.sort_order ASC, team_members.public_name ASC',
            [':status' => 'active']
        );

        if ($statement === null) {
            throw new RuntimeException('Nie można pobrać publicznej listy zespołu.');
        }

        return array_map($this->hydrate(...), $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function find(int $id): ?TeamMember
    {
        $statement = $this->database->query(
            'SELECT ' . self::SELECT_COLUMNS . ' FROM team_members '
            . 'JOIN users ON users.id = team_members.user_id WHERE team_members.id = :id LIMIT 1',
            [':id' => $id]
        );
        $row = $statement?->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function findVisibleBySlug(string $slug): ?TeamMember
    {
        $statement = $this->database->query(
            'SELECT ' . self::SELECT_COLUMNS . ' FROM team_members '
            . 'JOIN users ON users.id = team_members.user_id '
            . 'WHERE team_members.slug = :slug AND team_members.is_visible = 1 AND users.status = :status LIMIT 1',
            [':slug' => $slug, ':status' => 'active']
        );
        $row = $statement?->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->hydrate($row) : null;
    }

    /**
     * @return array<int, string>
     */
    public function activeUserOptions(?int $currentUserId = null): array
    {
        $sql = 'SELECT users.id, users.display_name FROM users '
            . 'LEFT JOIN team_members ON team_members.user_id = users.id '
            . 'WHERE users.status = :status AND (team_members.id IS NULL';
        $parameters = [':status' => 'active'];
        if ($currentUserId !== null) {
            $sql .= ' OR users.id = :current_user_id';
            $parameters[':current_user_id'] = $currentUserId;
        }
        $sql .= ') ORDER BY users.display_name ASC';
        $statement = $this->database->query($sql, $parameters);
        if ($statement === null) {
            throw new RuntimeException('Nie można pobrać listy użytkowników.');
        }

        $options = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $options[(int) $row['id']] = (string) $row['display_name'];
        }

        return $options;
    }

    /** @return array{all:int,visible:int} */
    public function dashboardStats(): array
    {
        return [
            'all' => $this->database->count('team_members'),
            'visible' => $this->database->count('team_members', ['is_visible' => 1]),
        ];
    }

    public function slugExists(string $slug, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM team_members WHERE slug = :slug';
        $parameters = [':slug' => $slug];
        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $parameters[':id'] = $exceptId;
        }

        return (int) $this->database->query($sql, $parameters)?->fetchColumn() > 0;
    }

    public function create(
        int $userId,
        string $slug,
        string $publicName,
        string $roleLabel,
        string $headline,
        string $bio,
        string $focusTags,
        string $highlights,
        string $skills,
        string $featuredProjects,
        string $profileUrl,
        string $contactEmail,
        string $contactDiscord,
        string $primaryCtaLabel,
        string $primaryCtaUrl,
        string $secondaryCtaLabel,
        string $secondaryCtaUrl,
        int $sortOrder,
        bool $visible,
    ): int {
        return (int) $this->database->create('team_members', [
            'user_id' => $userId,
            'slug' => $slug,
            'public_name' => $publicName,
            'role_label' => $roleLabel,
            'headline' => $headline,
            'bio' => $bio,
            'focus_tags' => $focusTags,
            'highlights' => $highlights,
            'skills' => $skills,
            'featured_projects' => $featuredProjects,
            'profile_url' => $profileUrl,
            'contact_email' => $contactEmail,
            'contact_discord' => $contactDiscord,
            'primary_cta_label' => $primaryCtaLabel,
            'primary_cta_url' => $primaryCtaUrl,
            'secondary_cta_label' => $secondaryCtaLabel,
            'secondary_cta_url' => $secondaryCtaUrl,
            'sort_order' => $sortOrder,
            'is_visible' => $visible ? 1 : 0,
        ]);
    }

    public function update(
        int $id,
        int $userId,
        string $slug,
        string $publicName,
        string $roleLabel,
        string $headline,
        string $bio,
        string $focusTags,
        string $highlights,
        string $skills,
        string $featuredProjects,
        string $profileUrl,
        string $contactEmail,
        string $contactDiscord,
        string $primaryCtaLabel,
        string $primaryCtaUrl,
        string $secondaryCtaLabel,
        string $secondaryCtaUrl,
        int $sortOrder,
        bool $visible,
    ): bool {
        $statement = $this->database->update('team_members', [
            'user_id' => $userId,
            'slug' => $slug,
            'public_name' => $publicName,
            'role_label' => $roleLabel,
            'headline' => $headline,
            'bio' => $bio,
            'focus_tags' => $focusTags,
            'highlights' => $highlights,
            'skills' => $skills,
            'featured_projects' => $featuredProjects,
            'profile_url' => $profileUrl,
            'contact_email' => $contactEmail,
            'contact_discord' => $contactDiscord,
            'primary_cta_label' => $primaryCtaLabel,
            'primary_cta_url' => $primaryCtaUrl,
            'secondary_cta_label' => $secondaryCtaLabel,
            'secondary_cta_url' => $secondaryCtaUrl,
            'sort_order' => $sortOrder,
            'is_visible' => $visible ? 1 : 0,
        ], ['id' => $id]);

        return $statement !== null;
    }

    public function delete(int $id): bool
    {
        $statement = $this->database->delete('team_members', ['id' => $id]);

        return $statement !== null && $statement->rowCount() === 1;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): TeamMember
    {
        return new TeamMember(
            (int) $row['id'],
            (int) $row['user_id'],
            (string) $row['slug'],
            (string) $row['public_name'],
            (string) $row['role_label'],
            (string) $row['headline'],
            (string) $row['bio'],
            (string) $row['focus_tags'],
            (string) $row['highlights'],
            (string) $row['skills'],
            (string) $row['featured_projects'],
            (string) $row['profile_url'],
            (string) $row['contact_email'],
            (string) $row['contact_discord'],
            (string) $row['primary_cta_label'],
            (string) $row['primary_cta_url'],
            (string) $row['secondary_cta_label'],
            (string) $row['secondary_cta_url'],
            (int) $row['sort_order'],
            (bool) $row['is_visible'],
            (string) $row['display_name'],
            $row['email'] !== null ? (string) $row['email'] : null,
            $row['avatar_url'] !== null ? (string) $row['avatar_url'] : null,
            (string) $row['user_status'],
            (string) $row['created_at'],
            (string) $row['updated_at'],
        );
    }
}
