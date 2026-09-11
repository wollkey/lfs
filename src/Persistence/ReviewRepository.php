<?php

declare(strict_types=1);

namespace App\Persistence;

use App\Domain\Review;
use App\Domain\ReviewSource;

final readonly class ReviewRepository
{
    public function __construct(
        private \PDO $pdo,
    ) {
    }

    public function save(Review $review): void
    {
        $stmt = $this->pdo->prepare(<<<SQL
                INSERT INTO reviews (film_slug, member_username, body, written_on, source, telegram_message_id)
                VALUES (:film, :user, :body, :written, :source, :message)
                ON CONFLICT (film_slug, member_username)
                DO UPDATE SET body                = excluded.body,
                              written_on          = excluded.written_on,
                              source              = excluded.source,
                              telegram_message_id = excluded.telegram_message_id
            SQL);

        $stmt->execute([
            'film' => $review->filmSlug,
            'user' => $review->memberUsername,
            'body' => $review->body,
            'written' => $review->writtenOn,
            'source' => $review->source->value,
            'message' => $review->telegramMessageId,
        ]);
    }

    public function find(string $filmSlug, string $username): ?Review
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM reviews WHERE film_slug = :film AND member_username = :user',
        );
        $stmt->execute(['film' => $filmSlug, 'user' => $username]);

        $row = $stmt->fetch();

        return $row === false ? null : $this->toReview($row);
    }

    /**
     * @return Review[]
     */
    public function all(): array
    {
        $reviews = [];
        foreach ($this->pdo->query('SELECT * FROM reviews ORDER BY film_slug, member_username') as $row) {
            $reviews[] = $this->toReview($row);
        }

        return $reviews;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function toReview(array $row): Review
    {
        return new Review(
            (string) $row['film_slug'],
            (string) $row['member_username'],
            (string) $row['body'],
            $row['written_on'] !== null ? (string) $row['written_on'] : null,
            ReviewSource::from((string) $row['source']),
            $row['telegram_message_id'] !== null ? (int) $row['telegram_message_id'] : null,
        );
    }
}
