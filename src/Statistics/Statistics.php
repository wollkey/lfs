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
            $this->mostActiveMembers($members),
            $this->bestCurators($members),
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
                SELECT f.slug, f.title, rf.round_number, rf.picked_by, rf.position, rf.picked_on,
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
                $f['picked_on'],
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

        $rounds = [];
        foreach ($roundRows as $r) {
            $number = (int) $r['number'];
            ['films' => $films, 'qualified' => $qualified] = $this->filmsInRound($number);

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

    public function roundSummary(int $round): ?RoundSummary
    {
        ['films' => $films, 'qualified' => $qualified] = $this->filmsInRound($round);

        if ($films === []) {
            return null;
        }

        usort(
            $films,
            static fn (ListedFilm $a, ListedFilm $b): int => ($b->average ?? -1.0) <=> ($a->average ?? -1.0),
        );

        $meta = $this->fetchOne('SELECT started_on, ended_on FROM rounds WHERE number = :round', ['round' => $round]);

        $average = $this->fetchOne(<<<SQL
                SELECT AVG(r.score) AS average
                FROM round_films rf
                JOIN ratings r ON r.film_slug = rf.film_slug
                WHERE rf.round_number = :round
            SQL, ['round' => $round]);

        $activity = $this->roundActivity($round);
        $reviewed = array_filter($activity, static fn (MemberActivity $a) => $a->reviews > 0);

        return new RoundSummary(
            $round,
            $meta['started_on'] ?? null,
            $meta['ended_on'] ?? null,
            count($films),
            array_sum(array_map(static fn (MemberActivity $a) => $a->ratings, $activity)),
            array_sum(array_map(static fn (MemberActivity $a) => $a->reviews, $activity)),
            $average !== null && $average['average'] !== null ? round((float) $average['average'], 1) : null,
            $films,
            $this->topFilms($qualified, static fn (RatedFilm $f) => $f->average, true),
            $this->topFilms($qualified, static fn (RatedFilm $f) => $f->average, false),
            $this->topFilms($qualified, static fn (RatedFilm $f) => $f->stdDev, true),
            $this->topFilms($qualified, static fn (RatedFilm $f) => $f->stdDev, false),
            $activity,
            $this->topActivity($activity, static fn (MemberActivity $a) => (float) $a->ratings, true),
            $this->topActivity($reviewed, static fn (MemberActivity $a) => (float) $a->reviews, true),
            $this->topActivity($activity, static fn (MemberActivity $a) => $a->average ?? 0.0, true),
            $this->topActivity($activity, static fn (MemberActivity $a) => $a->average ?? 0.0, false),
            $this->roundBestPicks($round),
            $this->roundHotTakes($round),
        );
    }

    public function filmDetail(string $slug): ?FilmDetail
    {
        $header = $this->fetchOne(<<<SQL
                SELECT f.slug, f.title, rf.round_number, rf.picked_by, rf.picked_on
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
            $header['picked_on'],
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
     * @return array{films: ListedFilm[], qualified: RatedFilm[]}
     */
    private function filmsInRound(int $round): array
    {
        $rows = $this->fetchAll(<<<SQL
                SELECT rf.position, f.slug, f.title, rf.picked_by, rf.picked_on, r.score
                FROM round_films rf
                JOIN films f        ON f.slug      = rf.film_slug
                LEFT JOIN ratings r ON r.film_slug = f.slug
                WHERE rf.round_number = :round
                ORDER BY rf.position
            SQL, ['round' => $round]);

        /** @var array<string, array{title: string, pickedBy: ?string, pickedOn: ?string, position: int, scores: list<int>}> $byFilm */
        $byFilm = [];
        foreach ($rows as $row) {
            $slug = $row['slug'];

            $byFilm[$slug]['title'] = $row['title'];
            $byFilm[$slug]['pickedBy'] = $row['picked_by'];
            $byFilm[$slug]['pickedOn'] = $row['picked_on'];
            $byFilm[$slug]['position'] = (int) $row['position'];
            $byFilm[$slug]['scores'] ??= [];

            if ($row['score'] !== null) {
                $byFilm[$slug]['scores'][] = (int) $row['score'];
            }
        }

        $films = [];
        $qualified = [];
        foreach ($byFilm as $slug => $film) {
            $scores = $film['scores'];
            $average = $scores === [] ? null : round(array_sum($scores) / count($scores), 1);

            $films[] = new ListedFilm(
                $slug,
                $film['title'],
                $average,
                count($scores),
                $round,
                $film['pickedBy'],
                $film['position'],
                $film['pickedOn'],
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

        return ['films' => $films, 'qualified' => $qualified];
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
     * All active members sharing the highest watch count — the whole tie.
     *
     * @param MemberStats[] $members
     *
     * @return MemberStats[]
     */
    private function mostActiveMembers(array $members): array
    {
        $watched = array_filter($members, static fn (MemberStats $m) => $m->watched > 0);

        return $this->topMembers($watched, static fn (MemberStats $m) => (float) $m->watched);
    }

    /**
     * All qualified curators sharing the highest average — the whole tie.
     *
     * @param MemberStats[] $members
     *
     * @return MemberStats[]
     */
    private function bestCurators(array $members): array
    {
        $qualified = array_filter(
            $members,
            fn (MemberStats $m) => $m->picks >= $this->minCuratorPicks && $m->pickedAverage !== null,
        );

        return $this->topMembers($qualified, static fn (MemberStats $m) => (float) $m->pickedAverage);
    }

    /**
     * All members sharing the highest value of $metric — the whole tie, not one.
     *
     * @param MemberStats[]                $members
     * @param callable(MemberStats): float $metric
     *
     * @return MemberStats[]
     */
    private function topMembers(array $members, callable $metric): array
    {
        if ($members === []) {
            return [];
        }

        $values = array_map($metric, $members);
        $target = max($values);

        return array_values(array_filter($members, static fn (MemberStats $m) => $metric($m) === $target));
    }

    /**
     * @return MemberActivity[]
     */
    private function roundActivity(int $round): array
    {
        $rows = $this->fetchAll(<<<SQL
                SELECT m.username, m.display_name,
                       COUNT(r.score) AS ratings,
                       SUM(CASE WHEN r.review IS NOT NULL AND r.review <> '' THEN 1 ELSE 0 END) AS reviews,
                       AVG(r.score) AS average
                FROM ratings r
                JOIN round_films rf ON rf.film_slug = r.film_slug AND rf.round_number = :round
                JOIN members m      ON m.username   = r.member_username
                GROUP BY m.username
                ORDER BY ratings DESC, reviews DESC, m.display_name
            SQL, ['round' => $round]);

        return array_map(
            static fn (array $r) => new MemberActivity(
                $r['username'],
                $r['display_name'],
                (int) $r['ratings'],
                (int) $r['reviews'],
                $r['average'] !== null ? round((float) $r['average'], 1) : null,
            ),
            $rows,
        );
    }

    /**
     * @param MemberActivity[]                $activity
     * @param callable(MemberActivity): float $metric
     *
     * @return MemberActivity[]
     */
    private function topActivity(array $activity, callable $metric, bool $highest): array
    {
        if ($activity === []) {
            return [];
        }

        $values = array_map($metric, $activity);
        $target = $highest ? max($values) : min($values);

        return array_values(array_filter($activity, static fn (MemberActivity $a) => $metric($a) === $target));
    }

    /**
     * @return RoundPick[]
     */
    private function roundBestPicks(int $round): array
    {
        $rows = $this->fetchAll(<<<SQL
                SELECT m.display_name, f.title, AVG(r.score) AS film_average
                FROM round_films rf
                JOIN films f   ON f.slug     = rf.film_slug
                JOIN members m ON m.username = rf.picked_by
                JOIN ratings r ON r.film_slug = rf.film_slug
                WHERE rf.picked_by IS NOT NULL AND rf.round_number = :round
                GROUP BY rf.film_slug
                HAVING COUNT(r.score) >= {$this->quorum}
            SQL, ['round' => $round]);

        $picks = array_map(
            static fn (array $r) => new RoundPick(
                $r['display_name'],
                $r['title'],
                round((float) $r['film_average'], 1),
            ),
            $rows,
        );

        if ($picks === []) {
            return [];
        }

        $target = max(array_map(static fn (RoundPick $p) => $p->average, $picks));

        return array_values(array_filter($picks, static fn (RoundPick $p) => $p->average === $target));
    }

    /**
     * @return HotTake[]
     */
    private function roundHotTakes(int $round): array
    {
        $rows = $this->fetchAll(<<<SQL
                SELECT m.display_name, f.title, r.score, avgs.average AS film_average
                FROM ratings r
                JOIN round_films rf ON rf.film_slug = r.film_slug AND rf.round_number = :round
                JOIN films f        ON f.slug       = r.film_slug
                JOIN members m      ON m.username   = r.member_username
                JOIN (
                    SELECT r2.film_slug, AVG(r2.score) AS average, COUNT(r2.score) AS votes
                    FROM ratings r2
                    JOIN round_films rf2 ON rf2.film_slug = r2.film_slug AND rf2.round_number = :innerRound
                    GROUP BY r2.film_slug
                ) avgs ON avgs.film_slug = r.film_slug
                WHERE avgs.votes >= {$this->quorum}
                ORDER BY m.display_name
            SQL, ['round' => $round, 'innerRound' => $round]);

        $takes = array_map(
            static fn (array $r) => new HotTake(
                $r['display_name'],
                $r['title'],
                (int) $r['score'],
                round((float) $r['film_average'], 1),
            ),
            $rows,
        );

        if ($takes === []) {
            return [];
        }

        $deviation = static fn (HotTake $t): float => abs($t->score - $t->filmAverage);
        $target = max(array_map($deviation, $takes));

        if ($target <= 0.0) {
            return [];
        }

        return array_values(array_filter($takes, static fn (HotTake $t) => $deviation($t) === $target));
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
