<?php

declare(strict_types=1);

namespace App\Console;

use App\Letterboxd\Scraper\GuzzleHttpClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'fixtures:fetch',
    description: 'DEV: download Letterboxd HTML into tests/Fixtures. Fragile — Cloudflare may block it.',
)]
final class FetchFixturesCommand extends Command
{
    public function __invoke(SymfonyStyle $io): int
    {
        $user = getenv('LBXD_USER');
        $pass = getenv('LBXD_PASS');

        if ($user === false || $pass === false) {
            $io->error('Set LBXD_USER and LBXD_PASS in the environment.');

            return Command::INVALID;
        }

        $client = new GuzzleHttpClient(username: $user, password: $pass);

        $targets = [
            'list.html' => "$user/list/last-frame-society-1/",
            'friends_film.html' => "$user/friends/film/citizen-kane/",
        ];

        $dir = __DIR__.'/../../tests/Fixtures';

        foreach ($targets as $file => $path) {
            $html = $client->get($path);
            file_put_contents("$dir/$file", $html);
            $io->writeln(sprintf('saved %s (%d bytes)', $file, strlen($html)));
        }

        $io->success('Fixtures updated.');

        return Command::SUCCESS;
    }
}
