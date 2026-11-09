<?php

declare(strict_types=1);

namespace App\Persistence;

use App\Domain\Film;
use App\Domain\FilmDetails;

final readonly class FilmRepository
{
    public function __construct(
        private \PDO $pdo,
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

    public function saveDetails(string $slug, FilmDetails $details): void
    {
        $this->pdo->beginTransaction();

        $this->pdo->prepare(<<<SQL
                UPDATE films
                SET release_year = :release_year, runtime = :runtime, letterboxd_rating = :letterboxd_rating
                WHERE slug = :slug
            SQL)->execute([
            'slug' => $slug,
            'release_year' => $details->releaseYear,
            'runtime' => $details->runtime,
            'letterboxd_rating' => $details->letterboxdRating,
        ]);

        $this->pdo->prepare('DELETE FROM film_tags WHERE film_slug = :slug')->execute(['slug' => $slug]);

        $insert = $this->pdo->prepare('INSERT INTO film_tags (film_slug, kind, name) VALUES (:slug, :kind, :name)');
        foreach (['director' => $details->directors, 'genre' => $details->genres, 'country' => $details->countries] as $kind => $names) {
            foreach ($names as $name) {
                $insert->execute(['slug' => $slug, 'kind' => $kind, 'name' => $name]);
            }
        }

        $this->pdo->commit();
    }

    /**
     * @return list<string>
     */
    public function slugsWithoutDetails(): array
    {
        return array_map(
            static fn (array $r) => (string) $r['slug'],
            $this->pdo->query('SELECT slug FROM films WHERE release_year IS NULL ORDER BY slug')->fetchAll(),
        );
    }

    public function find(string $slug): ?Film
    {
        $stmt = $this->pdo->prepare('SELECT slug, title FROM films WHERE slug = :slug');
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch();

        return $row ? new Film($row['slug'], $row['title']) : null;
    }

    /**
     * @return list<string>
     */
    public function slugs(): array
    {
        return array_map(
            static fn (array $r) => (string) $r['slug'],
            $this->pdo->query('SELECT slug FROM films')->fetchAll(),
        );
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
