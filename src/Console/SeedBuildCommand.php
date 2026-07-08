<?php

declare(strict_types=1);

namespace App\Console;

use App\Letterboxd\Parser\FriendsRatingsParser;
use App\Letterboxd\Parser\ListParser;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'seed:build',
    description: 'DEV: parse local HTML into data/ratings.seed.json. HTML never leaves your machine.',
)]
final class SeedBuildCommand extends Command
{
    public function __construct(
        private readonly ListParser $listParser,
        private readonly FriendsRatingsParser $ratingsParser,
    ) {
        parent::__construct();
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Directory with list.html and friends/<slug>.html files')]
        string $htmlDir = 'data',
        #[Argument(description: 'Output JSON path')]
        string $out = 'data/ratings.seed.json',
    ): int {
        $listHtml = (string) file_get_contents("$htmlDir/list.html");

        $films = $this->listParser->parse($listHtml);
        $ownerRatings = $this->listParser->ownerRatings($listHtml);
        $ratings = [];

        foreach ($ownerRatings as $slug => $ownerRating) {
            $ratings[] = ['slug' => $slug, 'username' => $ownerRating->username, 'score' => $ownerRating->rating];
        }

        foreach ($films as $film) {
            $file = "$htmlDir/friends/{$film->slug}.html";
            if (!is_file($file)) {
                $io->warning("No friends HTML for {$film->slug}, skipping ratings.");
                continue;
            }

            foreach ($this->ratingsParser->parse((string) file_get_contents($file)) as $r) {
                $ratings[] = ['slug' => $film->slug, 'username' => $r->username, 'score' => $r->rating];
            }
        }

        $payload = [
            'films' => array_map(static fn ($f) => ['slug' => $f->slug, 'title' => $f->title], $films),
            'ratings' => $ratings,
        ];

        file_put_contents($out, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $io->success(sprintf('Wrote %s: %d films, %d ratings.', $out, count($films), count($ratings)));

        return Command::SUCCESS;
    }
}
