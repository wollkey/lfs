<?php

declare(strict_types=1);

namespace App\Console;

use App\Telegram\Messages;
use App\Telegram\MicroPoster;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'bot:post-flashback', description: 'Post the "a year ago this week" flashback card to Telegram.')]
final class PostFlashbackCommand extends Command
{
    public function __construct(
        private readonly Messages $messages,
        private readonly MicroPoster $poster,
    ) {
        parent::__construct();
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Write the card to var/ instead of posting it.')]
        bool $dryRun = false,
    ): int {
        return $this->poster->post($io, $this->messages->flashback(), $dryRun);
    }
}
