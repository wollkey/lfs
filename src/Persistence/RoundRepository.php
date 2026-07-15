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

    public function ensure(int $number): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO rounds (number) VALUES (:n) ON CONFLICT (number) DO NOTHING',
        );
        $stmt->execute(['n' => $number]);
    }
}
