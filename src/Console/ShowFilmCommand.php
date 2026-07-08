<?php

declare(strict_types=1);

namespace App\Console;

use App\Persistence\RatingRepository;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'film:show', description: 'Show all ratings for a film.')]
final class ShowFilmCommand extends Command
{
    public function __construct(
        private readonly RatingRepository $ratings,
    ) {
        parent::__construct();
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Film slug')]
        string $film,
    ): int {
        $rows = $this->ratings->forFilm($film);

        if ($rows === []) {
            $io->warning(sprintf('No ratings for "%s" yet.', $film));

            return Command::SUCCESS;
        }

        $io->table(
            ['Member', 'Score'],
            array_map(static fn ($r) => [$r->username, $r->score->value], $rows),
        );

        return Command::SUCCESS;
    }
}
