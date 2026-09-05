<?php

declare(strict_types=1);

namespace App\Persistence;

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
     * @return array<string, int> score keyed by "film_slug|member_username"
     */
    public function scores(): array
    {
        $scores = [];
        foreach ($this->pdo->query('SELECT film_slug, member_username, score FROM ratings') as $row) {
            $scores["{$row['film_slug']}|{$row['member_username']}"] = (int) $row['score'];
        }

        return $scores;
    }

    public function findScore(string $filmSlug, string $username): ?int
    {
        $stmt = $this->pdo->prepare(
            'SELECT score FROM ratings WHERE film_slug = :film AND member_username = :user',
        );
        $stmt->execute(['film' => $filmSlug, 'user' => $username]);

        $score = $stmt->fetchColumn();

        return $score === false ? null : (int) $score;
    }
}
