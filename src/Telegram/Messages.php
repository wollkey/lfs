<?php

declare(strict_types=1);

namespace App\Telegram;

use App\Domain\MemberStatus;
use App\Statistics\ListedFilm;
use App\Statistics\MemberStats;
use App\Statistics\Statistics;

final readonly class Messages
{
    public function __construct(
        private Statistics $stats,
    ) {
    }

    public function forCommand(string $command): ?Post
    {
        return match ($this->normalize($command)) {
            'members' => $this->activeMembers(),
            'films' => $this->watchedFilms(),
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
                (string) ($index + 1),
                $member->displayName,
                (string) $member->watched,
                $this->rating($member->averageGiven),
            ];
        }

        return new Post('👥 Активные участники', new Table(['#', 'Участник', 'Фильмов', 'Ср. балл'], $rows));
    }

    public function watchedFilms(): Post
    {
        $films = array_values(array_filter(
            $this->stats->films(),
            static fn (ListedFilm $film): bool => $film->votes > 0,
        ));

        if ($films === []) {
            return new Post('🎬 Просмотренные фильмы', intro: 'Пока нет просмотренных фильмов.');
        }

        $rows = [];
        foreach ($films as $film) {
            $rows[] = [
                $film->round !== null ? (string) $film->round : '—',
                $film->title,
                $this->rating($film->average),
                (string) $film->votes,
            ];
        }

        return new Post('🎬 Просмотренные фильмы', new Table(['Круг', 'Фильм', 'Балл', 'Голосов'], $rows));
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
                (string) ($index + 1),
                $film->title,
                $this->rating($film->average),
                (string) $film->votes,
            ];
        }

        return new Post($title, new Table(['Место', 'Фильм', 'Средняя', 'Голосов'], $rows));
    }

    private function rating(?float $value): string
    {
        return $value !== null ? number_format($value, 1) : '—';
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
