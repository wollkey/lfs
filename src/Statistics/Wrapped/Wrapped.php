<?php

declare(strict_types=1);

namespace App\Statistics\Wrapped;

use App\Statistics\FilmFigure;
use App\Statistics\RatedFilm;
use App\Statistics\ScoredFilm;
use App\Statistics\Statistics;
use App\Statistics\TasteMatch;

/**
 * A year of the club: the films picked in it (by `picked_on`) and every rating of them,
 * summed up for the club and for each active member who rated any.
 */
final readonly class Wrapped
{
    private const string IN_YEAR = 'SELECT film_slug FROM round_films WHERE substr(picked_on, 1, 4) = :year';

    public function __construct(
        private \PDO $pdo,
        private Statistics $stats,
        private int $quorum = 5,
        private int $minGenreFilms = 3,
        private int $minClubGenreFilms = 5,
    ) {
    }

    public function year(int $year): ?WrappedYear
    {
        $films = $this->films($year);
        if ($films === []) {
            return null;
        }

        $scores = $this->scores($year);
        $tags = $this->tags($year);
        $reviews = $this->reviews($year);
        $picks = $this->picks($year, $scores);

        return new WrappedYear(
            $year,
            $this->club($films, $scores, $tags, $reviews, $picks),
            $this->members($year, $films, $scores, $tags, $reviews, $picks),
        );
    }

    /**
     * @param array<string, array{title: string, runtime: ?int, releaseYear: ?int, letterboxd: ?float}> $films
     * @param array<string, array<string, int>>                                                         $scores
     * @param array<string, array<string, list<string>>>                                                $tags
     * @param array<string, int>                                                                        $reviews
     * @param YearPick[]                                                                                $picks
     */
    private function club(array $films, array $scores, array $tags, array $reviews, array $picks): ClubYear
    {
        $rated = [];
        foreach ($scores as $slug => $byMember) {
            if (count($byMember) >= $this->quorum) {
                $rated[] = $this->ratedFilm($slug, $films[$slug]['title'], array_values($byMember));
            }
        }

        $average = static fn (RatedFilm $f): float => $f->average;
        $qualifiedPicks = array_filter($picks, fn (YearPick $p): bool => $p->votes >= $this->quorum);

        $directors = $this->counted($tags['director'] ?? []);
        $countries = $this->counted($tags['country'] ?? []);
        $count = static fn (TagFigure $t): int => $t->films;

        $genres = [];
        foreach ($this->filmsByName($tags['genre'] ?? []) as $genre => $slugs) {
            $genreScores = array_merge(...array_map(static fn (string $slug): array => array_values($scores[$slug] ?? []), $slugs));
            if (count($slugs) >= $this->minClubGenreFilms && $genreScores !== []) {
                $genres[] = new TagFigure($genre, count($slugs), round($this->mean($genreScores), 1));
            }
        }

        $dated = array_filter($films, static fn (array $f): bool => $f['releaseYear'] !== null);
        $oldest = array_map(
            static fn (string $slug) => new FilmFigure($slug, $dated[$slug]['title'], (int) $dated[$slug]['releaseYear']),
            array_keys($dated),
        );

        $gaps = [];
        foreach ($rated as $film) {
            $letterboxd = $films[$film->slug]['letterboxd'];
            if ($letterboxd !== null) {
                $gaps[] = new LetterboxdGap($film->slug, $film->title, $film->average, round($letterboxd * 2, 1));
            }
        }
        $gap = static fn (LetterboxdGap $g): float => round($g->ours - $g->letterboxd, 1);

        return new ClubYear(
            count($films),
            array_sum(array_map(static fn (array $f): int => $f['runtime'] ?? 0, $films)),
            array_sum(array_map(count(...), $scores)),
            array_sum($reviews),
            count($countries),
            $this->ties($rated, $average, true),
            $this->ties($rated, $average, false),
            $this->ties($rated, static fn (RatedFilm $f): float => $f->stdDev, true),
            $this->ties($qualifiedPicks, static fn (YearPick $p): float => (float) $p->average, true),
            array_values(array_filter($this->ties($directors, $count, true), static fn (TagFigure $t): bool => $t->films > 1)),
            $this->ties($genres, static fn (TagFigure $t): float => (float) $t->average, true),
            $this->ties($countries, $count, true),
            $this->ties($oldest, static fn (FilmFigure $f): int|float => $f->value, false),
            array_values(array_filter($this->ties($gaps, $gap, true), static fn (LetterboxdGap $g): bool => $gap($g) > 0)),
            array_values(array_filter($this->ties($gaps, $gap, false), static fn (LetterboxdGap $g): bool => $gap($g) < 0)),
        );
    }

    /**
     * @param array<string, array{title: string, runtime: ?int, releaseYear: ?int, letterboxd: ?float}> $films
     * @param array<string, array<string, int>>                                                         $scores
     * @param array<string, array<string, list<string>>>                                                $tags
     * @param array<string, int>                                                                        $reviews
     * @param YearPick[]                                                                                $picks
     *
     * @return MemberYear[]
     */
    private function members(int $year, array $films, array $scores, array $tags, array $reviews, array $picks): array
    {
        $byMember = [];
        foreach (array_keys($films) as $slug) {
            foreach ($scores[$slug] ?? [] as $username => $score) {
                $byMember[$username][$slug] = $score;
            }
        }

        $active = array_filter(
            $this->pdo->query("SELECT username, display_name FROM members WHERE status = 'active' ORDER BY position NULLS LAST, display_name")->fetchAll(),
            static fn (array $m): bool => isset($byMember[$m['username']]),
        );
        $watched = array_map(static fn (array $m): int => count($byMember[$m['username']]), $active);

        $genresOf = $tags['genre'] ?? [];
        $matches = $this->stats->tasteMatches($year);
        $agreement = static fn (TasteMatch $m): int => $m->agreement;

        $members = [];
        foreach ($active as $m) {
            $mine = $byMember[$m['username']];

            $scored = [];
            $leanings = [];
            $hot = [];
            $letterboxd = [];
            $byGenre = [];
            foreach ($mine as $slug => $score) {
                $all = array_values($scores[$slug]);
                $film = new ScoredFilm($slug, $films[$slug]['title'], $score, round($this->mean($all), 1));
                $scored[] = $film;

                $others = array_diff_key($scores[$slug], [$m['username'] => true]);
                if ($others !== []) {
                    $leanings[] = $score - $this->mean(array_values($others));
                }
                if (count($all) >= $this->quorum) {
                    $hot[] = $film;
                }
                if ($films[$slug]['letterboxd'] !== null) {
                    $letterboxd[] = new LetterboxdGap($slug, $film->title, (float) $score, round($films[$slug]['letterboxd'] * 2, 1));
                }
                foreach ($genresOf[$slug] ?? [] as $genre) {
                    $byGenre[$genre][] = $score;
                }
            }

            $genres = [];
            foreach ($byGenre as $genre => $genreScores) {
                if (count($genreScores) >= $this->minGenreFilms) {
                    $genres[] = new TagFigure($genre, count($genreScores), round($this->mean($genreScores), 1));
                }
            }

            $score = static fn (ScoredFilm $f): int => $f->score;
            $favorites = $this->ties($scored, $score, true);
            $leastFavorites = $this->ties($scored, $score, false);
            $deviation = static fn (ScoredFilm $f): float => round(abs($f->score - $f->average), 1);
            $distance = static fn (LetterboxdGap $g): float => round(abs($g->ours - $g->letterboxd), 1);

            $ownPicks = array_values(array_filter($picks, static fn (YearPick $p): bool => $p->username === $m['username']));
            $qualified = array_filter($ownPicks, fn (YearPick $p): bool => $p->votes >= $this->quorum);

            $closest = $this->ties($matches[$m['username']] ?? [], $agreement, true);
            $farthest = $this->ties($matches[$m['username']] ?? [], $agreement, false);

            $members[] = new MemberYear(
                $m['username'],
                $m['display_name'],
                count($mine),
                array_sum(array_map(static fn (string $slug): int => $films[$slug]['runtime'] ?? 0, array_keys($mine))),
                1 + count(array_filter($watched, static fn (int $n): bool => $n > count($mine))),
                round($this->mean(array_values($mine)), 1),
                $leanings === [] ? null : round($this->mean($leanings), 1),
                $reviews[$m['username']] ?? 0,
                $favorites,
                $leastFavorites === $favorites ? [] : $leastFavorites,
                array_values(array_filter($this->ties($hot, $deviation, true), static fn (ScoredFilm $f): bool => $deviation($f) > 0)),
                $this->ties($genres, static fn (TagFigure $t): float => (float) $t->average, true),
                $ownPicks,
                $qualified === [] ? null : round($this->mean(array_map(static fn (YearPick $p): float => (float) $p->average, $qualified)), 1),
                $this->ties($qualified, static fn (YearPick $p): float => (float) $p->average, true),
                $closest,
                $farthest === $closest ? [] : $farthest,
                array_values(array_filter($this->ties($letterboxd, $distance, true), static fn (LetterboxdGap $g): bool => $distance($g) > 0)),
            );
        }

        return $members;
    }

    /**
     * @return array<string, array{title: string, runtime: ?int, releaseYear: ?int, letterboxd: ?float}>
     */
    private function films(int $year): array
    {
        $films = [];
        foreach ($this->fetchAll('SELECT slug, title, runtime, release_year, letterboxd_rating FROM films WHERE slug IN ('.self::IN_YEAR.') ORDER BY title', $year) as $f) {
            $films[$f['slug']] = [
                'title' => $f['title'],
                'runtime' => $f['runtime'] !== null ? (int) $f['runtime'] : null,
                'releaseYear' => $f['release_year'] !== null ? (int) $f['release_year'] : null,
                'letterboxd' => $f['letterboxd_rating'] !== null ? (float) $f['letterboxd_rating'] : null,
            ];
        }

        return $films;
    }

    /**
     * @return array<string, array<string, int>>
     */
    private function scores(int $year): array
    {
        $scores = [];
        foreach ($this->fetchAll('SELECT film_slug, member_username, score FROM ratings WHERE film_slug IN ('.self::IN_YEAR.')', $year) as $r) {
            $scores[$r['film_slug']][$r['member_username']] = (int) $r['score'];
        }

        return $scores;
    }

    /**
     * @return array<string, array<string, list<string>>> names by film, by kind; genres and countries in Russian
     */
    private function tags(int $year): array
    {
        $tags = [];
        foreach ($this->fetchAll('SELECT film_slug, kind, name FROM film_tags WHERE film_slug IN ('.self::IN_YEAR.') ORDER BY rowid', $year) as $t) {
            $tags[$t['kind']][$t['film_slug']][] = match ($t['kind']) {
                'genre' => TagNames::genre($t['name']),
                'country' => TagNames::country($t['name']),
                default => $t['name'],
            };
        }

        return $tags;
    }

    /**
     * @return array<string, int>
     */
    private function reviews(int $year): array
    {
        $reviews = [];
        foreach ($this->fetchAll('SELECT member_username, COUNT(*) AS reviews FROM reviews WHERE film_slug IN ('.self::IN_YEAR.') GROUP BY member_username', $year) as $r) {
            $reviews[$r['member_username']] = (int) $r['reviews'];
        }

        return $reviews;
    }

    /**
     * @param array<string, array<string, int>> $scores
     *
     * @return YearPick[]
     */
    private function picks(int $year, array $scores): array
    {
        return array_map(
            fn (array $p) => new YearPick(
                $p['film_slug'],
                $p['title'],
                $p['username'],
                $p['display_name'],
                isset($scores[$p['film_slug']]) ? round($this->mean(array_values($scores[$p['film_slug']])), 1) : null,
                count($scores[$p['film_slug']] ?? []),
            ),
            $this->fetchAll(<<<SQL
                    SELECT rf.film_slug, f.title, m.username, m.display_name
                    FROM round_films rf
                    JOIN films f   ON f.slug     = rf.film_slug
                    JOIN members m ON m.username = rf.picked_by
                    WHERE substr(rf.picked_on, 1, 4) = :year
                    ORDER BY rf.picked_on, rf.round_number, rf.position
                SQL, $year),
        );
    }

    /**
     * @param list<int> $scores
     */
    private function ratedFilm(string $slug, string $title, array $scores): RatedFilm
    {
        $mean = $this->mean($scores);
        $variance = $this->mean(array_map(static fn (int $s): float => ($s - $mean) ** 2, $scores));

        return new RatedFilm($slug, $title, round($mean, 1), count($scores), max($scores) - min($scores), round(sqrt($variance), 2));
    }

    /**
     * @param array<string, list<string>> $namesByFilm
     *
     * @return TagFigure[]
     */
    private function counted(array $namesByFilm): array
    {
        return array_map(
            static fn (string $name, array $slugs) => new TagFigure($name, count($slugs)),
            array_keys($this->filmsByName($namesByFilm)),
            $this->filmsByName($namesByFilm),
        );
    }

    /**
     * @param array<string, list<string>> $namesByFilm
     *
     * @return array<string, list<string>>
     */
    private function filmsByName(array $namesByFilm): array
    {
        $films = [];
        foreach ($namesByFilm as $slug => $names) {
            foreach ($names as $name) {
                $films[$name][] = $slug;
            }
        }

        return $films;
    }

    /**
     * @param array<int|float> $values
     */
    private function mean(array $values): float
    {
        return array_sum($values) / count($values);
    }

    /**
     * Every item that shares the highest (or lowest) value.
     *
     * @template T
     *
     * @param T[]                      $items
     * @param callable(T): (int|float) $metric
     *
     * @return list<T>
     */
    private function ties(array $items, callable $metric, bool $highest): array
    {
        if ($items === []) {
            return [];
        }

        $values = array_map($metric, $items);
        $target = $highest ? max($values) : min($values);

        return array_values(array_filter($items, static fn ($item): bool => $metric($item) === $target));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchAll(string $sql, int $year): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['year' => (string) $year]);

        return $stmt->fetchAll();
    }
}
