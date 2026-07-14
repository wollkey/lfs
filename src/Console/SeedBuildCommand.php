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
        private readonly FriendsRatingsParser $friendsParser,
    ) {
        parent::__construct();
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Directory with list.html and friends/{slug}.html')]
        string $htmlDir = 'data',
        #[Argument(description: 'Output JSON path')]
        string $out = 'data/ratings.seed.json',
    ): int {
        $listFile = "$htmlDir/list.html";

        if (!is_file($listFile)) {
            $io->error("List HTML not found: {$listFile}");

            return Command::INVALID;
        }

        $listHtml = (string) file_get_contents($listFile);

        $films = $this->listParser->parse($listHtml);
        $owner = $this->listParser->ownerRatings($listHtml);

        $ratings = [];

        foreach ($owner as $slug => $rating) {
            $ratings[] = [
                'slug' => $slug,
                'username' => $rating->username,
                'score' => $rating->rating,
            ];
        }

        $parsed = 0;
        $missing = 0;

        foreach ($films as $film) {
            $file = "$htmlDir/friends/{$film->slug}.html";

            if (!is_file($file)) {
                $io->warning(sprintf('No friends page for "%s".', $film->slug));
                ++$missing;
                continue;
            }

            $friendRatings = $this->friendsParser->parse((string) file_get_contents($file));

            foreach ($friendRatings as $rating) {
                $ratings[] = [
                    'slug' => $film->slug,
                    'username' => $rating->username,
                    'score' => $rating->rating,
                ];
            }

            ++$parsed;
        }

        $payload = [
            'films' => array_map(
                static fn ($f) => ['slug' => $f->slug, 'title' => $f->title],
                $films,
            ),
            'ratings' => $ratings,
        ];

        file_put_contents(
            $out,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );

        $io->success(sprintf(
            'Wrote %s: %d films, %d ratings from %d friends page(s).',
            $out,
            count($films),
            count($ratings),
            $parsed,
        ));

        if ($missing > 0) {
            $io->warning(sprintf('%d film(s) had no friends page — only owner ratings for them.', $missing));
        }

        return Command::SUCCESS;
    }
}
