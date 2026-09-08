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
    description: 'Enter the ratings of one member interactively.',
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
            $io->error('No active members in the database.');

            return Command::FAILURE;
        }

        $slugByLabel = $this->filmLabels();
        if ($slugByLabel === []) {
            $io->error('No films in the database.');

            return Command::FAILURE;
        }

        $memberLabel = $io->choice('Whose ratings are we entering?', array_keys($usernameByLabel));
        $username = $usernameByLabel[$memberLabel];

        $io->writeln(sprintf(
            'Entering ratings for <info>%s</info>. Empty title to finish.',
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
                $io->writeln(sprintf('  current: <comment>%d</comment> (will be overwritten)', $current));
            }

            $score = $this->askScore($io);
            $this->ratings->setRating($slug, $username, $score);
            ++$entered;

            $io->writeln(sprintf('  <info>✓</info> %s → %d', $slug, $score));
        }

        $io->success(sprintf('Done. Entered or updated: %d.', $entered));

        return Command::SUCCESS;
    }

    /**
     * @return array<string, string>
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
     * @return array<string, string>
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
     */
    private function askFilm(SymfonyStyle $io, array $slugByLabel): ?string
    {
        $question = new Question('Film');
        $question->setAutocompleterValues(array_keys($slugByLabel));
        $question->setValidator(static function (?string $answer) use ($slugByLabel): ?string {
            $title = trim((string) $answer);
            if ($title === '') {
                return null;
            }
            if (!isset($slugByLabel[$title])) {
                throw new \RuntimeException("No such film: \"{$title}\". Start typing the title and press Tab.");
            }

            return $slugByLabel[$title];
        });

        return $io->askQuestion($question);
    }

    private function askScore(SymfonyStyle $io): int
    {
        $question = new Question('  Score (1–10)');
        $question->setMaxAttempts(null);
        $question->setValidator(static function (?string $answer): int {
            $raw = trim((string) $answer);
            if (!ctype_digit($raw)) {
                throw new \RuntimeException('A whole number is required.');
            }

            $score = (int) $raw;
            if ($score < Score::MIN || $score > Score::MAX) {
                throw new \RuntimeException('Score is outside the 1–10 range.');
            }

            return $score;
        });

        return $io->askQuestion($question);
    }
}
