<?php

declare(strict_types=1);

namespace App\Console;

use App\Domain\MemberStatus;
use App\Domain\Score;
use App\Persistence\FilmRepository;
use App\Persistence\MemberRepository;
use App\Persistence\RatingRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'rating:add',
    description: 'Интерактивно внести оценки одного участника (batch).',
)]
final class AddRatingsCommand extends Command
{
    public function __construct(
        private readonly MemberRepository $members,
        private readonly FilmRepository $films,
        private readonly RatingRepository $ratings,
    ) {
        parent::__construct();
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $usernameByLabel = $this->activeMemberLabels();
        if ($usernameByLabel === []) {
            $io->error('Нет активных участников. Сначала members:seed.');

            return Command::FAILURE;
        }

        $slugByLabel = $this->filmLabels();
        if ($slugByLabel === []) {
            $io->error('В базе нет фильмов.');

            return Command::FAILURE;
        }

        $memberLabel = $io->choice('Чьи оценки вносим?', array_keys($usernameByLabel));
        $username = $usernameByLabel[$memberLabel];

        $io->writeln(sprintf(
            'Вносим оценки: <info>%s</info>. Пустой ввод названия — закончить.',
            $memberLabel,
        ));

        $entered = 0;
        while (true) {
            $slug = $this->askFilm($io, $slugByLabel);
            if ($slug === null) {
                break;
            }

            $current = $this->ratings->findScore($slug, $username);
            if ($current !== null) {
                $io->writeln(sprintf('  текущая: <comment>%d</comment> (перезапишем)', $current));
            }

            $score = $this->askScore($io);
            $this->ratings->setRating($slug, $username, $score);
            ++$entered;

            $io->writeln(sprintf('  <info>✓</info> %s → %d', $slug, $score));
        }

        $io->success(sprintf('Готово. Внесено/обновлено: %d.', $entered));

        return Command::SUCCESS;
    }

    /**
     * @return array<string, string> метка => username, только активные
     */
    private function activeMemberLabels(): array
    {
        $labels = [];
        foreach ($this->members->all() as $member) {
            if ($member->status !== MemberStatus::Active) {
                continue;
            }
            $label = sprintf('%s (@%s)', $member->displayName, $member->username);
            $labels[$label] = $member->username;
        }

        return $labels;
    }

    /**
     * @return array<string, string> метка => slug; при совпадении названий slug добавляется в метку
     */
    private function filmLabels(): array
    {
        $films = $this->films->all();

        $titleCount = [];
        foreach ($films as $film) {
            $titleCount[$film->title] = ($titleCount[$film->title] ?? 0) + 1;
        }

        $labels = [];
        foreach ($films as $film) {
            $label = $titleCount[$film->title] > 1
                ? sprintf('%s [%s]', $film->title, $film->slug)
                : $film->title;
            $labels[$label] = $film->slug;
        }

        return $labels;
    }

    /**
     * @param array<string, string> $slugByLabel
     *
     * @return string|null slug выбранного фильма, либо null при пустом вводе (выход)
     */
    private function askFilm(SymfonyStyle $io, array $slugByLabel): ?string
    {
        $question = new Question('Фильм');
        $question->setAutocompleterValues(array_keys($slugByLabel));
        $question->setValidator(static function (?string $answer) use ($slugByLabel): ?string {
            $title = trim((string) $answer);
            if ($title === '') {
                return null;
            }
            if (!isset($slugByLabel[$title])) {
                throw new \RuntimeException("Фильм не найден: «{$title}». Начните вводить название и нажмите Tab.");
            }

            return $slugByLabel[$title];
        });

        return $io->askQuestion($question);
    }

    private function askScore(SymfonyStyle $io): int
    {
        $question = new Question('  Оценка (1–10)');
        $question->setMaxAttempts(null);
        $question->setValidator(static function (?string $answer): int {
            $raw = trim((string) $answer);
            if (!ctype_digit($raw)) {
                throw new \RuntimeException('Нужно целое число.');
            }

            $score = (int) $raw;
            if ($score < Score::MIN || $score > Score::MAX) {
                throw new \RuntimeException('Оценка вне диапазона 1–10.');
            }

            return $score;
        });

        return $io->askQuestion($question);
    }
}
