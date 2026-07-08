<?php

declare(strict_types=1);

namespace App\Console;

use App\Domain\Film;
use App\Letterboxd\Parser\ListParser;
use App\Persistence\FilmRepository;
use App\Persistence\RatingRepository;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'import:html',
    description: 'Rebuild films and ratings from saved Letterboxd HTML. Your recovery path.',
)]
final class ImportHtmlCommand extends Command
{
    public function __construct(
        private readonly ListParser $listParser,
        private readonly FilmRepository $films,
        private readonly RatingRepository $ratings,
    ) {
        parent::__construct();
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'HTML file or directory of .html files')]
        string $src,
    ): int {
        $files = is_dir($src) ? glob(rtrim($src, '/').'/*.html') : [$src];
        $imported = 0;

        foreach ($files as $file) {
            $parsed = $this->listParser->parse((string) file_get_contents($file));
            $this->films->save(new Film($parsed->film->slug, $parsed->film->title));

            foreach ($parsed->ratings as $r) {
                try {
                    $this->ratings->setRating($parsed->film->slug, $r->username, $r->rating);
                    ++$imported;
                } catch (\PDOException) {
                    $io->warning(sprintf('Skipped unknown member "%s".', $r->username));
                }
            }
        }

        $io->success(sprintf('Imported %d rating(s) from %d file(s).', $imported, count($files)));

        return Command::SUCCESS;
    }
}
