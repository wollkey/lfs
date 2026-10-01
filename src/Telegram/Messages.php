<?php

declare(strict_types=1);

namespace App\Telegram;

use App\Domain\MemberStatus;
use App\Statistics\HotTake;
use App\Statistics\ListedFilm;
use App\Statistics\MemberActivity;
use App\Statistics\MemberScore;
use App\Statistics\MemberSpotlight;
use App\Statistics\MemberStats;
use App\Statistics\RatedFilm;
use App\Statistics\RoundPick;
use App\Statistics\RoundSummary;
use App\Statistics\ScoredFilm;
use App\Statistics\Statistics;

final readonly class Messages
{
    public function __construct(
        private Statistics $stats,
        private string $siteUrl,
        private string $postersDir = 'public/posters',
    ) {
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

    public function flashback(?\DateTimeImmutable $now = null): ?Post
    {
        $weekAgo = ($now ?? new \DateTimeImmutable())->modify('-1 year')->modify('monday this week');

        $films = $this->stats->filmsPickedBetween(
            $weekAgo->format('Y-m-d'),
            $weekAgo->modify('+6 days')->format('Y-m-d'),
        );

        if ($films === []) {
            return null;
        }

        $images = [];
        $lines = ['<b>🕰 Год назад в этот день</b>', ''];
        foreach ($films as $film) {
            $images[] = $this->poster($film->slug);
            $lines[] = sprintf('%s - %s (%d оценок)', $this->filmLink($film->slug, $film->title), $this->rating($film->average), $film->votes);
        }

        return new Post('🕰 Год назад в этот день', intro: implode("\n", $lines), images: $images);
    }

    public function weeklyHighlight(): ?Post
    {
        $film = $this->stats->latestRatedFilm();
        if ($film === null || $film->ratings === []) {
            return null;
        }

        $scores = array_map(static fn (MemberScore $r): int => $r->score, $film->ratings);
        $max = max($scores);
        $min = min($scores);

        $lines = [
            '<b>🎬 Последний кадр</b>',
            '',
            sprintf('%s - %s', $this->filmLink($film->slug, $film->title), $this->rating($film->average)),
        ];

        if ($max === $min) {
            $lines[] = sprintf('Единогласно - %d', $max);
        } else {
            $lines[] = sprintf('Высшая оценка: %s (%d)', $this->esc($this->scorers($film->ratings, $max)), $max);
            $lines[] = sprintf('Низшая оценка: %s (%d)', $this->esc($this->scorers($film->ratings, $min)), $min);
        }

        return new Post('🎬 Последний кадр', intro: implode("\n", $lines), images: [$this->poster($film->slug)]);
    }

    public function spotlight(?\DateTimeImmutable $now = null): ?Post
    {
        $monday = ($now ?? new \DateTimeImmutable())->modify('monday this week');
        $s = $this->stats->pickerSpotlight($monday->format('Y-m-d'), $monday->modify('+6 days')->format('Y-m-d'));

        if ($s === null) {
            return null;
        }

        $sections = array_filter([
            sprintf("<b>⭐ Неделя славы - %s</b>\nВыбор недели - %s", $this->mention($s), $this->filmLink($s->pick->slug, $s->pick->title)),
            $this->section('🎬 Выбор фильмов', $this->pickLines($s)),
            $this->section('🍿 Оценки', $this->ratingLines($s)),
            $this->favorites($s->favorites),
        ]);

        return new Post(
            '⭐ Неделя славы',
            new Table([], [[new Cell($s->displayName), new Cell($s->pick->title)]]),
            implode("\n\n", $sections),
        );
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

    private function mention(MemberSpotlight $s): string
    {
        return $s->telegramUserId === null
            ? $this->esc($s->displayName)
            : sprintf('<a href="tg://user?id=%d">%s</a>', $s->telegramUserId, $this->esc($s->displayName));
    }

    /**
     * @param list<string> $lines
     */
    private function section(string $heading, array $lines): string
    {
        return $lines === [] ? '' : sprintf("<b>%s</b>\n<blockquote>%s</blockquote>", $heading, implode("\n", $lines));
    }

    /**
     * @return list<string>
     */
    private function pickLines(MemberSpotlight $s): array
    {
        $lines = [];

        if ($s->bestPicks !== []) {
            $lines[] = sprintf('Лучший - %s (%s)', $this->listedLinks($s->bestPicks), $this->rating($s->bestPicks[0]->average));
        }
        if ($s->worstPicks !== []) {
            $lines[] = sprintf('Худший - %s (%s)', $this->listedLinks($s->worstPicks), $this->rating($s->worstPicks[0]->average));
        }

        $previous = $s->previousPick;
        if ($previous !== null && !in_array($previous, [...$s->bestPicks, ...$s->worstPicks], true)) {
            $lines[] = sprintf('Прошлый - %s (%s)', $this->filmLink($previous->slug, $previous->title), $this->rating($previous->average));
        }
        if ($previous === null) {
            $lines[] = 'Первый выбор в клубе - дебют!';
        }

        if ($s->picksAverage !== null && $s->clubPicksAverage !== null) {
            $lines[] = sprintf('Средний рейтинг - %s (по клубу %s)', $this->rating($s->picksAverage), $this->rating($s->clubPicksAverage));
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function ratingLines(MemberSpotlight $s): array
    {
        if ($s->ratings === 0) {
            return [];
        }

        $line = sprintf('Всего - %d, средняя %s', $s->ratings, $this->rating($s->averageGiven));
        if ($s->leaning !== null && abs($s->leaning) >= 0.1) {
            $line .= sprintf(' - на %s %s, чем у клуба', number_format(abs($s->leaning), 1), $s->leaning > 0 ? 'выше' : 'ниже');
        }

        $lines = [$line];

        if ($s->hotTakes !== []) {
            $lines[] = '🌶️ Самая спорная - '.implode(', ', array_map(
                fn (ScoredFilm $f): string => sprintf('%s (%d при средней %s)', $this->filmLink($f->slug, $f->title), $f->score, $this->rating($f->average)),
                $s->hotTakes,
            ));
        }

        return $lines;
    }

    /**
     * @param ScoredFilm[] $films
     */
    private function favorites(array $films): string
    {
        if ($films === []) {
            return '';
        }

        return sprintf(
            "<b>❤️ %s - %d из 10</b>\n<blockquote expandable>%s</blockquote>",
            count($films) > 1 ? 'Любимые фильмы' : 'Любимый фильм',
            $films[0]->score,
            implode("\n", array_map(fn (ScoredFilm $f): string => $this->filmLink($f->slug, $f->title), $films)),
        );
    }

    /**
     * @param ListedFilm[] $films
     */
    private function listedLinks(array $films): string
    {
        return implode(', ', array_map(fn (ListedFilm $f): string => $this->filmLink($f->slug, $f->title), $films));
    }

    private function filmLink(string $slug, string $title): string
    {
        return sprintf('<b><a href="%s">%s</a></b>', $this->filmUrl($slug), $this->esc($title));
    }

    /**
     * @param MemberScore[] $ratings
     */
    private function scorers(array $ratings, int $score): string
    {
        $names = [];
        foreach ($ratings as $rating) {
            if ($rating->score === $score) {
                $names[] = $rating->displayName;
            }
        }

        return implode(', ', $names);
    }

    private function poster(string $slug): string
    {
        return $this->postersDir.'/'.$slug.'.jpg';
    }

    private function filmUrl(string $slug): string
    {
        return $this->siteUrl.'/films/'.rawurlencode($slug);
    }

    private function esc(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
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
            $this->panel('Самые высокие оценки', $this->memberNames($s->mostGenerous), $this->memberValue($s->mostGenerous, $average)),
            $this->panel('Самые низкие оценки', $this->memberNames($s->harshest), $this->memberValue($s->harshest, $average)),
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
}
