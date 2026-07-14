<?php

declare(strict_types=1);

namespace App\Console;

use App\Domain\Film;
use App\Domain\Round;
use App\Persistence\FilmRepository;
use App\Persistence\RatingRepository;
use App\Persistence\RoundRepository;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'seed:load',
    description: 'Load seed JSON into the database. Safe on prod: no HTML or parser needed.',
)]
final class SeedLoadCommand extends Command
{
    public function __construct(
        private readonly FilmRepository $films,
        private readonly RoundRepository $rounds,
        private readonly RatingRepository $ratings,
    ) {
        parent::__construct();
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Ratings seed JSON')]
        string $ratingsFile = 'data/ratings.seed.json',
        #[Argument(description: 'Rounds seed JSON (optional)')]
        string $roundsFile = 'data/rounds.seed.json',
    ): int {
        $data = $this->read($ratingsFile);

        foreach ($data['films'] ?? [] as $f) {
            $this->films->save(new Film($f['slug'], $f['title']));
        }

        if (is_file($roundsFile)) {
            $rounds = $this->read($roundsFile);

            foreach ($rounds['rounds'] ?? [] as $r) {
                $this->rounds->save(new Round($r['number'], $r['startedOn'] ?? null, $r['endedOn'] ?? null));
            }
            foreach ($rounds['roundFilms'] ?? [] as $rf) {
                $this->rounds->ensure($rf['round']);
                $this->rounds->addFilm(
                    $rf['round'],
                    $rf['slug'],
                    $rf['pickedBy'] ?? null,
                    $rf['position'],
                );
            }
        }

        $loaded = 0;
        foreach ($data['ratings'] ?? [] as $r) {
            try {
                $this->ratings->setRating($r['slug'], $r['username'], $r['score']);
                ++$loaded;
            } catch (\PDOException) {
                $io->warning(sprintf('Skipped rating: unknown member "%s" or film "%s".', $r['username'], $r['slug']));
            }
        }

        $io->success(sprintf('Loaded %d film(s) and %d rating(s).', count($data['films'] ?? []), $loaded));

        return Command::SUCCESS;
    }

    /**
     * @return array<string,mixed>
     */
    private function read(string $path): array
    {
        return json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    }
}
