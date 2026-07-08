<?php

declare(strict_types=1);

namespace App\Console;

use App\Persistence\RatingRepository;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'rating:set', description: 'Set (or update) one member rating for a film.')]
final class SetRatingCommand extends Command
{
    public function __construct(
        private readonly RatingRepository $ratings,
    ) {
        parent::__construct();
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Film slug, e.g. "the-zone-of-interest"')]
        string $film,
        #[Argument(description: 'Member username, e.g. "wollkey"')]
        string $member,
        #[Argument(description: 'Score 1–10')]
        int $score,
    ): int {
        try {
            $this->ratings->setRating($film, $member, $score);
        } catch (\InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::INVALID;
        } catch (\PDOException $e) {
            $io->error('Could not save. Do the film and member exist? '.$e->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf('%s rated %s → %d.', $member, $film, $score));

        return Command::SUCCESS;
    }
}
