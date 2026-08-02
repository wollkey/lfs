<?php

declare(strict_types=1);

namespace App\Telegram;

use App\Domain\MemberStatus;
use App\Statistics\HotTake;
use App\Statistics\ListedFilm;
use App\Statistics\MemberActivity;
use App\Statistics\MemberStats;
use App\Statistics\RatedFilm;
use App\Statistics\RoundPick;
use App\Statistics\RoundSummary;
use App\Statistics\Statistics;

final readonly class Messages
{
    public function __construct(
        private Statistics $stats,
        private string $siteUrl,
    ) {
    }

    public function forCommand(string $command): ?Post
    {
        return match ($this->normalize($command)) {
            'members' => $this->activeMembers(),
            'films' => $this->watchedFilms(),
            'links' => $this->links(),
            default => null,
        };
    }

    public function activeMembers(): Post
    {
        $active = array_values(array_filter(
            $this->stats->membersWithStats(),
            static fn (MemberStats $member): bool => $member->status === MemberStatus::Active,
        ));

        if ($active === []) {
            return new Post('👥 Активные участники', intro: 'Пока нет активных участников.');
        }

        $rows = [];
        foreach ($active as $index => $member) {
            $rows[] = [
                new Cell((string) ($index + 1)),
                new Cell($member->displayName, $this->letterboxdUrl($member->username)),
                new Cell((string) $member->watched),
                new Cell($this->rating($member->averageGiven)),
            ];
        }

        return new Post('👥 Активные участники', new Table(['#', 'Участник', 'Фильмов', 'Ср. балл'], $rows));
    }

    public function watchedFilms(): Post
    {
        return new Post(
            '🎬 Фильмы',
            intro: 'Полный список просмотренных фильмов и статистика — на сайте:',
            links: [new Cell('Открыть список фильмов', $this->siteUrl.'/films')],
        );
    }

    public function links(): Post
    {
        return new Post('🔗 Ссылки клуба', links: [
            new Cell('Главная', $this->siteUrl.'/'),
            new Cell('Фильмы', $this->siteUrl.'/films'),
            new Cell('Круги', $this->siteUrl.'/rounds'),
            new Cell('Участники', $this->siteUrl.'/members'),
        ]);
    }

    public function currentRoundStandings(): Post
    {
        $current = $this->stats->currentRound();
        if ($current === null) {
            return new Post('🎬 Итоги недели', intro: 'Круги ещё не начались.');
        }

        $films = null;
        foreach ($this->stats->rounds() as $round) {
            if ($round->number === $current) {
                $films = $round->films;
                break;
            }
        }

        $title = sprintf('Круг %d 🎬 итоги недели', $current);

        if ($films === null || $films === []) {
            return new Post($title, intro: 'В этом круге пока нет фильмов.');
        }

        usort(
            $films,
            static fn (ListedFilm $a, ListedFilm $b): int => ($b->average ?? -1.0) <=> ($a->average ?? -1.0),
        );

        $rows = [];
        foreach ($films as $index => $film) {
            $rows[] = [
                new Cell((string) ($index + 1)),
                new Cell($film->title),
                new Cell($this->rating($film->average)),
                new Cell((string) $film->votes),
            ];
        }

        return new Post($title, new Table(['№', 'Фильм', 'Рейтинг', 'Оценок'], $rows));
    }

    /**
     * @return array{caption: string, cards: Post[]}
     */
    public function roundSummary(int $round): array
    {
        $summary = $this->stats->roundSummary($round);

        if ($summary === null) {
            return ['caption' => sprintf('Круг %d ещё не собрал фильмов.', $round), 'cards' => []];
        }

        return [
            'caption' => $this->roundCaption($summary),
            'cards' => [
                $this->roundStandings($summary),
                $this->filmAwards($summary),
                $this->memberAwards($summary),
                $this->roundActivity($summary),
            ],
        ];
    }

    private function roundCaption(RoundSummary $s): string
    {
        $lines = [sprintf('🎬 Круг %d закрыт - подводим итоги!', $s->number), ''];

        if ($s->best !== []) {
            $lines[] = sprintf('🏆 Лучший фильм: %s (%s)', $this->titles($s->best), $this->rating($s->best[0]->average));
        }
        if ($s->worst !== []) {
            $lines[] = sprintf('💩 Аутсайдер: %s (%s)', $this->titles($s->worst), $this->rating($s->worst[0]->average));
        }
        if ($s->divisive !== []) {
            $lines[] = sprintf('⚔️ Больше всего спорили о фильме %s', $this->titles($s->divisive));
        }
        if ($s->mostRatings !== []) {
            $lines[] = sprintf('👑 Активнее всех: %s (%d оценок)', $this->memberNames($s->mostRatings), $s->mostRatings[0]->ratings);
        }
        if ($s->mostReviews !== []) {
            $lines[] = sprintf('✍️ Больше всех рецензий - %s', $this->memberNames($s->mostReviews));
        }
        if ($s->hotTakes !== []) {
            $lines[] = sprintf('🌶️ Горячая оценка: %s', $this->hotTakes($s->hotTakes));
        }
        if ($s->average !== null) {
            $lines[] = sprintf('Средний балл круга - %s.', $this->rating($s->average));
        }

        $lines[] = '';
        $lines[] = 'Как вам круг? Давайте обсуждать!';

        return implode("\n", $lines);
    }

    /**
     * @param HotTake[] $takes
     */
    private function hotTakes(array $takes): string
    {
        return implode('; ', array_map(
            fn (HotTake $t): string => sprintf('%s - %d фильму %s (средняя %s)', $t->displayName, $t->score, $t->filmTitle, $this->rating($t->filmAverage)),
            $takes,
        ));
    }

    private function roundStandings(RoundSummary $s): Post
    {
        $rows = [];
        foreach ($s->films as $index => $film) {
            $rows[] = [
                new Cell((string) ($index + 1)),
                new Cell($film->title),
                new Cell($this->rating($film->average)),
                new Cell((string) $film->votes),
            ];
        }

        return new Post(
            sprintf('Круг %d 🎬 рейтинг фильмов', $s->number),
            new Table(['№', 'Фильм', 'Рейтинг', 'Оценок'], $rows),
        );
    }

    private function roundActivity(RoundSummary $s): Post
    {
        $rows = [];
        foreach ($s->activity as $index => $member) {
            $rows[] = [
                new Cell((string) ($index + 1)),
                new Cell($member->displayName),
                new Cell((string) $member->ratings),
                new Cell((string) $member->reviews),
            ];
        }

        return new Post(
            sprintf('Круг %d 👥 активность', $s->number),
            new Table(['№', 'Участник', 'Оценки', 'Отзывы'], $rows),
        );
    }

    private function filmAwards(RoundSummary $s): Post
    {
        $average = fn (RatedFilm $f): string => $this->rating($f->average);
        $spread = static fn (RatedFilm $f): string => 'разброс '.$f->spread;

        $rows = [
            $this->panel('Лучший фильм', $this->titles($s->best), $this->filmValue($s->best, $average)),
            $this->panel('Худший фильм', $this->titles($s->worst), $this->filmValue($s->worst, $average)),
            $this->panel('Самый спорный', $this->titles($s->divisive), $this->filmValue($s->divisive, $spread)),
            $this->panel('Мнения совпали', $this->titles($s->agreed), $this->filmValue($s->agreed, $spread)),
        ];

        return new Post(
            sprintf('Круг %d 🏆 награды фильмам', $s->number),
            new Table([], $rows),
        );
    }

    private function memberAwards(RoundSummary $s): Post
    {
        $ratings = static fn (MemberActivity $m): string => (string) $m->ratings;
        $reviews = static fn (MemberActivity $m): string => (string) $m->reviews;
        $average = fn (MemberActivity $m): string => $this->rating($m->average);

        $rows = [
            $this->panel('Больше всех оценок', $this->memberNames($s->mostRatings), $this->memberValue($s->mostRatings, $ratings)),
            $this->panel('Больше всех рецензий', $this->memberNames($s->mostReviews), $this->memberValue($s->mostReviews, $reviews)),
            $this->panel('Самый щедрый', $this->memberNames($s->mostGenerous), $this->memberValue($s->mostGenerous, $average)),
            $this->panel('Самый строгий', $this->memberNames($s->harshest), $this->memberValue($s->harshest, $average)),
            $this->panel('Лучший выбор', $this->pickNames($s->bestPicks), $this->pickValue($s->bestPicks)),
        ];

        return new Post(
            sprintf('Круг %d 🎖️ награды участникам', $s->number),
            new Table([], $rows),
        );
    }

    /**
     * @return list<Cell>
     */
    private function panel(string $label, string $primary, string $value): array
    {
        return [new Cell($label), new Cell($primary), new Cell($value)];
    }

    /**
     * @param RatedFilm[] $films
     */
    private function titles(array $films): string
    {
        return $films === [] ? '—' : implode(', ', array_map(static fn (RatedFilm $f): string => $f->title, $films));
    }

    /**
     * @param RatedFilm[]                 $films
     * @param callable(RatedFilm): string $value
     */
    private function filmValue(array $films, callable $value): string
    {
        return $films === [] ? '' : $value($films[0]);
    }

    /**
     * @param MemberActivity[] $members
     */
    private function memberNames(array $members): string
    {
        return $members === [] ? '—' : implode(', ', array_map(static fn (MemberActivity $m): string => $m->displayName, $members));
    }

    /**
     * @param MemberActivity[]                 $members
     * @param callable(MemberActivity): string $value
     */
    private function memberValue(array $members, callable $value): string
    {
        return $members === [] ? '' : $value($members[0]);
    }

    /**
     * @param RoundPick[] $picks
     */
    private function pickNames(array $picks): string
    {
        return $picks === [] ? '—' : implode(', ', array_map(static fn (RoundPick $p): string => sprintf('%s (%s)', $p->pickedBy, $p->filmTitle), $picks));
    }

    /**
     * @param RoundPick[] $picks
     */
    private function pickValue(array $picks): string
    {
        return $picks === [] ? '' : $this->rating($picks[0]->average);
    }

    private function rating(?float $value): string
    {
        return $value !== null ? number_format($value, 1) : '—';
    }

    private function letterboxdUrl(string $username): string
    {
        return 'https://letterboxd.com/'.$username.'/';
    }

    private function normalize(string $command): string
    {
        $command = trim($command);
        if ($command === '' || $command[0] !== '/') {
            return '';
        }

        $word = preg_split('/\s+/', substr($command, 1))[0] ?? '';
        $at = strpos($word, '@');
        if ($at !== false) {
            $word = substr($word, 0, $at);
        }

        return strtolower($word);
    }
}
