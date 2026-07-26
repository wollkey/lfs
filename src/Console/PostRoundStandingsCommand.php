<?php

declare(strict_types=1);

namespace App\Console;

use App\Telegram\Exception\TelegramException;
use App\Telegram\Messages;
use App\Telegram\Post;
use App\Telegram\TelegramClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'bot:post-round', description: 'Post current-round films sorted by rating to Telegram.')]
final class PostRoundStandingsCommand extends Command
{
    public function __construct(
        private readonly Messages $messages,
        private readonly ?TelegramClient $client,
        private readonly ?string $chatId,
    ) {
        parent::__construct();
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Print the message instead of posting it.')]
        bool $dryRun = false,
    ): int {
        $post = $this->messages->currentRoundStandings();

        if ($dryRun) {
            $io->writeln($this->preview($post));

            return Command::SUCCESS;
        }

        if ($this->client === null || $this->chatId === null) {
            $io->error('Set TELEGRAM_BOT_TOKEN and TELEGRAM_CHAT_ID to post.');

            return Command::INVALID;
        }

        try {
            $this->client->send($this->chatId, $post);
        } catch (TelegramException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success('Posted current-round standings.');

        return Command::SUCCESS;
    }

    private function preview(Post $post): string
    {
        $lines = [$post->title];

        if ($post->intro !== null) {
            $lines[] = $post->intro;
        }

        if ($post->imagePath !== null) {
            $lines[] = '[image] '.$post->imagePath;
        }

        if ($post->table !== null) {
            $lines[] = implode(' | ', $post->table->headers);
            foreach ($post->table->rows as $row) {
                $lines[] = implode(' | ', $row);
            }
        }

        return implode("\n", $lines);
    }
}
