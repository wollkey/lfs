<?php

declare(strict_types=1);

namespace App\Console;

use App\Letterboxd\FilmPage;
use App\Letterboxd\Posters;
use App\Persistence\FilmRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'posters:fetch',
    description: 'Re-download every film poster into public/posters/.',
)]
final class FetchPostersCommand extends Command
{
    public function __construct(
        private readonly FilmPage $filmPage,
        private readonly Posters $posters,
        private readonly FilmRepository $films,
    ) {
        parent::__construct();
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Re-download posters that already exist')]
        bool $force = false,
    ): int {
        $downloaded = 0;
        $skipped = 0;

        foreach ($this->films->slugs() as $slug) {
            if ($this->posters->has($slug) && !$force) {
                ++$skipped;
                continue;
            }

            $film = $this->filmPage->fetch($slug);
            if ($film?->posterUrl === null || !$this->posters->fetch($slug, $film->posterUrl)) {
                $io->warning("No poster for {$slug}.");
                ++$skipped;
                continue;
            }

            $io->writeln("saved {$slug}.jpg");
            ++$downloaded;
        }

        $io->success(sprintf('Downloaded %d, skipped %d.', $downloaded, $skipped));

        return Command::SUCCESS;
    }
}
