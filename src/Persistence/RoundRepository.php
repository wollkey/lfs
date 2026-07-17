<?php

declare(strict_types=1);

namespace App\Persistence;

namespace App\Persistence;

use App\Domain\Round;
use PDO;

final readonly class RoundRepository
{
    public function __construct(
        private PDO $pdo,
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

    public function addFilm(int $round, string $filmSlug, ?string $pickedBy, int $position): void
    {
        $stmt = $this->pdo->prepare(<<<SQL
                INSERT INTO round_films (round_number, film_slug, picked_by, position)
                VALUES (:round, :film, :by, :pos)
                ON CONFLICT (round_number, film_slug) DO UPDATE
                    SET picked_by = excluded.picked_by,
                        position  = excluded.position
            SQL);

        $stmt->execute([
            'round' => $round,
            'film' => $filmSlug,
            'by' => $pickedBy,
            'pos' => $position,
        ]);
    }

    public function syncFilm(int $round, string $filmSlug, int $position): void
    {
        $stmt = $this->pdo->prepare(<<<SQL
                INSERT INTO round_films (round_number, film_slug, picked_by, position)
                VALUES (:round, :film, NULL, :pos)
                ON CONFLICT (round_number, film_slug) DO UPDATE
                    SET position = excluded.position
            SQL);

        $stmt->execute([
            'round' => $round,
            'film' => $filmSlug,
            'pos' => $position,
        ]);
    }

    public function setPicker(int $round, string $filmSlug, string $username): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE round_films SET picked_by = :user WHERE round_number = :round AND film_slug = :film',
        );

        $stmt->execute(['user' => $username, 'round' => $round, 'film' => $filmSlug]);
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
                WHERE rf.picked_by IS NULL
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
}
