<?php

declare(strict_types=1);

namespace App\Statistics;

use App\Domain\MemberStatus;

final readonly class Statistics
{
    public function __construct(
        private \PDO $pdo,
        private int $quorum = 5,
        private int $minCuratorPicks = 2,
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

        $rated = $this->ratedFilms();
        $members = $this->membersWithStats();

        return new Overview(
            $totals,
            $this->topFilms($rated, static fn (RatedFilm $f) => $f->average, true),
            $this->topFilms($rated, static fn (RatedFilm $f) => $f->average, false),
            $this->topFilms($rated, static fn (RatedFilm $f) => $f->stdDev, true),
            $this->topFilms($rated, static fn (RatedFilm $f) => $f->stdDev, false),
            $this->mostActiveMember($members),
            $this->bestCurator($members),
        );
    }

    public function bestFilm(): ?RatedFilm
    {
        return $this->pickFilm(
            $this->ratedFilms(),
            static fn (RatedFilm $a, RatedFilm $b) => $b->average <=> $a->average,
        );
    }

    public function worstFilm(): ?RatedFilm
    {
        return $this->pickFilm(
            $this->ratedFilms(),
            static fn (RatedFilm $a, RatedFilm $b) => $a->average <=> $b->average,
        );
    }

    public function mostDivisive(): ?RatedFilm
    {
        return $this->pickFilm(
            $this->ratedFilms(),
            static fn (RatedFilm $a, RatedFilm $b) => $b->stdDev <=> $a->stdDev,
        );
    }

    public function mostAgreed(): ?RatedFilm
    {
        return $this->pickFilm(
            $this->ratedFilms(),
            static fn (RatedFilm $a, RatedFilm $b) => $a->stdDev <=> $b->stdDev,
        );
    }

    /**
     * @return MemberStats[]
     */
    public function membersWithStats(): array
    {
        $rows = $this->pdo->query(<<<SQL
                SELECT m.username, m.display_name, m.status, m.position,
                   COUNT(r.score) AS watched,
                   AVG(r.score)   AS average_given
                FROM members m
                LEFT JOIN ratings r ON r.member_username = m.username
                GROUP BY m.username
                ORDER BY m.position NULLS LAST, m.display_name
            SQL)->fetchAll();

        $curators = $this->curatorStats();

        return array_map(
            static fn (array $r) => new MemberStats(
                $r['username'],
                $r['display_name'],
                (int) $r['watched'],
                $r['average_given'] !== null ? round((float) $r['average_given'], 1) : null,
                MemberStatus::from($r['status']),
                $curators[$r['username']]['picks'] ?? 0,
                $curators[$r['username']]['average'] ?? null,
                $r['position'] !== null ? (int) $r['position'] : null,
            ),
            $rows,
        );
    }

    /**
     * Films an active member has not rated. Each ListedFilm keeps its round,
     * so the frontend can group them by round.
     *
     * @return array<string, ListedFilm[]>
     */
    public function missedByMember(): array
    {
        $rows = $this->pdo->query(<<<SQL
                SELECT m.username, f.slug, f.title, rf.round_number, rf.picked_by, rf.position
                FROM members m
                CROSS JOIN round_films rf
                JOIN films f ON f.slug = rf.film_slug
                WHERE m.status = 'active'
                  AND NOT EXISTS (
                    SELECT 1 FROM ratings r
                    WHERE r.film_slug = rf.film_slug
                      AND r.member_username = m.username
                  )
                ORDER BY m.username, rf.round_number DESC, rf.position
            SQL)->fetchAll();

        $missed = [];
        foreach ($rows as $row) {
            $missed[$row['username']][] = new ListedFilm(
                $row['slug'],
                $row['title'],
                null,
                0,
                (int) $row['round_number'],
                $row['picked_by'],
                (int) $row['position'],
                null,
            );
        }

        return $missed;
    }

    public function currentRound(): ?int
    {
        $max = $this->pdo->query('SELECT MAX(number) FROM rounds')->fetchColumn();

        return $max === false || $max === null ? null : (int) $max;
    }

    /**
     * Films each member has picked, with their average. Former members are
     * kept — their picks are still club films.
     *
     * @return array<string, ListedFilm[]>
     */
    public function picksByMember(): array
    {
        $rows = $this->pdo->query(<<<SQL
                SELECT rf.picked_by AS username, f.slug, f.title, rf.round_number, rf.position,
                       AVG(r.score) AS average, COUNT(r.score) AS votes
                FROM round_films rf
                JOIN films f        ON f.slug      = rf.film_slug
                LEFT JOIN ratings r ON r.film_slug = f.slug
                WHERE rf.picked_by IS NOT NULL
                GROUP BY rf.round_number, f.slug
                ORDER BY rf.picked_by, rf.round_number, rf.position
            SQL)->fetchAll();

        $picks = [];
        foreach ($rows as $row) {
            $picks[$row['username']][] = new ListedFilm(
                $row['slug'],
                $row['title'],
                $row['average'] !== null ? round((float) $row['average'], 1) : null,
                (int) $row['votes'],
                (int) $row['round_number'],
                $row['username'],
                (int) $row['position'],
                null,
            );
        }

        return $picks;
    }

    /**
     * @return ListedFilm[]
     */
    public function films(bool $withRatings = false): array
    {
        $filmRows = $this->pdo->query(<<<SQL
                SELECT f.slug, f.title, rf.round_number, rf.picked_by, rf.position,
                       AVG(r.score) AS average, COUNT(r.score) AS votes
                FROM films f
                LEFT JOIN round_films rf ON rf.film_slug = f.slug
                LEFT JOIN ratings r      ON r.film_slug  = f.slug
                GROUP BY f.slug
                ORDER BY rf.round_number, rf.position
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
                $f['position'] !== null ? (int) $f['position'] : null,
                $withRatings ? ($ratingsBySlug[$f['slug']] ?? []) : null,
            ),
            $filmRows,
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

        $averageRows = $this->pdo->query(<<<SQL
                SELECT rf.round_number, AVG(r.score) AS average
                FROM round_films rf
                JOIN ratings r ON r.film_slug = rf.film_slug
                GROUP BY rf.round_number
            SQL)->fetchAll();

        $roundAverage = [];
        foreach ($averageRows as $row) {
            $roundAverage[(int) $row['round_number']] = round((float) $row['average'], 1);
        }

        $scoreRows = $this->pdo->query(<<<SQL
                SELECT rf.round_number, rf.position, f.slug, f.title, rf.picked_by, r.score
                FROM round_films rf
                JOIN films f        ON f.slug      = rf.film_slug
                LEFT JOIN ratings r ON r.film_slug = f.slug
                ORDER BY rf.round_number, rf.position
            SQL)->fetchAll();

        /**
         * @var array<int, array<string, array{title: string, pickedBy: ?string, position: int, scores: list<int>}>> $byRound
         */
        $byRound = [];
        foreach ($scoreRows as $row) {
            $n = (int) $row['round_number'];
            $slug = $row['slug'];

            $byRound[$n][$slug]['title'] = $row['title'];
            $byRound[$n][$slug]['pickedBy'] = $row['picked_by'];
            $byRound[$n][$slug]['position'] = (int) $row['position'];
            $byRound[$n][$slug]['scores'] ??= [];

            if ($row['score'] !== null) {
                $byRound[$n][$slug]['scores'][] = (int) $row['score'];
            }
        }

        $rounds = [];
        foreach ($roundRows as $r) {
            $number = (int) $r['number'];
            $filmsInRound = $byRound[$number] ?? [];

            $films = [];
            $qualified = [];

            foreach ($filmsInRound as $slug => $film) {
                $scores = $film['scores'];
                $average = $scores === [] ? null : round(array_sum($scores) / count($scores), 1);

                $films[] = new ListedFilm(
                    $slug,
                    $film['title'],
                    $average,
                    count($scores),
                    $number,
                    $film['pickedBy'],
                    $film['position'],
                    null,
                );

                if (count($scores) >= $this->quorum) {
                    $qualified[] = new RatedFilm(
                        $slug,
                        $film['title'],
                        (float) $average,
                        count($scores),
                        max($scores) - min($scores),
                        $this->populationStdDev($scores),
                    );
                }
            }

            $rounds[] = new RoundView(
                $number,
                $r['started_on'],
                $r['ended_on'],
                $roundAverage[$number] ?? null,
                $this->pickFilm($qualified, static fn (RatedFilm $a, RatedFilm $b) => $b->average <=> $a->average),
                $this->pickFilm($qualified, static fn (RatedFilm $a, RatedFilm $b) => $a->average <=> $b->average),
                $films,
            );
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
                SELECT m.username, m.display_name, r.score, r.review
                FROM ratings r
                JOIN members m ON m.username = r.member_username
                WHERE r.film_slug = :slug
                ORDER BY r.score DESC, m.display_name
            SQL, ['slug' => $slug]);

        $scores = array_map(static fn (array $r) => (int) $r['score'], $ratingRows);
        $average = $scores === [] ? null : round(array_sum($scores) / count($scores), 1);
        $spread = $scores === [] ? null : max($scores) - min($scores);

        $ratings = array_map(
            static fn (array $r) => new MemberScore($r['username'], $r['display_name'], (int) $r['score'], $r['review']),
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

    /**
     * Все фильмы, набравшие кворум, со всеми метриками.
     * stdDev считаем в PHP — в SQLite нет STDDEV.
     *
     * @return RatedFilm[]
     */
    private function ratedFilms(): array
    {
        $rows = $this->pdo->query(<<<SQL
                SELECT f.slug, f.title, r.score
                FROM films f
                JOIN ratings r ON r.film_slug = f.slug
            SQL)->fetchAll();

        /** @var array<string, array{title: string, scores: list<int>}> $byFilm */
        $byFilm = [];
        foreach ($rows as $row) {
            $byFilm[$row['slug']]['title'] = $row['title'];
            $byFilm[$row['slug']]['scores'][] = (int) $row['score'];
        }

        $films = [];
        foreach ($byFilm as $slug => $film) {
            $scores = $film['scores'];

            if (count($scores) < $this->quorum) {
                continue;
            }

            $films[] = new RatedFilm(
                $slug,
                $film['title'],
                round(array_sum($scores) / count($scores), 1),
                count($scores),
                max($scores) - min($scores),
                $this->populationStdDev($scores),
            );
        }

        return $films;
    }

    /**
     * @param RatedFilm[]                         $films
     * @param callable(RatedFilm, RatedFilm): int $comparator
     */
    private function pickFilm(array $films, callable $comparator): ?RatedFilm
    {
        if ($films === []) {
            return null;
        }

        usort($films, $comparator);

        return $films[0];
    }

    /**
     * All films sharing the extreme value of $metric — the whole tie, not one.
     *
     * @param RatedFilm[]                $films
     * @param callable(RatedFilm): float $metric
     *
     * @return RatedFilm[]
     */
    private function topFilms(array $films, callable $metric, bool $highest): array
    {
        if ($films === []) {
            return [];
        }

        $values = array_map($metric, $films);
        $target = $highest ? max($values) : min($values);

        return array_values(array_filter($films, static fn (RatedFilm $f) => $metric($f) === $target));
    }

    /**
     * @param list<int> $scores
     */
    private function populationStdDev(array $scores): float
    {
        $n = count($scores);
        $mean = array_sum($scores) / $n;

        $variance = 0.0;
        foreach ($scores as $score) {
            $variance += ($score - $mean) ** 2;
        }
        $variance /= $n;

        return round(sqrt($variance), 2);
    }

    /**
     * @param MemberStats[] $members
     */
    private function mostActiveMember(array $members): ?MemberStats
    {
        $watched = array_filter($members, static fn (MemberStats $m) => $m->watched > 0);

        if ($watched === []) {
            return null;
        }

        usort($watched, static fn (MemberStats $a, MemberStats $b) => $b->watched <=> $a->watched);

        return $watched[0];
    }

    /**
     * @param MemberStats[] $members
     */
    private function bestCurator(array $members): ?MemberStats
    {
        $qualified = array_values(array_filter(
            $members,
            fn (MemberStats $m) => $m->picks >= $this->minCuratorPicks && $m->pickedAverage !== null,
        ));

        if ($qualified === []) {
            return null;
        }

        usort($qualified, static fn (MemberStats $a, MemberStats $b) => $b->pickedAverage <=> $a->pickedAverage);

        return $qualified[0];
    }

    /**
     * Кураторская статистика: среднее ИЗ СРЕДНИХ по кворумным пикам.
     * Каждый пик весит одинаково — «насколько в среднем заходит одна ставка».
     *
     * @return array<string, array{picks: int, average: float}>
     */
    private function curatorStats(): array
    {
        $rows = $this->pdo->query(<<<SQL
                SELECT rf.picked_by, AVG(r.score) AS film_average
                FROM round_films rf
                JOIN ratings r ON r.film_slug = rf.film_slug
                WHERE rf.picked_by IS NOT NULL
                GROUP BY rf.round_number, rf.film_slug
                HAVING COUNT(r.score) >= {$this->quorum}
            SQL)->fetchAll();

        $byCurator = [];
        foreach ($rows as $row) {
            $byCurator[$row['picked_by']][] = (float) $row['film_average'];
        }

        $stats = [];
        foreach ($byCurator as $username => $averages) {
            $stats[$username] = [
                'picks' => count($averages),
                'average' => round(array_sum($averages) / count($averages), 1),
            ];
        }

        return $stats;
    }

    /**
     * @return array<string, MemberScore[]>
     */
    private function allRatingsGrouped(): array
    {
        $rows = $this->pdo->query(<<<SQL
                SELECT r.film_slug, m.username, m.display_name, r.score, r.review
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
                $row['review'],
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
