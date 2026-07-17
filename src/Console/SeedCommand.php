<?php

declare(strict_types=1);

namespace App\Console;

use App\Domain\Film;
use App\Letterboxd\Dto\ParsedFilm;
use App\Letterboxd\Parser\FriendsRatingsParser;
use App\Letterboxd\Parser\ListParser;
use App\Persistence\FilmRepository;
use App\Persistence\RatingRepository;
use App\Persistence\RoundRepository;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'seed',
    description: 'Parse local HTML and upsert films, ratings and round structure into the DB.',
)]
final class SeedCommand extends Command
{
    private const int FIRST_ROUND_SIZE = 20;
    private const int ROUND_SIZE = 10;

    public function __construct(
        private readonly ListParser $listParser,
        private readonly FriendsRatingsParser $friendsParser,
        private readonly FilmRepository $films,
        private readonly RatingRepository $ratings,
        private readonly RoundRepository $rounds,
    ) {
        parent::__construct();
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Directory with list.html and friends/{slug}.html')]
        string $htmlDir = 'data',
    ): int {
        $listFile = "{$htmlDir}/list.html";
        if (!is_file($listFile)) {
            $io->error("List HTML not found: {$listFile}");

            return Command::INVALID;
        }

        $listHtml = (string) file_get_contents($listFile);
        $films = $this->listParser->parse($listHtml);

        $this->seedFilms($films);
        $this->seedRounds($films);

        $ratingsLoaded = $this->seedRatings($io, $htmlDir, $listHtml, $films);

        $io->success(sprintf('Seeded %d film(s) and %d rating(s).', count($films), $ratingsLoaded));

        return Command::SUCCESS;
    }

    /**
     * @param list<ParsedFilm> $films
     */
    private function seedFilms(array $films): void
    {
        foreach ($films as $film) {
            $this->films->save(new Film($film->slug, $film->title));
        }
    }

    /**
     * Rebuilds round structure from list order (20/10/10). Positions are upserted;
     * picked_by is preserved on existing rows and left null on new ones.
     *
     * @param list<ParsedFilm> $films
     */
    private function seedRounds(array $films): void
    {
        $round = 1;
        $positionInRound = 0;
        $roundCapacity = self::FIRST_ROUND_SIZE;

        foreach ($films as $film) {
            if ($positionInRound === $roundCapacity) {
                ++$round;
                $positionInRound = 0;
                $roundCapacity = self::ROUND_SIZE;
            }
            ++$positionInRound;

            $this->rounds->ensure($round);
            $this->rounds->syncFilm($round, $film->slug, $positionInRound);
        }
    }

    /**
     * Loads owner ratings from the list plus friend ratings from friends/{slug}.html.
     * Returns the number of ratings written.
     *
     * @param list<ParsedFilm> $films
     */
    private function seedRatings(SymfonyStyle $io, string $htmlDir, string $listHtml, array $films): int
    {
        $loaded = 0;

        foreach ($this->listParser->ownerRatings($listHtml) as $slug => $rating) {
            $this->ratings->setRating($slug, $rating->username, $rating->rating);
            ++$loaded;
        }

        foreach ($films as $film) {
            $friendsFile = "{$htmlDir}/friends/{$film->slug}.html";
            if (!is_file($friendsFile)) {
                $io->warning(sprintf('No friends page for "%s".', $film->slug));
                continue;
            }

            $friendRatings = $this->friendsParser->parse((string) file_get_contents($friendsFile));
            foreach ($friendRatings as $rating) {
                $this->ratings->setRating($film->slug, $rating->username, $rating->rating);
                ++$loaded;
            }
        }

        return $loaded;
    }
}
