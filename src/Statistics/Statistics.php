<?php

declare(strict_types=1);

namespace App\Statistics;

use App\Domain\MemberStatus;

final readonly class Statistics
{
    public function __construct(
        private \PDO $pdo,
        private int $quorum = 5,
    ) {
    }

    public function overview(): Overview
    {
        $t = $this->pdo->query(<<<SQL
                SELECT
                    (SELECT COUNT(*) FROM films)   AS films,
                    (SELECT COUNT(*) FROM ratings) AS ratings,
                    (SELECT COUNT(*) FROM members WHERE status = 'active') AS members,
                    (SELECT MAX(number) FROM rounds) AS current_round
            SQL)->fetch();

        $totals = new Totals(
            (int) $t['films'],
            (int) $t['ratings'],
            (int) $t['members'],
            $t['current_round'] !== null ? (int) $t['current_round'] : null,
        );

        return new Overview($totals, $this->bestFilm(), $this->worstFilm(), $this->mostDivisive());
    }

    public function bestFilm(): ?RatedFilm
    {
        return $this->topByAverage('DESC');
    }

    public function worstFilm(): ?RatedFilm
    {
        return $this->topByAverage('ASC');
    }

    public function mostDivisive(): ?RatedFilm
    {
        $stmt = $this->pdo->prepare(<<<SQL
                SELECT f.slug, f.title,
                       AVG(r.score) AS average,
                       COUNT(r.score) AS votes,
                       MAX(r.score) - MIN(r.score) AS spread
                FROM films f
                JOIN ratings r ON r.film_slug = f.slug
                GROUP BY f.slug
                HAVING votes >= CAST(:quorum AS INTEGER)
                ORDER BY spread DESC, average DESC
                LIMIT 1
            SQL);
        $stmt->execute(['quorum' => $this->quorum]);
        $row = $stmt->fetch();

        return $row
            ? new RatedFilm(
                $row['slug'],
                $row['title'],
                round((float) $row['average'], 1),
                (int) $row['votes'],
                (int) $row['spread'],
            )
            : null;
    }

    /**
     * @return ListedFilm[]
     */
    public function films(bool $withRatings = false): array
    {
        $filmRows = $this->pdo->query(<<<SQL
                SELECT f.slug, f.title, rf.round_number, rf.picked_by,
                       AVG(r.score) AS average, COUNT(r.score) AS votes
                FROM films f
                LEFT JOIN round_films rf ON rf.film_slug = f.slug
                LEFT JOIN ratings r      ON r.film_slug  = f.slug
                GROUP BY f.slug
                ORDER BY f.title
            SQL)->fetchAll();

        $ratingsBySlug = $withRatings ? $this->allRatingsGrouped() : [];

        return array_map(
            fn (array $f) => new ListedFilm(
                $f['slug'],
                $f['title'],
                $f['average'] !== null ? round((float) $f['average'], 1) : null,
                (int) $f['votes'],
                $f['round_number'] !== null ? (int) $f['round_number'] : null,
                $f['picked_by'],
                $withRatings ? ($ratingsBySlug[$f['slug']] ?? []) : null,
            ),
            $filmRows,
        );
    }

    /**
     * @return MemberStats[]
     */
    public function membersWithStats(): array
    {
        $rows = $this->pdo->query(<<<SQL
                SELECT m.username, m.display_name, m.status,
                       COUNT(r.score) AS watched,
                       AVG(r.score)   AS average_given
                FROM members m
                LEFT JOIN ratings r ON r.member_username = m.username
                GROUP BY m.username
                ORDER BY (m.status = 'active') DESC, m.display_name
            SQL)->fetchAll();

        return array_map(
            static fn (array $r) => new MemberStats(
                $r['username'],
                $r['display_name'],
                (int) $r['watched'],
                $r['average_given'] !== null ? round((float) $r['average_given'], 1) : null,
                MemberStatus::from($r['status']),
            ),
            $rows,
        );
    }

    /**
     * @return RoundView[]
     */
    public function rounds(): array
    {
        $roundRows = $this->pdo->query(
            'SELECT number, started_on, ended_on FROM rounds ORDER BY number',
        )->fetchAll();

        $filmRows = $this->pdo->query(<<<SQL
                SELECT rf.round_number, f.slug, f.title, rf.picked_by,
                       AVG(r.score) AS average, COUNT(r.score) AS votes,
                       MAX(r.score) - MIN(r.score) AS spread
                FROM round_films rf
                JOIN films f          ON f.slug      = rf.film_slug
                LEFT JOIN ratings r   ON r.film_slug = f.slug
                GROUP BY rf.round_number, f.slug
                ORDER BY rf.round_number, average DESC, votes DESC
            SQL)->fetchAll();

        $byRound = [];
        foreach ($filmRows as $row) {
            $byRound[(int) $row['round_number']][] = $row;
        }

        $rounds = [];
        foreach ($roundRows as $r) {
            $number = (int) $r['number'];
            $rowsForRound = $byRound[$number] ?? [];

            $films = array_map(
                static fn (array $f) => new ListedFilm(
                    $f['slug'],
                    $f['title'],
                    $f['average'] !== null ? round((float) $f['average'], 1) : null,
                    (int) $f['votes'],
                    $number,
                    $f['picked_by'],
                    null,
                ),
                $rowsForRound,
            );

            $winner = null;
            foreach ($rowsForRound as $f) {
                if ((int) $f['votes'] > 0) {
                    $winner = new RatedFilm(
                        $f['slug'],
                        $f['title'],
                        round((float) $f['average'], 1),
                        (int) $f['votes'],
                        (int) $f['spread'],
                    );
                    break;
                }
            }

            $rounds[] = new RoundView($number, $r['started_on'], $r['ended_on'], $winner, $films);
        }

        return $rounds;
    }

    public function filmDetail(string $slug): ?FilmDetail
    {
        $header = $this->fetchOne(<<<SQL
                SELECT f.slug, f.title, rf.round_number, rf.picked_by
                FROM films f
                LEFT JOIN round_films rf ON rf.film_slug = f.slug
                WHERE f.slug = :slug
                LIMIT 1
            SQL, ['slug' => $slug]);

        if ($header === null) {
            return null;
        }

        $ratingRows = $this->fetchAll(<<<SQL
                SELECT m.username, m.display_name, r.score
                FROM ratings r
                JOIN members m ON m.username = r.member_username
                WHERE r.film_slug = :slug
                ORDER BY r.score DESC, m.display_name
            SQL, ['slug' => $slug]);

        $scores = array_map(static fn (array $r) => (int) $r['score'], $ratingRows);
        $average = $scores === [] ? null : round(array_sum($scores) / count($scores), 1);
        $spread = $scores === [] ? null : max($scores) - min($scores);

        $ratings = array_map(
            static fn (array $r) => new MemberScore($r['username'], $r['display_name'], (int) $r['score']),
            $ratingRows,
        );

        $notWatched = array_map(
            static fn (array $r) => new MemberName($r['username'], $r['display_name']),
            $this->fetchAll(<<<SQL
                    SELECT username, display_name FROM members
                    WHERE status = 'active'
                      AND username NOT IN (SELECT member_username FROM ratings WHERE film_slug = :slug)
                    ORDER BY display_name
                SQL, ['slug' => $slug]),
        );

        return new FilmDetail(
            $header['slug'],
            $header['title'],
            $header['round_number'] !== null ? (int) $header['round_number'] : null,
            $header['picked_by'],
            $average,
            $spread,
            $ratings,
            $notWatched,
        );
    }

    private function topByAverage(string $dir): ?RatedFilm
    {
        $stmt = $this->pdo->prepare(<<<SQL
                SELECT f.slug, f.title,
                       AVG(r.score) AS average,
                       COUNT(r.score) AS votes,
                       MAX(r.score) - MIN(r.score) AS spread
                FROM films f
                JOIN ratings r ON r.film_slug = f.slug
                GROUP BY f.slug
                HAVING votes >= CAST(:quorum AS INTEGER)
                ORDER BY average {$dir}, votes DESC
                LIMIT 1
            SQL);
        $stmt->execute(['quorum' => $this->quorum]);
        $row = $stmt->fetch();

        return $row
            ? new RatedFilm(
                $row['slug'],
                $row['title'],
                round((float) $row['average'], 1),
                (int) $row['votes'],
                (int) $row['spread'],
            )
            : null;
    }

    /**
     * @return array<string, MemberScore[]>
     */
    private function allRatingsGrouped(): array
    {
        $rows = $this->pdo->query(<<<SQL
                SELECT r.film_slug, m.username, m.display_name, r.score
                FROM ratings r
                JOIN members m ON m.username = r.member_username
                ORDER BY r.score DESC, m.display_name
            SQL)->fetchAll();

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row['film_slug']][] = new MemberScore(
                $row['username'],
                $row['display_name'],
                (int) $row['score'],
            );
        }

        return $grouped;
    }

    /**
     * @param array<string,mixed> $params
     *
     * @return array<string,mixed>|null
     */
    private function fetchOne(string $sql, array $params): ?array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @param array<string,mixed> $params
     *
     * @return list<array<string,mixed>>
     */
    private function fetchAll(string $sql, array $params): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }
}
