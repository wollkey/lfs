<?php

declare(strict_types=1);

namespace App\Console;

use App\Letterboxd\Parser\ListParser;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'posters:fetch',
    description: 'DEV: download film posters from saved list HTML into public/posters/.',
)]
final class FetchPostersCommand extends Command
{
    private const string USER_AGENT = 'LFS poster fetcher (personal film club project)';

    public function __construct(
        private readonly ListParser $listParser,
    ) {
        parent::__construct();
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Saved list HTML')]
        string $listHtml = 'data/list.html',
        #[Argument(description: 'Output directory')]
        string $outDir = 'public/posters',
    ): int {
        if (!is_file($listHtml)) {
            $io->error("List HTML not found: {$listHtml}");

            return Command::INVALID;
        }

        if (!is_dir($outDir) && !mkdir($outDir, 0o775, true) && !is_dir($outDir)) {
            $io->error("Could not create directory: {$outDir}");

            return Command::FAILURE;
        }

        $films = $this->listParser->parse((string) file_get_contents($listHtml));
        $downloaded = 0;
        $skipped = 0;

        foreach ($films as $film) {
            $target = "{$outDir}/{$film->slug}.jpg";

            if (is_file($target)) {
                ++$skipped;
                continue;
            }

            if ($film->posterUrl === null) {
                $io->warning("No poster for {$film->slug} (scroll the list before saving HTML).");
                ++$skipped;
                continue;
            }

            $bytes = $this->download($film->posterUrl);

            if ($bytes === null) {
                $io->warning("Download failed for {$film->slug}.");
                ++$skipped;
                continue;
            }

            file_put_contents($target, $bytes);
            $io->writeln("saved {$film->slug}.jpg");
            ++$downloaded;

            usleep(300_000);
        }

        $io->success(sprintf('Downloaded %d, skipped %d.', $downloaded, $skipped));

        return Command::SUCCESS;
    }

    private function download(string $url): ?string
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => 'User-Agent: '.self::USER_AGENT,
                'timeout' => 15,
            ],
        ]);

        $data = @file_get_contents($url, false, $context);

        return $data === false ? null : $data;
    }
}
