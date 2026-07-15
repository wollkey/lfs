<?php

declare(strict_types=1);

namespace App\Persistence;

use App\Domain\Rating;
use App\Domain\Score;

final readonly class RatingRepository
{
    public function __construct(
        private \PDO $pdo,
    ) {
    }

    public function setRating(string $filmSlug, string $username, int $score): void
    {
        new Score($score);

        $stmt = $this->pdo->prepare(<<<SQL
                INSERT INTO ratings (film_slug, member_username, score)
                VALUES (:film, :user, :score)
                ON CONFLICT (film_slug, member_username)
                DO UPDATE SET score = excluded.score
            SQL);

        $stmt->execute(['film' => $filmSlug, 'user' => $username, 'score' => $score]);
    }

    /**
     * @return Rating[]
     */
    public function forFilm(string $filmSlug): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT film_slug, member_username, score FROM ratings WHERE film_slug = :film',
        );
        $stmt->execute(['film' => $filmSlug]);

        return array_map(
            static fn (array $r) => new Rating($r['film_slug'], $r['member_username'], new Score((int) $r['score'])),
            $stmt->fetchAll(),
        );
    }
}
