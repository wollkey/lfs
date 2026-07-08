<?php

declare(strict_types=1);

namespace App\Persistence;

use App\Domain\Film;

final class FilmRepository
{
    public function __construct(
        private readonly \PDO $pdo,
    ) {
    }

    public function save(Film $film): void
    {
        $stmt = $this->pdo->prepare(<<<SQL
                INSERT INTO films (slug, title) VALUES (:slug, :title)
                ON CONFLICT (slug) DO UPDATE SET title = excluded.title
            SQL);
        $stmt->execute(['slug' => $film->slug, 'title' => $film->title]);
    }

    public function find(string $slug): ?Film
    {
        $stmt = $this->pdo->prepare('SELECT slug, title FROM films WHERE slug = :slug');
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch();

        return $row ? new Film($row['slug'], $row['title']) : null;
    }

    /**
     * @return Film[]
     */
    public function all(): array
    {
        return array_map(
            static fn (array $r) => new Film($r['slug'], $r['title']),
            $this->pdo->query('SELECT slug, title FROM films ORDER BY title')->fetchAll(),
        );
    }
}
