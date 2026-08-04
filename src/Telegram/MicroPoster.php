<?php

declare(strict_types=1);

namespace App\Telegram;

use App\Telegram\Card\PanelsCard;
use App\Telegram\Exception\RenderException;
use App\Telegram\Exception\TelegramException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

final readonly class MicroPoster
{
    public function __construct(
        private PanelsCard $card,
        private Rasterizer $rasterizer,
        private ?TelegramClient $client,
        private ?string $chatId,
    ) {
    }

    public function post(SymfonyStyle $io, ?Post $post, string $tagline, string $accent, string $name, bool $dryRun): int
    {
        if ($post === null) {
            $io->success('Nothing to post this time.');

            return Command::SUCCESS;
        }

        if ($dryRun) {
            return $this->dryRun($io, $post, $tagline, $accent, $name);
        }

        if ($this->client === null || $this->chatId === null) {
            $io->error('Set TELEGRAM_BOT_TOKEN and TELEGRAM_CHAT_ID to post.');

            return Command::INVALID;
        }

        $imagePath = $this->render($post, $tagline, $accent);

        try {
            if ($imagePath !== null) {
                $this->client->sendPhoto($this->chatId, $imagePath, $post->title);
            } else {
                $this->client->send($this->chatId, $post);
            }
        } catch (TelegramException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        } finally {
            if ($imagePath !== null) {
                @unlink($imagePath);
            }
        }

        $io->success('Posted.');

        return Command::SUCCESS;
    }

    private function render(Post $post, string $tagline, string $accent): ?string
    {
        if ($post->table === null) {
            return null;
        }

        try {
            return $this->rasterizer->toPng($this->card->render($post, $tagline, $accent));
        } catch (RenderException) {
            return null;
        }
    }

    private function dryRun(SymfonyStyle $io, Post $post, string $tagline, string $accent, string $name): int
    {
        if ($post->table === null) {
            $io->writeln($post->title);

            return Command::SUCCESS;
        }

        $svg = $this->card->render($post, $tagline, $accent);
        $dir = dirname(__DIR__, 2).'/var';
        @mkdir($dir, 0o775, true);
        file_put_contents($dir.'/'.$name.'.svg', $svg);

        try {
            rename($this->rasterizer->toPng($svg), $dir.'/'.$name.'.png');
            $io->success(sprintf('Wrote var/%s.svg and var/%s.png', $name, $name));
        } catch (RenderException $e) {
            $io->warning(sprintf('Wrote var/%s.svg, but rasterizing failed: %s', $name, $e->getMessage()));
        }

        return Command::SUCCESS;
    }
}
