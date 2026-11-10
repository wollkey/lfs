<?php

declare(strict_types=1);

namespace App\Statistics\Ceremony;

use App\Statistics\Statistics;

/**
 * The club's awards for a year (films picked in it, by `picked_on`): nominees and winners
 * counted from the ratings, the owner's own nominations from `{year}.json` before the
 * last one, and the film of the year saved for the end.
 *
 * @phpstan-type Candidate array{name: string, detail: ?string, slug: ?string, value: int|float, label: string}
 */
final readonly class Ceremony
{
    private const string IN_YEAR = 'SELECT film_slug FROM round_films WHERE substr(picked_on, 1, 4) = :year';

    public function __construct(
        private \PDO $pdo,
        private Statistics $stats,
        private string $nominationsDir,
        private int $quorum = 5,
        private int $minRatings = 10,
        private int $nominees = 3,
    ) {
    }

    /**
     * @throws InvalidNominations
     */
    public function year(int $year): ?CeremonyYear
    {
        $films = $this->films($year);
        if ($films === []) {
            return null;
        }

        $members = $this->activeMembers();
        $rated = array_filter($films, fn (array $f): bool => count($f['scores']) >= $this->quorum);

        $nominations = [
            $this->filmNomination('Провал года', 'Самая низкая средняя оценка клуба', $rated, false),
            $this->divisive($rated),
            $this->hotTake($rated, $members),
            $this->bestPick($year, $rated, $members),
            $this->memberNomination('Самый активный', 'Больше всех оценок', $this->ratingsBy($films, $members), ['оценка', 'оценки', 'оценок']),
            $this->memberNomination('Главный рецензент', 'Больше всех отзывов', $this->reviewsBy($year, $members), ['отзыв', 'отзыва', 'отзывов']),
            ...$this->generosity($films, $members),
            $this->tastePair($year),
            ...$this->ownNominations($year),
            $this->filmNomination('Фильм года', 'Самая высокая средняя оценка клуба', $rated, true),
        ];

        return new CeremonyYear($year, array_values(array_filter($nominations)));
    }

    /**
     * @param array<string, array{title: string, scores: array<string, int>}> $rated
     */
    private function filmNomination(string $title, string $lead, array $rated, bool $highest): ?Nomination
    {
        $candidates = [];
        foreach ($rated as $slug => $film) {
            $average = round($this->mean($film['scores']), 1);
            $candidates[] = $this->candidate($film['title'], null, $slug, $average, number_format($average, 1));
        }

        return $this->nominate($title, $lead, $candidates, $highest);
    }

    /**
     * @param array<string, array{title: string, scores: array<string, int>}> $rated
     */
    private function divisive(array $rated): ?Nomination
    {
        $candidates = [];
        foreach ($rated as $slug => $film) {
            $mean = $this->mean($film['scores']);
            $spread = round(sqrt($this->mean(array_map(static fn (int $s): float => ($s - $mean) ** 2, $film['scores']))), 2);
            $candidates[] = $this->candidate($film['title'], null, $slug, $spread, sprintf('оценки от %d до %d', min($film['scores']), max($film['scores'])));
        }

        return $this->nominate('Самый спорный фильм', 'Оценки разошлись сильнее всего', $candidates, true);
    }

    /**
     * @param array<string, array{title: string, scores: array<string, int>}> $rated
     * @param array<string, string>                                           $members
     */
    private function hotTake(array $rated, array $members): ?Nomination
    {
        $candidates = [];
        foreach ($rated as $slug => $film) {
            $average = $this->mean($film['scores']);
            foreach (array_intersect_key($film['scores'], $members) as $username => $score) {
                $candidates[] = $this->candidate(
                    $members[$username],
                    $film['title'],
                    $slug,
                    round(abs($score - $average), 1),
                    sprintf('%d при средней %s', $score, number_format($average, 1)),
                );
            }
        }

        return $this->nominate('Горячая оценка', 'Оценка дальше всего от мнения клуба', $candidates, true, onePerName: true);
    }

    /**
     * @param array<string, array{title: string, scores: array<string, int>}> $rated
     * @param array<string, string>                                           $members
     */
    private function bestPick(int $year, array $rated, array $members): ?Nomination
    {
        $candidates = [];
        foreach ($this->fetchAll('SELECT film_slug, picked_by FROM round_films WHERE substr(picked_on, 1, 4) = :year AND picked_by IS NOT NULL', $year) as $pick) {
            $film = $rated[$pick['film_slug']] ?? null;
            if ($film !== null && isset($members[$pick['picked_by']])) {
                $average = round($this->mean($film['scores']), 1);
                $candidates[] = $this->candidate($members[$pick['picked_by']], $film['title'], $pick['film_slug'], $average, number_format($average, 1));
            }
        }

        return $this->nominate('Лучший выбор', 'Участник, чей фильм клуб оценил выше всех', $candidates, true, onePerName: true);
    }

    /**
     * @param array<string, int>            $counts
     * @param array{string, string, string} $forms
     */
    private function memberNomination(string $title, string $lead, array $counts, array $forms): ?Nomination
    {
        $candidates = [];
        foreach ($counts as $name => $count) {
            if ($count > 0) {
                $candidates[] = $this->candidate($name, null, null, $count, $this->count($count, $forms));
            }
        }

        return $this->nominate($title, $lead, $candidates, true);
    }

    /**
     * @param array<string, array{title: string, scores: array<string, int>}> $films
     * @param array<string, string>                                           $members
     *
     * @return array<string, int> by display name
     */
    private function ratingsBy(array $films, array $members): array
    {
        $counts = array_fill_keys(array_values($members), 0);
        foreach ($films as $film) {
            foreach (array_intersect_key($film['scores'], $members) as $username => $score) {
                ++$counts[$members[$username]];
            }
        }

        return $counts;
    }

    /**
     * @param array<string, string> $members
     *
     * @return array<string, int> by display name
     */
    private function reviewsBy(int $year, array $members): array
    {
        $counts = [];
        foreach ($this->fetchAll('SELECT member_username, COUNT(*) AS reviews FROM reviews WHERE film_slug IN ('.self::IN_YEAR.') GROUP BY member_username', $year) as $r) {
            if (isset($members[$r['member_username']])) {
                $counts[$members[$r['member_username']]] = (int) $r['reviews'];
            }
        }

        return $counts;
    }

    /**
     * @param array<string, array{title: string, scores: array<string, int>}> $films
     * @param array<string, string>                                           $members
     *
     * @return list<?Nomination>
     */
    private function generosity(array $films, array $members): array
    {
        $given = [];
        foreach ($films as $film) {
            foreach (array_intersect_key($film['scores'], $members) as $username => $score) {
                $given[$username][] = $score;
            }
        }

        $candidates = [];
        foreach ($given as $username => $scores) {
            if (count($scores) >= $this->minRatings) {
                $average = round($this->mean($scores), 1);
                $candidates[] = $this->candidate($members[$username], null, null, $average, number_format($average, 1));
            }
        }

        return [
            $this->nominate('Самые высокие оценки', 'Самая высокая средняя оценка участника', $candidates, true),
            $this->nominate('Самые низкие оценки', 'Самая низкая средняя оценка участника', $candidates, false),
        ];
    }

    private function tastePair(int $year): ?Nomination
    {
        $candidates = [];
        foreach ($this->stats->tasteMatches($year) as $username => $matches) {
            foreach ($matches as $match) {
                if (strcmp($username, $match->username) < 0) {
                    $candidates[] = ['username' => $username, 'match' => $match];
                }
            }
        }

        $names = $this->activeMembers();

        return $this->nominate('Вкусовая пара', 'Чаще всех сходятся во мнении', array_map(
            fn (array $c): array => $this->candidate(
                sprintf('%s и %s', $names[$c['username']], $c['match']->displayName),
                null,
                null,
                $c['match']->agreement,
                sprintf('%d%% совпадений', $c['match']->agreement),
            ),
            $candidates,
        ), true);
    }

    /**
     * @return list<Nomination>
     *
     * @throws InvalidNominations
     */
    private function ownNominations(int $year): array
    {
        $file = sprintf('%s/%d.json', $this->nominationsDir, $year);
        if (!is_file($file)) {
            return [];
        }

        $entries = json_decode((string) file_get_contents($file), true);
        if (!is_array($entries) || !array_is_list($entries)) {
            throw new InvalidNominations("{$file} must hold a JSON list of nominations.");
        }

        return array_map(fn (mixed $entry): Nomination => $this->ownNomination($entry, $file), $entries);
    }

    /**
     * @throws InvalidNominations
     */
    private function ownNomination(mixed $entry, string $file): Nomination
    {
        if (!is_array($entry) || !is_string($entry['title'] ?? null) || !is_array($entry['nominees'] ?? null) || $entry['nominees'] === []) {
            throw new InvalidNominations("Every nomination in {$file} needs a title and a list of nominees.");
        }

        $winners = (array) ($entry['winner'] ?? []);
        $nominees = [];
        foreach (array_values($entry['nominees']) as $index => $nominee) {
            if (!is_array($nominee) || !is_string($nominee['name'] ?? null)) {
                throw new InvalidNominations(sprintf('Nominee %d of "%s" in %s needs a name.', $index, $entry['title'], $file));
            }
            $nominees[] = new Nominee(
                $nominee['name'],
                is_string($nominee['detail'] ?? null) ? $nominee['detail'] : null,
                is_string($nominee['film'] ?? null) ? $nominee['film'] : null,
                null,
                in_array($index, $winners, true),
            );
        }

        return new Nomination($entry['title'], is_string($entry['lead'] ?? null) ? $entry['lead'] : '', $nominees);
    }

    /**
     * @param list<Candidate> $candidates
     * @param bool            $onePerName a member is nominated once, for their strongest entry
     */
    private function nominate(string $title, string $lead, array $candidates, bool $highest, bool $onePerName = false): ?Nomination
    {
        if ($candidates === []) {
            return null;
        }

        usort($candidates, static fn (array $a, array $b): int => ($highest ? $b['value'] <=> $a['value'] : $a['value'] <=> $b['value']) ?: strcmp($a['name'], $b['name']));
        if ($onePerName) {
            $strongest = [];
            foreach ($candidates as $candidate) {
                $strongest[$candidate['name']] ??= $candidate;
            }
            $candidates = array_values($strongest);
        }
        $best = $candidates[0]['value'];
        $winners = count(array_filter($candidates, static fn (array $c): bool => $c['value'] === $best));

        return new Nomination($title, $lead, array_map(
            static fn (array $c): Nominee => new Nominee($c['name'], $c['detail'], $c['slug'], $c['label'], $c['value'] === $best),
            array_slice($candidates, 0, max($this->nominees, $winners)),
        ));
    }

    /**
     * @return Candidate
     */
    private function candidate(string $name, ?string $detail, ?string $slug, int|float $value, string $label): array
    {
        return ['name' => $name, 'detail' => $detail, 'slug' => $slug, 'value' => $value, 'label' => $label];
    }

    /**
     * @return array<string, array{title: string, scores: array<string, int>}>
     */
    private function films(int $year): array
    {
        $films = [];
        foreach ($this->fetchAll('SELECT f.slug, f.title, r.member_username, r.score FROM films f JOIN ratings r ON r.film_slug = f.slug WHERE f.slug IN ('.self::IN_YEAR.') ORDER BY f.title', $year) as $r) {
            $films[$r['slug']]['title'] = $r['title'];
            $films[$r['slug']]['scores'][$r['member_username']] = (int) $r['score'];
        }

        return $films;
    }

    /**
     * @return array<string, string> display names by username
     */
    private function activeMembers(): array
    {
        return array_column(
            $this->pdo->query("SELECT username, display_name FROM members WHERE status = 'active' ORDER BY position NULLS LAST, display_name")->fetchAll(),
            'display_name',
            'username',
        );
    }

    /**
     * @param array<int|float> $values
     */
    private function mean(array $values): float
    {
        return array_sum($values) / count($values);
    }

    /**
     * @param array{string, string, string} $forms one, few, many
     */
    private function count(int $n, array $forms): string
    {
        $form = match (true) {
            $n % 10 === 1 && $n % 100 !== 11 => $forms[0],
            $n % 10 >= 2 && $n % 10 <= 4 && ($n % 100 < 12 || $n % 100 > 14) => $forms[1],
            default => $forms[2],
        };

        return $n.' '.$form;
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
