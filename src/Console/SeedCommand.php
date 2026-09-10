<?php

declare(strict_types=1);

namespace App\Console;

use App\Domain\Film;
use App\Letterboxd\FilmPage;
use App\Letterboxd\Parser\ListParser;
use App\Letterboxd\Posters;
use App\Persistence\FilmRepository;
use App\Seeding\NewFilms;
use App\Seeding\ScrapedRatings;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'seed',
    description: 'Update the club from the scraped HTML: new films, ratings and missing posters.',
)]
final class SeedCommand extends Command
{
    public function __construct(
        private readonly ListParser $listParser,
        private readonly NewFilms $newFilms,
        private readonly ScrapedRatings $scrapedRatings,
        private readonly FilmPage $filmPage,
        private readonly Posters $posters,
        private readonly FilmRepository $films,
    ) {
        parent::__construct();
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Directory with list.html, friends/ and friends_activity/')]
        string $htmlDir = 'data',
    ): int {
        if (!is_dir($htmlDir)) {
            $io->error("HTML directory not found: {$htmlDir}");

            return Command::INVALID;
        }

        $this->seedTitles($htmlDir);
        $this->seedFilms($io, $htmlDir);
        $this->seedRatings($io, $htmlDir);
        $this->seedPosters($io);

        $io->success('Database up to date. Next: make deploy.');

        return Command::SUCCESS;
    }

    private function seedTitles(string $htmlDir): void
    {
        $file = "{$htmlDir}/list.html";
        if (!is_file($file)) {
            return;
        }

        foreach ($this->listParser->parse((string) file_get_contents($file)) as $film) {
            $this->films->save(new Film($film->slug, $film->title));
        }
    }

    private function seedFilms(SymfonyStyle $io, string $htmlDir): void
    {
        $pending = $this->newFilms->pending($htmlDir);
        if ($pending === []) {
            return;
        }

        $today = date('Y-m-d');

        foreach ($pending as $slug) {
            $added = $this->newFilms->add($slug, $today);

            if ($added === null) {
                $io->warning("Could not read the Letterboxd page for {$slug}.");
                continue;
            }

            $io->text(sprintf(
                'Added "%s" — round %d, pick #%d, picked by %s.',
                $added['title'],
                $added['round'],
                $added['position'],
                $added['picker'] ?? 'nobody yet (run make pick)',
            ));
        }
    }

    private function seedRatings(SymfonyStyle $io, string $htmlDir): void
    {
        ['added' => $added, 'updated' => $updated, 'skipped' => $skipped] = $this->scrapedRatings->import($htmlDir);

        foreach ($skipped as $username) {
            $io->warning(sprintf('Skipping "%s": not a club member.', $username));
        }

        $io->text($added === 0 && $updated === 0
            ? 'Ratings: nothing changed.'
            : sprintf('Ratings: %d new, %d changed.', $added, $updated));
    }

    private function seedPosters(SymfonyStyle $io): void
    {
        $fetched = 0;

        foreach ($this->films->slugs() as $slug) {
            if ($this->posters->has($slug)) {
                if (!$this->posters->resize($slug)) {
                    $io->warning("Could not resize {$slug}.");
                }

                continue;
            }

            $film = $this->filmPage->fetch($slug);
            if ($film?->posterUrl === null || !$this->posters->fetch($slug, $film->posterUrl)) {
                $io->warning("No poster for {$slug}.");
                continue;
            }

            ++$fetched;
        }

        if ($fetched > 0) {
            $io->text(sprintf('Posters: %d downloaded.', $fetched));
        }
    }
}
