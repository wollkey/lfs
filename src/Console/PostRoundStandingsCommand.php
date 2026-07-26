<?php

declare(strict_types=1);

namespace App\Console;

use App\Telegram\Card\StandingsCard;
use App\Telegram\Exception\RenderException;
use App\Telegram\Exception\TelegramException;
use App\Telegram\Messages;
use App\Telegram\Post;
use App\Telegram\Rasterizer;
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
        private readonly StandingsCard $card,
        private readonly Rasterizer $rasterizer,
        private readonly ?TelegramClient $client,
        private readonly ?string $chatId,
    ) {
        parent::__construct();
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Write the card to var/ instead of posting it.')]
        bool $dryRun = false,
    ): int {
        $standings = $this->messages->currentRoundStandings();

        if ($dryRun) {
            return $this->dryRun($io, $standings);
        }

        if ($this->client === null || $this->chatId === null) {
            $io->error('Set TELEGRAM_BOT_TOKEN and TELEGRAM_CHAT_ID to post.');

            return Command::INVALID;
        }

        $imagePath = $this->renderCard($standings);

        try {
            if ($imagePath !== null) {
                $this->client->sendPhoto($this->chatId, $imagePath, $standings->title);
            } else {
                $this->client->send($this->chatId, $standings);
            }
        } catch (TelegramException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        } finally {
            if ($imagePath !== null) {
                @unlink($imagePath);
            }
        }

        $io->success($imagePath !== null
            ? 'Posted the standings card.'
            : 'Posted the standings table (card render unavailable).');

        return Command::SUCCESS;
    }

    private function renderCard(Post $standings): ?string
    {
        if ($standings->table === null) {
            return null;
        }

        try {
            return $this->rasterizer->toPng($this->card->render($standings));
        } catch (RenderException) {
            return null;
        }
    }

    private function dryRun(SymfonyStyle $io, Post $standings): int
    {
        if ($standings->table === null) {
            $io->writeln($this->previewText($standings));

            return Command::SUCCESS;
        }

        $svg = $this->card->render($standings);
        $dir = dirname(__DIR__, 2).'/var';
        @mkdir($dir, 0o775, true);
        file_put_contents($dir.'/standings.svg', $svg);

        try {
            rename($this->rasterizer->toPng($svg), $dir.'/standings.png');
            $io->success('Wrote var/standings.svg and var/standings.png');
        } catch (RenderException $e) {
            $io->warning('Wrote var/standings.svg, but rasterizing failed: '.$e->getMessage());
            $io->writeln($this->previewText($standings));
        }

        return Command::SUCCESS;
    }

    private function previewText(Post $post): string
    {
        $lines = [$post->title];

        if ($post->intro !== null) {
            $lines[] = $post->intro;
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
