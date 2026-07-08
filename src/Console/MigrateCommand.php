<?php

declare(strict_types=1);

namespace App\Console;

use App\Persistence\Migrator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'migrate', description: 'Apply pending database migrations.')]
final class MigrateCommand extends Command
{
    public function __construct(
        private readonly Migrator $migrator,
    ) {
        parent::__construct();
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $ran = $this->migrator->migrate();

        $io->success($ran === []
            ? 'Database is up to date. Nothing to migrate.'
            : sprintf('Applied %d migration(s): %s', count($ran), implode(', ', $ran)));

        return Command::SUCCESS;
    }
}
