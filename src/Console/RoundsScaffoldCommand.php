<?php

declare(strict_types=1);

namespace App\Console;

use App\Letterboxd\Parser\ListParser;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'rounds:scaffold',
    description: 'DEV: generate rounds.seed.json skeleton from list order. Fill pickedBy by hand afterwards.',
)]
final class RoundsScaffoldCommand extends Command
{
    private const int FIRST_ROUND_SIZE = 20;
    private const int ROUND_SIZE = 10;

    public function __construct(
        private readonly ListParser $listParser,
    ) {
        parent::__construct();
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Saved list HTML')]
        string $listHtml = 'data/list.html',
        #[Argument(description: 'Output JSON')]
        string $out = 'data/rounds.seed.json',
        #[Option(description: 'Overwrite existing file (DESTROYS your pickedBy edits!)')]
        bool $force = false,
    ): int {
        if (is_file($out) && !$force) {
            $io->error("{$out} already exists. Your pickedBy edits would be lost. Use --force to overwrite.");

            return Command::INVALID;
        }

        if (!is_file($listHtml)) {
            $io->error("List HTML not found: {$listHtml}");

            return Command::INVALID;
        }

        $films = $this->listParser->parse((string) file_get_contents($listHtml));

        $rounds = [];
        $roundFilms = [];

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

            $roundFilms[] = [
                'round' => $round,
                'slug' => $film->slug,
                'title' => $film->title,
                'pickedBy' => null,
                'position' => $positionInRound,
            ];
        }

        for ($n = 1; $n <= $round; ++$n) {
            $rounds[] = ['number' => $n, 'startedOn' => null, 'endedOn' => null];
        }

        $payload = ['rounds' => $rounds, 'roundFilms' => $roundFilms];

        file_put_contents(
            $out,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );

        $io->success(sprintf(
            'Wrote %s: %d rounds, %d films. Now fill in "pickedBy" by hand.',
            $out,
            $round,
            count($roundFilms),
        ));

        return Command::SUCCESS;
    }
}
