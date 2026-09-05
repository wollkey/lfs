<?php

declare(strict_types=1);

namespace App\Persistence;

use App\Domain\Round;

final readonly class RoundRepository
{
    public function __construct(
        private \PDO $pdo,
    ) {
    }

    public function save(Round $round): void
    {
        $stmt = $this->pdo->prepare(<<<SQL
                INSERT INTO rounds (number, started_on, ended_on)
                VALUES (:number, :started, :ended)
                ON CONFLICT (number) DO UPDATE
                    SET started_on = excluded.started_on,
                        ended_on   = excluded.ended_on
            SQL);
        $stmt->execute([
            'number' => $round->number,
            'started' => $round->startedOn,
            'ended' => $round->endedOn,
        ]);
    }

    public function addFilm(int $round, string $filmSlug, ?string $pickedBy, int $position, string $pickedOn): void
    {
        $stmt = $this->pdo->prepare(<<<SQL
                INSERT INTO round_films (round_number, film_slug, picked_by, position, picked_on)
                VALUES (:round, :film, :by, :pos, :picked)
                ON CONFLICT (round_number, film_slug) DO UPDATE
                    SET picked_by = excluded.picked_by,
                        position  = excluded.position
            SQL);

        $stmt->execute([
            'round' => $round,
            'film' => $filmSlug,
            'by' => $pickedBy,
            'pos' => $position,
            'picked' => $pickedOn,
        ]);
    }

    public function setPicker(int $round, string $filmSlug, string $username): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE round_films SET picked_by = :user WHERE round_number = :round AND film_slug = :film',
        );

        $stmt->execute(['user' => $username, 'round' => $round, 'film' => $filmSlug]);
    }

    public function markExternal(int $round, string $filmSlug): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE round_films SET externally_sourced = 1 WHERE round_number = :round AND film_slug = :film',
        );

        $stmt->execute(['round' => $round, 'film' => $filmSlug]);
    }

    /**
     * @return list<array{round: int, slug: string, title: string}>
     */
    public function filmsWithoutPicker(?int $round = null): array
    {
        $sql = <<<SQL
                SELECT rf.round_number AS round, rf.film_slug AS slug, f.title AS title
                FROM round_films rf
                JOIN films f ON f.slug = rf.film_slug
                WHERE rf.picked_by IS NULL AND rf.externally_sourced = 0
            SQL;

        $params = [];
        if ($round !== null) {
            $sql .= ' AND rf.round_number = :round';
            $params['round'] = $round;
        }
        $sql .= ' ORDER BY rf.round_number, rf.position';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return array_map(
            static fn (array $row) => [
                'round' => (int) $row['round'],
                'slug' => $row['slug'],
                'title' => $row['title'],
            ],
            $stmt->fetchAll(),
        );
    }

    public function ensure(int $number): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO rounds (number) VALUES (:n) ON CONFLICT (number) DO NOTHING',
        );
        $stmt->execute(['n' => $number]);
    }

    /**
     * The highest round that actually holds films, not the highest declared round.
     */
    public function lastRound(): ?int
    {
        $number = $this->pdo->query('SELECT MAX(round_number) FROM round_films')->fetchColumn();

        return $number === false || $number === null ? null : (int) $number;
    }

    public function filmCount(int $round): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM round_films WHERE round_number = :round');
        $stmt->execute(['round' => $round]);

        return (int) $stmt->fetchColumn();
    }

    public function maxPosition(int $round): int
    {
        $stmt = $this->pdo->prepare('SELECT MAX(position) FROM round_films WHERE round_number = :round');
        $stmt->execute(['round' => $round]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return array{round: int, position: int, picker: ?string}|null null when the film has no slot yet
     */
    public function slotOf(string $filmSlug): ?array
    {
        $stmt = $this->pdo->prepare(<<<SQL
                SELECT round_number, position, picked_by FROM round_films
                WHERE film_slug = :film
                ORDER BY round_number DESC LIMIT 1
            SQL);
        $stmt->execute(['film' => $filmSlug]);

        $row = $stmt->fetch();

        return $row === false ? null : [
            'round' => (int) $row['round_number'],
            'position' => (int) $row['position'],
            'picker' => $row['picked_by'],
        ];
    }

    /**
     * @return list<string> members who already picked in this round
     */
    public function pickersIn(int $round): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT DISTINCT picked_by FROM round_films WHERE round_number = :round AND picked_by IS NOT NULL',
        );
        $stmt->execute(['round' => $round]);

        return array_map(static fn (array $row) => (string) $row['picked_by'], $stmt->fetchAll());
    }
}
